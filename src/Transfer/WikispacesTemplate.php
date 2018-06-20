<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

/**
 * A template that a space can be created from.
 */
class WikispacesTemplate extends WikispacesTransferObject {

	/**
	 * The unique integer identifier for the template.
	 * @var int
	 */
	public $id;

	/**
	 * The unique name of the template.
	 * @var string
	 */
	public $name;

	/**
	 * The integer identifier for the space that the template will base templated spaces on.
	 * @var int
	 */
	public $spaceId;

}
