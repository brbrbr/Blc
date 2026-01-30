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

use Blc\Component\Blc\Administrator\Event\BlcCheckerRequestEvent;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Event/BlcCheckerRequestEvent

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcCheckerRequestEvent::class)]
class BlcCheckerRequestEventTest extends UnitTestCase
{
 

    /**
     *
     * @since 25.44.7562
     * @return BlcCheckerRequestEvent
     */
    public function testbootEvent()
    {
        $arguments              = [

        ];
        $event = new BlcCheckerRequestEvent('BlcCheckerRequestEvent', $arguments);
        $this->assertInstanceOf(BlcCheckerRequestEvent::class, $event);
        return $event;
    }
}
