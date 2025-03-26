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

use Blc\Component\Blc\Administrator\Event\BlcParserRequestEvent as BlcEvent;
use Blc\Component\Blc\Administrator\Blc\BlcParseController;
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
class BlcParserRequestEventTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void {}
    
    public function testbootEventController()
    {
        $subject =  $this->createMock(BlcParseController::class);
        $event = new BlcEvent('BlcEvent', [
            'subject' => $subject

        ]);

        $this->assertSame($subject, $event->getItem());
    }

    public function testSubjectNotSet()
    {
        $this->expectException(\BadMethodCallException::class);
        new BlcEvent('BlcEvent', []);
    }

    public function testSubjectNotImplements()
    {
        $this->expectException(\BadMethodCallException::class);
        $subject =  $this->createMock(BlcParserRequestEventTest::class);
        new BlcEvent('BlcEvent', [
            'subject' => $subject

        ]);
    }
}
