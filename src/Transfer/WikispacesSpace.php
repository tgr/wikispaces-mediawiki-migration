<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * Contains information about a wiki space.
 */
class WikispacesSpace extends WikispacesTransferObject {

	/**
	 * The unique integer identifier for the space.
	 * @var int
	 */
	public $id;

	/**
	 * The unique name of the space.
	 * Appears in the URL as the subdomain, unless the space uses a custom domain.
	 * @var String
	 */
	public $name;

	/**
	 * A custom name for the space.
	 * Displayed on web pages and used when more appropriate than "name".
	 * @var String
	 */
	public $text;

	/**
	 * A custom description of the space.
	 * @var String
	 */
	public $description;

	/**
	 * The number of pages in the space.
	 * @var int
	 */
	public $pageCount;

	/**
	 * The status of the space.
	 * @var string
	 */
	public $status;

	/**
	 * deprecated
	 * (0 or null)
	 * @var int|null
	 */
	public $edits;

	/**
	 * The type of the image that the space logo is.
	 *   g = GIF
	 *   j = JPEG
	 *   p = PNG
	 *   null = none
	 * @var string
	 */
	public $imageType;

	/**
	 * The HEX triplet color that the space uses for a background color (if the theme supports it).
	 * @var string
	 */
	public $backgroundColor;

	/**
	 * The HEX triplet color that the space uses for a highlight color (if the theme supports it).
	 * @var string
	 */
	public $highlightColor;

	/**
	 * The HEX triplet color that the space uses for a text color (if the theme supports it).
	 * @var string
	 */
	public $textColor;

	/**
	 * The HEX triplet color that the space uses for a link color (if the theme supports it).
	 * @var string
	 */
	public $linkColor;

	/**
	 * The subscription type for the space.
	 *   N = none
	 *   C = comp
	 *   P = prepay
	 *   R = recurring
	 *   T = trial
	 * @var string
	 */
	public $subscriptionType;

	/**
	 * The subscription level for the space.
	 *   F = free
	 *   P = plus
	 *   S = super
	 * @var string
	 */
	public $subscriptionLevel;

	/**
	 * The timestamp of the end date of the current subscription.
	 * @var int
	 */
	public $subscriptionEndDate;

	/**
	 * Specifies who can see the space.
	 *   A = all
	 *   L = logged in
	 *   M = member
	 *   O = organizer
	 * @var string
	 */
	public $viewGroup;

	/**
	 * Specifies who can edit the space.
	 *   A = all
	 *   L = logged in
	 *   M = member
	 *   O = organizer
	 * @var string
	 */
	public $editGroup;

	/**
	 * Specifies who can create pages in the space.
	 *   A = all
	 *   L = logged in
	 *   M = member
	 *   O = organizer
	 * @var string
	 */
	public $createGroup;

	/**
	 * Specifies who can post messages in the space.
	 *   A = all
	 *   L = logged in
	 *   M = member
	 *   O = organizer
	 * @var string
	 */
	public $messageEditGroup;

	/**
	 * Should search engines be allowed to crawl the space.
	 * @var bool
	 */
	public $isCrawled;

	/**
	 * The license that the space content is released under.
	 * Can be one of many Creative Commons licenses, GNU-FDL, or "none".
	 * @var string
	 */
	public $license;

	/**
	 * The discussion settings of the space.
	 *   N = none
	 *   O = one
	 *   P = per page
	 * @var string
	 */
	public $discussions;

	/**
	 * The unix timestamp of the time when the space was created.
	 * @var int
	 */
	public $dateCreated;

	/**
	 * The unix timestamp of the time when the space was last modified.
	 * @var int
	 */
	public $dateUpdated;

	/**
	 * The user ID of the user that created this space.
	 * @var int
	 */
	public $userCreated;

	/**
	 * The user ID of the user that last modified this space.
	 * @var int
	 */
	public $userUpdated;

}
