<?php

/**
 * @package     Blc.Plugin
 * @subpackage  Blc.Blclogin
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

\defined('_JEXEC') or die;

use Blc\Plugin\System\Blclogin\Extension\BlcPluginActor;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.4.0
     */
    public function register(Container $container): void
    {

        $container->set(
            PluginInterface::class,
            function (Container $container) {

                $plugin     = new BlcPluginActor(
                    (array) PluginHelper::getPlugin('system', 'blclogin')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setUserFactory($container->get(UserFactoryInterface::class));
                $plugin->setDispatcher($container->get(DispatcherInterface::class)); // @phpstan-ignore method.deprecated (this plugin uses DispatcherAwareTrait)
                return $plugin;
            }
        );
    }
};
