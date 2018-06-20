<?php

namespace MediaWiki\Extension\WikispacesMigration\Soap;

use Http;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * SoapClient wrapper.
 */
class SoapClient extends \SoapClient implements LoggerAwareInterface {

	use LoggerAwareTrait;

	/** @var LoggerInterface */
	protected $logger;

	private $callContext = [];
	private $functionDefinitions = [];

	public function __construct( $wsdl, array $options = null ) {
		$options = ($options ?: []) + [ 'user_agent' => Http::userAgent() ];
		$options['trace'] = true;
		if ( Http::getProxy() ) {
			$proxyParts = explode( ':', Http::getProxy() );
			$options += [ 'proxy_host' => $proxyParts[0] ];
			if ( isset( $proxyParts[1] ) ) {
				$options += [ 'proxy_port' => $proxyParts[1] ];
			}
		}
		parent::__construct( $wsdl, $options );
	}

	public function __call( $name, $arguments ) {
		$this->callContext = [
			'name' => $name,
			'arguments' => $arguments,
		];
		try {
			return parent::__call( $name, $arguments );
		} catch ( \SoapFault $e ) {
			$e = $this->__handleSoapFault( $e );
			throw $e;
		} finally {
			$this->callContext = [];
		}
	}

	private function __handleSoapFault( $e ) {
		$functionName = $this->callContext['name'] ?? null;
		$arguments = $this->callContext['arguments'] ?? null;
		list( $responseHttpCode, $responseHttpStatus, $responseHeaders ) =
			$this->__parseHeader( $this->__getLastResponseHeaders() );

		$e = new SoapException( $e, $functionName, $arguments );
		$e->setResponse( $responseHttpCode, $responseHttpStatus, $responseHeaders );
		$functionDefinition = $this->__getFunctionDefinition( $functionName );
		if ( $functionDefinition ) {
			$e->setFunctionDefinition( $functionDefinition[0], $functionDefinition[1] );
		}

		$this->logger->error( 'SOAP error while calling {sig}: {message} ({code})', [
			'functionName' => $functionName,
			'sig' => $e->getSignature(),
			'responseHeaders' => $e->responseHeaders,
			'message' => $e->getMessage(),
			'code' => $e->getSoapCode(),
			'detail' => $e->getDetail(),
		] );

		return $e;
	}

	/**
	 * @param string $outputHeadersString
	 * @return array [ HTTP response code, HTTP response status, [ header => value ] ]
	 */
	private function __parseHeader( $outputHeadersString ) {
		$code = $status = null;
		$outputHeaders = [];
		foreach ( preg_split( '/\r\n?|\n/', $outputHeadersString ) as $i => $headerLine ) {
			if ( $i === 0 ) {
				list( , $code, $status ) = explode (' ', $headerLine, 3 );
			} elseif ( trim( $headerLine ) ) {
				$headerLineParts = explode( ':', $headerLine, 2 );
				$outputHeaders[$headerLineParts[0]] = $headerLineParts[1];
			}
		}
		return [ (int)$code, $status, $outputHeaders ];
	}

	/**
	 * @param $functionName
	 * @return array|false [ return type, [ parameter name => parameter type, ... ] ]
	 */
	private function __getFunctionDefinition( $functionName ) {
		if ( !$this->functionDefinitions ) {
			foreach ( $this->__getFunctions() as $functionDefinitionString ) {
				preg_match( '/(\S+) (\S+)\((.*\))/', $functionDefinitionString, $matches );
				preg_match_all( '/([\w\d]+) ([$\w\d]+)/', $matches[3], $matches2, PREG_SET_ORDER );
				$args = [];
				foreach ( $matches2 as list( $_, $argType, $argName ) ) {
					$args[$argName] = $argType;
				}
				$this->functionDefinitions[$matches[2]] = [ $matches[1], $args ];
			}
		}
		return $this->functionDefinitions[$functionName] ?? false;
	}

}
