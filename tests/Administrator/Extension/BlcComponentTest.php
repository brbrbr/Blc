<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Extension;

use Blc\Component\Blc\Administrator\Extension\BlcComponent;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcComponent::class)]
class BlcComponentTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testboot()
    {

        $container = $this->getContainer()->createChild();
        $container->registerServiceProvider(new MVCFactory('\\Blc\\Component\\Dummy'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Blc\\Component\\Dummy'));
        $component = new BlcComponent($container->get(ComponentDispatcherFactoryInterface::class));
        $component->setRegistry($container->get(Registry::class));
        $component->setMVCFactory($container->get(MVCFactoryInterface::class));

        $registry        = $container->get(Registry::class);
        $protectedMethod = (
            function () {
                /** @phpstan-ignore method.notFound */
                unset($this->serviceMap['blc']);
            }
        );
        $protectedMethod->call($registry);

        $component->boot($this->container);
        $this->assertInstanceOf(BlcComponent::class, $component);
    }
    public function testgetHelpLink()
    {

        $link = BlcComponent::getHelpLink();
        $this->assertStringStartsWith('https://', $link);
    }
}
