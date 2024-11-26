<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Components\Controller;

use Joomla\CMS\Session\Session;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Blc\Component\Blc\Administrator\Controller\LinkController;

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



    public static function linkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl',  'result' => true],
            ['url' => 'https://brambring.nl/index.php?a=a&b=b',  'result' => true],
            ['url' => 'https://brambring.nl/index.php?a=a&amp;b=b',  'result' => true],
            ['url' => 'https://brambring.nl/index.php?a=a&amp;b='.urlencode('@#$@#%2323"\''),  'result' => true],
            ['url' => 'http://brambring.nl',  'result' => true], 
            ['url' => 'http://brambring.nl/xyz',  'result' => true],
            ['url' => 'https://facebook.com',  'result' => true],
            ['url' => '"https://brambring.nl',  'result' => false],
            ['url' => 'https://brambring.nl/test"test',  'result' => false],
            ['url' => 'https://brambring.nl/test"test',  'result' => false],
            ['url' => 'https://brambring.nl/test\'test',  'result' => false],
            ['url' => 'https://brambring.nl/'. urlencode('test\'test'),  'result' => true],
            ['url' => 'https://brambring.nl/xxx<script>alert()</script>',  'result' => false],
            ['url'=>'index.php?option=com_content&view=article&id=178:rs-form-shows-wrong-links&catid=10:faq',  'result' => true],

        ];
    }


    #[Attributes\DataProvider('linkProvider')]
    public function testLink($url,$result)
    {
        $controller = $this->testCanBoot();

        $protectedMethod = function (string $url) {
            /** @phpstan-ignore method.notFound */
            return $this->validLink($url);
        };
        $test=  $protectedMethod->call($controller, $url);

      
        $this->assertSame($result,$test,'for:' . $url);
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
        $this->getApplication()->getMessageQueue(true);
        $newurl = "https://phpunit.invalid/new-link\"bla/" . uniqid();
        $this->executeReplace($newurl);
        $this->assertLinkExists($newurl,true);
        $this->assertMessageQueue('success', empty: true);
    }
}
