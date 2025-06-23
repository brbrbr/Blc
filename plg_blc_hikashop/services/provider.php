<?php

/**
 * @package     Blc.Plugin
 * @subpackage  Blc.Hikashop
 * @version    24.02.01
 * @copyright  2025 Bram Brambring
 * @license    GNU General Public License version 3 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Plugin\Blc\Hikashop\Extension\BlcPluginActor;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since 25.52.7279
     */
    public function register(Container $container): void
    {

        $container->set(
            PluginInterface::class,
            function (Container $container) {

                $plugin     = new BlcPluginActor(
                    (array) PluginHelper::getPlugin('blc', 'hikashop')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));
                return $plugin;
            }
        );
    }
};
