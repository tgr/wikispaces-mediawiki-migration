<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * A message (comment).
 */
class WikispacesMessage extends WikispacesTransferObject {

	/**
	 * The unique integer identifier for this tag.
	 * @var int
	 */
	public $id;

	/**
	 * The subject of the message or topic.
	 * @var string
	 */
	public $subject;

	/**
	 * The body/contents of the message, as wikitext.
	 * @note Some SOAP methods return message metadata only, leaving this field empty.
	 * @var string
	 */
	public $body;

	/**
	 * The rendered HTML of the message body.
	 * @note Some SOAP methods return message metadata only, leaving this field empty.
	 * @var string
	 */
	public $html;

	/**
	 * The id of the page that this message is associated with.
	 * @var int
	 */
	public $pageId;

	/**
	 * The id of the message that is at the top level of the thread, representing the topic.
	 * @var int
	 */
	public $topicId;

	/**
	 * The number of responses to this message, if it is a topic.
	 * @var int
	 */
	public $responses;

	/**
	 * Undocumented. The ID of the last message in this topic, presumably.
	 * @var int
	 */
	public $latestResponseId;

	/**
	 * Undocumented. The creation timestamp of $latestResponseId, maybe?
	 * @var int
	 */
	public $dateResponse;

	/**
	 * The date that this message was posted.
	 * @var int
	 */
	public $dateCreated;

	/**
	 * The id of the user that posted this message.
	 * @var int
	 */
	public $userCreated;

	/**
	 * The username of the user that posted this message.
	 * @var string
	 */
	public $userCreatedUsername;

}
