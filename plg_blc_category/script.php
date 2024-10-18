<?php

/**
 * @version   24.44
 * @package    BLC Packge
 * @module    plg_blc_category
 * @author     Bram <bram@brokenlinkchecker.dev>
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

                /**
                 * Minimum BLC Version to check.
                 *
                 * @var    string
                 * @since  24.44.6625
                 */
                private $minimumBlcVersion = '24.44.6679';

                public function __construct()
                {
                    $this->app = Factory::getApplication();
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                }

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

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {

                    $driver = $this->db->getServerType();
                    if ($driver !== 'mysql') {
                        $this->app->enqueueMessage(
                            Text::sprintf('JLIB_HTML_ERROR_NOTSUPPORTED', 'Database', $driver),
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

                private function checkBlc($name)
                {

                    $query = $this->db->getQuery(true);
                    $query->select($this->db->quoteName('manifest_cache'))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('pkg_blc'))
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
