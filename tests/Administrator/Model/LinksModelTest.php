<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Model;

use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Model\LinksModel;

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
#[Attributes\CoversClass(LinksModel::class)]
#[Attributes\TestDox('Test of the System - BLC Plugin')]
class LinksModelTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }
    public function getModel($component = 'com_blc', $model = 'links', $client = 'Administrator', array $config = ['ignore_request' => true])
    {

        return parent::getModel($component, $model, $client, $config);
    }

    #[Attributes\Group('BlcExtractInterface')]
    public function testLinksModel()
    {

        $mock = $this->getMockBuilder(BlcExtractInterface::class)->getMock();
        $mock->expects($this->once())->method('onBlcExtract')->willReturnCallback(
            function ($event) {
                $eventMax = $event->getMax();
                $this->assertEquals(99, $eventMax);
                return $eventMax;
            }
        );

        //just in case the blc plugin are imported.
        $listeners =  $this->getDispatcher()->getListeners('onBlcExtract');
        $this->getDispatcher()->clearListeners('onBlcExtract');

        $this->getDispatcher()->addListener('onBlcExtract', [$mock, 'onBlcExtract']);
        $model = $this->getModel();
        //this will dispatch the event
        $event = $model->runBlcExtract(99);
        $this->assertInstanceOf(BlcExtractEvent::class, $event);

        //restore listeners. priority is lost but order should be the same as before.
        foreach ($listeners as $listener) {
            $this->getDispatcher()->addListener('onBlcExtract', $listener);
        }

        $this->getDispatcher()->removeListener('onBlcExtensionAfterSave', [$mock, 'onBlcExtensionAfterSave']);
    }
}
