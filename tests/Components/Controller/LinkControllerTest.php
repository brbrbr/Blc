<?php

/**
 * @version   __DEPLOY_VERSION__
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin\Parsers;

use Joomla\CMS\Session\Session;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Blc\Component\Blc\Administrator\Controller\LinkController;
use Joomla\CMS\Factory;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
define('JPATH_COMPONENT', JPATH_ROOT . '/administrator/components/com_blc');
#[Attributes\TestDox('Test Embed Parser')]
class LinkControllerTest extends UnitTestCase
{
    protected $wrappedClass;

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testCanBoot()
    {
        $mvcFactory = $this->getApplication()->bootComponent('com_blc')->getMVCFactory();
        $controller = new LinkController(factory: $mvcFactory, app: $this->getApplication());
        $this->assertInstanceOf(LinkController::class, $controller);
        return $controller;
    }

    public function executeReplace($newurl) {
        $this->setUser();
        $token = Session::getFormToken();
        $this->getApplication()->getInput()->post->set($token, 1);
        $controller = $this->testCanBoot();
        $link = $this->getSomeLink();
        $newurls = [$link->id => $newurl];
        $jform = ['id' => $link->id];
        $this->getApplication()->getInput()->post->set('newurl', $newurls);
        $this->getApplication()->getInput()->post->set('jform', $jform);
        $controller->replace();

    }

    public function testCanReplace()
    {
        $newurl = "https://phpunit.invalid/new-link/" . uniqid();
        $this->executeReplace($newurl);
        $this->assertLinkExists($newurl);
        $this->assertMessageQueue('success', empty: false);
    }


    public function testCanNotReplace()
    {
        $newurl = "https://phpunit.invalid/new-link\"bla/" . uniqid();
        $this->executeReplace($newurl);
        $this->assertLinkExists($newurl,true);
        $this->assertMessageQueue('success', empty: true);
    }
}
