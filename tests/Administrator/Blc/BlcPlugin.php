<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

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
#[Attributes\TestDox('Test of the Messages Handler')]
#[Attributes\CoversClass(BlcPlugin::class)]
class BlcPluginTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }
    /* code coverage */
    public function testClone()
    {
        $moduleInstance = BlcModule::getInstance();
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class singleton cant be cloned.');
        clone $moduleInstance;
    }
    public function testWakeup()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Class singleton cant be serialized.');
        $moduleInstance    = BlcModule::getInstance();
        $serializeInstance = serialize($moduleInstance);
        $moduleInstance    = unserialize($serializeInstance);
    }
}
