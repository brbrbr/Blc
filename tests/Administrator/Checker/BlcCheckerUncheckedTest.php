<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerUnchecked;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Checker/BlcCheckerUnchecked

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcCheckerUnchecked::class)]
class BlcCheckerUncheckedTest extends UnitTestCase
{



    public static function checkLinkProvider(): array
    {
        return   [
            ['url' => 'https://example.com/path1-example'],
            ['url' => 'ftps://mail.fiets4daagsen.nl'],
            ['url' => 'mailto:john@example.com'],
            ['url' => 'response.invalid'],
        ];
    }

    public function testCanBoot()
    {
        $checker = BlcCheckerUnchecked::getInstance();
        $this->assertInstanceOf(BlcCheckerUnchecked::class, $checker);
        $this->isSingeTon($checker);
    }
    /**
     *
     * @since 25.44.7589
     * BlcCheckerUnchecked::checkLink will always return BLC_UNCHECKED_PROTOCOL_HTTP_CODE and BLC_BROKEN_FALSE
     *
     */
    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCheckLink($url)
    {
        $checker  = BlcCheckerUnchecked::getInstance();
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_UNCHECKED_PROTOCOL_HTTP_CODE);
        $this->assertSame($linkItem->broken, HTTPCODES::BLC_BROKEN_FALSE);
    }

    /**
     *
     * @since 25.44.7589
     * BlcCheckerUnchecked::canCheckLink will always return FALSE if link already checked ( http_code != BLC_CHECK_UNSER)
     *
     */

    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCanNotCheckLink($url)
    {

        $checker  = BlcCheckerUnchecked::getInstance();
        $linkItem = $this->loadLinkItem($url, http_code: 200);
        $result   = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
    }

    /**
     *
     * @since 25.44.7589
     * BlcCheckerUnchecked::canCheckLink will always return FALSE for unkownprotocols = true if link not checked ( http_code = BLC_CHECK_UNSER)
     *
     */

    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCanCheckLinkTrue($url)
    {
        $url      = 'https://example.com';
        $checker  = BlcCheckerUnchecked::getInstance();
        $checker->setConfigOption('unkownprotocols', true);
        $linkItem = $this->loadLinkItem($url);
        $result   = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_TRUE);
    }

    /**
     *
     * @since 25.44.7589
     * BlcCheckerUnchecked::canCheckLink will always return IGNORE for unkownprotocols = false  if link not checked ( http_code = BLC_CHECK_UNSER)
     *
     */


    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCanCheckLinkIgnore($url)
    {
        $url      = 'https://example.com';
        $checker  = BlcCheckerUnchecked::getInstance();
        $checker->setConfigOption('unkownprotocols', false);
        $linkItem = $this->loadLinkItem($url);
        $result   = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_IGNORE);
    }
}
