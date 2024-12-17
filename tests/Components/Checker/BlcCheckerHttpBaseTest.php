<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Component\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpBase;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Table\LinkTable;
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
    }

    public function testcheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $url      = 'https://example.com/';
        $linkItem->bind([
            'url' => $url,
        ]);
        $linkItem->_toCheck = $url;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_WRONG_CLASS_HTTP_CODE);
    }


    public function testSkipWrongSchemeFtpcheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $url      = 'ftp://example.com/';
        $linkItem->bind([
            'url' => $url,
        ]);
        $linkItem->_toCheck = $url;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }


    public function testSkipWrongSchemeMailtocheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $url      = 'mailto:dummy@example.com/';
        $linkItem->bind([
            'url' => $url,
        ]);
        $linkItem->_toCheck = $url;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }
    public function testinvalidDNScheckLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $url      = 'https://sub.invalid/hello.txt';
        $linkItem->bind([
            'url' => $url,
        ]);
        $linkItem->_toCheck = $url;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_DNS_HTTP_CODE);
    }



    public function testIpv6checkLink()
    {
        $checker  = BlcCheckerHttpBase::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $url      = 'https://k6usy.net/';
        $linkItem->bind([
            'url' => $url,
        ]);
        $linkItem->_toCheck = $url;
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_WRONG_CLASS_HTTP_CODE);
    }
}
