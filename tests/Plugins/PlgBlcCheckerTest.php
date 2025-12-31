<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Plugins;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTP_CODES;
use Blc\Plugin\Blc\Checker\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
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
class PlgBlcCheckerTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'checker';
    protected string $class   = BlcPluginActor::class;
    protected string $context = 'checker';


    protected function customConfig(string $host, array $replace = []): array
    {
        $config           =  (array)PluginHelper::getPlugin($this->folder, $this->element) ?? [];
        $oneRow           = [
            'host'            => $host,
            'match'           => '',
            'timeout_http'    => 1,
            'timeout_cli'     => 1,
            'head'            => 1,
            'range'           => 1,
            'follow'          => 1,
            'maxredirs'       => 5,
            'log_response'    => 0,
            'language'        => 1,
            'accept-language' => '-',
            'cookies'         => 1,
            'signature'       => 'chrome',
            'dynamicSecFetch' => 1,
            'valid_ssl'       => 2,
            'sslversion'      => 'CURL_SSLVERSION_DEFAULT',
        ];


        $oneRow = array_merge($oneRow, $replace);

        $config['params'] =  [
            'hosts' => [
                'hosts0' => $oneRow,
            ],
        ];

        return $config;
    }

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }
    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }

    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }


    public function testcanNotCheckLink()
    {
        $url      = 'index.php';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig('example.com'));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_FALSE);

        $url      = 'mailto:test@example.com';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig('example.com'));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_FALSE);
    }

    public function testcanCheckLink()
    {
        $url      = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig('cascadedesigns.com'));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_TRUE);
    }

    public function testcheckLink()
    {
        $url      = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig('cascadedesigns.com'));
        $options  =  clone ComponentHelper::getParams('com_blc');
        $options->set('accept-language', uniqid());
        $checker->checkLink($linkItem, $options);
        $this->assertSame($options->get('accept-language'), '-');
    }


    public function testsetCookies()
    {
        $linkChecker  = $this->getBlcCheckLink();
        $url          = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem     = $this->loadLinkItem($url);
        $cookies      = [
            'testcookie1',
            'testcookie2=value4two',
        ];
        $config      = $this->customConfig('cascadedesigns.com', ['cookiestring' => join("\n", $cookies)]);
        $curlChecker = $linkChecker->getChecker(BlcCheckerHttpCurl::class);
        $curlChecker->instance->clearCookies();
        $checker  = $this->bootPlugin(config: $config);
        $linkChecker->unregisterChecker($this->class);
        $linkChecker->registerChecker($checker, 5, true);
        $linkChecker->checkLink($linkItem);

        $curlCookies = $curlChecker->instance->cookies;

        $firstCookie = $curlCookies[0] ?? '';
        $this->assertStringContainsString("\ttestcookie1\t", $firstCookie);

        $secondCookie = $curlCookies[1] ?? '';
        $this->assertStringContainsString("\ttestcookie2\t", $secondCookie);
        $this->assertStringContainsString("\tvalue4two", $secondCookie);
    }


    public function testEOWD_SESS_SITE()
    {

        $signature =    [
            'userAgent'       => 'curl/8.5.0',
            'Accept-Language' => '',
            'headers'         => [
                'Accept' => 'Accept: */*',
                // 'Cookie' => 'Cookie: EOWD_SESS_SITE=btd',
            ],
        ];
        $cookie       = "EOWD_SESS_SITE=btd";
        $linkChecker  = $this->getBlcCheckLink();
        $curlChecker  = $linkChecker->getChecker(BlcCheckerHttpCurl::class);

        $checker = $this->bootPlugin(config: $this->customConfig('uitinalmelo.nl', ["match" => "www", 'cookiestring' => $cookie, 'signature' => $signature]));
        $linkChecker->unregisterChecker($this->class);
        $linkChecker->registerChecker($checker, 5, true);

        $url = 'https://www.uitinalmelo.nl/overnachten/kamperen/24441-camperplaats-centrum-almelo/';

        $linkItem = $this->loadLinkItem($url);


        $linkChecker->checkLink($linkItem);

        $linkItem->save();

        $curlChecker->instance->clearCookieJar();

        $this->assertEmpty($linkItem->final_url, "Final URL should be empty\n" . $linkItem->final_url);

        //test reset of options

        $linkChecker->unregisterChecker($this->class);

        $linkChecker->checkLink($linkItem);
        //   $linkItem->save();
        $curlChecker->instance->clearCookieJar();

        $this->assertNotEmpty($linkItem->final_url, "Final URL should not be empty\n" . $linkItem->url . ' -> "' . $linkItem->final_url . '"');
    }


    public function testEOWD_SESS_Multisites()
    {

        $signature =    [
            'userAgent'       => 'curl/8.5.0',
            'Accept-Language' => '',
            'headers'         => [
                'Accept' => 'Accept: */*',
                // 'Cookie' => 'Cookie: EOWD_SESS_SITE=btd',
            ],
        ];
        $cookie       = "EOWD_SESS_SITE=btd";
        $linkChecker  = $this->getBlcCheckLink();
        $curlChecker  = $linkChecker->getChecker(BlcCheckerHttpCurl::class);

        $checker = $this->bootPlugin(config: $this->customConfig("uitinzwolloe\nuitinalmelo.nl", ["match" => "www", 'cookiestring' => $cookie, 'signature' => $signature]));
        $linkChecker->unregisterChecker($this->class);
        $linkChecker->registerChecker($checker, 5, true);

        $url = 'https://www.uitinalmelo.nl/overnachten/kamperen/24441-camperplaats-centrum-almelo/';

        $linkItem = $this->loadLinkItem($url);


        $linkChecker->checkLink($linkItem);

        $linkItem->save();

        $curlChecker->instance->clearCookieJar();

        $this->assertEmpty($linkItem->final_url, "Final URL should be empty\n" . $linkItem->final_url);
    }





    /***
     *
     * if accept language is set to '-' (empty) cascasedesigns should not redirect to localized page
     */
    public function testCascadedesigns()
    {
        $linkChecker  = $this->getBlcCheckLink();
        $checker      = $this->bootPlugin(config: $this->customConfig('cascadedesigns.com'));
        $linkChecker->unregisterChecker($this->class);
        $linkChecker->registerChecker($checker, 5, true);

        $url = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';

        $linkItem = $this->loadLinkItem($url);

        $linkChecker->checkLink($linkItem);
        $this->assertEmpty($linkItem->final_url, "Final URL should be empty\n" . $linkItem->final_url);

        //test reset of options

        $linkChecker->unregisterChecker($this->class);

        $linkChecker->checkLink($linkItem);
        $this->assertNotEmpty($linkItem->final_url, "Final URL should not be empty\n" . $linkItem->final_url);
    }




    public function testcanCheckLinkMulti()
    {
        $url      = 'https://cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig("nu.nl\ncascadedesigns.com"));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_TRUE);
    }

    public function testCanNotCheckLinkWww()
    {
        $url      = 'https://www.cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig("cascadedesigns.com"));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_FALSE);
    }

    public function testCanCheckLinkWww()
    {
        $url      = 'https://www.cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig("cascadedesigns.com", ["match" => "www"]));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_TRUE);
    }

    public function testCanNotCheckLinkEnd()
    {
        $url      = 'https://www.cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig("ascadedesigns.com", ["match" => "ends"]));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_FALSE);
    }

    public function testCanCheckLinkEnd()
    {
        $url      = 'https://some.cascadedesigns.com/products/elixir-2-backpacking-tent';
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootPlugin(config: $this->customConfig("cascadedesigns.com", ["match" => "ends"]));
        $canCheck =  $checker->canCheckLink($linkItem);
        $this->assertSame($canCheck, HTTP_CODES::BLC_CHECK_TRUE);
    }
}
