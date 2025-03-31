<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Controller;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Controller\LinkController;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Session\Session;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

// phpcs:disable PSR1.Files.SideEffects
\define('JPATH_COMPONENT', JPATH_ROOT . '/administrator/components/com_blc');
// phpcs:enable PSR1.Files.SideEffects

#[Attributes\CoversClass(LinkController::class)]
class LinkControllerTest extends UnitTestCase
{

    public function setUp(): void
    {
        $this->initApplication();
        $this->setUser();
    }

    protected function bootController()
    {
        $mvcFactory = $this->getApplication()->bootComponent('com_blc')->getMVCFactory();
        $controller = new LinkController(factory: $mvcFactory, app: $this->getApplication());
        return $controller;
    }


    public function testCanBoot()
    {
        $controller = $this->bootController();
        $this->assertInstanceOf(LinkController::class, $controller);
    }



    public static function linkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl',  'result' => true],
            ['url' => 'https://brambring.nl/index.php?a=a&b=b',  'result' => true],
            ['url' => 'https://brambring.nl/index.php?a=a&amp;b=b',  'result' => true],
            ['url' => 'https://brambring.nl/index.php?a=a&amp;b=' . urlencode('@#$@#%2323"\''),  'result' => true],
            ['url' => 'http://brambring.nl',  'result' => true],
            ['url' => 'http://brambring.nl/xyz',  'result' => true],
            ['url' => 'https://facebook.com',  'result' => true],
            ['url' => '"https://brambring.nl',  'result' => false],
            ['url' => 'https://brambring.nl/test"test',  'result' => false],
            ['url' => 'https://brambring.nl/test"test',  'result' => false],
            ['url' => 'https://brambring.nl/test\'test',  'result' => false],
            ['url' => 'https://brambring.nl/' . urlencode('test\'test'),  'result' => true],
            ['url' => 'https://brambring.nl/xxx<script>alert()</script>',  'result' => false],
            ['url' => 'index.php?option=com_content&view=article&id=178:rs-form-shows-wrong-links&catid=10:faq',  'result' => true],

        ];
    }


    #[Attributes\DataProvider('linkProvider')]
    public function testLink($url, $result)
    {
        $controller = $this->bootController();

        $protectedMethod = (fn(string $url) =>
        /** @phpstan-ignore method.notFound */
        $this->validLink($url));
        $test =  $protectedMethod->call($controller, $url);


        $this->assertSame($result, $test, 'for:' . $url);
    }

    public function executeReplace($newurl)
    {

        $token = Session::getFormToken();
        $this->getApplication()->getInput()->post->set($token, 1);
        $controller = $this->bootController();
        $link       = $this->assertGetSomeLink();

        $newurls    = [$link->id => $newurl];
        $jform      = ['id' => $link->id];
        $this->getApplication()->getInput()->post->set('newurl', $newurls);
        $this->getApplication()->getInput()->post->set('jform', $jform);
        $controller->replace();
    }

    public function testCanReplace()
    {
        $newurl = "https://phpunit.invalid/new-link/" . uniqid();
        $this->executeReplace($newurl);

        $this->assertMessageQueue('error', empty: true);
        $this->assertMessageQueue('success', empty: false);
        $this->assertLinkExists($newurl);
    }


    public function testCanNotReplace()
    {
        $this->getApplication()->getMessageQueue(true);
        $newurl = "https://phpunit.invalid/new-link\"bla/" . uniqid();
        $this->executeReplace($newurl);
        $this->assertLinkExists($newurl, true);
        $this->assertMessageQueue('success', empty: true);
    }


    public function testtrashit()
    {

        $controller = $this->bootController();
        $this->getApplication()->getInput()->get->set('do', 'reset');
        $this->getApplication()->getInput()->get->set('what', 'synch');
        $this->getApplication()->getInput()->get->set('plugin', 'phpunit');

        $controller->trashit();
        $this->assertMessageQueue('info', empty: false);
    }


    public function testReplace()
    {

        //read in all the parsers
        $parserInstance = BlcParseController::getInstance();
        $parserInstance->setConfigOption('test', 'test', true); //reset
        $last = BlcTransientManager::getInstance()->get('lastListeners:onBlcParserRequest', true);
        //iframe is not allowed on normal Joomla sites.
        unset($last['iframe']);
     
        foreach (array_keys($last) as $parser) {
            $this->clearMessageQueue();
            $newurl = $this->getRandomLink();



            $token = Session::getFormToken();
            $this->getApplication()->getInput()->post->set($token, 1);
            $controller = $this->bootController();
            $link       = $this->assertGetSomeLink(parser: $parser, plugin: '', fields: []); //,linkPattern:'%invalid%');

            $newurls    = [$link->id => $newurl];

            //  $jform      = ['id' => $link->id];
            $jform      = ['id' => $link->id];

            $this->getApplication()->getInput()->post->set('jform', $jform);
            $this->getApplication()->getInput()->post->set('newurl', $newurls);
            //  $this->getApplication()->getInput()->post->set('jform', $jform);
            $controller->replace();

            $newLinkItem = $this->assertGetSomeLink(linkPattern: $newurl, parser: $parser, plugin: '', fields: []);
            $this->assertEquals($newLinkItem->url, $newurl);
            $hasLink = $this->getSomeLinkId(linkPattern: $link->url, parser: '', plugin: '', fields: []);
            $this->assertNull($hasLink);
        }
    }
}
