<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Extension;

use Blc\Component\Blc\Administrator\Extension\BlcComponent;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Extension\ComponentInterface;
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
    #[Attributes\RunInSeparateProcess]
    public function testBootService()
    {
        $provider = include(JPATH_ROOT . '/administrator/components/com_blc/services/provider.php');
        $provider->register($this->container);
        $component = $this->container->get(ComponentInterface::class);
        $component->boot($this->container);
        $this->assertInstanceOf(BlcComponent::class, $component);

        unset($component);
    }
    public function testgetHelpLink()
    {

        $link = BlcComponent::getHelpLink();
        $this->assertStringStartsWith('https://', $link);
    }
}
