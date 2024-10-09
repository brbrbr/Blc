<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 *
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

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

                public function preflight($type, $parent): bool
                {
                    if ($type === 'uninstall') {
                        $this->disable();
                    }
                    //the component is typically installed from the package.
                    //the version checkers are there
                    return true;
                }
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    $this->app->enqueueMessage(
                        'Broken link Checker removed; Related plugins and modules disabled.',
                        'success'
                    );
                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    //joomla adds a front end dir. Even when not installing a site part.
                    $extensionSite = Path::clean(JPATH_SITE . '/components/' . $adapter->element);

                    if (is_dir($extensionSite)) {
                        Folder::delete($extensionSite);
                    }
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }
                public function install(InstallerAdapter $adapter): bool
                {

                    $params        = new StdClass();
                    $params->token = ApplicationHelper::getHash(UserHelper::genRandomPassword());
                    if (!$this->app->isClient('cli')) {
                        $params->live_site = Uri::root();
                    }

                    $query         = $this->db->getQuery(true);
                    $query->update($this->db->quoteName('#__extensions'))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote("com_blc"))
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote("component"))
                        ->where($this->db->quoteName('params') . ' = ' . $this->db->quote("{}"))
                        ->set($this->db->quoteName('params') . ' = ' . $this->db->quote(json_encode($params)));
                    $this->db->setQuery($query)->execute();
                    $this->app->enqueueMessage(
                        // phpcs:disable Generic.Files.LineLength
                        'Brokenlink checker installed. Visit the <a href="index.php?option=com_blc&view=setup">Maintenance page to start</a>',
                        // phpcs:enable Generic.Files.LineLength

                        'success'
                    );
                    return true;
                }


                /**
                 * disable extension. This avoids fatal errors
                 * main problem is the  mod_blc
                 */

                private function disable(): void
                {
                    $query         = $this->db->getQuery(true);
                    $query->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 0')
                        ->where(
                            $this->db->quoteName('type') . ' = ' . $this->db->quote("plugin") . ' AND ' . $this->db->quoteName('folder') . ' = ' . $this->db->quote("blc")
                        );
                    $this->db->setQuery($query)->execute();

                    $query         = $this->db->getQuery(true);
                    $query->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 0')
                        ->where(
                            $this->db->quoteName('type') . ' = ' . $this->db->quote("plugin") . ' AND ' . $this->db->quoteName('folder') . ' = ' . $this->db->quote("system")
                        )
                        ->whereIn('element', ["blclogin", "blc"], ParameterType::STRING);
                    $this->db->setQuery($query)->execute();

                    $query         = $this->db->getQuery(true);
                    $query->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 0')
                        ->where(
                            $this->db->quoteName('type') . ' = ' . $this->db->quote("module") . ' AND ' . $this->db->quoteName('element') . ' = ' . $this->db->quote("mod_blc")
                        );
                    $this->db->setQuery($query)->execute();

                    $query         = $this->db->getQuery(true);
                    $query->update($this->db->quoteName('#__modules'))
                        ->set($this->db->quoteName('published') . ' = 0')
                        ->where(
                            $this->db->quoteName('module') . ' = ' . $this->db->quote("mod_blc")
                        );
                    $this->db->setQuery($query)->execute();
                }
            }
        );
    }
};
