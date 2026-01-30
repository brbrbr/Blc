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

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
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

        $this->assertSame($linkItem->http_code, $code, $url . "\n" .  json_encode($linkItem->log, JSON_PRETTY_PRINT));
    }


    public function testCheckLinkTimeout()
    {

        $url      = 'https://10.0.0.1/';
        $code     = HTTPCODES::BLC_TIMEOUT_HTTP_CODE;
        $broken   = HTTPCODES::BLC_BROKEN_TIMEOUT;
        $checker  = BlcCheckerHttpCurl::getInstance();

        $config                 = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc');
        $config['timeout_http'] = 0;
        $config['timeout_cli']  = 0;

        $linkItem            = $this->loadLinkItem($url);
        $linkItem->http_code = HTTPCODES::BLC_CHECK_UNSET;
        $checker->checkLink($linkItem, $config);

        $this->assertSame($linkItem->http_code, $code, json_encode($linkItem->log, JSON_PRETTY_PRINT));
        $this->assertSame(
            $linkItem->broken,
            $broken,
            json_encode($linkItem->log, JSON_PRETTY_PRINT)
        );
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
        $host = parse_url((string) $linkItem->final_url, PHP_URL_HOST);
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

        $url      = 'mailto:dummy@example.com';
        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET, "Checked link is: {$linkItem->toCheck}");
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

        $url      = 'https://42.be/';
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, 200);
    }

    public function testCookies()
    {
        $checker  = BlcCheckerHttpCurl::getInstance();
        $config   = ComponentHelper::getParams('com_blc');
        $url      = BlcHelper::root('');
        $host     = Uri::getInstance($url)->toString(['host']);
        $linkItem = $this->loadLinkItem($url);

        $cookies = [];
        $uniqid  = uniqid();
        $cookie  = [
            'domain'     => $host, //- The domain that created and that can read the variable.
            'flag'       => 'FALSE', ///F value indicating if all machines within a given domain can access the variable. This value is set automatically by the browser, depending on the value you set for domain.
            'path'       => '/', // The path within the domain that the variable is valid for.
            'secure'     => 'FALSE', //- A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.
            'expiration' => time() + 3600, // The UNIX time that the variable will expire on.
            'name'       => 'TEST', //- The name of the variable.
            'value'      => 'ABCD', // - The value of the variable.
        ];
        $cookies[] = join("\t", array_values($cookie));

        $cookie = [
            'domain'     => $host, //- The domain that created and that can read the variable.
            'flag'       => 'FALSE', ///F value indicating if all machines within a given domain can access the variable. This value is set automatically by the browser, depending on the value you set for domain.
            'path'       => '/', // The path within the domain that the variable is valid for.
            'secure'     => 'FALSE', //- A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.
            'expiration' => time() + 3600, // The UNIX time that the variable will expire on.
            'name'       => 'TEST_2', //- The name of the variable.
            'value'      => 'HELLO: ' . $uniqid, // - The value of the variable.
        ];
        $cookies[] = join("\t", array_values($cookie));

        $config->set(
            'cookies',
            $cookies
        );
        $config->set('log_response', BlcCheckerHttpCurl::CHECKER_LOG_RESPONSE_ALWAYS);

        $config->set('verbose', true);
        $checker->checkLink($linkItem, $config);
        $this->assertNotEmpty($checker->cookies);
        $this->assertStringContainsString($uniqid, $linkItem->log['Verbose Log']);
        $cookieJar = $checker->cookieJar;
        $this->assertFileExists($cookieJar);
        $content = file_get_contents($cookieJar);
        $this->assertStringContainsString($uniqid, $content);
    }
    public static function sslVersionProvider()
    {
        return [
            [
                'CURL_SSLVERSION_DEFAULT',
                CURL_SSLVERSION_DEFAULT,
            ],
            [
                'CURL_SSLVERSION_MAX_DEFAULT',
                CURL_SSLVERSION_MAX_DEFAULT,
            ],
            [
                'CURL_SSLVERSION_TLSv1_3',
                CURL_SSLVERSION_TLSv1_3,
            ],
            [
                'CURL_SSLVERSION_TLSv1_4',
                CURL_SSLVERSION_MAX_DEFAULT,
            ],
            [
                CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_TLSv1_3 | CURL_SSLVERSION_MAX_DEFAULT,
                CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_TLSv1_3 | CURL_SSLVERSION_MAX_DEFAULT,
            ],
            [
                CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_TLSv1_3,
                CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_TLSv1_3,
            ],


        ];
    }
    #[Attributes\DataProvider('sslVersionProvider')]
    public function testsetSSlVersion($sslVersionString, $sslVersionConstant)
    {
        $checker  = BlcCheckerHttpCurl::getInstance();
        $config   = ComponentHelper::getParams('com_blc');



        $config->set('sslversion', $sslVersionString);

        $checker->setConfig($config);
        $effectiveVersion = $checker->sslVersion;
        $this->assertSame($sslVersionConstant, $effectiveVersion);
    }

    public function testLogResponse()
    {
        $checker  = BlcCheckerHttpCurl::getInstance();
        $config   = ComponentHelper::getParams('com_blc');
        $config->set('log_response', BlcCheckerHttpCurl::CHECKER_LOG_RESPONSE_NEVER);
        $url = BlcHelper::root();

        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem, $config);
        $this->assertEmpty($linkItem->log['Response']);

        $config->set('log_response', BlcCheckerHttpCurl::CHECKER_LOG_RESPONSE_ALWAYS);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem, $config);
        $this->assertNotEmpty($linkItem->log);
    }
}
