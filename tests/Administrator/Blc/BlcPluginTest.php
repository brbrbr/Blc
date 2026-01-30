<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

#[Attributes\CoversClass(BlcPlugin::class)]
class BlcPluginTest extends UnitTestCase
{
    protected function getWrapperPlugin()
    {

        $app        = $this->getApplication();
        $config     = [];
        $plugin     = new class ($config) extends BlcPlugin {
        };
        $plugin->setApplication($app);
        return $plugin;
    }

    public function testCanBoot()
    {
        $plugin = $this->getWrapperPlugin();
        $this->assertInstanceOf(BlcPlugin::class, $plugin);
    }

    public function testgetContext()
    {
        $plugin  = $this->getWrapperPlugin();
        $context = $plugin->context;
        $this->assertSame('joomla', $context);
    }

    public function testgetDefaultNull()
    {
        $plugin  = $this->getWrapperPlugin();
        $default = $plugin->any;
        $this->assertNull($default);
    }
}
