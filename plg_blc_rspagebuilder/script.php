<?php

/**
 * @package     Blc.Plugin
 * @subpackage  Blc.RsPageBuilder
 * @version   24.44
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
                    $this->loadLanguage($adapter);
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
                 * return the version if the extension is installed , false otherwise
                 *
                 * @since  __DEPLOY_VERSION__
                 */

                 private function checkextension(string $name): bool | string
                 {
                     $query = $this->db->getQuery(true);
                     $query->select($this->db->quoteName('manifest_cache'))
                         ->where($this->db->quoteName('element') . ' = :name')
                         ->bind(':name', $name)
                         ->from($this->db->quoteName('#__extensions'));
                     $this->db->setQuery($query);
                     $item     = $this->db->loadResult();
                     $manifest = json_decode($item ?? '{}');
                     return  $manifest->version ?? false;
                 }
                 /**
                  * @param   string    $name  The (untranslated) name of the current extension
                  * check BLC is installed and the correct version
                  * @since   24.44.6625
                  * @return bool wether or not to install
                  */
 
                 private function checkBlc(string $name): bool
                 {
 
                     $version  = $this->checkExtension('com_rspagebuilder');
                     if ($version === false) {
                         $this->app->enqueueMessage(
                             Text::_('PLG_BLC_PLUGIN_INSTALL_PAGEBUILDER_FIRST'),
                             'error'
                         );
                         return false;
                     }
 
                     $version  = $this->checkExtension('pkg_blc');
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

                
                /**
                 * Reloads the language from the installation package
                 *
                 * @since  __DEPLOY_VERSION__
                 */
                private function loadLanguage(InstallerAdapter $adapter): void
                {

                    //There is a $adapter->loadLanguage();
                    //but why is that the sys file. That one is loaded always and everytime.

                    $folder = $adapter->group;
                    $name = $adapter->element;
                    $extension = strtolower('plg_' . $folder . '_' . $name);


                    $source = $adapter->parent->getPath('source');
                    $lang      = $this->app->getLanguage();
                    $lang->load($extension, $source, reload: true) ||
                        $lang->load($extension, JPATH_ADMINISTRATOR, reload: true) ||
                        $lang->load($extension, JPATH_PLUGINS . '/' . $folder . '/' . $name, reload: true);
                }
            
            }
        );
    }
};
