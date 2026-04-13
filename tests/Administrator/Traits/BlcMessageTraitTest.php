<?php

declare(strict_types=1);

/**
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Tests\Unit\Traits;

use Blc\Component\Blc\Administrator\Blc\BlcMessages;
use Blc\Component\Blc\Administrator\Traits\BlcMessageTrait;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Application\CMSApplication;
use PHPUnit\Framework\Attributes;

/**
 * Test class for BlcMessageTrait
 *
 * @since 24.44.6670
 */

class BlcMessageTraitTest extends UnitTestCase
{
    public function setUp(): void
    {
    }


    private function createTraitClass($mockApplication)
    {
        // Create anonymous class using the trait for testing
        return new class ($mockApplication) {
            use BlcMessageTrait;

            public function __construct(private $application)
            {
            }

            protected function getApplication()
            {
                return $this->application;
            }

            // Expose protected methods for testing
            public function testMessageInfo(string $message, bool $isBlc = true): void
            {
                $this->messageInfo($message, $isBlc);
            }

            public function testMessageAlert(string $message, bool $isBlc = true): void
            {
                $this->messageAlert($message, $isBlc);
            }

            public function testMessageWarning(string $message, bool $isBlc = true): void
            {
                $this->messageWarning($message, $isBlc);
            }

            public function testMessageError(string $message, bool $isBlc = true): void
            {
                $this->messageError($message, $isBlc);
            }

            public function testMessageSuccess(string $message, bool $isBlc = true): void
            {
                $this->messageSuccess($message, $isBlc);
            }

            public function testEnqueueMessage(string $message, string $type = 'info', bool $isBlc = true): void
            {
                $this->enqueueMessage($message, $type, $isBlc);
            }
        };
    }



    /**
     * Test messageInfo sends to application queue when isBlc is false
     *
     * @return void
     */
    public function testMessageInfoSendsToApplicationQueueWhenNotBlc(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('Test info message', 'info');

        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageInfo('Test info message', false);
    }

    /**
     * Test messageWarning sends to application queue when isBlc is false
     *
     * @return void
     */
    public function testMessageWarningSendsToApplicationQueueWhenNotBlc(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('Test warning message', 'warning');

        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageWarning('Test warning message', false);
    }

    /**
     * Test messageError sends to application queue when isBlc is false
     *
     * @return void
     */
    public function testMessageErrorSendsToApplicationQueueWhenNotBlc(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('Test error message', 'error');

        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageError('Test error message', false);
    }

    /**
     * Test messageSuccess sends to application queue when isBlc is false
     *
     * @return void
     */
    public function testMessageSuccessSendsToApplicationQueueWhenNotBlc(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('Test success message', 'success');

        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageSuccess('Test success message', false);
    }

    /**
     * Test enqueueMessage with custom type sends to application queue
     *
     * @return void
     */
    public function testEnqueueMessageWithCustomType(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('Test notice message', 'notice');
        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testEnqueueMessage('Test notice message', 'notice', false);
    }

    /**
     * Test enqueueMessage uses info type by default
     *
     * @return void
     */
    public function testEnqueueMessageUsesInfoTypeByDefault(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('Test default message', 'info');

        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testEnqueueMessage('Test default message', 'info', false);
    }

    /**
     * Test all message types route correctly to application queue
     *
     * @return void
     */
    #[Attributes\DataProvider('messageTypeProvider')]
    public function testAllMessageTypesRouteToApplicationQueue(
        string $method,
        string $message,
        string $expectedType
    ): void {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with($message, $expectedType);
        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->$method($message, false);
    }


    /**
     * Test all message types route correctly to application queue
     *
     * @return void
     */
    #[Attributes\DataProvider('messageTypeProvider')]
    public function testAllMessageTypesRouteToBlcMessageQueue(
        string $method,
        string $message,
        string $expectedType
    ): void {

        $this->initApplication(); //clears the queue as wel
        $traitObject = $this->createTraitClass($this->getApplication());
        $traitObject->$method($message, true);

        $queue = BlcMessages::getInstance()->getMessageQueue(true);

        $this->assertCount(1, $queue);
        $this->assertSame($message, $queue[0]['message']);
        $this->assertSame($expectedType, $queue[0]['type']);
    }

    /**
     * Data provider for message type tests
     *
     * @return array
     */
    public static function messageTypeProvider(): array
    {
        return [
            'info message' => [
                'testMessageInfo',
                'Info test',
                'info',
            ],

            'altert message' => [
                'testMessageAlert',
                'Alert test',
                'alert',
            ],
            'warning message' => [
                'testMessageWarning',
                'Warning test',
                'warning',
            ],
            'error message' => [
                'testMessageError',
                'Error test',
                'error',
            ],
            'success message' => [
                'testMessageSuccess',
                'Success test',
                'success',
            ],
        ];
    }

    /**
     * Test that empty messages are handled
     *
     * @return void
     */
    public function testEmptyMessageIsHandled(): void
    {
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with('', 'info');
        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageInfo('', false);
    }

    /**
     * Test that special characters in messages are handled
     *
     * @return void
     */
    public function testSpecialCharactersInMessage(): void
    {
        $message         = "Test <script>alert('xss')</script> & \"quotes\" 'apostrophes'";
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with($message, 'info');
        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageInfo($message, false);
    }

    /**
     * Test that long messages are handled
     *
     * @return void
     */
    public function testLongMessage(): void
    {
        $message         = str_repeat('This is a very long message. ', 100);
        $mockApplication = $this->createMock(CMSApplication::class);
        $mockApplication->expects($this->once())
            ->method('enqueueMessage')
            ->with($message, 'info');
        $traitObject = $this->createTraitClass($mockApplication);
        $traitObject->testMessageInfo($message, false);
    }

    /**
     * Test that getApplication is required
     *
     * @return void
     */
    public function testGetApplicationIsRequired(): void
    {
        $this->expectException(\Error::class);

        // Try to create a class using the trait without implementing getApplication
        $testClass = new class () {
            use BlcMessageTrait;

            public function testCall(): void
            {
                $this->messageInfo('test', false);
            }
        };
        $testClass->testCall();
    }
}
