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

use Blc\Component\Blc\Administrator\Blc\BlcMessages;
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
#[Attributes\CoversClass(BlcMessages::class)]
class BlcMessagesTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
        try {
            BlcMessages::getInstance()->getMessageQueue(true); //clear the queue since this is a singleton the queee might contain messages from previous rest
            $this->getApplication()->getMessageQueue(true); //clear queue
        } catch (\Error) {
        }
    }


    public function tearDown(): void
    {

        try {
            BlcMessages::getInstance()->getMessageQueue(true); //clear the queue since this is a singleton the queee might contain messages from previous rest
            $this->getApplication()->getMessageQueue(true); //clear queue
        } catch (\Error) {
        }
    }


    public function testCanBoot()
    {
        $messageHandler = BlcMessages::getInstance();
        $this->assertInstanceOf(BlcMessages::class, $messageHandler);
        $this->isSingeTon($messageHandler);
    }

    public function testEmptyMessage()
    {

        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage('');
        $this->assertSame(
            $result,
            [
                'message' => '',
                'type'    => 'info',
            ]
        );
        $result =  $messageHandler->getMessageQueue();
        $this->assertEmpty(
            $result
        );
    }


    public function testMessage()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $this->assertSame(
            $result,
            [
                'message' => $msg,
                'type'    => 'error',
            ]
        );
        $result =  $messageHandler->getMessageQueue();
        $this->assertCount(
            1,
            $result
        );
    }

    public function testClear()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $this->assertSame(
            $result,
            [
                'message' => $msg,
                'type'    => 'error',
            ]
        );
        $result =  $messageHandler->getMessageQueue(true);
        $result =  $messageHandler->getMessageQueue();
        $this->assertCount(
            0,
            $result
        );
    }

    public function testIdentical()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $result         =  $messageHandler->getMessageQueue();
        $this->assertCount(1, $result);
    }

    public function testDifferentMessage()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $messageHandler->enqueueMessage($msg . ' 1', 'error');
        $messageHandler->enqueueMessage($msg . ' 2', 'error');
        $result =  $messageHandler->getMessageQueue();
        $this->assertCount(2, $result);
    }

    public function testDifferentType()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $result         = $messageHandler->enqueueMessage($msg, 'info');
        $result         =  $messageHandler->getMessageQueue();
        $this->assertCount(2, $result);
    }

    public function testmoveToApplication()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $result         = $messageHandler->enqueueMessage($msg, 'info');
        $messageHandler->moveToApplication($this->getApplication());
        $result = $this->getApplication()->getMessageQueue();
        $this->assertCount(2, $result);
    }

    public function testmoveToApplicationNull()
    {
        $msg            = __FUNCTION__;
        $messageHandler = BlcMessages::getInstance();
        $result         = $messageHandler->enqueueMessage($msg, 'error');
        $result         = $messageHandler->enqueueMessage($msg, 'info');
        $messageHandler->moveToApplication();
        $result = $this->getApplication()->getMessageQueue();
        $this->assertCount(2, $result);
    }
}
