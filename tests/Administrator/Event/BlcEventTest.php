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

use Blc\Component\Blc\Administrator\Event\BlcEvent as BlcEvent;
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
class BlcEventTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
    }


    public function testbootEvent()
    {
        $event = new BlcEvent('BlcEvent', []);
        $this->assertInstanceOf(BlcEvent::class, $event);
        return $event;
    }



    public function testgetItemNotSet()
    {
        $event = new BlcEvent('BlcEvent', []);
        $this->expectException(\BadMethodCallException::class);
        $event->getItem();
    }


    public function testgetItemt()
    {
        $event   = new BlcEvent('BlcEvent', []);
        $subject = new \StdClass();

        $event->setArgument('subject', $subject);
        $this->assertSame($subject, $event->getItem());
    }

    public function testgetItemTyperError()
    {
        $event   = new BlcEvent('BlcEvent', []);
        $subject = true;
        $event->setArgument('subject', $subject);
        $this->expectException(\TypeError::class);
        $event->getItem();
    }

    public function testgetItemConstructorSubject()
    {
        $subject = new \StdClass();
        $event   = new BlcEvent('BlcEvent', [
            'subject' => $subject,
        ]);

        $this->assertSame($subject, $event->getItem());
    }


    public function testgetItemConstructorItem()
    {
        $subject = new \StdClass();
        $event   = new BlcEvent('BlcEvent', [
            'item' => $subject,

        ]);

        $this->assertSame($subject, $event->getItem());
    }

    public function testgetIdConstructor()
    {
        $id    = 1;
        $event = new BlcEvent('BlcEvent', [
            'id' => $id,

        ]);

        $this->assertSame($id, $event->getId());
    }

    #[Attributes\Depends('testbootEvent')]
    public function testgetIdUnset(BlcEvent $event)
    {
        $this->assertEquals(0, $event->getId());
    }

    public function testgetIdConstructorString()
    {
        $id    = '1';
        $event = new BlcEvent('BlcEvent', [
            'id' => $id,

        ]);
        $this->assertEquals(1, $event->getId());
    }


    public function testgetContextConstructor()
    {
        $context = 'string';
        $event   = new BlcEvent('BlcEvent', [
            'context' => $context,

        ]);


        $this->assertEquals($context, $event->getContext());
    }
    #[Attributes\Depends('testbootEvent')]
    public function testgetContextNull(BlcEvent $event)
    {

        $this->assertEquals('', $event->getContext());
    }

    public function testgetContextConstructorTypeError()
    {
        $context = true;
        $event   = new BlcEvent('BlcEvent', [
            'context' => $context,

        ]);

        $this->expectException(\TypeError::class);
        $event->getContext();
    }




    public function testgetEventConstructor()
    {
        $eventString = 'string';
        $event       = new BlcEvent('BlcEvent', [
            'event' => $eventString,

        ]);


        $this->assertSame($eventString, $event->getEvent());
    }

    public function testgetEventConstructorTypeError()
    {
        $eventString = true;
        $event       = new BlcEvent('BlcEvent', [
            'event' => $eventString,

        ]);

        $this->expectException(\TypeError::class);
        $event->getEvent();
    }
}
