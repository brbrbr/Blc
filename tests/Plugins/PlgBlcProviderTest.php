<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Plugin\Blc\Provider\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Provider\Extension\FacebookChecker;
use Blc\Plugin\Blc\Provider\Extension\OEmbedChecker;
use Blc\Plugin\Blc\Provider\Extension\YoutubeChecker;
use Blc\Tests\UnitTestCase;
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
#[Attributes\TestDox('Test of the BLC - Content Plugin')]
class PlgBlcProviderTest extends UnitTestCase
{
    private string $folder         = 'blc';
    private string $element        = 'provider';
    protected string $fieldContext = 'com_content.categories';
    //facebook is really unpredictable. So not a real test on 'correct' codes but just on expeded codes inlcuding fault
    //the response should  not contain 'normal' codes like 301 and 404 when the  facebook page plugin checker is active
    private $possibleCodes = [200, HTTPCODES::BLC_FACEBOOK_PAGE_FOUND_HTTP_CODE, HTTPCODES::BLC_FACEBOOK_PAGE_NOT_FOUND_HTTP_CODE];

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }

    public static function checkLinkProvider(): array
    {
        return   [
            ['https://youtu.be/Z5Gfw5_xksY'],
            ['https://www.youtube.com/watch?v=Z5Gfw5_xksY&ab_channel=brambring.nl'],
            ['https://www.tiktok.com/@itsyaboymaina?lang=nl-NL'],
        ];
    }

    public static function checkBrokenLinkProvider(): array
    {
        return   [
            ['https://youtu.be/xxx'],
            ['https://www.youtube.com/watch?v=xxx&ab_channel=brambring.nl'],
            ['https://www.tiktok.com/@xsefsws?lang=nl-NL'],
        ];
    }

    public static function checkYoutubeLinkProvider(): array
    {
        return   [
            ['https://youtu.be/Z5Gfw5_xksY'],
            ['https://www.youtube.com/watch?v=Z5Gfw5_xksY&ab_channel=brambring.nl'],
        ];
    }

    public static function checkFacebookLinkProvider(): array
    {
        return   [
            ['https://www.facebook.com/fietsvierdaagsen/'],
            ['https://www.facebook.com/bram.brambring/'],
            ['https://www.facebook.com/people/Avond4daagse-Ruinen/100064739977295/'],
            ['https://www.facebook.com/Oranjecomitewagenberg'],

        ];
    }



    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'provider'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }

    protected function bootOEmbedChecker()
    {
        $plugin        =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'provider'));
        $OEmbedChecker = OEmbedChecker::getInstance();
        $OEmbedChecker->setParams($plugin->params);
        return $OEmbedChecker;
    }

    protected function bootFacebookChecker()
    {
        $plugin          =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'provider'));
        $FacebookChecker = FacebookChecker::getInstance();
        $FacebookChecker->setParams($plugin->params);
        return $FacebookChecker;
    }

    protected function bootYoutubeChecker()
    {
        $plugin         =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'provider'));
        if ($plugin->params->get('youapi', '') == '') {
            $this->markTestSkipped('No Youtube API key set');
        }
        $YoutubeChecker = YoutubeChecker::getInstance();
        $YoutubeChecker->setParams($plugin->params);
        return $YoutubeChecker;
    }


    public function testCanNotCheckInternal()
    {
        $link     = $this->getSomeLink(destination: 'internal');
        $checker  = $this->bootOEmbedChecker();
        $canCheck = $checker->canCheckLink($link);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $canCheck);
    }

    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCanCheck($url)
    {
        $linkItem = $this->loadLinkItem($url);

        $checker  = $this->bootOEmbedChecker();
        $canCheck = $checker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);
    }

    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCheckLink($url)
    {
        $linkItem = $this->loadLinkItem($url);

        $checker = $this->bootOEmbedChecker();
        $checker->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
    }

    #[Attributes\DataProvider('checkBrokenLinkProvider')]
    public function testBrokenCheckLinkEmbedOnly($url)
    {
        $linkItem = $this->loadLinkItem($url);

        $checker = $this->bootOEmbedChecker();
        $checker->setParamsOption('embed', 1);
        $checker->checkLink($linkItem);
        $this->assertSame(400, $linkItem->http_code);
    }

    #[Attributes\DataProvider('checkBrokenLinkProvider')]
    public function testBrokenCheckLinkEmbedContinue($url)
    {
        $linkItem = $this->loadLinkItem($url);

        $checker = $this->bootOEmbedChecker();
        $checker->setParamsOption('embed', 0);
        $checker->checkLink($linkItem);
        $this->assertSame(0, $linkItem->http_code);
    }


    #[Attributes\DataProvider('checkLinkProvider')]
    public function testBlcCheckLink($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $plugin   = $this->getPlugin($this->folder, $this->element);
        $plugin->params->set('embed', 1);
        $this->checkLinkWrapped($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(HTTPCODES::BLC_BROKEN_FALSE, $linkItem->broken);
    }

    #[Attributes\DataProvider('checkBrokenLinkProvider')]
    public function testBrokenBlcCheckLinkOnly($url)
    {
        $linkItem   = $this->loadLinkItem($url);
        $plugin     = $this->getPlugin($this->folder, $this->element);
        $currentApi =  $plugin->params->get('youapi', '');
        $plugin->params->set('embed', 1);
        $plugin->params->set('youapi', '');
    
        $this->checkLinkWrapped($linkItem);
        $plugin->params->set('youapi', $currentApi);
        $this->assertSame(400, $linkItem->http_code);
        $this->assertSame(HTTPCODES::BLC_BROKEN_TRUE, $linkItem->broken);
        $this->assertSame('OEmbedChecker', $linkItem->log['Checker Embed']);
    }
    #[Attributes\DataProvider('checkBrokenLinkProvider')]
    public function testBrokenBlcCheckLinkContinue($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $plugin   = $this->getPlugin($this->folder, $this->element);
        $plugin->params->set('embed', 0);
        $this->checkLinkWrapped($linkItem);
        $this->assertNotSame(400, $linkItem->http_code);
    }
    #[Attributes\DataProvider('checkYoutubeLinkProvider')]
    public function testCanCheckyoutubeApi($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootYoutubeChecker();
        $canCheck = $checker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);
    }

    #[Attributes\DataProvider('checkYoutubeLinkProvider')]
    public function testCannotCheckyoutubeApi($url)
    {
        $linkItem   = $this->loadLinkItem($url);
        $checker    = $this->bootYoutubeChecker();
        $plugin     = $this->getPlugin($this->folder, $this->element);
        $currentApi =  $plugin->params->get('youapi', '');
        $checker->setParamsOption('youapi', '');
        $canCheck = $checker->canCheckLink($linkItem);
        $plugin->params->set('youapi', $currentApi);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $canCheck);
    }

    #[Attributes\DataProvider('checkYoutubeLinkProvider')]
    public function testCheckyoutubeApi($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootYoutubeChecker();
        $checker->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame('YoutubeChecker', $linkItem->log['Checker Embed']);
    }

    #[Attributes\DataProvider('checkYoutubeLinkProvider')]
    public function testBlcCheckLinkyoutubeapi($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $plugin   = $this->getPlugin($this->folder, $this->element);
        if ($plugin->params->get('youapi', '') == '') {
            $this->markTestSkipped('No Youtube API key set');
        }
        $plugin->params->set('embed', 1);
        $this->checkLinkWrapped($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(HTTPCODES::BLC_BROKEN_FALSE, $linkItem->broken);
        $this->assertSame('YoutubeChecker', $linkItem->log['Checker Embed']);
    }

    #[Attributes\DataProvider('checkYoutubeLinkProvider')]
    public function testBlcCheckLinkYoutubeNoapiKey($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $plugin   = $this->getPlugin($this->folder, $this->element);
        $plugin->params->set('embed', 1);
        $plugin     = $this->getPlugin($this->folder, $this->element);
        $currentApi =  $plugin->params->get('youapi', '');
        $plugin->params->set('youapi', '');
        $this->checkLinkWrapped($linkItem);
        $plugin->params->set('youapi', $currentApi);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(HTTPCODES::BLC_BROKEN_FALSE, $linkItem->broken);
        $this->assertSame('OEmbedChecker', $linkItem->log['Checker Embed']);
    }


    #[Attributes\DataProvider('checkFacebookLinkProvider')]
    public function testBlcCheckLinkFacebook($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $plugin   = $this->getPlugin($this->folder, $this->element);
        $plugin->params->set('embed', 1);
        $plugin->params->set('facebook', 1);
        $this->checkLinkWrapped($linkItem);

        $this->assertContains($linkItem->http_code, $this->possibleCodes);
        if ($linkItem->http_code < 300) {
            $this->assertSame(HTTPCODES::BLC_BROKEN_FALSE, $linkItem->broken);
        } else {
            $this->assertSame(HTTPCODES::BLC_BROKEN_TRUE, $linkItem->broken);
        }
        if ($linkItem->http_code != 200) {
            $this->assertSame('FacebookChecker', $linkItem->log['Checker Embed']);
        }
    }

    #[Attributes\DataProvider('checkFacebookLinkProvider')]
    public function testCanNotCheckFacebook($url)
    {
        $linkItem = $this->loadLinkItem($url);
        $checker  = $this->bootFacebookChecker();
        $canCheck = $checker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $canCheck);
    }
    #[Attributes\DataProvider('checkFacebookLinkProvider')]
    public function testCanCheckFacebook($url)
    {
        $linkItem            = $this->loadLinkItem($url);
        $linkItem->final_url = 'https://facebook.com/login';
        $linkItem->http_code = 301;
        $checker             = $this->bootFacebookChecker();
        $canCheck            = $checker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);
    }
    #[Attributes\DataProvider('checkFacebookLinkProvider')]
    public function testCheckLinkFacebook($url)
    {
        $url                 = self::checkFacebookLinkProvider()[0][0];
        $linkItem            = $this->loadLinkItem($url);
        $linkItem->final_url = 'https://facebook.com/login';
        $linkItem->http_code = 301;
        $checker             = $this->bootFacebookChecker();
        $checker->CheckLink($linkItem);
        $this->assertContains($linkItem->http_code, $this->possibleCodes);
    }
}
