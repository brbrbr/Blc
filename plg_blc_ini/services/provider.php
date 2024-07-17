<?php

/**
 * @package     Brambring.Plugin
 * @subpackage  Blc.Ini
 * @version    24.02.01
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Blc\Plugin\Blc\Ini\Extension\BlcPluginActor;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;

return new class() implements ServiceProviderInterface
{
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since 24.44.6473
     */
    public function register(Container $container): void
    {

        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = new BlcPluginActor(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('blc', 'ini')
                );
                $plugin->setApplication(Factory::getApplication());

                $plugin->setDatabase($container->get(DatabaseInterface::class));
                return $plugin;
            }
        );
    }
};
