<?php

namespace MediaWiki\Extension\WikispacesMigration;

use Config;
use MediaHandler;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesPage;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\RevisionStore;
use Message;
use MessageSpecifier;
use MimeAnalyzer;
use MWFileProps;
use OldRevisionImporter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Status;
use StatusValue;
use Title;
use User;
use WikiPage;
use WikiRevision;
use WikitextContent;
use WikitextContentHandler;

/**
 * Imports files into MediaWiki.
 */
class Importer implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** Never overwrite existing pages. */
	const OVERWRITE_NEVER = 0;

	/** Overwrite when the remote page is newer than the local one. */
	const OVERWRITE_OLDER = 1;

	/** Always overwrite. */
	const OVERWRITE_ALWAYS = 2;

	/** @var LoggerInterface */
	protected $logger;

	/** @var MimeAnalyzer */
	private $mimeAnalyzer;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var OldRevisionImporter */
	private $mediaWikiOldRevisionImporter;

	/** @var Message Edit summary for imports. */
	private $summary = null;

	/** @var int Whether to overwrite the content of existing pages. */
	private $overwrite = self::OVERWRITE_OLDER;

	/** @var bool Use the modification date of the page as the timestamp for the edit. */
	private $useTimestamp = false;
	private $config;

	public function __construct(
		Config $config,
		RevisionStore $revisionStore,
		OldRevisionImporter $mediaWikiOldRevisionImporter,
		MimeAnalyzer $mimeAnalyzer
	) {
		$this->config = $config;
		$this->revisionStore = $revisionStore;
		$this->mediaWikiOldRevisionImporter = $mediaWikiOldRevisionImporter;
		$this->mimeAnalyzer = $mimeAnalyzer;

		$this->summary = wfMessage( 'wikispacesmigration-summary' )->plain();
		$this->setLogger( new NullLogger() );
	}

	/**
	 * Set whether to overwrite the content of existing pages.
	 * @param int $owerwrite One of the OVERWRITE_* constants.
	 */
	public function setOverwrite( $owerwrite ) {
		$this->overwrite = $owerwrite;
	}

	/**
	 * Set whether to use the modification date of the page as the timestamp for the edit.
	 * @param bool $useTimestamp
	 */
	public function setUseTimestamp( $useTimestamp ) {
		$this->useTimestamp = $useTimestamp;
	}

	/**
	 * Set edit summary for imports.
	 * Will receive original edit summary as a parameter.
	 * @param MessageSpecifier $summary
	 */
	public function setSummary( $summary ) {
		$this->summary = $summary;
	}

	/**
	 * Convert a Wikispaces page name into a MediaWiki title object.
	 * @param string $pageName
	 * @return Title|null
	 */
	public function makeTitle( $pageName ) {
		// TODO special-case 'home' into 'Main Page'?
		$title = Title::newFromText( $pageName );
		if ( $title && $title->hasFragment() ) {
			// # is not allowed in MediaWiki titles, replace with a Unicode lookalike
			$title = Title::newFromText( str_replace( '#', '＃', $pageName ) );
		}
		return $title;
	}

	/**
	 * Convert a Wikispaces username into a MediaWiki user object.
	 * @param string $userName
	 * @param int $wikispacesUserId
	 * @param bool $create Create the User if it does not exist.
	 * @return null|User
	 */
	public function makeUser( $userName, $wikispacesUserId, $create = false ) {
		// TODO allow use of interwiki prefix (ExternalUserNames)
		// TODO store Wikispaces ID?
		$user = User::newFromName( $userName );
		if ( $user && $create && !$user->getId() ) {
			$user->addToDatabase();
		}
		return $user;
	}

	/**
	 * Import a Wikispaces revision
	 * @param WikispacesPage $page
	 * @param string $text Text of the Wikispaces page, converted to MediaWiki markup
	 * @return SkippingStatusValue
	 */
	public function importRevision( WikispacesPage $page, $text ) {
		$status = SkippingStatusValue::newGood();
		$title = $this->makeTitle( $page->name );
		if ( !$title ) {
			$this->logger->error( "Invalid title '$page->name'. Skipping." );
			$status->fatal( 'wikispacesmigration-importer-invalidtitle', $page->name );
			$status->failCount = 1;
			return $status;
		}
		$timestamp = $this->useTimestamp ? wfTimestamp( TS_UNIX, (int)$page->dateCreated )
			: wfTimestampNow();
		$user = $this->makeUser( $page->userCreatedUsername, $page->userCreated );

		$oldRev = $this->revisionStore->getRevisionByTitle( $title);
		$actualTitle = $title->getPrefixedText();
		// make sure timestamp is not older than last revision of the page
		if ( $this->useTimestamp && $this->overwrite === self::OVERWRITE_ALWAYS && $oldRev ) {
			$timestamp = (int)$oldRev->getTimestamp() + 1000;
		}
		if ( $oldRev ) {
			$touched = wfTimestamp( TS_UNIX, $title->getTouched() );
			if ( $this->overwrite === self::OVERWRITE_NEVER ) {
				$this->logger->notice( "Title $actualTitle already exists. Skipping." );
				$status->warning( 'wikispacesmigration-importer-titleexists', $page->name );
				$status->skipCount = 1;
				return $status;
			} elseif (
				$this->overwrite === self::OVERWRITE_OLDER
				&& $this->useTimestamp
				&& intval( $touched ) >= intval( $timestamp )
			) {
				$this->logger->notice( "File for title $actualTitle has not been modified since the destination page was touched. Skipping." );
				$status->warning( 'wikispacesmigration-importer-notmodified', $page->name );
				$status->skipCount = 1;
				return $status;
			}
		}

		$rev = new WikiRevision( $this->config );
		$rev->setText( $text );
		$rev->setTitle( $title );
		$rev->setUserObj( $user );
		$rev->setComment( $this->makeSummary ( $page ) );
		$rev->setTimestamp( $timestamp );

		if (
			$this->overwrite === self::OVERWRITE_OLDER
			&& $oldRev
			&& $rev->getContent()->equals( $oldRev->getContent( 'main' ) )
		) {
			$this->logger->notice( "Page for title $actualTitle contains no changes from the current revision. Skipping." );
			$status->warning( 'wikispacesmigration-importer-notchanged', $page->name );
			$status->skipCount = 1;
			return $status;
		}

		$success = $this->mediaWikiOldRevisionImporter->import( $rev );
		if ( $success ) {
			$action = $oldRev ? 'updated' : 'created';
			$this->logger->notice( "Successfully $action $actualTitle" );
			$status->successCount++;
		} else {
			$action = $oldRev ? 'update' : 'create';
			$this->logger->error( "Failed to $action $actualTitle" );
			$status->fatal( 'wikispacesmigration-importer-failed', $page->name );
			$status->failCount++;
			$this->exit = 1;
		}
		return $status;
	}

	/**
	 * Import a single comment (talkpage edit).
	 * @param string $pageName
	 * @param int $sectionId
	 * @param User $user
	 * @param string $wikitext
	 * @param int $timestamp
	 * @return SkippingStatusValue
	 */
	public function importTalkEdit( $pageName, $sectionId, User $user, $wikitext, $timestamp ) {
		// TODO i18n + configurable + handle section title nicely
		return $this->appendEdit( $pageName, $sectionId, $user, $wikitext, $timestamp,
			'[Importing from Wikispaces] ' . $wikitext );
	}

	/**
	 * Add page footer with imported metadata (e.g. categories) to a page.
	 * @param string $pageName
	 * @param string $wikitext
	 * @return SkippingStatusValue
	 */
	public function importPageFooter( $pageName, $wikitext ) {
		$user = User::newSystemUser( 'Wikispaces importer' );
		// TODO i18n + configurable
		return $this->appendEdit( $pageName, null, $user, $wikitext, wfTimestampNow(),
			'[Importing page metadata from Wikispaces]' );
	}

	/**
	 * Import a Wikispaces file (or any file, really).
	 * @param string $filePath Path to the downloaded file
	 * @param User $user Original uploader of the file.
	 * @return StatusValue Contains the Title object for the file on success.
	 */
	public function importFile( $filePath, User $user ) {
		$baseName = \UtfNormal\Validator::cleanUp( wfBaseName( $filePath ) );
		$title = Title::makeTitleSafe( NS_FILE, $baseName );
		if ( !$title ) {
			$this->logger->error( '{baseName} could not be imported; a valid title cannot be produced',
				[ 'baseName' => $baseName ] );
			return StatusValue::newFatal( 'wikispacesmigration-importfile-invalidtitle', $baseName );
		}
		$localFile = wfLocalFile( $title );
		if ( $localFile->exists() ) {
			if ( $this->overwrite !== self::OVERWRITE_ALWAYS ) {
				// TODO handle OVERWRITE_OLDER
				$this->logger->notice( '{baseName} exists, skipping', [ 'baseName' => $baseName ] );
				return StatusValue::newGood( $title );
			}
		}

		$mwProps = new MWFileProps( $this->mimeAnalyzer );
		$props = $mwProps->getPropsFromPath( $filePath, true );
		$publishOptions = [];
		$handler = MediaHandler::getHandler( $props['mime'] );
		if ( $handler ) {
			$metadata = \MediaWiki\quietCall( 'unserialize', $props['metadata'] );
			$publishOptions['headers'] = $handler->getContentHeaders( $metadata );
		} else {
			$publishOptions['headers'] = [];
		}
		$status = $localFile->publish( $filePath, 0, $publishOptions );
		if ( $status->isGood() ) {
			$recordStatus = $localFile->recordUpload2( $status->value, '', '', $props, false, $user );
			if ( $recordStatus->hasMessage( 'fileexists-no-change' ) ) {
				$this->logger->notice( '{baseName} exists with same content, skipping', [ 'baseName' => $baseName ] );
				$recordStatus->setOK( true );
			} elseif ( !$recordStatus->isOK() ) {
				$this->logger->notice( 'Importing {baseName} failed: {message}', [
					'baseName' => $baseName,
					'message' => Status::wrap( $recordStatus )->getWikiText( false, false, 'en' ),
				] );
			}
			$status->merge( $recordStatus );
			$status->setResult( $status->isOK(), $localFile->getTitle() );
		}
		return $status;
	}

	/**
	 * Import a talkpage edit, that is, appending some wikitext to the end of a talkpage.
	 * @param string $pageName
	 * @param int|null $sectionId MediaWiki section ID (or null to append to whole page).
	 * @param User $user
	 * @param string $wikitext
	 * @param int $editTimestamp
	 * @param string $summary Edit summary
	 * @return SkippingStatusValue
	 */
	private function appendEdit(
		$pageName, $sectionId, User $user, $wikitext, $editTimestamp, $summary
	) {
		$title = $this->makeTitle( $pageName );
		if ( !$title ) {
			// Should not happen, we already imported the page.
			return SkippingStatusValue::newFatal( 'wikispacesmigration-importer-invalidtitle', $pageName );
		}
		$page = WikiPage::factory( $title );

		$oldContent = $page->getContent();
		if ( $sectionId !== null) {
			$oldContent = $oldContent->getSection( $sectionId );
		}
		if ( !$oldContent ) {
			return SkippingStatusValue::newFatal( 'wikispacesmigration-importer-sectionnotfound',
				$pageName, $sectionId );
		} elseif ( !( $oldContent instanceof WikitextContent ) ) {
			// WTF?
			return SkippingStatusValue::newFatal( 'wikispacesmigration-importer-nottext', $pageName );
		}
		$newSectionContent = WikitextContentHandler::makeContent( $oldContent->getNativeData()
			. $wikitext, $title );
		$newPageContent = $page->replaceSectionAtRev( $sectionId ?: '', $newSectionContent );

		// TODO handle overwrite flags?
		// TODO handle overwrite via sha1
		$rev = new WikiRevision( $this->config );
		$rev->setText( $newPageContent->getNativeData() );
		$rev->setTitle( $title );
		$rev->setUserObj( $user );
		$rev->setComment( $summary );
		$rev->setTimestamp( $editTimestamp );
		$success = $this->mediaWikiOldRevisionImporter->import( $rev );

		if ( !$success ) {
			// FIXME this method needs more info for proper logging
			return SkippingStatusValue::newFatal( 'wikispacesmigration-importer-talkeditimportfailed',
				$pageName );
		}
		return SkippingStatusValue::newGood();
	}

	/**
	 * Create a MediaWiki revision comment from a Wikispaces revision.
	 * @param WikispacesPage $page
	 * @return string
	 */
	private function makeSummary( WikispacesPage $page ) {
		if ( $this->summary === null ) {
			return $page->comment;
		} else {
			$summary = Message::newFromSpecifier( $this->summary );
			$summary->params( $page->comment );
			return $summary->text();
		}
	}

}
