<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Event;

use Blc\Component\Blc\Administrator\Event\BlcReportEvent as BlcEvent;
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
#[Attributes\CoversClass(BlcEvent::class)]
class BlcReportEventTest extends UnitTestCase
{
    private static $mustArguments =  [
        'action' => 'phpunit-action',
        'client' => 'phpunit-client',
        'format' => 'json',
    ];




    public function testbootEvent()
    {
        $event = new BlcEvent('BlcEvent', self::$mustArguments);
        $this->assertInstanceOf(BlcEvent::class, $event);
        return $event;
    }

    public static function argumentPairProvider()
    {
        return array_map(fn ($a, $b) => [$a, $b], array_keys(self::$mustArguments), array_values(self::$mustArguments));
    }

    public static function argumentProvider()
    {
        return array_map(fn ($a) => [$a], array_keys(self::$mustArguments));
    }

    #[Attributes\DataProvider('argumentProvider')]
    public function testgetArgumentNotSet($key)
    {
        $this->expectException(\BadMethodCallException::class);
        $arguments = self::$mustArguments;
        unset($arguments[$key]);
        new BlcEvent('BlcEvent', $arguments);
    }

    #[Attributes\DataProvider('argumentPairProvider')]
    public function testgetArgument($key, $value)
    {
        $arguments = self::$mustArguments;
        $event     = new BlcEvent('BlcEvent', $arguments);
        $argument  = $event->getArgument($key);
        $this->assertSame($value, $argument);
        $func      = "get" . ucfirst($key);
        $argument2 = $event->$func();
        $this->assertSame($value, $argument2);
    }

    #[Attributes\DataProvider('argumentProvider')]
    public function testsetArgument($key)
    {
        $value     = 'test';
        $arguments = self::$mustArguments;
        $event     = new BlcEvent('BlcEvent', $arguments);
        $event->setArgument($key, $value);
        $func      = "get" . ucfirst($key);
        $argument2 = $event->$func();
        $this->assertSame($value, $argument2);
    }

    #[Attributes\DataProvider('argumentProvider')]
    public function testsetConstructorInValidType($key)
    {
        $this->expectException(\TypeError::class);

        $arguments       = self::$mustArguments;
        $arguments[$key] =  new \stdClass();
        $event           = new BlcEvent('BlcEvent', $arguments);
    }

    #[Attributes\DataProvider('argumentProvider')]
    public function testsetArgumentInValidType($key)
    {
        $this->expectException(\TypeError::class);
        $value     = false; //new \stdClass();
        $arguments = self::$mustArguments;
        $event     = new BlcEvent('BlcEvent', $arguments);
        $event->setArgument($key, $value);
    }




    public function testReport()
    {
        $report  = ['string'];
        $event   = new BlcEvent('BlcEvent', self::$mustArguments);
        $event->setReport($report);
        $this->assertEquals($report, $event->getReport());
    }

    public function testgetReportUnset()
    {

        $event = new BlcEvent('BlcEvent', self::$mustArguments);
        $this->assertSame('', $event->getReport());
    }
}
