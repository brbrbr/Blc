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

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerIgnoreRedirect;
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
#[Attributes\CoversClass(BlcCheckerIgnoreRedirect::class)]
#[Attributes\TestDox('Test of the BLC DNS Checker')]
class BlcCheckerIgnoreRedirectTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    protected string $testIgnoreUrl = 'https://shoppies.nl/c/checker/example.com';
    protected string $testNotIgnoreUrl = 'https://c.12l.nl/checker/example.com';
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testCanBoot()
    {
        $checker = BlcCheckerIgnoreRedirect::getInstance();
        $this->assertInstanceOf(BlcCheckerIgnoreRedirect::class, $checker);
        $checker->setConfigOption('ignore_redirects', 'shoppies.nl',true);

        return $checker;
    }

    public function testcanNotCheckUnchecked()
    {
        $checker  = $this->testCanBoot();


        $linkItem = $this->loadLinkItem($this->testIgnoreUrl);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
    }

    public function testcanNotCheckInternal()
    {
        $checker  = $this->testCanBoot();
        $url = 'fake-artikel';

        $linkItem = $this->loadLinkItem($url);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
    }

    public function testcanNotCheckError()
    {
        $checker  = $this->testCanBoot();
        $linkItem = $this->loadLinkItem($this->testIgnoreUrl);
        $linkItem->http_code = 404;
        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
    }

    public function testcanCheckFound()
    {
        $checker  = $this->testCanBoot();
        $linkItem = $this->loadLinkItem($this->testIgnoreUrl);
        $linkItem->http_code = 200;
        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_TRUE);
    }

    public function testCheckIgnoreUrl()
    {
        $checker  = $this->testCanBoot();
        $linkItem = $this->loadLinkItem($this->testIgnoreUrl);
        $linkItem->http_code = 200;
        $linkItem->redirect_count = 2;
        $linkItem->final_url = $this->testNotIgnoreUrl;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->redirect_count, 0);
        $this->assertSame($linkItem->final_url, '');
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_IGNORED_REDIRECT_PROTOCOL_HTTP_CODE);
    }

    public function testCheckNotIgnoreUrl()
    {
        $checker  = $this->testCanBoot();
        $linkItem = $this->loadLinkItem($this->testNotIgnoreUrl);
        $linkItem->http_code = 200;
        $linkItem->redirect_count = 2;
        $linkItem->final_url = $this->testIgnoreUrl;;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->redirect_count, 2);
        $this->assertSame($linkItem->final_url,  $this->testIgnoreUrl);
        $this->assertSame($linkItem->http_code, 200);
    }

    public function testCheckLinkIgnoredRedirect()
    {
  
        $linkItem = $this->loadLinkItem($this->testIgnoreUrl);
        $linkItem->http_code = 0;
        $linkItem->redirect_count = 0;
        $checker = BlcCheckLink::getInstance();
        $checker->setConfigOption('ignore_redirects', 'shoppies.nl',true);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->redirect_count, 0);
        $this->assertSame($linkItem->final_url,  '');
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_IGNORED_REDIRECT_PROTOCOL_HTTP_CODE);
    }
}
