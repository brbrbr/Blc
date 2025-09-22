<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Controller;

use Blc\Component\Blc\Administrator\Controller\LinksController;
use Blc\Component\Blc\Administrator\Model\LinksModel;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\Session\Session;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Controller/LinksController

 *
 * @since       25.44.7398
 */
// phpcs:disable PSR1.Files.SideEffects
if (! defined('JPATH_COMPONENT')) {
    \define('JPATH_COMPONENT', JPATH_ROOT . '/administrator/components/com_blc');
}
// phpcs:enable PSR1.Files.SideEffects
#[Attributes\CoversClass(LinksController::class)]
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
        $model = $controller->getModel();
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

    public function testWorking()
    {
        $this->expectNotToPerformAssertions();
        $token = Session::getFormToken();
        $this->getApplication()->getInput()->post->set($token, 1);
        $controller = $this->bootController();
        $controller->working();
    }

    public function testRecheck()
    {
        $this->expectNotToPerformAssertions();
        $token = Session::getFormToken();
        $this->getApplication()->getInput()->post->set($token, 1);
        $controller = $this->bootController();

        $controller->recheck();
    }
}
