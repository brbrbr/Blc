<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerStatic;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
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

#[Attributes\CoversClass(BlcCheckLink::class)]
class BlcCheckLinkTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    protected function getBlcCheckLink()
    {
        //do not load as singleton to have a blank parser
        $checker =  BlcCheckLink::getInstance(false);

        $protectedMethod = function (): void {
            //avoid throttle while testing

            $this->internalThrottle = 0;
            $this->externalThrottle = 0;
        };
        $protectedMethod->call($checker);
        return $checker;
    }
    protected function getCheckerStub(array|object $return = [])
    {
        if (\is_object($return)) {
            $return = (array)$return;
        }
        $checkerStub = $this->getMockBuilder(BlcCheckerInterface::class)->getMock();
        $checkerStub->method('canCheckLink')
            ->willReturn(BlcCheckerInterface::BLC_CHECK_TRUE);
        $checkerStub->method('checkLink')->willreturnCallback(function ($linkItem) use ($return) {
            foreach ($return as $key => $value) {
                $linkItem->$key = $value;
            }
        });
        return $checkerStub;
    }


    public function testCanBoot()
    {
        $BlcCheckLink = BlcCheckLink::getInstance();
        $this->assertInstanceOf(BlcCheckLink::class, $BlcCheckLink);
        $this->isSingeTon($BlcCheckLink);
    }


    public function testregisterCheckerInstance()
    {

        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $checkerStub = $this->getMockBuilder(BlcCheckerInterface::class)->getMock();
        $BlcCheckLink->registerChecker($checkerStub, 10);

        $getStub =  $BlcCheckLink->getChecker($checkerStub::class);

        $this->assertNotNull($getStub);
        $this->assertInstanceOf($checkerStub::class, $getStub->instance);
        $this->assertEquals(10, $getStub->priority);

        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(1, \count($checkers));
    }


    public function testcannotregisterSameTwiceInstance()
    {
        $this->expectException(\Exception::class);
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $checkerStub = $this->getMockBuilder(BlcCheckerInterface::class)->getMock();

        $BlcCheckLink->registerChecker($checkerStub, 10);
        $BlcCheckLink->registerChecker($checkerStub, 20);
    }

    public function testcannotregisterSameTwiceName()
    {
        $this->expectException(\Exception::class);
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $BlcCheckLink->registerChecker(BlcCheckerStatic::class, 10);
        $BlcCheckLink->registerChecker(BlcCheckerStatic::class, 20);
    }

    public function testcannotregisterSameTwiceMixed()
    {
        $this->expectException(\Exception::class);
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $BlcCheckLink->registerChecker(BlcCheckerStatic::getInstance(), 10);
        $BlcCheckLink->registerChecker(BlcCheckerStatic::class, 20);
    }

    public function testcanunregisterChecker()
    {

        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $checkerStub = $this->getMockBuilder(BlcCheckerInterface::class)->getMock();

        $BlcCheckLink->registerChecker($checkerStub, 10);
        $BlcCheckLink->unregisterChecker($checkerStub::class);
        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(0, \count($checkers));

        $BlcCheckLink->registerChecker($checkerStub, 20);
        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(1, \count($checkers));
    }

    public function testCheckLink()
    {

        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
                'broken'    => 0,
            ]
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem = $this->loadLinkItem('https://testCheckLink.200.invalid');
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);
    }

    public function testUrltoLower()
    {
        $url         = 'https://SomeUpper.200.inValid/These-Stay-Upper';
        $urlLower    = 'https://someupper.200.invalid/These-Stay-Upper';
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
                'broken'    => 0,

            ]
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem            = $this->loadLinkItem($url);
        $linkItem->final_url = '';
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);

        $this->assertSame($url, $linkItem->url, 'orginal url does not match');
        $this->assertSame($urlLower, $linkItem->toCheck, 'tocheck url does not match');
        $this->assertSame($urlLower, $linkItem->final_url, 'final url does not match');
    }


    public function testUTF8UrltoLower()
    {
        $url         = 'https://úùû-ÚÙÛ.200.inValid/úùû-ÚÙÛ';
        $urlFinal    = 'https://úùû-úùû.200.invalid/úùû-ÚÙÛ';
        $encoded     = 'https://xn----6gabbced.200.invalid/%C3%BA%C3%B9%C3%BB-%C3%9A%C3%99%C3%9B';
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
                'broken'    => 0,

            ]
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->setConfigOption('urlencodefix', 0);
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem            = $this->loadLinkItem($url);
        $linkItem->final_url = '';
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);
        $this->assertSame(1, $linkItem->redirect_count);

        $this->assertSame($url, $linkItem->url, 'orginal url does not match');
        $this->assertSame($encoded, $linkItem->toCheck, 'tocheck url does not match');
        $this->assertSame($urlFinal, $linkItem->final_url, 'final url does not match');
    }

    public function testurlEncodingAsRedirect()
    {
        $this->urlEncodingAsRedirect(1);
        $this->urlEncodingAsRedirect(0);
    }

    public function urlEncodingAsRedirect($on)
    {
        $url = 'https://200.invalid/úùû-ÚÙÛ';
        if ($on) {
            $urlFinal = 'https://200.invalid/%C3%BA%C3%B9%C3%BB-%C3%9A%C3%99%C3%9B';
        } else {
            $urlFinal = '';
        }
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
                'broken'    => 0,

            ]
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->setConfigOption('urlencodefix', $on);
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem                 = $this->loadLinkItem($url);
        $linkItem->final_url      = '';
        $linkItem->redirect_count = 0;
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);
        $this->assertSame($on, $linkItem->redirect_count);
        $this->assertSame($url, $linkItem->url, 'orginal url does not match');
        $this->assertSame($urlFinal, $linkItem->final_url, 'final url does not match');
    }



    public function testUrlPunicode()
    {
        $url          = 'https://München.200.invalid';
        $urlPunnyCode = 'https://xn--Mnchen-3ya.200.invalid';
        $urlLower     = mb_strtolower($url);
        $checkerStub  = $this->getCheckerStub(
            [
                'http_code' => 200,
                'broken'    => 0,

            ]
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem            = $this->loadLinkItem($urlPunnyCode);
        $linkItem->final_url = '';
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);

        $this->assertSame($urlPunnyCode, $linkItem->url);
        $this->assertSame($urlPunnyCode, $linkItem->toCheck);
        $this->assertSame($urlLower, $linkItem->final_url);
    }

    public function testFinalUrlUTF8()
    {
        $url         = 'https://München.200.invalid';
        $urlLower    = mb_strtolower($url);
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
                'broken'    => 0,
                'final_url' => UrlHelper::urlToPunycode($url),
            ]
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem = $this->loadLinkItem($url);
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);
        $this->assertSame($urlLower, $linkItem->final_url);
    }



    public function testregisterCheckerClass()
    {


        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();


        $BlcCheckLink->registerChecker(BlcCheckerStatic::class, 10);

        $getStub =  $BlcCheckLink->getChecker(BlcCheckerStatic::class);

        $this->assertNotNull($getStub);
        $this->assertInstanceOf(BlcCheckerStatic::class, $getStub->instance);
        $this->assertEquals(10, $getStub->priority);

        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(1, \count($checkers));
    }

    public function testCheckersFunctions()
    {
        $BlcCheckLink = $this->getBlcCheckLink();
        $checkers     = $BlcCheckLink->getCheckers();
        $this->assertNotEquals(0, \count($checkers));

        $BlcCheckLink->clearCheckers();
        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(0, \count($checkers));
    }

    public function testcannotRegisterAnyClass()
    {
        $this->expectException(\TypeError::class);
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->registerChecker($this);
    }

    public function testcannotRegisterAnyClassByString()
    {
        $this->expectException(\Error::class);
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->registerChecker(static::class);
    }
}
