<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpBase;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerStatic;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Table\LinkTable;
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
    protected function getCheckerStub(array|object $return = [], ?int $canCheck = null)
    {
        static $count = 0;
        $count++;

        if (\is_object($return)) {
            $return = (array)$return;
        }
        $checkerStub =  $this->getMockBuilder(HTTPCODES::class)
            ->setMockClassName('getCheckerStub_' . $count)->getMock();
        if ($canCheck !== null) {
            $checkerStub->expects($this->atLeastOnce())->method('canCheckLink')
                ->willReturn($canCheck);
        }
        if ($return) {
            $checkerStub->expects($this->atLeastOnce())->method('checkLink')->willreturnCallback(function ($linkItem) use ($return) {
                foreach ($return as $key => $value) {
                    $linkItem->$key = $value;
                }
                if (!isset($return['broken']) && isset($return['http_code'])) {
                    //simulte a failed check by not setting the http code
                    $linkItem->broken =  BlcCheckerHttpBase::getInstance()->isErrorCode($return['http_code']);
                }
            });
        }
        return  $checkerStub;
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

        $checkerStub = $this->createStub(HTTPCODES::class);
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

        $checkerStub = $this->createStub(HTTPCODES::class);

        $BlcCheckLink->registerChecker($checkerStub, 10);
        $BlcCheckLink->registerChecker($checkerStub, 20);
    }



    public function testunRegisterChecker()
    {

        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $checkerStub = $this->createStub(HTTPCODES::class);

        $BlcCheckLink->registerChecker($checkerStub, 10);
        $BlcCheckLink->unregisterChecker($checkerStub::class);
        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(0, \count($checkers));

        $BlcCheckLink->registerChecker($checkerStub, 20);
        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(1, \count($checkers));
    }

    public function testcheckLink()
    {

        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
            ],
            HTTPCODES::BLC_CHECK_TRUE
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();

        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem = $this->loadLinkItem('https://testCheckLink.200.invalid');
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);
    }

    public function testCheckLinkFailed()
    {
        $fakeBrokenCode = 99;
        $checkerStub    = $this->getCheckerStub(
            [
                'http_code' => HTTPCODES::BLC_CHECK_FAILED,
                'broken'    => $fakeBrokenCode,
            ],
            HTTPCODES::BLC_CHECK_TRUE
        );

        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem = $this->loadLinkItem('https://10.255.255.1/');

        $BlcCheckLink->checkLink($linkItem);

        //reset the http code to unset
        $this->assertSame(HTTPCODES::BLC_CHECK_UNSET, $linkItem->http_code, 'BLC_CHECK_UNSET');
        //do not change the broken code -- todo think about this one maybe it should be BLC_BROKEN_FALSE
        $this->assertSame($fakeBrokenCode, $linkItem->broken, 'Broken code changed');
        $this->assertSame(HTTPCODES::BLC_CHECKSTATE_TOCHECK, $linkItem->being_checked, 'BLC_CHECKSTATE_TOCHECK');
    }

    public function testMyraCloudWAFBlocked()
    {
        $testLink    = 'https://testCheckLink.503.invalid';
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 503,
                'final_url' => $testLink . '/myracloud-blocked',
            ],
            HTTPCODES::BLC_CHECK_TRUE
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem = $this->loadLinkItem($testLink);

        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(BlcCheckLink::BLC_DNS_WAF_CODE, $linkItem->http_code, 'BLC_DNS_WAF_CODE');
        $this->assertSame(BlcCheckLink::BLC_BROKEN_WARNING, $linkItem->broken, 'not reported as warning');
        $this->assertSame('', $linkItem->final_url, 'final_url not cleared');
    }


    public function testCheckLinkException()
    {
        $msg         = 'PHPUNIT Test Exception test';
        $checkerStub =  $this->getMockBuilder(HTTPCODES::class)

            ->setMockClassName('getCheckerStub_testCheckLinkException')->getMock();
        $checkerStub->expects($this->once())->method('canCheckLink')
            ->willReturn(HTTPCODES::BLC_CHECK_TRUE);
        $checkerStub->expects($this->once())->method('checkLink')
            ->willReturnCallback(fn () => throw new \Exception($msg));

        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);
        $linkItem = $this->loadLinkItem('https://testCheckLink.200.invalid');
        $BlcCheckLink->checkLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_EXCEPTION_HTTP_CODE, $linkItem->http_code, 'BLC_EXCEPTION_HTTP_CODE');
        $this->assertSame(HTTPCODES::BLC_CHECKSTATE_CHECKED, $linkItem->being_checked, 'BLC_CHECKSTATE_CHECKED');
        $this->assertStringContainsString($msg, $linkItem->log['Message']);
    }

    public function testUrltoLower()
    {
        $url         = 'https://SomeUpper.200.inValid/These-Stay-Upper';
        $urlLower    = 'https://someupper.200.invalid/These-Stay-Upper';
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
            ],
            HTTPCODES::BLC_CHECK_TRUE
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
            ],
            HTTPCODES::BLC_CHECK_TRUE
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
            ],
            HTTPCODES::BLC_CHECK_TRUE
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
            ],
            HTTPCODES::BLC_CHECK_TRUE
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
                'final_url' => UrlHelper::urlToPunycode($url),
            ],
            HTTPCODES::BLC_CHECK_TRUE
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

    public function testMailto()
    {
        $url         = 'mailto:bram@example.com';
        $urlLower    = mb_strtolower($url);
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,
                'final_url' => $url,
            ],
            HTTPCODES::BLC_CHECK_TRUE
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

    public function testNotParsableUrlCheckLink()
    {

        $url          = 'https://external:site.com';
        $BlcCheckLink = $this->getBlcCheckLink();

        $linkItem = $this->loadLinkItem($url);

        $nullDate             = $this->getDatabase()->getNullDate();
        $linkItem->last_check = $nullDate;
        $BlcCheckLink->checkLink($linkItem);
        $linkItem = $this->loadLinkItem($url, http_code: false);

        $this->assertSame(1, $linkItem->broken);
        $this->assertSame(HTTPCODES::BLC_INVALID_URL_HTTP_CODE, $linkItem->http_code);
        $this->assertNotSame($nullDate, $linkItem->last_check);
    }

    public function testNotParsableUrlCanCheckLink()
    {

        $url          = 'https://external:site.com';
        $BlcCheckLink = $this->getBlcCheckLink();

        $linkItem = $this->loadLinkItem($url);
        $BlcCheckLink->checkLink($linkItem);
        $linkItem = $this->loadLinkItem($url, http_code: false);

        $this->assertSame(1, $linkItem->broken);
        $this->assertSame(HTTPCODES::BLC_INVALID_URL_HTTP_CODE, $linkItem->http_code);
    }



    public function testregisterChecker()
    {


        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();


        $BlcCheckLink->registerChecker(BlcCheckerStatic::getInstance(), 10);

        $getStub =  $BlcCheckLink->getChecker(BlcCheckerStatic::class);

        $this->assertNotNull($getStub);
        $this->assertInstanceOf(BlcCheckerStatic::class, $getStub->instance);
        $this->assertEquals(10, $getStub->priority);

        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(1, \count($checkers));
        return $BlcCheckLink;
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


    #[Attributes\Depends('testgetChecker')]
    public function testgetCheckers($BlcCheckLink)
    {
        $name     = BlcCheckerStatic::class;
        $checkers =  $BlcCheckLink->getCheckers();
        $checker  = $checkers[$name] ?? null;
        $this->assertNotNull($checker);
        $this->assertInstanceOf(BlcCheckerStatic::class, $checker->instance);
        $this->assertEquals(10, $checker->priority);
        return $BlcCheckLink;
    }
    #[Attributes\Depends('testgetCheckers')]
    public function testclearCheckers($BlcCheckLink)
    {


        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(1, \count($checkers));
        $BlcCheckLink->clearCheckers();
        $checkers = $BlcCheckLink->getCheckers();
        $this->assertEquals(0, \count($checkers));
        return $BlcCheckLink;
    }


    #[Attributes\Depends('testregisterChecker')]
    public function testgetChecker($BlcCheckLink)
    {
        $name    = BlcCheckerStatic::class;
        $checker =  $BlcCheckLink->getChecker($name);
        $this->assertNotNull($checker);
        $this->assertInstanceOf(BlcCheckerStatic::class, $checker->instance);
        $this->assertEquals(10, $checker->priority);
        return $BlcCheckLink;
    }

    public function testcanCheckLink()
    {
        $linkItem = $this->createStub(LinkTable::class);

        $checkerStubFalse = $this->getCheckerStub(
            [],
            HTTPCODES::BLC_CHECK_FALSE
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStubFalse, 10);


        $cancheck = $BlcCheckLink->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $cancheck);

        $checkerStubTrue = $this->getCheckerStub(
            [],
            HTTPCODES::BLC_CHECK_TRUE
        );

        $BlcCheckLink->registerChecker($checkerStubTrue, 20);
        $cancheck = $BlcCheckLink->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $cancheck);


        $checkerStubIgnore = $this->getCheckerStub(
            [],
            HTTPCODES::BLC_CHECK_IGNORE
        );

        $BlcCheckLink->registerChecker($checkerStubIgnore, 15);
        $cancheck = $BlcCheckLink->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_IGNORE, $cancheck);
    }

    public function testmanualLink()
    {
        $url         = 'https://münchen.200.invalid';
        $result      = [
            'url'       => $url,
            'http_code' => 404,

            'redirect_count'   => 88,
            'request_duration' => 0.1,
            'final_url'        => $url . '/final',
        ];
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->manualLink($result);

        $linkItem = $this->loadLinkItem($url, http_code: false);


        foreach ($result as $key => $value) {
            $this->assertEquals($value, $linkItem->$key);
        }
    }



    public function testcheckLinkId()
    {
        $checkerStub = $this->getCheckerStub(
            [
                'http_code' => 200,

            ],
            HTTPCODES::BLC_CHECK_TRUE
        );
        $BlcCheckLink = $this->getBlcCheckLink();
        $BlcCheckLink->clearCheckers();
        $BlcCheckLink->registerChecker($checkerStub, 10);

        $linkItem = $BlcCheckLink->checkLinkId(0);
        $this->assertFalse($linkItem);

        $linkItem = $BlcCheckLink->checkLinkId(-1);
        $this->assertFalse($linkItem);

        $linkItem = $this->loadLinkItem('https://testCheckLink.200.invalid');
        $linkItem = $BlcCheckLink->checkLinkId((int)$linkItem->id);
        $this->assertSame(200, $linkItem->http_code);
        $this->assertSame(0, $linkItem->broken);
    }
}
