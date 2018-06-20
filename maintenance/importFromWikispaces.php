<?php
/**
 * Import pages from Wikispaces
 *
 * ======================================================================
 *
 * To use this script, put it in your MediaWiki 'maintenance' folder
 * Then call the script on the command line.
 *
 * Examples of use :
 *
 * php importWikispaces.php --help
 *
 * php importWikispaces.php --overwrite -u username -p password -s wikiname
 *
 * ======================================================================
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Sylvain Philip <contact at sphilip.com>
 * @ingroup Maintenance
 */

namespace MediaWiki\Extension\WikispacesMigration\Maintenance;

use Maintenance;
use MediaWiki\Extension\WikispacesMigration\Importer;
use MediaWiki\Extension\WikispacesMigration\ImportManager;
use MediaWiki\Extension\WikispacesMigration\MaintenanceLogger;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script which reads in text files
 * and imports their content to a page of the wiki.
 *
 * @ingroup Maintenance
 */
class ImportFromWikispaces extends Maintenance {

	protected $exit = 0;

	protected $successCount = 0;

	protected $failCount = 0;

	protected $skipCount = 0;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'WikispacesMigration' );

		$this->addDescription( 'Reads WikiSpaces pages and imports their content and files in the wiki' );
		$this->addOption( 'user', 'Wikispaces username.', true, true, 'u' );
		$this->addOption( 'password', 'Wikispaces password.', true, true, 'p' );
		$this->addOption( 'spacename', 'Wikispaces space name.', true, true, 's' );
		$this->addOption( 'page', 'Import only one page. Specify the name of the page.', false, true );
		$this->addOption( 'overwrite', 'Overwrite existing pages. This will only overwrite pages if the remote page has been modified since the local page was last modified.' );
		$this->addOption( 'with-history', 'Preserve history importing wikispaces pages revisions.' );
		$this->addOption( 'use-timestamp', 'Use the modification date of the page as the timestamp for the edit' );
		$this->addOption( 'force', 'Force overwrite. It will force overwrite even if old content is equal with new one and doesn\'t check timestamp for the edit.', false, false, 'f' );
	}

	public function execute() {
		$importManager = new ImportManager( $this->getOption('user'), $this->getOption('password'),
			$this->getOption('spacename') );
		$importManager->setLogger( $this->getLogger() );
		if ( $this->hasOption( 'force' ) ) {
			$importManager->setOverwrite(  Importer::OVERWRITE_ALWAYS );
		} elseif ( $this->hasOption( 'overwrite' ) ) {
			$importManager->setOverwrite( Importer::OVERWRITE_OLDER );
		} else {
			$importManager->setOverwrite(  Importer::OVERWRITE_NEVER );
		}
		$importManager->setUseTimestamp( $this->hasOption( 'use-timestamp' ) );

		$pageName = $this->getOption( 'page' );
		$withHistory = $this->hasOption( 'with-history' );
		if ( $pageName === null ) {
			$status = $importManager->importAllPages( $withHistory );
		} else {
			$status = $importManager->importPage( $pageName, $withHistory );
		}
		$this->output( "Done! $status->successCount succeeded, $status->skipCount skipped.\n" );
		if ( !$status->isOK() ) {
			$this->error( "Import failed with $status->failCount failed pages.\n" );
		}
	}


	private function getLogger() {
		return new MaintenanceLogger(
			function ( $text ) {
				$this->output( $text );
			},
			function ( $text ) {
				$this->error( $text );
			}
		);
	}

}

$maintClass = ImportFromWikispaces::class;
require_once RUN_MAINTENANCE_IF_MAIN;
