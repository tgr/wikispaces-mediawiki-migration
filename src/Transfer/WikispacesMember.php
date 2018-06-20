<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * Describes a member of a space.
 */
class WikispacesMember extends WikispacesTransferObject {

	const TYPE_MEMBER = 'M';
	const TYPE_ORGANIZER = 'O';

	/**
	 * User ID.
	 * @var int
	 */
	public $userId;

	/**
	 * The username that belongs to the space.
	 * @var string
	 */
	public $username;

	/**
	 * The access level of the member.
	 *   M - member
	 *   O - organizer
	 * @var string
	 */
	public $type;

}
