<?php

/**
 * @version   24.44
 * @package    BLC Packge
 * @module    plg_system_blc
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

                public function __construct()
                {
                    $this->app = Factory::getApplication();
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                }
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'update'])) {
                        return true;
                    }


                    #old mysql versions don't support UPDATE SELECT FROM
                    $query = $this->db->getquery(true);
                    $query->select("max({$this->db->quoteName('ordering')})+1 as {$this->db->quoteName('max')}")
                        ->from($this->db->quoteName('#__extensions'))
                        ->where("{$this->db->quoteName('type')} = {$this->db->quote('plugin')}")
                        ->where("{$this->db->quoteName('folder')} = {$this->db->quote($adapter->group)}")
                        ->where("{$this->db->quoteName('element')} != {$this->db->quote($adapter->element)}");

                    $this->db->setQuery($query);
                    $maxOrdering = (int)$this->db->loadResult();
                    //this plugin prefers to be at the end
                    //todo check maybe not needed anymore with Event/Priority

                    $query->clear();
                    $query->update($this->db->quoteName('#__extensions'))
                        ->set("{$this->db->quoteName('ordering')} = {$this->db->quote($maxOrdering)}")
                        ->where("{$this->db->quoteName('type')} = {$this->db->quote('plugin')}")
                        ->where("{$this->db->quoteName('folder')} = {$this->db->quote($adapter->group)}")
                        ->where("{$this->db->quoteName('element')} = {$this->db->quote($adapter->element)}");

                    if ($type == 'install') {
                        $query->set("{$this->db->quoteName('enabled')} = 1");
                    }
                    $this->db->setQuery($query)->execute();
                    return true;
                }

                private function addTasks()
                {
                    if ($this->app->isClient('cli')) {
                        //task creation will fail since there is no user.
                        return;
                    }
                    $query = $this->db->getQuery(true);
                    $query->select('count(*)')
                        ->from($this->db->quoteName('#__scheduler_tasks'))
                        ->where("{$this->db->quoteName('type')} = {$this->db->quote('blc.tasks')}");
                    $this->db->setQuery($query);
                    $count = $this->db->loadResult();
                    if ($count == 0) {
                        $base =
                            [
                                'title'  => 'BLC',
                                'state'  => '0',
                                'id'     => 0,
                                'type'   => 'blc.tasks',
                                'params' => [
                                    'individual_log' => true,
                                    'log_file'       => 'blc.log',
                                ],

                            ];

                        $mvcFactory                    = $this->app->bootComponent('com_scheduler')->getMVCFactory();
                        $model                         = $mvcFactory->createModel('Task', 'Administrator', ['ignore_request' => true]);
                        $base['title']                 = 'BLC - Extract - Every 6 hours';
                        $base['params']['extracttask'] = true;
                        $base['execution_rules']       = ['rule-type' => 'interval-hours', 'interval-hours' => '6'];
                        $model->save($base);
                        $base['title'] = 'BLC - Check Links- Every 10 minutes';
                        unset($base['params']['extracttask']);
                        $base['params']['checktask'] = true;
                        $base['execution_rules']     = ['rule-type' => 'interval-minutes', 'interval-minutes' => '10'];
                        $model->save($base);
                        $base['title'] = 'BLC - Report - Every Day';
                        unset($base['params']['checktask']);
                        $base['params']['reporttask'] = true;
                        $base['execution_rules']      = ['rule-type' => 'interval-days',  'interval-days' => '1', 'exec-time' => '07:00'];
                        $model->save($base);
                    }
                }


                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type == 'install') {
                        $published = (int)is_dir(JPATH_ADMINISTRATOR . '/components/com_blc');
                        if (!$published) {
                            $this->app->enqueueMessage(
                                Text::_('Please install the BLC Package first.'),
                                'error'
                            );
                            return false;
                        }
                    }
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

                public function install(InstallerAdapter $adapter): bool
                {
                    $this->addTasks();
                    return true;
                }
            }
        );
    }
};
