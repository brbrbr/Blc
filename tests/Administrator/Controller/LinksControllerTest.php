<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Controller;

use Blc\Component\Blc\Administrator\Controller\LinksController;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Model\LinksModel;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Controller/LinksController

 *
 * @since       25.44.7398
 */
// phpcs:disable PSR1.Files.SideEffects
if (! \defined('JPATH_COMPONENT')) {
    \define('JPATH_COMPONENT', JPATH_ROOT . '/administrator/components/com_blc');
}
// phpcs:enable PSR1.Files.SideEffects
#[Attributes\CoversClass(LinksController::class)]
#[Attributes\CoversClass(LinksModel::class)]
class LinksControllerTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    protected function bootController()
    {
        $mvcFactory = $this->getApplication()->bootComponent('com_blc')->getMVCFactory();
        $controller = new LinksController(factory: $mvcFactory, app: $this->getApplication());
        return $controller;
    }


    public function testCanBoot()
    {
        $controller = $this->bootController();
        $this->assertInstanceOf(LinksController::class, $controller);
    }



    public function testGetModel()
    {

        $controller = $this->bootController();
        $model      = $controller->getModel();
        $this->assertInstanceOf(LinksModel::class, $model);
    }



    public function testCron()
    {

        $token = Session::getFormToken();
        $this->getApplication()->getInput()->get->set($token, 1);
        $controller = $this->bootController();
        ob_start();
        $controller->cron();
        $result =   ob_get_clean();
        $this->assertStringContainsString('"msgshort"', $result);
        $this->assertStringContainsString('"msglong"', $result);
        $this->assertStringContainsString('"status"', $result);
        $this->assertStringContainsString('"count"', $result);
        $this->assertStringContainsString('"broken"', $result);
    }
    private function seedPostInput()
    {
        $token = Session::getFormToken();
        $this->getApplication()->getInput()->post->set($token, 1);
        $this->getApplication()->getInput()->post->set('cid', null);
        $this->getApplication()->getInput()->post->set('jform', null);

        $this->clearMessageQueue();
    }

    public function testWorkingNoIdNoTask()
    {
        $this->seedPostInput();
        $controller = $this->bootController();
        $controller->working();
        $this->assertMessageQueue('warning', Text::_('COM_BLC_LINKS_NO_LINK_SPECIFIED'));
    }

    public function testWorkingNoTask()
    {
        $this->seedPostInput();
        $linkId = $this->getSomeLinkId()->link_id;

        $this->getApplication()->getInput()->post->set('cid', [$linkId]);
        $controller = $this->bootController();
        $controller->working();
        $this->assertMessageQueue('warning', Text::_('COM_BLC_LINKS_NO_TASK_SPECIFIED'));
    }

    public static function taskProvider()
    {
        return [
            ['hide', 'COM_BLC_LINKS_SUCCESS_HIDE', HTTPCODES::BLC_WORKING_HIDDEN],
            ['ignore', 'COM_BLC_LINKS_SUCCESS_IGNORE', HTTPCODES::BLC_WORKING_IGNORE],
            ['working', 'COM_BLC_LINKS_SUCCESS_WORKING', HTTPCODES::BLC_WORKING_WORKING],
            ['active', 'COM_BLC_LINKS_SUCCESS_ACTIVE', HTTPCODES::BLC_WORKING_ACTIVE],

        ];
    }


    #[Attributes\DataProvider('taskProvider')]
    public function testWorkingCid($task, $response, $working)
    {
        $this->seedPostInput();
        $linkId = $this->getSomeLinkId()->link_id;

        $this->getApplication()->getInput()->post->set('cid', [$linkId]);
        $controller = $this->bootController();
        $controller->execute($task);
        $previous = $controller->setMessage('');
        $linkItem = $this->loadLinkItemID($linkId);
        $this->assertSame($linkItem->working, $working);
        $this->assertSame($previous, Text::_($response));
    }

    #[Attributes\DataProvider('taskProvider')]
    public function testWorkingId($task, $response, $working)
    {
        $this->seedPostInput();
        $linkId = $this->getSomeLinkId()->link_id;
        $this->getApplication()->getInput()->post->set('jform', ['id' => $linkId]);
        $controller = $this->bootController();
        $controller->execute($task);
        $previous = $controller->setMessage('');

        $linkItem = $this->loadLinkItemID($linkId);
        $this->assertSame($linkItem->working, $working);
        $this->assertSame($previous, Text::_($response));
    }


    public function testRecheckNoId()
    {

        $this->seedPostInput();

        $controller = $this->bootController();

        $controller->recheck();
        $this->assertMessageQueue('warning', Text::_('COM_BLC_LINKS_NO_LINK_SPECIFIED'));
    }
    public function testRecheckLink()
    {

        $this->seedPostInput();
        $linkId = $this->getSomeLinkId()->link_id;

        $this->getApplication()->getInput()->post->set('cid', [$linkId]);
        $controller = $this->bootController();

        $controller->recheck();
        $this->assertMessageQueue('success', Text::_('COM_BLC_LINK_SUCCESS_RECHECK'));
    }

    public function testRecheckLinks()
    {

        $this->seedPostInput();
        $linkId = $this->getSomeLinkId()->link_id;
        //this fakes multiple links
        $this->getApplication()->getInput()->post->set('cid', [$linkId, $linkId]);
        $controller = $this->bootController();

        $controller->recheck();
        $this->assertMessageQueue('success', Text::_('COM_BLC_LINKS_SUCCESS_RECHECK'));
    }
    public function testRecheckLinkFailure()
    {

        $this->seedPostInput();


        $this->getApplication()->getInput()->post->set('jform', ['id' => -99]);
        $controller = $this->bootController();

        $controller->recheck();
        $this->assertMessageQueue('error', Text::_('COM_BLC_LINK_FAILED_RECHECK'));
    }


    public function testRecheckLinksailure()
    {

        $this->seedPostInput();

        //this fakes multiple links
        $this->getApplication()->getInput()->post->set('cid', [-99,-98]);
        $controller = $this->bootController();

        $controller->recheck();
        //multiple report success for rescheduling, regardless of they exists
        $this->assertMessageQueue('success', Text::_('COM_BLC_LINKS_SUCCESS_RECHECK'));
    }
}
