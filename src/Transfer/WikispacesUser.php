<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * An existing user in the system.
 * @see http://helpcenter.wikispaces.com/customer/portal/articles/1964502-api-customizations
 */
class WikispacesUser extends WikispacesTransferObject {

	/**
	 * The unique integer identifier for this user.
	 * @var int
	 */
	public $id;

	/**
	 * The unique username for this user.
	 * Users log in with this, and it is displayed as their name.
	 * @var string
	 */
	public $username;

	/**
	 * The number of message posts a user has made.
	 * @var int
	 */
	public $posts;

	/**
	 * The number of wiki page edits a user has made.
	 * @var int
	 */
	public $edits;

	/**
	 * The id of the authentication source the user is associated with.
	 * @var int
	 */
	public $authSourceId;

	/**
	 * A unique identifier of the user in its authentication source.
	 * @var string
	 */
	public $authExternalId;

	/**
	 * The unix timestamp of the time when the user was created.
	 * @var int
	 */
	public $dateCreated;

	/**
	 * The unix timestamp of the time when the user was last modified
	 * @var int
	 */
	public $dateUpdated;

	/**
	 * The user ID of the user that created this user
	 * @var int
	 */
	public $userCreated;

	/**
	 * The user ID of the user that last modified this user.
	 * @var int
	 */
	public $userUpdated;

}
