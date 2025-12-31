<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpBase;
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
#[Attributes\CoversClass(BlcCheckerHttpBase::class)]
#[Attributes\TestDox('Test of the BLC Curl Checker')]
class BlcCheckerHttpBaseTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]

    public function setUp(): void
    {
        $this->initApplication();
    }





    public function testCanBoot()
    {
        $checker = BlcCheckerHttpBase::getInstance();
        $this->assertInstanceOf(BlcCheckerHttpBase::class, $checker);
        $this->isSingeTon($checker);
    }

    public function testcheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();

        $url      = 'https://example.com/';
        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_WRONG_CLASS_HTTP_CODE);
    }


    public function testSkipWrongSchemeFtpcheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();

        $url      = 'ftp://example.com/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }


    public function testSkipWrongSchemeMailtocheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();

        $url      = 'mailto:dummy@example.com/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }

    public function testinvalidDNScheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();

        $url      = 'https://sub.invalid/hello.txt';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_DNS_HTTP_CODE);
    }

    public function testNotParsableUrl()
    {
        $checker  = BlcCheckerHttpBase::getInstance();

        $url      = 'https://sub:invalid/hello.txt';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        //parse_url in validateUrl wil return BLC_INVALID_URL_HTTP_CODE
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_INVALID_URL_HTTP_CODE);
    }

    public function testIpv6checkLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();
        $url      = 'https://k6usy.net/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_WRONG_CLASS_HTTP_CODE);
    }

    public function testCookies()
    {
        $cookie = [
            'domain'     => 'example.com', //- The domain that created and that can read the variable.
            'flag'       => 'FALSE', ///F value indicating if all machines within a given domain can access the variable. This value is set automatically by the browser, depending on the value you set for domain.
            'path'       => '/', // The path within the domain that the variable is valid for.
            'secure'     => 'FALSE', //- A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.
            'expiration' => time() + 3600, // The UNIX time that the variable will expire on.
            'name'       => 'TEST', //- The name of the variable.
            'value'      => 'ABCD', // - The value of the variable.
        ];
        $cookieString = join("\t", array_values($cookie));

        $checker  = BlcCheckerHttpBase::getInstance();
        $config   = ComponentHelper::getParams('com_blc');
        $config->set('cookies', 1);
        $checker->setConfig($config);
        $this->assertEmpty($checker->cookies);
        $this->assertNotEmpty($checker->cookieJar);

        $config->set('cookies', '1');
        $checker->setConfig($config);
        $this->assertEmpty($checker->cookies);
        $this->assertNotEmpty($checker->cookieJar);


        $config->set('cookies', true);
        $checker->setConfig($config);
        $this->assertEmpty($checker->cookies);
        $this->assertNotEmpty($checker->cookieJar);

        $config->set('cookies', false);
        $checker->setConfig($config);
        $this->assertEmpty($checker->cookies);
        $this->assertFalse($checker->cookieJar);

        $config->set('cookies', '0');
        $checker->setConfig($config);
        $this->assertEmpty($checker->cookies);
        $this->assertFalse($checker->cookieJar);

        $config->set('cookies', $cookieString);
        $checker->setConfig($config);
        $this->assertNotEmpty($checker->cookies);
        $this->assertNotEmpty($checker->cookieJar);

        $checker->clearCookies();
        $this->assertEmpty($checker->cookies);

        $checker->addCookie($cookieString);
        $this->assertNotEmpty($checker->cookies);


        $checker->clearCookies();
        $this->assertEmpty($checker->cookies);
        $config->set('cookies', false);
        $checker->addCookie([$cookieString]);
        $this->assertNotEmpty($checker->cookies);
        $this->assertNotEmpty($checker->cookieJar);

        $checker->clearCookies();
        $this->assertEmpty($checker->cookies);
        $config->set('cookies', false);
        $checker->addCookie((object)[$cookieString]);
        $this->assertNotEmpty($checker->cookies);
        $this->assertNotEmpty($checker->cookieJar);
    }

    public function testCookiesSetCookie()
    {

        $cookieString = "Set-Cookie: session_id=abc123xyz; Expires=Fri, 19 Dec 2025 23:59:59 GMT; Path=/; Domain=example.com; Secure; HttpOnly; SameSite=Lax";

        $checker  = BlcCheckerHttpBase::getInstance();
        $config   = ComponentHelper::getParams('com_blc');
        $checker->clearCookies();

        $config->set('cookies', $cookieString);
        $checker->setConfig($config);
        $this->assertNotEmpty($checker->cookies);
        $firstCookie = $checker->cookies[0] ?? '';
        $this->assertSame($cookieString, $firstCookie);
    }



    public function testCookiesPair()
    {

        $cookieString = "KeyValue=NameValue";
        $checker      = BlcCheckerHttpBase::getInstance(false);
        $config       = ComponentHelper::getParams('com_blc');
        $checker->clearCookies();
        $config->set('cookies', $cookieString);
        $checker->setConfig($config);
        $this->assertNotEmpty($checker->cookies);
        $firstCookie = $checker->cookies[0] ?? '';
        $this->assertNotEmpty($checker->cookieJar);
        $this->assertStringContainsString('{HOST}', $firstCookie);
        $this->assertStringContainsString("\tKeyValue\t", $firstCookie);
        $this->assertStringContainsString("\tNameValue", $firstCookie);

        $checker->clearCookies();


        $cookieString = " KeyValue = NameValue ";
        $config->set('cookies', $cookieString);
        $checker->setConfig($config);

        $firstCookie = $checker->cookies[0] ?? '';
        $this->assertNotEmpty($checker->cookieJar);
        $this->assertStringContainsString('{HOST}', $firstCookie);
        $this->assertStringContainsString("\tKeyValue\t", $firstCookie);
        $this->assertStringContainsString("\tNameValue", $firstCookie);
    }
}
