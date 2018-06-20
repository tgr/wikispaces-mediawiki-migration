<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * Data transfer object for a Wikispaces page.
 * @see http://helpcenter.wikispaces.com/customer/portal/articles/1964502-api-customizations
 */
class WikispacesPage extends WikispacesTransferObject {

	/**
	 * The unique integer identifier for this page (which is a collection of versions of the page).
	 * @var int
	 */
	public $id;

	/**
	 * Version ID.
	 * @var int
	 */
	public $versionId;

	/**
	 * The name of the page, which is unique within the space.
	 * @var string
	 */
	public $name;

	/**
	 * The integer id of the space that this page is in.
	 * @var int
	 */
	public $spaceId;

	/**
	 * The version id of the latest version of the page.
	 * @var int
	 */
	public $latestVersion;

	/**
	 * The number of versions of this page that exist (some pages have 0 versions).
	 * @var int
	 */
	public $versions;

	/**
	 * Is the current page locked for editing by organizers only?
	 * @var bool
	 */
	public $isReadOnly;

	/**
	 * Undocumented, probably the same as in WikispacesSpace
	 * @see WikispacesSpace::$viewGroup
	 * @var string
	 */
	public $viewGroup;

	/**
	 * Undocumented, probably the same as in WikispacesSpace
	 * @see WikispacesSpace::$editGroup
	 * @var string
	 */
	public $editGroup;

	/**
	 * The change comment of the version of the page.
	 * @var string
	 */
	public $comment;

	/**
	 * The un-rendered wikitext content of the version of the page.
	 * @note Some SOAP methods return page metadata only, leaving this field empty.
	 * @var string
	 */
	public $content;

	/**
	 * The renderd HTML content of the version of the page.
	 * @note Some SOAP methods return page metadata only, leaving this field empty.
	 * @var string
	 */
	public $html;

	/**
	 * The unix timestamp of the time when this version of the page was created (can be null).
	 * @var int|null
	 */
	public $dateCreated;

	/**
	 * The user ID of the user that created this version of the page (can be null).
	 * @var int|null
	 */
	public $userCreated;

	/**
	 * The username of hte user that created this version of the page (can be null).
	 * @var string|null
	 */
	public $userCreatedUsername;

}
