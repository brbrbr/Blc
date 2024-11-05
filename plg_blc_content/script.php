<?php

/**
 * @version   24.44
 * @package    BLC Packge
 * @module    plg_blc_content
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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Registry\Registry;

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
                    if ($type == 'uninstall') {
                        return true;
                    }
                    //this plugin is part of the package so it should never get into this:
                    if ($type == 'install') {
                        $published = (int)is_dir(JPATH_ADMINISTRATOR . '/components/com_blc');
                        if (!$published) {
                            $this->app->enqueueMessage(
                                Text::_('PLG_BLC_PLUGIN_INSTALL_FIRST'),
                                'error'
                            );
                            return false;
                        }
                    }
                    return true;
                }
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $oldPlugin = 'cfcontent';
                    try {
                        if (PluginHelper::isEnabled('blc', $oldPlugin)) {
                            $this->app->enqueueMessage(
                                Text::_('PLG_BLC_PLUGIN_CONTENT_HAS_FIELDS'),
                                'warning'
                            );
                            $currentParams = new Registry(PluginHelper::getPlugin('blc', $adapter->element)->params ?? '');
                            if ( $currentParams->exists('cf')) {
                                //already migrated
                                return true;
                            }
                            $migrateParams = new Registry(PluginHelper::getPlugin('blc', $oldPlugin)->params ?? '');
                            $currentParams->set('enablecf', 1);
                            $currentParams->set('cf', $migrateParams->toObject()); //migrating everything. Redudant params are cleared whenever the plugin is edited
                            $query = $this->db->getquery(true);
                            $query->update($this->db->quoteName('#__extensions'))
                                ->set($this->db->quoteName('params') . ' = ' . $this->db->quote($currentParams->toString()))
                                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                                ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($adapter->group))
                                ->where($this->db->quoteName('element') . ' = ' . $this->db->quote($adapter->element));
                            $this->db->setQuery($query)->execute();

                            $query = $this->db->getquery(true);
                            $query->update($this->db->quoteName('#__extensions'))
                                ->set($this->db->quoteName('enabled') . ' = 0')
                                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                                ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($adapter->group))
                                ->where($this->db->quoteName('element') . ' = ' . $this->db->quote($oldPlugin));
                            $this->db->setQuery($query)->execute();
                           
                            $mvcFactory = $this->app->bootComponent('com_blc')->getMVCFactory();
                            $model      = $mvcFactory->createModel('Link', 'Administrator');
                            $model->trashit('delete', 'synch', $adapter->element);
                            $model->trashit('delete', 'synch', $oldPlugin);
                            }
                        
                    } catch (\Error) {
                    }


                    return true;
                }
            }
        );
    }
};
