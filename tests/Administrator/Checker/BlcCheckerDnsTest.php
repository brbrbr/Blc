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

use Blc\Component\Blc\Administrator\Checker\BlcCheckerDns;
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
#[Attributes\CoversClass(BlcCheckerDns::class)]
#[Attributes\TestDox('Test of the BLC DNS Checker')]
class BlcCheckerDnsTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]

    protected string $class = BlcPluginActor::class;
    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'https://example.com/path1-example', 'code' => HTTPCODES::BLC_CHECK_UNSET], //a (aaaa)
            ['url' => 'https://mail.fiets4daagsen.nl', 'code' => HTTPCODES::BLC_CHECK_UNSET], //cname
            ['url' => 'mailto:john@example.com', 'code' => HTTPCODES::BLC_CHECK_UNSET], //cname
            ['url' => 'https://response.invalid', 'code' => HTTPCODES::BLC_DNS_HTTP_CODE], //does not exist
            ['url' => 'https://ipv6.google.com', 'code' => HTTPCODES::BLC_CHECK_UNSET], //ipv6
            ['url' => 'https://37.97.174.91/', 'code' => HTTPCODES::BLC_CHECK_UNSET], //inet
        ];
    }
    protected function bootInstance()
    {
        $checker = BlcCheckerDns::getInstance();

        return $checker;
    }

    public function testCanBoot()
    {
        $checker = BlcCheckerDns::getInstance();
        $this->assertInstanceOf(BlcCheckerDns::class, $checker);
        $this->isSingeTon($checker);
    }
    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckHost($url, $code)
    {
        $checker  = BlcCheckerDns::getInstance();
        $linkItem = $this->loadLinkItem($url);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_TRUE);
        if ($code !== HTTPCODES::BLC_CHECK_UNSET) {
            $linkItem->http_code = $code;
            $result              = $checker->canCheckLink($linkItem);
            $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
        }
        $this->assertMessageQueue();
    }



    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testCheckLink($url, $code)
    {
        $checker  = BlcCheckerDns::getInstance();
        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, $code);
        $this->assertMessageQueue();
    }
}
