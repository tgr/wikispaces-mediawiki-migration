<?php

namespace MediaWiki\Extension\WikispacesMigration;

use StatusValue;

class SkippingStatusValue extends StatusValue {

	/** @var int Counter for batch operations */
	public $skipCount = 0;

	public function merge( $other, $overwriteValue = false ) {
		parent::merge( $other, $overwriteValue );
		if ( $other instanceof SkippingStatusValue ) {
			$this->skipCount += $other->skipCount;
		}
	}

}
