<?php

/**
 * @version   24.44.6991
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

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface {
                /**
                 * Minimum  Joomla version to check
                 *
                 * @var    string
                 * @since  24.44.6991
                 */
                private $minimumJoomlaVersion = '5.2';
                /**
                 * Minimum  PHP version to check
                 *
                 * @var    string
                 * @since  24.44.6991
                 */
                private $minimumPHPVersion = '8.2';
                /**
                 * Minimum  MariaDB  version to check
                 *
                 * @var    string
                 * @since  24.44.6991
                 */
                private $dbMinimumMariaDb = '10.4';
                /**
                 * Minimum  MysqDb version to check
                 *f
                 * @var    string
                 * @since  24.44.6991
                 */
                private $dbMinimumMySql = '8.0.13';

                private readonly CMSApplicationInterface $app;
                private readonly DatabaseInterface $db;

                public function __construct()
                {
                    $this->app = Factory::getApplication();
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                }

                public function preflight($type, $adapter): bool
                {
                    if ($type == 'uninstall') {
                        return true;
                    }

                    $driver = $this->db->getServerType();
                    if ($driver !== 'mysql') {
                        Log::add(
                            Text::sprintf('JLIB_HTML_ERROR_NOTSUPPORTED', 'Database', $driver),
                            Log::ERROR,
                            'jerror'
                        );
                        return false;
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
                    return true;
                }
                /**
                 * @since  24.44.6991
                 */

                public function postflight($type, InstallerAdapter $adapter): bool
                {
                    if (PHP_SAPI == 'cli') {
                        return true;
                    }

                    if ($type === 'uninstall') {
                        return true;
                    }

                    $manifest =  $adapter->getManifest();
                    $name     = trim((string)$manifest->name);
                    $version  = trim((string)$manifest->version);
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

                /**
                 * @since  24.44.6991
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * method to update the component
                 * @since  24.44.6991
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Delete files that should not exist
                 * If set to true, will not actually delete files, but just report their status for use in CLI
                 * @param bool  $dryRun
                 *
                 * @return  array<string, array<string>>
                 * @since  24.44.6991
                 *
                 */

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }
            }
        );
    }
};
