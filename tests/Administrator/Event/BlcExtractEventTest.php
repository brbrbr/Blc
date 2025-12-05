<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Event;

use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Event/BlcExtractEvent

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcExtractEvent::class)]
class BlcExtractEventTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testBootEvent()
    {
        $event = new BlcExtractEvent('BlcExtractEvent', []);
        $this->assertInstanceOf(BlcExtractEvent::class, $event);
        return $event;
    }

    public function testupdateDidExtract()
    {
        $event = new BlcExtractEvent('BlcExtractEvent', []);
        $did   = rand(1, 10);
        $event->updateDidExtract($did);
        $this->assertEquals($did, $event->getDidExtract());
    }

    public function testsetExtractor()
    {
        $extractorName = 'TestExtractor' . uniqid();
        $event         = new BlcExtractEvent('BlcExtractEvent', []);
        $event->setExtractor($extractorName);
        $this->assertEquals($extractorName, $event->getExtractor());
    }

    public function testupdateTodo()
    {
        $todo  = rand(10, 100);
        $event = new BlcExtractEvent('BlcExtractEvent', []);
        $event->updateTodo($todo);
        $this->assertEquals($todo, $event->getTodo());
    }

    public function testgetMax()
    {
        $event = new BlcExtractEvent('BlcExtractEvent', []);
        $this->assertEquals(0, $event->getMax());

        $maxExtract = rand(5, 10);
        $event      = new BlcExtractEvent('BlcExtractEvent', ['maxExtract' => $maxExtract]);
        $this->assertEquals($maxExtract, $event->getMax());
        $did = 4;
        $event->updateDidExtract($did);
        $this->assertEquals($maxExtract - $did, $event->getMax());
        $event->updateDidExtract($maxExtract * 2);
        $this->assertEquals(0, $event->getMax());
    }
}
