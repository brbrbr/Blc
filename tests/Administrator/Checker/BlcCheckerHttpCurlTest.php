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
use Joomla\CMS\Component\ComponentHelper;
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

    public static function canRedirectLinkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl/joomla'],
            // return 'location' without host
            ['url' => 'https://dev.projecten.dev/redirect-without-host.php'],
        ];
    }

    public static function checkLinkProvider(): array
    {
        return   [
            //    ['url' => 'https://brambring.nl',  'code' => 200],
            //    ['url' => 'http://brambring.nl',  'code' => 200], //redirect reported as 200!
            //    ['url' => 'https://Brambring.nl',  'code' => 200], //redirect reported as 200!
            //    ['url' => 'http://brambring.nl/xyz',  'code' => 404],
            //    ['url' => 'https://facebook.com',  'code' => 200],
            //checkLink will not check without http or https protocol
            //    ['url' => 'ftp://brambring.nl',  'code' => 0],
            //    ['url' => '//brambring.nl',  'code' => 0],
            //    ['url' => 'https://bladiblazyx.nl/',  'code' => HTTPCODES::BLC_DNS_HTTP_CODE],
            ['url' => 'https://expired.badssl.com/',  'code' => HTTPCODES::BLC_FAILED_SSL_CODE],
            ['url' => 'https://dh480.badssl.com/',  'code' => HTTPCODES::BLC_FAILED_SSL_VERSION_CODE],





        ];
    }



    public function testCanBoot()
    {
        $checker = BlcCheckerHttpCurl::getInstance();
        $this->assertInstanceOf(BlcCheckerHttpCurl::class, $checker);
        $this->isSingeTon($checker);
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

        $this->assertSame($linkItem->http_code, $code, $url . "\n" . join("\n", $linkItem->log));
    }


    public function testCheckLinkTimeout()
    {

        $url = 'https://10.0.0.1/';
        $code = HTTPCODES::BLC_TIMEOUT_HTTP_CODE;
        $broken = HTTPCODES::BLC_BROKEN_TIMEOUT;
        $checker  = BlcCheckerHttpCurl::getInstance();

        $config              = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc');
        $config['timeout_http'] = 0;
        $config['timeout_cli'] = 0;

        $linkItem            = $this->loadLinkItem($url);
        $linkItem->http_code = HTTPCODES::BLC_CHECK_UNSET;
        $checker->checkLink($linkItem, $config);

        $this->assertSame($linkItem->http_code, $code,  join("\n", $linkItem->log));
        $this->assertSame($linkItem->broken, $broken,  join("\n", $linkItem->log));
    }

    #[Attributes\DataProvider('canRedirectLinkProvider')]
    public function testRedirectlUrl($url)
    {

        $checker  = BlcCheckerHttpCurl::getInstance();

        $config = ComponentHelper::getParams('com_blc');
        //facke open_basedir
        $config->set('follow', false);

        //this url return a partial link without host

        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem, $config);

        $this->assertNotEmpty($linkItem->final_url);
        $host = parse_url($linkItem->final_url, PHP_URL_HOST);
        $this->assertNotEmpty($host);
        $this->assertNotSame($url, $linkItem->final_url);

        $this->assertContains($linkItem->http_code, [301, 302, 303, 307]);
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
