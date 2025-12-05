<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Helper;

use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Tests\UnitTestCase;
use Joomla\Uri\Uri;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

#[Attributes\CoversClass(UrlHelper::class)]
class UrlHelperTest extends UnitTestCase
{
    public function setUp(): void
    {
        //  $this->initApplication();
    }

    public static function utf8hosts(): array
    {
        return [
            ['nörgler.com', 'xn--nrgler-wxa.com'],
            ['München.de', 'xn--Mnchen-3ya.de'],
            ['SomeUpper.200.inValid', 'someupper.200.invalid'],
            ['úùû-ÚÙÛ.com', 'xn----6gabbced.com'],

        ];
    }


    #[Attributes\DataProvider('utf8hosts')]
    public function testHostToPunycode($host, $expectedTo): void
    {
        $to         = UrlHelper::hostToPunnycode($host);
        $expectedTo = strtolower((string) $expectedTo);
        $this->assertEquals(
            $to,
            $expectedTo,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    #[Attributes\DataProvider('utf8hosts')]
    public function testPunycodetoUrl($expectedTo, $from): void
    {
        $expectedTo = mb_strtolower((string) $expectedTo);
        $url        = "https://$from/Bla-Bla-Bla/$from";

        $urlTo = "https://$expectedTo/Bla-Bla-Bla/$from";
        $to    = UrlHelper::urlToUTF8($url);

        $this->assertEquals(
            $urlTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $urlTo,
                $to
            )
        );
    }

    public function testPunycodeHostNoHost(): void
    {
        $expectedTo = '';
        $to         = UrlHelper::hostToPunnycode($expectedTo);
        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }


    public function testPunycodetoUrlNoHost(): void
    {
        $expectedTo = 'images/úùû-ÚÙÛ.jpg';
        $to         = UrlHelper::urlToUTF8($expectedTo);
        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    public function testPunycodetoUrlUriEmpty(): void
    {
        $expectedTo = '';
        $to         = UrlHelper::urlToUTF8($expectedTo);
        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    public function testPunycodetoUrlUriNull(): void
    {
        $expectedTo = null;
        $to         = UrlHelper::urlToUTF8($expectedTo);
        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }
    /**
     *
     * urlencodefix will urlencode and return true
     * however Uri will revert it to an decoded string.
     */
    public function testurlencodeFixPartsQuery(): void
    {
        $from       = "https://example.com/?param=úùû&param2=ÚÙÛ";
        $expectedTo = "https://example.com/?param=úùû&param2=ÚÙÛ";
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['query']);
        $to         = $parsedItem->toString();

        $this->assertTrue(
            $result
        );

        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }


    /**
     *

     */
    public function testurlencodeFixPartsFragmentNoFragment(): void
    {
        $from       = "https://example.com/úùû";
        $expectedTo = $from;
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['fragment']);
        $to         = $parsedItem->toString();
        //fragment never changes the result to true
        $this->assertFalse(
            $result
        );

        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    public function testurlencodeFixPartsFragment(): void
    {
        $from       = "https://example.com/#úùû";
        $expectedTo = 'https://example.com/#%C3%BA%C3%B9%C3%BB';
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['fragment']);
        $to         = $parsedItem->toString();
        //fragment never changes the result to true
        $this->assertFalse(
            $result
        );

        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    public function testurlencodeFixPartsFragmentAndPath(): void
    {
        $from       = "https://example.com/úùû#úùû";
        $expectedTo = 'https://example.com/%C3%BA%C3%B9%C3%BB#%C3%BA%C3%B9%C3%BB';
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['path','fragment']);
        $to         = $parsedItem->toString();
        //path will change to true
        $this->assertTrue(
            $result
        );

        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }


    public function testurlencodeFixPartsPath(): void
    {
        $from       = "https://example.com/úùû#úùû";
        $expectedTo = 'https://example.com/%C3%BA%C3%B9%C3%BB#úùû';
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['path']);
        $to         = $parsedItem->toString();
        //path will change to true
        $this->assertTrue(
            $result
        );

        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    /**
     *
     * urlencodefix will urlencode and return true
     *  Uri  setquery will use the raw values and return an urlencoded query.
     */

    public function testurlencodeFixPartsQueryArray(): void
    {
        $from       = "https://example.com/?param=úùû&param2=ÚÙÛ";
        $expectedTo = "https://example.com/?param=%C3%BA%C3%B9%C3%BB&param2=%C3%9A%C3%99%C3%9B";
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['queryarray']);
        $to         = $parsedItem->toString();

        $this->assertTrue(
            $result
        );

        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    public function testurlencodeFixParts(): void
    {
        $from       = "https://example.com/úùû-ÚÙÛ/?param=úùû&param2=ÚÙÛ#úùû-ÚÙÛ";
        $expectedTo = "https://example.com/%C3%BA%C3%B9%C3%BB-%C3%9A%C3%99%C3%9B/?param=úùû&param2=ÚÙÛ#%C3%BA%C3%B9%C3%BB-%C3%9A%C3%99%C3%9B";
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem);
        $to         = $parsedItem->toString();
        $this->assertTrue(
            $result
        );
        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }

    public function testurlencodeFixPartsencoded(): void
    {

        $from       = "https://example.com/%C3%BA%C3%B9%C3%BB-%C3%9A%C3%99%C3%9B/?param=úùû&param2=ÚÙÛ#%C3%BA%C3%B9%C3%BB-%C3%9A%C3%99%C3%9B";
        $expectedTo = $from;
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem);
        $to         = $parsedItem->toString();
        $this->assertFalse(
            $result,
            \sprintf(
                'Sequences "%s" change to "%s"',
                $from,
                $to
            )
        );
        $this->assertEquals(
            $expectedTo,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $expectedTo,
                $to
            )
        );
    }
}
