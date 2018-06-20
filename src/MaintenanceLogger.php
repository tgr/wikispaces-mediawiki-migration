<?php

namespace MediaWiki\Extension\WikispacesMigration;

use Maintenance;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * A PSR-3 adapter for the logging methods of the maintenance class
 */
class MaintenanceLogger implements LoggerInterface {

	use LoggerTrait;

	/** @var callable */
	private $outputCallback;

	/** @var callable */
	private $errorCallback;

	public function __construct( callable $outputCallback, callable $errorCallback ) {
		$this->outputCallback = $outputCallback;
		$this->errorCallback = $errorCallback;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 *
	 * @return void
	 */
	public function log( $level, $message, array $context = [] ) {
		$message = $this->interpolate( $message, $context );
		switch ( $level ) {
			case LogLevel::ERROR:
			case LogLevel::ALERT:
			case LogLevel::EMERGENCY:
			case LogLevel::CRITICAL:
				call_user_func( $this->errorCallback, $message . PHP_EOL );
				break;

			case LogLevel::WARNING:
			case LogLevel::NOTICE:
			case LogLevel::INFO:
			case LogLevel::DEBUG:
				call_user_func( $this->outputCallback, $message . PHP_EOL );
				return;
		}
	}

	private function interpolate( $message, array $context ) {
		return preg_replace_callback( '/{([\w\d]+)}/', function ( $match ) use ( $context ) {
			return $context[$match[1]] ?? $match[0];
		}, $message );
	}

}
