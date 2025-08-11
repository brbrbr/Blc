<?php

declare(strict_types=1);

/**
 * @package     Brambring.Plugin
 * @subpackage  Blc.Invalid
 * @version    24.02.01
 * @copyright  2024 Bram Brambring
 * @license    GNU General Public License version 3 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Plugin\Blc\Invalid\Extension\BlcPluginActor;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
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
     * @since 24.52.6877
     */
    public function register(Container $container): void
    {

        $container->set(
            PluginInterface::class,
            function () {

                $plugin     = new BlcPluginActor(
                    (array) PluginHelper::getPlugin('blc', 'invalid')
                );
                $plugin->setApplication(Factory::getApplication());
                return $plugin;
            }
        );
    }
};
