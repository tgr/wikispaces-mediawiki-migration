<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * Represents a tag associated with a page.
 */
class WikispacesTag extends WikispacesTransferObject {

	/**
	 * The unique integer identifier for this tag.
	 * @var int
	 */
	public $id;

	/**
	 * The name of the tag.
	 * @var string
	 */
	public $name;

	/**
	 * The integer id of the page that this tag is associated with.
	 * @var int
	 */
	public $pageId;

	/**
	 * The date that this tag was added to the page.
	 * @var int
	 */
	public $dateCreated;

	/**
	 * The id of the user that added the tag to the page.
	 * @var int
	 */
	public $userCreated;

}
