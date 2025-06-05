<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Event;

use Blc\Component\Blc\Administrator\Event\BlcInstanceDisplayEvent;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Event/BlcInstanceDisplayEvent

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcInstanceDisplayEvent::class)]
class BlcInstanceDisplayEventTest extends UnitTestCase
{
  public function setUp(): void
  {
    $this->initApplication();
  }

  public  function getSomeinstances()
  {
    $introtextInstanceNotYootheme = (object)['field' => 'introtext', 'parser' => 'img'];
    $fulltextInstanceNotYootheme  = (object)['field' => 'fulltext', 'parser' => 'img'];


    return      [$introtextInstanceNotYootheme, $fulltextInstanceNotYootheme];
  }

  /**
   * 
   * @since 25.44.7562
   * @return BlcInstanceDisplayEvent
   */
  public function testbootEvent()
  {
    $arguments              = [
      'subject' => $this->getSomeinstances(),
    ];
    $event = new BlcInstanceDisplayEvent('BlcInstanceDisplayEvent', $arguments);
    $this->assertInstanceOf(BlcInstanceDisplayEvent::class, $event);
    return $event;
  }
  /**
   * 
   * @since 25.44.7562
   * @return BlcInstanceDisplayEvent
   */
  public function testcanNotBootEventNoSubject()
  {
    $arguments              = [];
    $this->expectException(\BadMethodCallException::class);
    $event = new BlcInstanceDisplayEvent('BlcInstanceDisplayEvent', $arguments);
    return $event;
  }


  /**
   * 
   * @since 25.44.7562

   * @return array
   */

  public function testsetArgument()
  {
    /*get new event, as the order of tests is not fixed the subject might change between tests*/
    $event = $this->testbootEvent();
    $expected = __FUNCTION__ . ' ' . uniqid();
    $result = $event->setArgument('foo', $expected);
    $this->assertInstanceOf(BlcInstanceDisplayEvent::class, $result);
    return [$event, $expected];
  }
  /**
   * 
   * @since 25.44.7562
   * @param array $dependData
   * @return void
   */
  #[Attributes\Depends('testsetArgument')]
  public function testgetArgument(array $dependData)
  {
    [$event, $expected] = $dependData;
    $result = $event->getArgument('foo');
    $this->assertSame($result, $expected);
  }

  /**
   * 
   * @since 25.44.7562
   * @return array
   */



  public function testsetSubject()
  {
    /*get new event, as the order of tests is not fixed the subject might change between tests*/
    $event = $this->testbootEvent();
    $expected = [__FUNCTION__ . ' ' . uniqid()];
    $result = $event->setSubject($expected);
    $this->assertInstanceOf(BlcInstanceDisplayEvent::class, $result);
    return [$event, $expected];
  }

  /**
   * 
   * @since 25.44.7562
   * @param BlcInstanceDisplayEvent $event

   */


  #[Attributes\Depends('testbootEvent')]
  public function testsetInstancesWrongType(BlcInstanceDisplayEvent $event)
  {
    $this->expectException(\TypeError::class);
    $expected = __FUNCTION__ . ' ' . uniqid();
    $event->setArgument('instances', $expected);
  }

  #[Attributes\Depends('testbootEvent')]
  public function testsetSubjectWrongType(BlcInstanceDisplayEvent $event)
  {
    $this->expectException(\TypeError::class);
    $expected = __FUNCTION__ . ' ' . uniqid();
    $event->setArgument('subject', $expected);
  }

  /**
   * 
   * @since 25.44.7562
   * @param array $dependData
   * @return void
   */

  #[Attributes\Depends('testsetSubject')]
  public function testgetSubject(array $dependData)
  {
    [$event, $expected] = $dependData;
    $instances = $event->getSubject();
    $this->assertSame($expected, $instances);
  }
  /**
   * 
   * @since 25.44.7562
   * @param BlcInstanceDisplayEvent $event
   * @return array
   */

  #[Attributes\Depends('testbootEvent')]
  public function testgetInstancesSetSubject(BlcInstanceDisplayEvent $event)
  {
    $expected = [__FUNCTION__ . ' ' . uniqid()];
    $event->setSubject($expected);
    $instances = $event->getInstances();
    $this->assertSame($expected, $instances);
  }
  /**
   * 
   * @since 25.44.7562
   * @param array $dependData
   * @return void
   */
  #[Attributes\Depends('testsetInstances')]
  public function testgetInstances(array $dependData)
  {
    [$event, $expected] = $dependData;
    $instances = $event->getInstances();
    $this->assertSame($expected, $instances);
  }
  /**
   * 
   * @since 25.44.7562

   * @return array
   */

  public function testsetInstances()
  {
    /*get new event, as the order of tests is not fixed the subject might change between tests*/
    $event = $this->testbootEvent();
    $expected = [__FUNCTION__ . ' ' . uniqid()];
    $result =    $event->setInstances($expected);
    $this->assertInstanceOf(BlcInstanceDisplayEvent::class, $result);
    return [$event, $expected];
  }
  /**
   * 
   * @since 25.44.7562
   * @param BlcInstanceDisplayEvent $event
   * @return array
   */
  #[Attributes\Depends('testbootEvent')]
  public function testsetInstancesSetArgument(BlcInstanceDisplayEvent $event)
  {
    $expected = [uniqid()];
    $event->setArgument('subject', $expected);
    $instances = $event->getInstances();
    $this->assertSame($expected, $instances);
  }
}
