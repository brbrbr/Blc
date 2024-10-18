<?php

/**
 * @package     Brambring.Plugin
 * @subpackage  Blc.Ini
 * @since 24.44.6473
 * @version    24.02.01
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR12.Classes.AnonClassDeclaration
return new class () implements
    ServiceProviderInterface {
    // phpcs:enable PSR12.Classes.AnonClassDeclaration
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            // phpcs:disable PSR12.Classes.AnonClassDeclaration
            new class () implements
                InstallerScriptInterface {
                // phpcs:enable PSR12.Classes.AnonClassDeclaration
                private CMSApplicationInterface $app;
                private DatabaseInterface $db;
                private string $minimumJoomlaVersion = '4.4';
                /**
                 * Minimum BLC Version to check.
                 *
                 * @var    string
                 * @since  24.44.6625
                 */
                private $minimumBlcVersion = '24.44.6679';
                public function __construct()
                {
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                    $this->app = Factory::getApplication();
                }
                /**
                 * @since 24.44.6473
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
                 * @since 24.44.6473
                 */

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @since 24.44.6473
                 */

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @since 24.44.6473
                 */

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type == 'uninstall') {
                        return true;
                    }

                    $driver = strtolower($this->db->name);
                    if (strpos($driver, 'mysql') === false) {
                        $this->app->enqueueMessage(
                            Text::sprintf('JLIB_HTML_ERROR_NOTSUPPORTED', 'Database', $driver),
                            'error'
                        );
                        return false;
                    }

                    if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                        $this->app->enqueueMessage(
                            Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                            'error'
                        );
                        return false;
                    }

                    $published = $this->checkBlc($adapter->name);
                    if (!$published) {
                        return false;
                    }

                    return true;
                }
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * @param   string    $name  The (untranslated) name of the current extension
                 * check BLC is installed and the correct version
                 * @since   24.44.6625
                 * @return bool wether or not to install
                 */

                private function checkBlc(string $name): bool
                {

                    $query = $this->db->getQuery(true);
                    $query->select($this->db->quoteName('manifest_cache'))
                        ->where($this->db->quoteName('name') . ' = ' . $this->db->quote('pkg_blc'))
                        ->from($this->db->quoteName('#__extensions'));
                    $this->db->setQuery($query);
                    $item     = $this->db->loadResult();
                    $manifest = json_decode($item ?? '{}');
                    $version  = $manifest->version ?? false;
                    if ($version === false) {
                        $this->app->enqueueMessage(
                            Text::_('PLG_BLC_PLUGIN_INSTALL_FIRST'),
                            'error'
                        );
                        return false;
                    }
                    if (version_compare($version, $this->minimumBlcVersion, '<')) {
                        $this->app->enqueueMessage(
                            Text::sprintf('PLG_BLC_PLUGIN_INSTALL_NEWER', TEXT::_($name), $this->minimumBlcVersion),
                            'error'
                        );
                        return false;
                    }
                    return true;
                }
            }
        );
    }
};
