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
use Joomla\CMS\HTML\Registry;
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

        $componentDispatcherFactoryMock = $this->createMock(ComponentDispatcherFactoryInterface::class);
        $component                      = new BlcComponent($componentDispatcherFactoryMock);
        $component->setRegistry(new Registry());

        $this->assertInstanceOf(BlcComponent::class, $component);
        unset($component);
    }
    public function testgetHelpLink()
    {

        $link = BlcComponent::getHelpLink();
        $this->assertStringStartsWith('https://', $link);
    }
}
