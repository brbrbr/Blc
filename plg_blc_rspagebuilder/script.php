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

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
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

                private DatabaseInterface $db;

                public function __construct()
                {

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
                        Log::add(
                            Text::sprintf('JLIB_HTML_ERROR_NOTSUPPORTED', 'Database', $driver),
                            Log::ERROR,
                            'jerror'
                        );
                        return false;
                    }
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
