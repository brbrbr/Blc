<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

//using constants but not implementing

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcCheckerHttpCurl::class)]
#[Attributes\TestDox('Test of the BLC Curl Checker')]
class BlcCheckerHttpCurlTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]

    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl',  'canCheck' => HTTPCODES::BLC_CHECK_TRUE],
            //cancheck will return true on links without protocol
            ['url' => '//brambring.nl',  'canCheck' => HTTPCODES::BLC_CHECK_TRUE],
            ['url' => 'ftp://brambring.nl', 'canCheck' => HTTPCODES::BLC_CHECK_FALSE],
        ];
    }

    public static function checkLinkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl',  'code' => 200],
            ['url' => 'http://brambring.nl',  'code' => 200], //redirect reported as 200!
            ['url' => 'http://brambring.nl/xyz',  'code' => 404],
            ['url' => 'https://facebook.com',  'code' => 200],
            //checkLink will not check without http or https protocol
            ['url' => 'ftp://brambring.nl',  'code' => 0],
            ['url' => '//brambring.nl',  'code' => 0],

        ];
    }

    public function testCanBoot()
    {
        $checker = BlcCheckerHttpCurl::getInstance();
        $this->assertInstanceOf(BlcCheckerHttpCurl::class, $checker);
    }
    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckLink($url, $canCheck)
    {
        $checker  = BlcCheckerHttpCurl::getInstance();
        $linkItem = $this->loadLinkItem($url);

        $this->assertSame($checker->canCheckLink($linkItem), $canCheck);
        $this->assertMessageQueue();
    }
    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCheckLink($url, $code)
    {
        $checker  = BlcCheckerHttpCurl::getInstance();

        $config              = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc');
        $linkItem            = $this->loadLinkItem($url);
        $linkItem->http_code = HTTPCODES::BLC_CHECK_UNSET;
        $checker->checkLink($linkItem, $config);

        $this->assertSame($linkItem->http_code, $code);
    }





    public function testSkipWrongSchemeFtpcheckLink()
    {
        $checker  = BlcCheckerHttpCurl::getInstance();

        $url      = 'ftp://example.com/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }


    public function testSkipWrongSchemeMailtocheckLink()
    {
        $checker  = BlcCheckerHttpCurl::getInstance();

        $url      = 'mailto:dummy@example.com/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }
    public function testinvalidDNScheckLink()
    {
        $checker  = BlcCheckerHttpCurl::getInstance();

        $url      = 'https://sub.invalid/hello.txt';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_DNS_HTTP_CODE);
    }



    public function testIpv6checkLink()
    {
        $checker  = BlcCheckerHttpCurl::getInstance();

        $url      = 'https://k6usy.net/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, 200);
    }
}
