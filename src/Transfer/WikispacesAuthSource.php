<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * An authentication source users can be associated with in the system.
 * @see http://helpcenter.wikispaces.com/customer/portal/articles/1964502-api-customizations
 */
class WikispacesAuthSource extends WikispacesTransferObject {

	const TYPE_PASSWORD = 'P';
	const TYPE_WIKISPACES_SSO = 'W';
	const TYPE_SAML = 'S';
	const TYPE_OPENID = 'O';
	const TYPE_GOOGLE = 'G';
	const TYPE_LDAP = 'L';
	const TYPE_MOODLE = '?';
	const TYPE_LTI = 'T';

	/**
	 * The unique integer identifier for this authentication source.
	 * @var int
	 */
	public $id;

	/**
	 * The name of this authentication source.
	 * @var string
	 */
	public $name;

	/**
	 * The type of this authentication source.
	 *   P = Wikispaces Password
	 *   W = Wikispaces SSO
	 *   S = SAML
	 *   O = OpenID
	 *   G = Google Apps
	 *   L = LDAP
	 *   M = Moodle
	 *   T = LTI
	 * @var string
	 */
	public $type;

	/**
	 * The status of this authentication source.
	 *   A = active
	 *   D = disabled
	 * @var string
	 */
	public $status;

}
