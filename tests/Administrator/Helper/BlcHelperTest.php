<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Helper;

use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use Joomla\Utilities\IpHelper; //using constants but not implementing
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Helper/BlcHelper

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcHelper::class)]
class BlcHelperTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function intervalTohoursProvider(): array
    {
        return [
            [1, 'second', 1 / 3600],
            [60, 'second', 60 / 3600],
            [1, 'minute', 1 / 60],
            [60, 'minute', 1],
            [1, 'hour', 1],
            [24, 'hour', 24],
            [1, 'day', 24],
            [7, 'day', 168],
            [1, 'week', 168],
            [4, 'week', 672],
            [1, 'month', 24 * 7 * 4.333],
            [12, 'month', 12 * 24 * 7 * 4.333],
            [1, 'year', 24 * 7 * 365],
            [2, 'year', 2 * 24 * 7 * 365],
            [100, 'invalid', 100], // Should default to hours
        ];
    }

    #[Attributes\DataProvider('intervalTohoursProvider')]
    public function testIntervalTohours(int $freq, string $unit, float $expected): void
    {
        $expected = round($expected, 3);
        $result   = round(BlcHelper::intervalTohours($freq, $unit), 3);
        $this->assertEquals($expected, $result, \sprintf('Failed for frequency %d and unit %s', $freq, $unit));
    }

    public function testresponseCode()
    {
        $oClass    = new \ReflectionClass(HTTPCODES::class);
        $constants = $oClass->getConstants();

        foreach ($constants as $value) {
            if (!\is_int($value)) {
                continue;
            }
            if ($value < 100) {
                continue;
            }

            $result = BlcHelper::responseCode($value);
            $this->assertNotEquals($result, 'Response code: ' . $value);
        }
    }

    public function testresponseCodeNotTranslated()
    {
        $value = 9999;

        $result = BlcHelper::responseCode($value);
        $this->assertEquals($result, 'Response code: ' . $value);
    }

    public function testgetIp()
    {

        unset($_SERVER['REMOTE_ADDR']);
        IpHelper::setIP(null);

        $result = BlcHelper::getIP();
        $this->assertEquals($result, '127.0.0.2');
    }

    public function testgetIpRemoteAdres()
    {

        IpHelper::setAllowIpOverrides(false);
        IpHelper::setIP(null);
        $value                  = "192.82.1.2";
        $_SERVER['REMOTE_ADDR'] = $value;

        $result = BlcHelper::getIP();
        $this->assertEquals($result, $value);
    }

    public function testsetLastAction()
    {
        $ajaxEvent = __FUNCTION__;
        $who       = __CLASS__;
        $expected  = BlcHelper::setLastAction($who, $ajaxEvent);
        $transient = "Cron {$ajaxEvent}";
        $stored    = BlcTransientManager::getInstance()->get($transient, true);
        $this->assertEquals($expected, $stored);
        $this->assertEquals($who, $stored['who']);
        $this->assertEquals(BlcHelper::getIP(), $stored['ip']);
    }
}
