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

use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Plugin\System\Blclogin\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Registry\Registry;
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
class PlgSystemBlcLoginTest extends UnitTestCase
{
    protected string $folder  = 'system';
    protected string $element = 'blclogin';
    protected string $class   = BlcPluginActor::class;

    protected function bootPlugin(?string $class = null, ?array $config = null, bool $assert = false)
    {
        $config =  (array)PluginHelper::getPlugin($this->folder, $this->element) ?? [];


        $user = $this->container->get(UserFactoryInterface::class)->loadUserByUsername('phpunit');
        if ($assert) {
            $this->assertNotEquals(0, $user->id, 'A user must be configured for this test.');
        }
        $plugin = parent::bootPlugin($class, $config, $assert);
        $plugin->params->set('id', $user->id);
        $plugin->params->set('ip', BlcHelper::getIp());
        $plugin->setUserFactory($this->container->get(UserFactoryInterface::class));
        $this->getApplication()->loadIdentity();
        return $plugin;
    }
    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }
    public function setUp(): void
    {
        $this->initApplication('site');
        $this->checkPluginEnabled();
    }

    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }

    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();

        $this->enableBlc(false);
        $this->assertSubscribedEvents(true);
        $this->enableBlc(true);
    }

    #[Attributes\Depends('testcheckLink')]
    public function testonAfterRoute($headers)
    {

        $plugin = $this->bootPlugin();
        $app    = $this->getApplication();

        $webClient   = new \Joomla\Application\Web\WebClient();
        $app->client = $webClient;

        /* this covers the code that checks for headers */
        $plugin->onAfterRoute();

        $this->checkTransient('HEADER');
        $protectedMethod = (
            function (array $headers) {
                $this->detection['headers'] = 1;
                $this->headers              = [];
                foreach ($headers as $header) {
                    [$key, $value]             = explode(':', $header);
                    $this->headers[trim($key)] = trim($value);
                }
            }
        );
        $protectedMethod->call($webClient, $headers);
        $app->client = $webClient;

        $plugin->onAfterRoute();
        $this->checkTransient('REQUEST');
        $userId = $plugin->params->get('user', 0);
        $this->assertNotEquals(0, $userId, 'A user must be configured for this test.');

        $user = $this->container->get(UserFactoryInterface::class)->loadUserById((int)$userId);
        $this->getApplication()->getSession()->set('user', $user);
        $this->getApplication()->loadIdentity($user);

        $isUser =  $this->getApplication()->getIdentity();

        $this->assertSame((int)$userId, (int)$isUser->id);
        /* this covers the code that checks for guest - already logged in*/
        $plugin->onAfterRoute();
        $this->checkTransient('LOGEDIN');
    }
    #[Attributes\Depends('testcheckLink')]
    public function testonAfterRouteWrongOtp($headers)
    {

        $plugin = $this->bootPlugin();
        $app    = $this->getApplication();

        $webClient       = new \Joomla\Application\Web\WebClient();
        $protectedMethod = (
            function (array $headers) {
                $this->detection['headers'] = 1;
                $this->headers              = [];
                foreach ($headers as $header) {
                    [$key, $value]             = explode(':', $header);
                    $this->headers[trim($key)] = md5(trim($value));
                }
            }
        );
        $protectedMethod->call($webClient, $headers);
        $app->client = $webClient;

        $plugin->onAfterRoute();
        $userId = $plugin->params->get('user', 0);
        $this->assertNotEquals(0, $userId, 'A user must be configured for this test.');

        $isUser = $this->app->getIdentity();
        $this->assertSame(0, (int)$isUser->id);
        $this->checkTransient('FAILED - OTP');
        /* this covers the code that checks for guest - already logged in*/
        $plugin->onAfterRoute();
    }

    #[Attributes\Depends('testcheckLink')]
    public function testonAfterRouteNoUser($headers)
    {
        $user = $this->container->get(UserFactoryInterface::class)->loadUserById(0);
        $this->app->getSession()->set('user', $user);
        $this->app->loadIdentity($user);
        $plugin = $this->bootPlugin();

        /* this covers the code that checks for headers */


        $app = $this->getApplication();

        $webClient       = new \Joomla\Application\Web\WebClient();
        $protectedMethod = (
            function (array $headers) {
                $this->detection['headers'] = 1;
                $this->headers              = [];
                foreach ($headers as $header) {
                    [$key, $value]             = explode(':', $header);
                    $this->headers[trim($key)] = trim($value);
                }
            }
        );
        $protectedMethod->call($webClient, $headers);
        $app->client = $webClient;
        $plugin->params->set('user', 0);

        $plugin->onAfterRoute();
        $this->checkTransient('FAILED - USER');
    }


    protected function getConfigWithoutHeaders(): Registry
    {
        $config =  clone ComponentHelper::getParams('com_blc');
        $config->set('headers', []);
        return $config;
    }

    public function testonAfterRouteWrongIp()
    {

        $plugin = $this->bootPlugin();


        $url            = 'index.php';
        $linkItem       = $this->loadLinkItem($url);
        $plugin         = $this->bootPlugin();

        //create headers for some IP
        $plugin->params->set('ip', '127.1.1.1');
        $config = $this->getConfigWithoutHeaders();
        $plugin->checkLink($linkItem, $config);
        $headers = $config->get('headers', []);
        $this->assertnotempty($headers);




        $app = $this->getApplication();

        $webClient       = new \Joomla\Application\Web\WebClient();
        $protectedMethod = (
            function (array $headers) {
                $this->detection['headers'] = 1;
                $this->headers              = [];
                foreach ($headers as $header) {
                    [$key, $value]             = explode(':', $header);
                    $this->headers[trim($key)] = trim($value);
                }
            }
        );
        $protectedMethod->call($webClient, $headers);
        $app->client = $webClient;


        $plugin->onAfterRoute();

        $this->checkTransient('FAILED - IP');
    }

    private function checkTransient($status)
    {
        $transientmanager = BlcTransientManager::getInstance();
        $transient        = "BLC LOGIN REQUEST";
        $data             = $transientmanager->get($transient);

        $this->assertSame($status, $data->status);
    }
    public function testonBlcCheckerRequest()
    {
        $this->assertOnBlcCheckerRequest();
    }

    public function testcanCheckLink()
    {
        $url            = 'index.php';
        $linkItem       = $this->loadLinkItem($url);
        $plugin         = $this->bootPlugin();
        $canCheck       = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);

        //   print "\na: $url}\n{$linkItem->internal_url}\n";
    }
    public function testcanCheckLinknotExternal()
    {
        $url            = 'https://example.com';
        $linkItem       = $this->loadLinkItem($url);
        $plugin         = $this->bootPlugin();
        $canCheck       = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $canCheck);
    }

    public function testcheckLink()
    {
        $url            = 'index.php';
        $linkItem       = $this->loadLinkItem($url);
        $plugin         = $this->bootPlugin();
        $plugin->params->set('user', 1);

        $config = $this->getConfigWithoutHeaders();
        $plugin->checkLink($linkItem, $config);
        $headers = $config->get('headers', []);
        $this->assertnotempty($headers);

        return $headers;
    }

    public function testcheckLinknotExternal()
    {
        $url            = 'https://example.com';
        $linkItem       = $this->loadLinkItem($url);
        $plugin         = $this->bootPlugin();
        $plugin->params->set('user', 1);
        $config = $this->getConfigWithoutHeaders();
        $plugin->checkLink($linkItem, $config);
        $headers = $config->get('headers', []);
        $this->assertEmpty($headers, json_encode($headers));
    }

    public function testcheckLinknoUser()
    {
        $url            = 'index.php';
        $linkItem       = $this->loadLinkItem($url);
        $plugin         = $this->bootPlugin();
        $plugin->params->set('user', 0);
        $config = $this->getConfigWithoutHeaders();

        $plugin->checkLink($linkItem, $config);
        $headers = $config->get('headers', []);



        $this->assertEmpty($headers, json_encode($headers));
    }

    public function testgetHelpLink()
    {
        $this->assertgetHelpLink();
    }
    public function testsetTransientIp()
    {
        $plugin          =  $this->bootPlugin();
        $protectedMethod = (
            function (string $status) {
                $this->setTransientIp($status);
            }
        );
        /** @phpstan-ignore method.notFound */
        $transientStatus = 'PHPUNIT';
        $protectedMethod->call($plugin, $transientStatus);
        $this->checkTransient($transientStatus);
    }

    public function testBlcCheckLink()
    {
        $protectedMethod = (
            fn () => $this->getKey()
        );

        $BlcCheckLink = $this->getBlcCheckLink();

        //internal link


        $url            = 'example-cat/example-content';
        $linkItem       = $this->loadLinkItem($url);

        $BlcCheckLink->checkLink($linkItem);


        $checkers = $BlcCheckLink->getCheckers();


        $plugin          = $checkers[BlcPluginActor::class]->instance;
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $key = $protectedMethod->call($plugin);

        $curlChecker          = $checkers[BlcCheckerHttpCurl::class]->instance;
        $this->assertInstanceOf(BlcCheckerHttpCurl::class, $curlChecker);
        $this->assertArrayHasKey($key, $curlChecker->headers, json_encode(array_keys($checkers), JSON_PRETTY_PRINT));


        //external link
        $url            = 'https://example.com';
        $linkItem       = $this->loadLinkItem($url);

        //checker disabled
        $url            = 'index.php';
        $linkItem       = $this->loadLinkItem($url);
        $BlcCheckLink->unRegisterChecker(BlcPluginActor::class);
        $BlcCheckLink->checkLink($linkItem);
        $this->assertFalse(
            isset($key, $curlChecker->headers[$key]),
            'key must not be set'
        );
    }
}
