<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright  2023 - 2024  Bram Brambring
 * @license    GNU General Public License version 3 or later;
 *
 *
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Exception\FilesystemException;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

return new class() implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            new class() implements InstallerScriptInterface
            {
                /**
                 * Minimum  Joomla version to check
                 *
                 * @var    string
                 * @since  4.0.0
                 */
                private $minimumJoomlaVersion = '4.4';
                /**
                 * Minimum  PHP version to check
                 *
                 * @var    string
                 * @since  4.0.0
                 */
                private $minimumPHPVersion = '8.1';
                /**
                 * Minimum  MariaDB  version to check
                 *
                 * @var    string
                 * @since  4.0.0
                 */
                private $dbMinimumMariaDb = '10.4';
                /**
                 * Minimum  MysqDb version to check
                 *f
                 * @var    string
                 * @since  4.0.0
                 */
                private $dbMinimumMySql = '8.0.13';
                /**
                 * Obsolete files to be deleted
                 *
                 * @var    array<string>
                 * @since  4.0.0
                 */

                private $oldFiles = [
                    '/administrator/components/com_blc/src/Blc/BlcTokenBucketList.php',
                    '/administrator/components/com_blc/src/Blc/BlcCheckerHelper.php',
                    '/administrator/components/com_blc/src/Helper/BlcUtility.php',
                    '/administrator/components/com_blc/src/Helper/idn/uctc.php',
                    '/administrator/components/com_blc/src/Event/BlcLinkEvent.php',
                    '/administrator/components/com_blc/src/Helper/idn/transcode_wrapper.php',
                    '/administrator/components/com_blc/src/Helper/idn/idna_convert.class.php',
                    '/administrator/components/com_blc/src/Helper/idn/LICENCE',
                    '/administrator/components/com_blc/src/Helper/idn/ReadMe.txt',
                    '/administrator/components/com_blc/sql/updates/1.0.0.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.02.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.03.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.04.sql',
                    // 23.12
                    '/administrator/components/com_blc/sql/updates/23.11.5154.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.5155.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.5170.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.5177.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.5233.sql',
                    '/administrator/components/com_blc/sql/updates/23.11.5248.sql',
                    '/administrator/components/com_blc/sql/updates/23.12.5320.sql',
                    '/administrator/components/com_blc/sql/updates/23.12.5344.sql',
                    '/administrator/components/com_blc/sql/updates/23.12.5391.sql',
                    '/administrator/components/com_blc/sql/updates/23.12.5388.sql',
                    '/administrator/components/com_blc/sql/updates/23.12.5390.sql',
                    //23.12.5391
                    '/administrator/components/com_blc/src/Blc/BlcChecker.php',
                    '/administrator/components/com_blc/src/Blc/BlcCurlHttp.php',
                    '/administrator/components/com_blc/src/Blc/BlcHttpCheckerBase.php',
                    '/administrator/components/com_blc/src/Blc/BlcHttpChecker.php',
                    '/administrator/components/com_blc/src/Blc/BlcParser.php',
                    '/administrator/components/com_blc/src/Blc/BlcTagParser.php',
                    '/administrator/components/com_blc/src/Event/BlcParseEvent.php',
                    //24.02.5728
                    '/administrator/components/com_blc/src/Blc/BlcCheckerIgnore.php',
                    '/administrator/components/com_blc/src/Blc/BlcCheckerTrait.php',
                    //24.02.5824
                    '/administrator/components/com_blc/src/Field/MimeField.php',
                    //24.03.5884
                    '/administrator/components/com_blc/src/Button/PublishedButton.php',
                    '/administrator/components/com_blc/src/Button/FeaturedButton.php',
                    //24.03.6000
                    '/administrator/components/com_blc/src/Controller/ExploreController.php',
                    //24.44.
                    'administrator/modules/mod_blc/mod_blc.php',
                    //__DEPLOY_VERSION__
                    '/administrator/components/com_blc/sql/updates/23.12.5392.sql',
                    '/administrator/components/com_blc/sql/updates//23.12.5470.sql',
                    '/administrator/components/com_blc/sql/updates//24.01.5503.sql',
                    '/administrator/components/com_blc/sql/updates//24.01.5613.sql',
                    '/administrator/components/com_blc/sql/updates/24.02.5618.sql',
                    '/administrator/components/com_blc/sql/updates//24.02.5759.sql',
                    '/administrator/components/com_blc/sql/updates//24.02.5790.sql',
                    '/administrator/components/com_blc/sql/updates//24.02.5791.sql',
                    '/administrator/components/com_blc/sql/updates//24.02.5850.sql',
                    '/administrator/components/com_blc/sql/updates//24.03.5857.sql',
                    '/administrator/components/com_blc/sql/updates//24.03.5873.sql',
                    '/administrator/components/com_blc/sql/updates//24.03.5916.sql',
                    '/administrator/components/com_blc/sql/updates//24.44.6367.sql',


                ];
                /**
                 * Obsolete folders to be deleted
                 *
                 * @var    array<string>
                 * @since  4.0.0
                 */

                private $oldFolders = [
                    //    '/components/com_blc',
                    '/administrator/components/com_blc/presets',
                    //    23.12
                    '/administrator/components/com_blc/src/Helper/idn/',
                    '/administrator/components/com_blc/src/Parsers',

                ];

                private CMSApplicationInterface $app;
                private DatabaseInterface $db;

                public function __construct()
                {
                    $this->app = Factory::getApplication();
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                }

                public function preflight($type, $adapter): bool
                {
                    if ($type === 'install') {
                        $driver = strtolower($this->db->name);
                        if (strpos($driver, 'mysql') === false) {
                            Log::add(
                                Text::sprintf('JLIB_HTML_ERROR_NOTSUPPORTED', 'Database', $driver),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                    }

                    if ($type !== 'uninstall') {
                        $dbVersion       = $this->db->getVersion();
                        $minDbVersionCms =  $this->db->isMariaDb() ? $this->dbMinimumMariaDb : $this->dbMinimumMySql;
                        if (version_compare($dbVersion, $minDbVersionCms, '<')) {
                            //   $this->app->enqueueMessage(Text::_('PKG_BLC_EXTENSION_OUTDATEDDB',$dbVersion), 'warning');
                            Log::add(
                                Text::_('PKG_BLC_EXTENSION_OUTDATEDDB', $dbVersion),
                                Log::WARNING,
                                'jwarning'
                            );
                        }

                        // Check for the minimum PHP version before continuing
                        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                        // Check for the minimum Joomla version before continuing
                        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                                Log::ERROR,
                                'jerror'
                            );
                            return false;
                        }
                    }
                    if (!$this->checkCurl()) {
                        Log::add(
                            Text::_('PKG_BLC_EXTENSION_NOCURL'),
                            Log::ERROR,
                            'jerror'
                        );
                        return false;
                    }

                    return true;
                }
                private function checkCurl(): bool
                {
                    if (!\function_exists('curl_init')) {
                        return false;
                    }
                    $ch = curl_init("https://www.example.com/");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_exec($ch);
                    return curl_error($ch) ? false : true;
                }

                public function postflight($type, InstallerAdapter $adapter): bool
                {

                    if (php_sapi_name() == 'cli') {
                        return true;
                    }
                    if ($type === 'uninstall') {
                        return true;
                    }
                    $manifest =  $adapter->getManifest();
                    $name     = trim($manifest->name);
                    $version  = trim($manifest->version);
                    $msg      = $type == 'install' ? "PKG_BLC_EXTENSION_INSTALLED" : "PKG_BLC_EXTENSION_UPDATED";

                    $this->app->enqueueMessage(
                        Text::sprintf(
                            $msg,
                            Text::_($name),
                            $version
                        ),
                        'success'
                    );
                    return true;
                }


                public function install(InstallerAdapter $adapter): bool
                {

                    $this->recreateNamespaceMap();
                    return true;
                }

                /**
                 * method to update the component
                 */
                public function update(InstallerAdapter $adapter): bool
                {

                    $this->deleteOld();
                    return true;
                }

                /**
                 * Delete files that should not exist
                 * If set to true, will not actually delete files, but just report their status for use in CLI
                 * @param bool  $dryRun
                 *
                 * @return  array<string, array<string>>
                 */


                private function deleteOld($dryRun = false)
                {
                    $status = [
                        'files_exist'     => [],
                        'folders_exist'   => [],
                        'files_deleted'   => [],
                        'folders_deleted' => [],
                        'files_errors'    => [],
                        'folders_errors'  => [],
                        'folders_checked' => $this->oldFolders,
                        'files_checked'   => $this->oldFiles,
                    ];

                    foreach ($this->oldFiles as $file) {
                        $fullPath = Path::clean(JPATH_ROOT . $file);
                        if (is_file($fullPath)) {
                            $status['files_exist'][] = $file;
                            if ($dryRun === false) {
                                try {
                                    File::delete($fullPath);
                                    $status['files_deleted'][] = $file;
                                } catch (FilesystemException $e) {
                                    $status['files_errors'][] = $e->getMessage();
                                }
                            }
                        }
                    }

                    foreach ($this->oldFolders as $folder) {
                        $fullPath = Path::clean(JPATH_ROOT . $folder);
                        if (is_dir($fullPath)) {
                            $status['folders_exist'][] = $folder;
                            if ($dryRun === false) {
                                try {
                                    Folder::delete($fullPath);
                                    $status['folders_deleted'][] = $folder;
                                } catch (FilesystemException $e) {
                                    $status['folders_errors'][] = $e->getMessage();
                                }
                            }
                        }
                    }

                    $reports = [
                        'files_exist'     => Text::_('Old files found'),
                        'folders_exist'   => Text::_('Old folders found'),
                        'files_deleted'   => Text::_('Old files deleted'),
                        'folders_deleted' => Text::_('Old folders deleted'),
                        'files_errors'    => Text::_('Delete files failed - Please delete manually'),
                        'folders_errors'  => Text::_('Delete folders failed- Please delete manually'),

                    ];
                    $states = [
                        'files_deleted'   => 'success',
                        'folders_deleted' => 'success',
                        'files_errors'    => 'error',
                        'folders_errors'  => 'error',

                    ];

                    foreach ($reports as $key => $h3) {
                        if (\count($status[$key])) {
                            Factory::getApplication()->enqueueMessage(
                                "<h3>$h3</h3>" .
                                    implode('<br>', $status[$key]),
                                $states[$key] ?? 'warning'
                            );
                        }
                    }

                    return $status;
                }
                /*
                * @return  void
                */
                private function recreateNamespaceMap(): void
                {
                    // Remove the administrator/cache/autoload_psr4.php file
                    //only needed on install
                    $filename = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

                    if (file_exists($filename)) {
                        $this->clearFileInOPCache($filename);
                        clearstatcache(true, $filename);

                        @unlink($filename);
                    }

                    $this->app->createExtensionNamespaceMap();
                }

                /*
                * @param string  $file
                * @return  bool
                */

                private function clearFileInOPCache(string $file): bool
                {
                    $hasOpCache = \ini_get('opcache.enable')
                        && \function_exists('opcache_invalidate')
                        && (
                            !\ini_get('opcache.restrict_api')
                            || stripos(realpath($_SERVER['SCRIPT_FILENAME']), \ini_get('opcache.restrict_api')) === 0
                        );

                    if (!$hasOpCache) {
                        return false;
                    }

                    return opcache_invalidate($file, true);
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {

                    return true;
                }
            }
        );
    }
};
