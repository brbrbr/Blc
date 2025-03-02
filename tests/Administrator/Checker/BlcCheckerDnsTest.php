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

use Blc\Component\Blc\Administrator\Checker\BlcCheckerDns;
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
#[Attributes\CoversClass(BlcCheckerDns::class)]
#[Attributes\TestDox('Test of the BLC DNS Checker')]
class BlcCheckerDnsTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    private $linkItem;
    public function setUp(): void
    {
        $this->initApplication();
        $this->linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
    }

    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'https://example.com/path1-example', 'code' => HTTPCODES::BLC_CHECK_UNSET], //a (aaaa)
            ['url' => 'https://mail.fiets4daagsen.nl', 'code' => HTTPCODES::BLC_CHECK_UNSET], //cname
            ['url' => 'https://response.invalid', 'code' => HTTPCODES::BLC_DNS_HTTP_CODE], //does not exist
        ];
    }

    public function testCanBoot()
    {
        $checker = BlcCheckerDns::getInstance();
        $this->assertInstanceOf(BlcCheckerDns::class, $checker);
        return $checker;
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckHost($url, $code)
    {
        $checker  = $this->testCanBoot();

        $this->linkItem->bind([
            'url' => $url,
        ]);

        $result = $checker->canCheckLink($this->linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_TRUE);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testCheckLink($url, $code)
    {
        $checker  = $this->testCanBoot();


        $this->linkItem->bind([
            'url' => $url,
        ]);
        $this->linkItem->http_code = HTTPCODES::BLC_CHECK_UNSET;


        $checker->checkLink($this->linkItem);
        $this->assertSame($this->linkItem->http_code, $code);
        $this->assertMessageQueue();
    }
}
