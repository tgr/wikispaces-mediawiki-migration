<?php

namespace MediaWiki\Extension\WikispacesMigration;

use MediaWiki\Extension\WikispacesMigration\Soap\SoapException;
use MediaWiki\Extension\WikispacesMigration\Soap\WikispacesApi;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesPage;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesTag;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use SoapFault;
use Status;

/**
 * Manager class to handle the other import-related classes.
 */
class ImportManager implements LoggerAwareInterface {

	/** @var LoggerInterface */
	protected $logger;

	/** @var WikispacesApi */
	private $wikispacesApi;

	/** @var Downloader */
	private $fileDownloader;

	/** @var Importer */
	private $importer;

	/** @var WikitextConverter */
	private $wikitextConverter;

	/** @var array Status cache for not downloading things multiple time */
	private $seen = [];

	/** @var WikispacesPage[] Page metadata cache, keyed by page name. */
	private $pages = [];

	/** @var bool Seen flag for all pages. */
	private $gotAllPages = false;

	/**
	 * @param string $user Wikispaces username.
	 * @param string $password Wikispaces password.
	 * @param string $spacename Wikispaces space name.
	 */
	public function __construct( $user, $password, $spacename ) {
		$this->wikispacesApi = new WikispacesApi( $user, $password, $spacename );
		$this->fileDownloader = new Downloader( $user, $password, $spacename );
		$this->importer = new Importer(
			MediaWikiServices::getInstance()->getMainConfig(),
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getWikiRevisionOldRevisionImporter(),
			MediaWikiServices::getInstance()->getMimeAnalyzer()
		);
		$this->wikitextConverter = new WikitextConverter();

		$this->importer->setSummary( wfMessage( 'wikispacesmigration-summary' ) );
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
		$this->wikispacesApi->setLogger( $logger );
		$this->fileDownloader->setLogger( $logger );
		$this->importer->setLogger( $logger );
	}

	/**
	 * Set whether to use the modification date of the page as the timestamp for the edit.
	 * @param bool $useTimestamp
	 */
	public function setUseTimestamp( $useTimestamp ) {
		$this->importer->setUseTimestamp( $useTimestamp );
	}

	/**
	 * Set whether to overwrite the content of existing pages.
	 * @param int $owerwrite One of the Importer::OVERWRITE_* constants.
	 */
	public function setOverwrite( $owerwrite ) {
		$this->importer->setOverwrite( $owerwrite );
	}

	/**
	 * Import all pages (with files and users that made an edit) from the Wikispaces space.
	 * @param bool $withHistory Import pages with full history (as opposed to last revision only).
	 * @return SkippingStatusValue
	 */
	public function importAllPages( $withHistory = true ) {
		$status = SkippingStatusValue::newGood();

		$pages = $this->getAllPages();
		foreach ( $pages as $page ) {
			$pageStatus = $this->importPage( $page->name, $withHistory );
			$status->merge( $pageStatus );
		}

		return $status;
	}

	/**
	 * Import a single page (with files and users that made an edit) from the Wikispaces space.
	 * @param string $pageName Wikispaces page name
	 * @param bool $withHistory Import pages with full history (as opposed to last revision only).
	 * @param bool $withTags Import tags as categories
	 * @param bool $withComments Import comments to the talk page.
	 * @return SkippingStatusValue
	 */
	public function importPage(
		$pageName, $withHistory = true, $withTags = true, $withComments = true
	) {
		// FIXME what happens if $pageName does not exist?
		// TODO should delete the page on OVERWRITE_ALWAYS + $withHistory
		$status = SkippingStatusValue::newGood();
		$page = $this->getPage( $pageName );

		if ( $withHistory ) {
			$versions = $this->wikispacesApi->listPageVersions( $pageName );
		} else {
			$versions = [ $page ];
		}

		foreach ( $versions as $version ) {
			$revisionStatus = $this->importRevision( $version );
			$status->merge( $revisionStatus );
		}

		if ( $withTags && $status->successCount > 0 ) {
			$tags = $this->wikispacesApi->listTagsForPage( $page->id );
			if ( $tags ) {
				$tagNames = array_map( function ( WikispacesTag $tag ) {
					return $tag->name;
				}, $tags );
				// TODO create category pages
				$footer = $this->wikitextConverter->convertTags( $tagNames );
				$footerStatus = $this->importer->importPageFooter( $pageName, $footer );
				$status->merge( $footerStatus );
			}
		}

		if ( $withComments && $status->successCount > 0 ) {
			$talkStatus = $this->importTalkPage( $pageName );
			$status->merge( $talkStatus );
		}

		return $status;
	}

	/**
	 * Import a single revision (with files and the user who made it) from the Wikispaces space.
	 * @param WikispacesPage $revision
	 * @return SkippingStatusValue
	 */
	public function importRevision( WikispacesPage $revision ) {
		$status = SkippingStatusValue::newGood();

		// Make sure the content is not empty.
		// FIXME not very elegant
		$revision = $this->wikispacesApi->getPageWithVersion( $revision->name, $revision->versionId );

		$text = $revision->content;

		$files = $this->wikitextConverter->extractFiles( $text );
		foreach ( $files as $fileName ) {
			if ( $this->wasSeen( $fileName, 'file' ) ) {
				continue;
			}
			$fileStatus = $this->fileDownloader->downloadFile( $fileName );
			if ( $fileStatus->isOK() ) {
				$user = $this->importer->makeUser( $revision->userCreatedUsername,
					$revision->userCreated, true );
				$fileImportStatus = $this->importer->importFile( $fileStatus->getValue(), $user );
				$fileStatus->merge( $fileImportStatus );
			}
			if ( $fileStatus->isGood() ) {
				$this->logger->notice( "File imported: $fileName" );
			} elseif ( !$fileStatus->isOK() ) {
				$this->logger->error( "Failed to import file: $fileName" );
				$status->error( 'wikispacesmigration-importmanager-fileerror', $fileName,
					Status::wrap( $fileStatus )->getWikiText() );
			}
		}

		$revisionImportStatus = $this->importer->importRevision( $revision,
			$this->wikitextConverter->convertToMediaWiki( $text ) );

		$status->merge( $revisionImportStatus );
		return $status;
	}

	/**
	 * Import all users of the space.
	 * This must be called before anything else if you want to use it at all!
	 * @return SkippingStatusValue
	 */
	public function importUsers() {
		$status = SkippingStatusValue::newGood();

		try {
			$authSources = [];
			foreach ( $this->wikispacesApi->listAuthSources() as $authSource ) {
				$authSources[$authSource->id] = $authSource;
			}
			$users = $this->wikispacesApi->listUsers();

			foreach ( $users as $user ) {
				$authSource = $authSources[$user->authSourceId];
				// TODO set user creation date
				// TODO set authentication
				// TODO set organizer group
				$this->importer->makeUser( $user->username, $user->id );
			}
		} catch ( SoapFault $e ) {
			throw $e;
		}

		return $status;
	}

	/**
	 * @param string $pageName
	 * @return SkippingStatusValue
	 */
	public function importTalkPage( $pageName ) {
		$status = SkippingStatusValue::newGood();
		$page = $this->getPage( $pageName );

		$edits = [];
		$topics = $this->wikispacesApi->listTopics( $page->id );
		foreach ( $topics as $i => $topic ) {
			// Sections are numbered from 1, with 0 being the initial, title-less part.
			$sectionId = $i + 1;
			$edits[] = [
				'topic' => $topic->topicId,
				'section' => $sectionId,
				'position' => 0,
				'message' => $topic,
				'timestamp' => $topic->dateCreated,
			];
			// TODO does this duplicate the top-level comment?
			try {
				$replies = $this->wikispacesApi->listMessagesInTopic( $topic->topicId );
			} catch ( SoapException $e ) {
				if ( $e->getSoapMessage() === 'Invalid Object' ) {
					// This seems to happen sometimes. Best we can do is ignore it and skip that topic.
					continue;
				} else {
					throw $e;
				}
			}
			foreach ( $replies as $j => $reply ) {
				$edits[] = [
					'topic' => $topic->topicId,
					'section' => $sectionId,
					'position' => $j + 1,
					'message' => $reply,
					'timestamp' => $reply->dateCreated,
				];
			}
		}
		usort( $edits, function ( $left, $right ) {
			// Can't just use the timestamp as it's not necessarily unique, and might
			// result in logical inconsistencies.
			if ( $left['position'] === 0 && $right['position'] === 0 ) {
				// Creation order and display order is the same thing for topics.
				return $left['section'] - $right['section'];
			} elseif ( $left['section'] === $right['section'] ) {
				// Creation order and display order is the same thing within a topic.
				return $left['position'] - $right['position'];
			}
			return $left['timestamp'] - $right['timestamp'];
		} );

		foreach ( $edits as $edit ) {
			$sectionId = $edit['section'];
			$message = $edit['message'];
			$isTopComment = ( $edit['position'] === 0 );

			$user = $this->importer->makeUser( $message->userCreatedUsername, $message->userCreated );
			if ( $isTopComment ) {
				$wikitext = $this->wikitextConverter->convertTopComment( $message->subject, $message->body,
					$user->getName(), $message->dateCreated );
			} else {
				$wikitext = $this->wikitextConverter->convertComment( $message->body, $user->getName(),
					$message->dateCreated );
			}
			$editStatus = $this->importer->importTalkEdit( $page->name, $sectionId, $user, $wikitext,
				$message->dateCreated );
			$status->merge( $editStatus );
		}

		return $status;
	}

	/**
	 * Check if something was seen already (ie. wasSeen was called for it with $markSeen === true).
	 * @param string $name Page name, file name etc.
	 * @param string $type 'page', 'file' etc.
	 * @param bool $markSeen
	 * @return bool
	 */
	private function wasSeen( $name, $type, $markSeen = true ) {
		if ( !isset( $this->seen[$type] ) ) {
			$this->seen[$type] = [];
		}
		$seen = isset( $this->seen[$type][$name] );
		if ( $markSeen ) {
		}
		$this->seen[$type][$name] = true;
		return $seen;
	}

	// FIXME move these into a wrapper around WikispacesApi, probably

	/**
	 * Get page metadata, with caching.
	 * @param string $pageName
	 * @return WikispacesPage
	 */
	private function getPage( $pageName ) {
		if ( !isset( $this->pages[$pageName] ) ) {
			$this->pages[$pageName] = $this->wikispacesApi->getPage( $pageName );
		}
		return $this->pages[$pageName];
	}

	/**
	 * Get page metadata for all pages, with caching.
	 * @return WikispacesPage[]
	 */
	private function getAllPages() {
		if ( !$this->gotAllPages ) {
			$this->pages = [];
			foreach ( $this->wikispacesApi->listPages() as $page ) {
				$this->pages[$page->name] = $page;
			}
		}
		return $this->pages;
	}

}
