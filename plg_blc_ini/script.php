<?php

/**
 * @package     Brambring.Plugin
 * @subpackage  Blc.Ini
 * @since __DEPLOY_VERSION__
 * @version    24.02.01
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR12.Classes.AnonClassDeclaration
return new class() implements
    ServiceProviderInterface
{
    // phpcs:enable PSR12.Classes.AnonClassDeclaration
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            // phpcs:disable PSR12.Classes.AnonClassDeclaration
            new class() implements
                InstallerScriptInterface
            {
                // phpcs:enable PSR12.Classes.AnonClassDeclaration
                private CMSApplicationInterface $app;
                private DatabaseInterface $db;
                private string $minimumJoomlaVersion = '4.4';
                public function __construct()
                {
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                    $this->app = Factory::getApplication();
                }
                /**
                 * @since __DEPLOY_VERSION__
                 */

                public function install(InstallerAdapter $adapter): bool
                {
                    $query = $this->db->getquery(true);
                    $query->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 1')
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                        ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($adapter->group))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote($adapter->element));
                    $this->db->setQuery($query)->execute();
                    return true;
                }

                /**
                 * @since __DEPLOY_VERSION__
                 */

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @since __DEPLOY_VERSION__
                 */

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @since __DEPLOY_VERSION__
                 */

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type == 'uninstall') {
                        return true;
                    }

                    $driver = strtolower($this->db->name);
                    if (strpos($driver, 'mysql') === false) {
                        Log::add(
                            Text::sprintf('JLIB_HTML_ERROR_NOTSUPPORTED', 'Database', $driver),
                            Log::ERROR,
                            'jerror'
                        );
                        return false;
                    }

                    if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                        Log::add(
                            Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                            Log::ERROR,
                            'jerror'
                        );
                        return false;
                    }
                    return true;

                    return true;
                }
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }
            }
        );
    }
};
