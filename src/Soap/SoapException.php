<?php

namespace MediaWiki\Extension\WikispacesMigration\Soap;

use RuntimeException;

class SoapException extends RuntimeException {

	/** @var string SOAP function name */
	public $functionName;

	/** @var mixed[] SOAP call arguments */
	public $arguments;

	/** @var int HTTP response code */
	public $responseCode;

	/** @var int HTTP response status text */
	public $responseStatus;

	/** @var string[] HTTP response headers*/
	public $responseHeaders;

	private $functionDefinition;

	/**
	 * @param \SoapFault $original
	 * @param string $functionName The name of the SOAP function that was called.
	 * @param array $arguments Function arguments.
	 * @param array $outputHeaders SOAP response headers.
	 * @param int $responseCode HTTP response code from the SOAP server.
	 */
	public function __construct( \SoapFault $original, $functionName, array $arguments ) {
		$message = $original->getMessage();
		parent::__construct( "$functionName: $message ($original->faultcode)",
			$original->getCode(), $original );
		$this->functionName = $functionName;
		$this->arguments = $arguments;
	}

	/**
	 * Display a method signature for logging.
	 * @return string
	 */
	public function getSignature() {
		$args = $this->functionDefinition
			? implode( ', ', array_keys( $this->functionDefinition[1] ) ) : '???';
		return "$this->functionName( $args )";
	}

	/**
	 * Display a method signature with actual values for logging.
	 * @return string
	 */
	public function getSignatureWithValues() {
		$printedArgs = implode( ', ', array_map( function ( $arg ) {
			static $i = 0;
			if ( is_array( $arg ) ) {
				$length = count( $arg );
				$value = "<array>($length)";
			} elseif ( is_object( $arg ) ) {
				$type = get_class( $arg );
				$value = "<$type> $arg";
			} else {
				$type = gettype( $arg );
				$value = "<$type> $arg";
			}
			if ( $this->functionDefinition ) {
				return array_keys( $this->functionDefinition[1] )[$i] . " => $value";
			} else {
				return $value;
			}
		}, $this->arguments ) );
		return "$this->functionName( $printedArgs )";
	}

	/**
	 * @param int $httpCode
	 * @param string $httpStatus
	 * @param array $headers
	 */
	public function setResponse( $httpCode, $httpStatus, array $headers ) {
		$this->responseCode = $httpCode;
		$this->responseStatus = $httpStatus;
		$this->responseHeaders = $headers;
	}

	public function getSoapMessage() {
		return $this->getPrevious()->getMessage();
	}

	public function getSoapCode() {
		return $this->getPrevious()->faultcode;
	}

	public function getDetail() {
		return $this->getPrevious()->detail ?: null;
	}

	/**
	 * @param string $returnType
	 * @param string[] $args Parameter name => type
	 */
	public function setFunctionDefinition( $returnType, $args ) {
		$this->functionDefinition = [ $returnType, $args];
	}

}
