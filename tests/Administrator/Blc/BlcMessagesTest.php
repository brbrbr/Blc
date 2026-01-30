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
    private BlcMessages $messages;
    public function setUp(): void
    {
        parent::setUp();
        $this->messages =  BlcMessages::getInstance();
    }

    public function tearDown(): void
    {
        //clear the singleton
        BlcMessages::resetInstance();
    }


    public function testCanBoot()
    {
        $this->assertInstanceOf(BlcMessages::class, $this->messages);
        $this->isSingeTon($this->messages);
    }

    public function testEmptyMessage()
    {


        $result         = $this->messages->enqueueMessage('');
        $this->assertSame(
            $result,
            [
                'message' => '',
                'type'    => 'info',
            ]
        );
        $result =  $this->messages->getMessageQueue();
        $this->assertEmpty(
            $result
        );
    }


    public function testMessage()
    {
        $msg            = __FUNCTION__;

        $result         = $this->messages->enqueueMessage($msg, 'error');
        $this->assertSame(
            $result,
            [
                'message' => $msg,
                'type'    => 'error',
            ]
        );
        $result =  $this->messages->getMessageQueue();
        $this->assertCount(
            1,
            $result
        );
    }

    public function testClear()
    {
        $msg            = __FUNCTION__;
        $result         = $this->messages->enqueueMessage($msg, 'error');
        $this->assertSame(
            $result,
            [
                'message' => $msg,
                'type'    => 'error',
            ]
        );
        $result =  $this->messages->getMessageQueue(true);
        $result =  $this->messages->getMessageQueue();
        $this->assertCount(
            0,
            $result
        );
    }

    public function testIdentical()
    {
        $msg            = __FUNCTION__;

        $result         = $this->messages->enqueueMessage($msg, 'error');
        $result         = $this->messages->enqueueMessage($msg, 'error');
        $result         =  $this->messages->getMessageQueue();
        $this->assertCount(1, $result);
    }

    public function testDifferentMessage()
    {
        $msg            = __FUNCTION__;
        $this->messages->enqueueMessage($msg . ' 1', 'error');
        $this->messages->enqueueMessage($msg . ' 2', 'error');
        $result =  $this->messages->getMessageQueue();
        $this->assertCount(2, $result);
    }

    public function testDifferentType()
    {
        $msg            = __FUNCTION__;
        $result         = $this->messages->enqueueMessage($msg, 'error');
        $result         = $this->messages->enqueueMessage($msg, 'info');
        $result         =  $this->messages->getMessageQueue();
        $this->assertCount(2, $result);
    }

    public function testmoveToApplication()
    {
        $msg            = __FUNCTION__;
        $result         = $this->messages->enqueueMessage($msg, 'error');
        $result         = $this->messages->enqueueMessage($msg, 'info');
        $this->messages->moveToApplication($this->getApplication());
        $result = $this->getApplication()->getMessageQueue();
        $this->assertCount(2, $result);
    }

    public function testmoveToApplicationNull()
    {
        $msg            = __FUNCTION__;

        $result         = $this->messages->enqueueMessage($msg, 'error');
        $result         = $this->messages->enqueueMessage($msg, 'info');
        $this->messages->moveToApplication();
        $result = $this->getApplication()->getMessageQueue();
        $this->assertCount(2, $result);
    }
}
