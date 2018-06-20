<?php

namespace MediaWiki\Extension\WikispacesMigration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RawMessage;
use StatusValue;

/**
 * Get Wikispaces content using WebDAV.
 * Cannot get most metadata and is not really useful (except for files which cannot be downloaded
 * in any other way).
 * @see https://www.wikispaces.com/help+webdav
 */
class Downloader implements LoggerAwareInterface {
// TODO rename to WebDavDownloader

	use LoggerAwareTrait;

	/** @var LoggerInterface */
	protected $logger;

	/** @var string Wikispaces API username */
	private $user;

	/** @var string Wikispaces API password */
	private $password;

	/** @var string Wikispaces space name */
	private $spacename;

	/** @var string */
	private $cacheDir;

	public function __construct( $user, $password, $spacename ) {
		global $wgCacheDirectory;

		$this->user = $user;
		$this->password = $password;
		$this->spacename = $spacename;
		$this->cacheDir = ( $wgCacheDirectory ?: wfTempDir() ) . '/wikispaces';
		$this->setLogger( new NullLogger() );
	}

	private function initTempDir() {
		if ( !file_exists( $this->cacheDir ) ) {
			mkdir( $this->cacheDir );
		}
	}

	/**
	 * @return StatusValue Contains the contents of the downloaded URLon success.
	 */
	private function downloadUrl( $url ) {
		// TODO  use MediaWiki's HTTP class?

		$curlHandle = curl_init();
		curl_setopt_array( $curlHandle, [
			// CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_USERPWD => $this->user . ':' . $this->password,
			CURLOPT_URL => $url,
		] );
		$buffer = curl_exec( $curlHandle );
		$curlError = ( $buffer === false ) ? curl_error( $curlHandle ) : null;
		curl_close( $curlHandle );

		if ( $curlError ) {
			$this->logger->error( 'Curl error : {error}', [ 'error' => $curlError ] );
			return StatusValue::newFatal( 'wikispacesmigration-downloader-curlerror', $curlError );
		}
		return StatusValue::newGood( $buffer );
	}

	/**
	 * Get a file by name.
	 * @param string $fileName
	 * @return StatusValue Contains the path to the downloaded file on success.
	 */
	public function downloadFile( $fileName ) {
		$destination = $this->cacheDir . '/' . $fileName;
		if ( file_exists( $destination ) ) {
			return StatusValue::newGood( $destination );
		}

		$url = "https://{$this->spacename}.wikispaces.com/space/dav/files/$fileName";
		$status = $this->downloadUrl( $url );

		if ( $status->isOK() ) {
			$this->initTempDir();
			file_put_contents( $destination, $status->getValue() );
			$status->setResult( true, $destination );
		}
		return $status;
	}

	/**
	 * Get a page by name.
	 * @param string $pageName
	 * @param int $version
	 * @param bool $html Get HTML instead of wikitext
	 * @return StatusValue Contains the page source (wikitext) or HTML.
	 */
	public function downloadPage( $pageName, $version = null, $html = false ) {
		$html_postfix = $html ? '_html' : '';
		$url = $version === null
			? "https://{$this->spacename}.wikispaces.com/space/dav/pages$html_postfix/$pageName"
			: "https://{$this->spacename}.wikispaces.com/space/dav/history$html_postfix/$pageName/$version";
		return $this->downloadUrl( $url );
	}

	/**
	 * Get the version IDs for a page.
	 * @param string $pageName
	 * @return StatusValue Contains an array of IDs.
	 */
	public function getVersions( $pageName ) {
		$url = "https://{$this->spacename}.wikispaces.com/space/dav/history/$pageName";
		$status = $this->downloadUrl( $url );
		if ( $status->isOK() ) {
			preg_match_all( "#<a href=\"/space/dav/history/$pageName/(\d+)\">#",
				$status->getValue(), $matches );
			$status->setResult( true, $matches[1] );
		}
		return $status;
	}

}
