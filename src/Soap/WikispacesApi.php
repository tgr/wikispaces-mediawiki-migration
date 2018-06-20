<?php

namespace MediaWiki\Extension\WikispacesMigration\Soap;

use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesAuthSource;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesMember;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesMessage;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesPage;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesSpace;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesTag;
use MediaWiki\Extension\WikispacesMigration\Transfer\WikispacesUser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * @see http://helpcenter.wikispaces.com/customer/portal/articles/1959131-wikitext
 * @see http://helpcenter.wikispaces.com/customer/portal/articles/1964502-api-customizations
 */
class WikispacesApi implements LoggerAwareInterface {

	use LoggerAwareTrait;

	const WIKISPACES_URL = 'http://www.wikispaces.com';

	/** @var LoggerInterface */
	protected $logger;

	/** @var string Wikispaces API username */
	private $user;

	/** @var string Wikispaces API password */
	private $password;

	/** @var string Wikispaces space name */
	private $spacename;

	/** @var SoapClient */
	private $siteApi = null;

	/** @var SoapClient */
	private $userApi = null;

	/** @var SoapClient */
	private $spaceApi = null;

	/** @var SoapClient */
	private $pageApi = null;

	/** @var SoapClient */
	private $tagApi = null;

	/** @var SoapClient */
	private $messageApi = null;

	/** @var string Session token, valid for 24 hours. */
	private $session = null;

	/** @var WikispacesSpace */
	private $space;

	private $isOpen = false;

	public function __construct( $user, $password, $spacename ) {
		$this->user = $user;
		$this->password = $password;
		$this->spacename = $spacename;
	}

	// ----------- spaces -----------

	/**
	 * @return WikispacesSpace
	 */
	public function getSpace() {
		if ( !$this->space ) {
			$soapData = $this->spaceApi->getSpace( $this->session, $this->spacename );
			$this->space = WikispacesSpace::newFromSoapData( $soapData );
		}
		return $this->space;
	}

	/**
	 * Lists all authentication sources.
	 * This function cannot be used with Wikispaces Private Label Original.
	 * @return WikispacesAuthSource[]
	 */
	public function listAuthSources() {
		$this->open();
		$soapData = $this->userApi->listAuthSources( $this->session );
		return array_map( [ WikispacesAuthSource::class, 'newFromSoapData' ], $soapData );
	}

	/**
	 * Lists all users.
	 * This function cannot be used with Wikispaces Private Label Original.
	 * @return WikispacesUser[]
	 */
	public function listUsers() {
		$this->open();
		$soapData = $this->userApi->listUsers( $this->session );
		return array_map( [ WikispacesUser::class, 'newFromSoapData' ], $soapData );
	}

	/**
	 * List all members of a space, along with their member access level.
	 * Kinda useless since WikispacesMember has very little info.
	 * @return WikispacesMember[]
	 */
	public function listMembers() {
		$this->open();
		$soapData = $this->spaceApi->listMembers( $this->session, $this->getSpace()->id );
		return array_map( [ WikispacesMember::class, 'newFromSoapData' ], $soapData );
	}

	// ----------- pages -----------

	/**
	 * Get a list of all pages in a space.
	 * @return WikispacesPage[]
	 */
	public function listPages() {
		$this->open();
		$soapData = $this->pageApi->listPages( $this->session, $this->getSpace()->id );
		return array_map( [ WikispacesPage::class, 'newFromSoapData' ], $soapData );
	}

	/**
	 * Get a list of all page versions of a given page.
	 * @param string $pageName
	 * @return WikispacesPage[]
	 */
	public function listPageVersions( $pageName ) {
		$this->open();
		$soapData = $this->pageApi->listPageVersions( $this->session, $this->getSpace()->id, $pageName );
		return array_map( [ WikispacesPage::class, 'newFromSoapData' ], $soapData );
	}

	/**
	 * Get a page with the latest version of content.
	 * Some pages have no versions created and will not have content.
	 * @param string $pageName
	 * @return WikispacesPage
	 */
	public function getPage( $pageName ) {
		$this->open();
		$soapData = $this->pageApi->getPage( $this->session, $this->getSpace()->id, $pageName );
		return WikispacesPage::newFromSoapData( $soapData );
	}

	/**
	 * Get a page with a specific version of the content.
	 * @param string $pageName
	 * @param int $version
	 * @note This is the only method that fills the "content" and "html" fields of the returned page.
	 * @return WikispacesPage
	 */
	public function getPageWithVersion( $pageName, $version ) {
		$this->open();
		$soapData = $this->pageApi->getPageWithVersion( $this->session, $this->getSpace()->id,
			$pageName, $version );
		return WikispacesPage::newFromSoapData( $soapData );
	}

	// ----------- tags -----------

	/**
	 * Get a list of tags added to the given page.
	 * @param int $pageId
	 * @return WikispacesTag[]
	 */
	public function listTagsForPage( $pageId ) {
		$this->open();
		$soapData = $this->tagApi->listTagsForPage( $this->session, $pageId ) ?: [];
		return array_map( [ WikispacesTag::class, 'newFromSoapData' ], $soapData );
	}

	// ----------- messages -----------

	/**
	 * Get a list of topics (top-level messages) for a given page.
	 * @note non-existing pages can have topics. There seems to be no way to get these
	 *   without knowing the page name.
	 * @note This method will not fill the body/html properties of the returned objects.
	 * @param int $pageId
	 * @return WikispacesMessage[]
	 */
	public function listTopics( $pageId ) {
		$this->open();
		$soapData = $this->messageApi->listTopics( $this->session, $pageId ) ?: [];
		return array_map( [ WikispacesMessage::class, 'newFromSoapData' ], $soapData );
	}

	/**
	 * Get a list of messages under a given topic (top-level message).
	 * @param int $topicId
	 * @return WikispacesMessage[]
	 */
	public function listMessagesInTopic( $topicId ) {
		$this->open();
		$soapData = $this->messageApi->listMessagesInTopic( $this->session, $topicId ) ?: [];
		return array_map( [ WikispacesMessage::class, 'newFromSoapData' ], $soapData );
	}

	// ----------- internal -----------

	private function open() {
		if ( $this->isOpen ) {
			return;
		}

		$this->siteApi = new SoapClient( self::WIKISPACES_URL . '/site/api/?wsdl' );
		$this->userApi = new SoapClient( self::WIKISPACES_URL . '/user/api/?wsdl' );
		$this->spaceApi = new SoapClient( self::WIKISPACES_URL . '/space/api/?wsdl' );
		$this->pageApi = new SoapClient( self::WIKISPACES_URL . '/page/api/?wsdl' );
		$this->tagApi = new SoapClient( self::WIKISPACES_URL . '/tag/api/?wsdl' );
		$this->messageApi = new SoapClient( self::WIKISPACES_URL . '/message/api/?wsdl' );

		$this->siteApi->setLogger( $this->logger );
		$this->userApi->setLogger( $this->logger );
		$this->spaceApi->setLogger( $this->logger );
		$this->pageApi->setLogger( $this->logger );
		$this->tagApi->setLogger( $this->logger );
		$this->messageApi->setLogger( $this->logger );

		$this->session = $this->siteApi->login( $this->user, $this->password );
		$this->space = $this->getSpace();
	}

}
