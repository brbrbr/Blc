<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugins;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Plugin\Blc\Invalid\Extension\BlcPluginActor;
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
#[Attributes\CoversClass(BlcPluginActor::class)]

class PlgBlcInvalidTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'invalid';
    protected string $class   = BlcPluginActor::class;


    protected string $fieldContext = 'com_content.categories';

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }


    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }

    public function testCanNotCheckInternal()
    {
        $url      = 'index.php';
        $linkItem = $this->loadLinkItem($url);
        $plugin   =  $this->bootPlugin();
        $result   = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $result);
    }

    public function testCanNotCheckCom()
    {
        $url      = 'https://domain.com';
        $linkItem = $this->loadLinkItem($url);
        $plugin   =  $this->bootPlugin();
        $result   = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $result);
    }

    public function testCanCheckInvalid()
    {
        $url      = 'https://domain.invalid';
        $linkItem = $this->loadLinkItem($url);
        $plugin   =  $this->bootPlugin();
        $result   = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $result);
    }
    public function testCheckInvalidDefault()
    {

        $url                = 'https://domain.invalid';
        $linkItem           = $this->loadLinkItem($url);
        $plugin             = $this->bootPlugin();
        $results            = [];
        $results            = $plugin->checkLink($linkItem, $results);
        $this->assertSame($linkItem->http_code, 206);
        $this->assertSame($linkItem->broken, 0);
    }
    public function testCheckInvalid200()
    {

        $url                = 'https://domain.200.invalid';
        $linkItem           = $this->loadLinkItem($url);
        $plugin             = $this->bootPlugin();
        $results            = [];
        $results            = $plugin->checkLink($linkItem, $results);
        $this->assertSame($linkItem->http_code, 200);
        $this->assertSame($linkItem->broken, 0);
    }

    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();
    }

    public function testonBlcCheckerRequest()
    {


        $this->assertOnBlcCheckerRequest();
    }


    public function testCheckInvalid301()
    {
        $url      = 'https://domain.301.invalid';
        $linkItem = $this->loadLinkItem($url);

        $plugin             = $this->bootPlugin();
        $results            = [];
        $results            = $plugin->checkLink($linkItem, $results);
        $this->assertSame($linkItem->http_code, 301);
        $this->assertSame($linkItem->broken, 0);
        $this->assertSame($linkItem->redirect_count, 1);
        $this->assertSame($linkItem->final_url, $linkItem->url . '-pseude-redirect-301');
    }


    public function testCheckInvalid302()
    {

        $url                = 'https://new.302.invalid';
        $linkItem           = $this->loadLinkItem($url);
        $plugin             =  $this->bootPlugin();
        $results            = [];
        $results            = $plugin->checkLink($linkItem, $results);
        $this->assertSame($linkItem->http_code, 302);
        $this->assertSame($linkItem->redirect_count, 1);
        $this->assertSame($linkItem->broken, 0);
        $this->assertSame($linkItem->final_url, $linkItem->url . '-pseude-redirect-302');
    }
    public function testCheckInvalid404()
    {

        $url                = 'https://domain.404.invalid';
        $linkItem           = $this->loadLinkItem($url);
        $plugin             = $this->bootPlugin();
        $results            = [];
        $results            = $plugin->checkLink($linkItem, $results);
        $this->assertSame($linkItem->http_code, 404);
        $this->assertSame($linkItem->broken, 1);
    }
}
