<?php

namespace MediaWiki\Extension\WikispacesMigration\Transfer;

use LogicException;

/**
 * Base class for Wikispaces transfer objects.
 * Transfer objects follow the structure and naming used by the Wikispaces API.
 * @see http://helpcenter.wikispaces.com/customer/portal/articles/1964502-api-customizations
 */
abstract class WikispacesTransferObject {

	/**
	 * Convert a SOAP response into a transfer object.
	 * @param array $data Data, as returned by the SOAP call.
	 * @return static
	 */
	public static function newFromSoapData( $data ) {
		$object = new static();
		foreach ( $data as $field => $value ) {
			$field = preg_replace_callback( '/_(\w)/', function ( array $matches ) {
				return strtoupper( $matches[1] );
			}, $field );
			if ( !property_exists( static::class, $field ) ) {
				throw new LogicException( static::class . " does not have a $field field" );
			}
			$object->$field = $value;
		}
		return $object;
	}

}
