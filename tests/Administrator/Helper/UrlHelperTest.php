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
        $to         = UrlHelper::hostToPunycode($host);
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
        $to         = UrlHelper::hostToPunycode($expectedTo);
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
     * urlencodefix will not urlencode and return trfalseue
     *  Uri will revert it to an decoded string.
     */
    public function testurlencodeFixPartsQuery(): void
    {
        $from       = "https://example.com/?param=úùû&param2=ÚÙÛ";
        $expectedTo = "https://example.com/?param=úùû&param2=ÚÙÛ";
        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['query']);
        $to         = $parsedItem->toString();

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
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['path', 'fragment']);
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
     *  Uri  setquery will use the raw values and return an urldecoded query.
     */

    public function testurlencodeFixPartsQueryArray(): void
    {
        $from       = "https://example.com/?param=úùû&param2=ÚÙÛ";

        $parsedItem = new Uri($from);
        $result     = UrlHelper::urlencodeFixParts($parsedItem, ['queryarray']);
        $to         = $parsedItem->toString();

        $this->assertfalse(
            $result
        );

        $this->assertEquals(
            $from,
            $to,
            \sprintf(
                'Sequences "%s" and "%s" do not match',
                $from,
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



    public function testHostToPunycodeWithEmptyString(): void
    {
        $result = UrlHelper::hostToPunycode('');

        $this->assertEquals('', $result);
    }

    public function testHostToPunycodeWithAsciiHost(): void
    {
        $result = UrlHelper::hostToPunycode('example.com');

        $this->assertEquals('example.com', $result);
    }

    public function testHostToPunycodeWithMixedCaseAscii(): void
    {
        $result = UrlHelper::hostToPunycode('Example.COM');

        $this->assertEquals('example.com', $result);
    }

    public function testHostToPunycodeWithUnicodeHost(): void
    {
        $result = UrlHelper::hostToPunycode('münchen.de');

        $this->assertStringStartsWith('xn--', $result);
        $this->assertStringContainsString('.de', $result);
    }

    public function testHostToPunycodeWithMultipleUnicodeParts(): void
    {
        $result = UrlHelper::hostToPunycode('münchen.café.de');

        $parts = explode('.', $result);
        $this->assertStringStartsWith('xn--', $parts[0]);
        $this->assertStringStartsWith('xn--', $parts[1]);
        $this->assertEquals('de', $parts[2]);
    }

    public function testHostToPunycodeWithCyrillicCharacters(): void
    {
        $result = UrlHelper::hostToPunycode('пример.рф');

        $this->assertStringStartsWith('xn--', $result);
    }

    public function testHostToPunycodeWithChineseCharacters(): void
    {
        $result = UrlHelper::hostToPunycode('例え.jp');

        $this->assertStringStartsWith('xn--', $result);
    }

    public function testHostToPunycodePreservesSubdomains(): void
    {
        $result = UrlHelper::hostToPunycode('sub.münchen.de');

        $parts = explode('.', $result);
        $this->assertEquals('sub', $parts[0]);
        $this->assertStringStartsWith('xn--', $parts[1]);
        $this->assertEquals('de', $parts[2]);
    }



    public function testUrlToUTF8WithEmptyString(): void
    {
        $result = UrlHelper::urlToUTF8('');

        $this->assertEquals('', $result);
    }

    public function testUrlToUTF8WithNull(): void
    {
        $result = UrlHelper::urlToUTF8(null);

        $this->assertEquals('', $result);
    }

    public function testUrlToUTF8WithNonString(): void
    {
        $result = UrlHelper::urlToUTF8(123);

        $this->assertEquals('', $result);
    }

    public function testUrlToUTF8WithAsciiUrl(): void
    {
        $url    = 'https://example.com/path';
        $result = UrlHelper::urlToUTF8($url);

        $this->assertEquals($url, $result);
    }

    public function testUrlToUTF8WithPunycodeHost(): void
    {
        $result = UrlHelper::urlToUTF8('https://xn--mnchen-3ya.de/path');

        $this->assertStringContainsString('münchen.de', $result);
    }

    public function testUrlToUTF8WithPunycodeHostAndQuery(): void
    {
        $result = UrlHelper::urlToUTF8('https://xn--mnchen-3ya.de/path?key=value');

        $this->assertStringContainsString('münchen.de', $result);
        $this->assertStringContainsString('key=value', $result);
    }

    public function testUrlToUTF8WithPunycodeHostAndFragment(): void
    {
        $result = UrlHelper::urlToUTF8('https://xn--mnchen-3ya.de/path#section');

        $this->assertStringContainsString('münchen.de', $result);
        $this->assertStringContainsString('#section', $result);
    }

    public function testUrlToUTF8WithoutHost(): void
    {
        $url    = '/relative/path';
        $result = UrlHelper::urlToUTF8($url);

        $this->assertEquals($url, $result);
    }

    public function testUrlToUTF8ConvertsToLowercase(): void
    {
        $result = UrlHelper::urlToUTF8('https://EXAMPLE.COM/path');

        $this->assertStringContainsString('example.com', $result);
    }

    public function testUrlToUTF8WithMultiplePunycodeParts(): void
    {
        $result = UrlHelper::urlToUTF8('https://xn--mnchen-3ya.xn--caf-dma.de');

        $this->assertStringContainsString('münchen', $result);
        $this->assertStringContainsString('café', $result);
    }



    public function testUrlencodeFixPartsWithDefaultParts(): void
    {
        $uri = new Uri('https://example.com/path with spaces');

        $result = UrlHelper::urlencodeFixParts($uri);

        $this->assertTrue($result);
        $this->assertStringNotContainsString(' ', $uri->getPath());
    }

    public function testUrlencodeFixPartsWithEncodedPath(): void
    {
        $uri = new Uri('https://example.com/already%20encoded');

        $result = UrlHelper::urlencodeFixParts($uri);

        $this->assertFalse($result);
        $this->assertEquals('/already%20encoded', $uri->getPath());
    }

    public function testUrlencodeFixPartsWithPathContainingSpecialCharacters(): void
    {
        $uri = new Uri('https://example.com/path/with spaces/and{brackets}');

        UrlHelper::urlencodeFixParts($uri, ['path']);

        $path = $uri->getPath();
        $this->assertStringNotContainsString(' ', $path);
        $this->assertStringNotContainsString('{', $path);
        $this->assertStringNotContainsString('}', $path);
    }

    public function testUrlencodeFixPartsWithFragment(): void
    {
        $uri = new Uri('https://example.com/path#section with spaces');

        // Fragment fixes don't return true since version 24.44.6611
        UrlHelper::urlencodeFixParts($uri, ['fragment']);

        $fragment = $uri->getFragment();
        $this->assertStringNotContainsString(' ', $fragment);
    }

    public function testUrlencodeFixPartsWithQuery(): void
    {
        $uri = new Uri('https://example.com/path?key=value with spaces');

        $result = UrlHelper::urlencodeFixParts($uri, ['query']);

        $this->assertFalse($result);
        $query = $uri->getQuery();
        $this->assertStringContainsString(' ', $query, "result: $query");
    }

    public function testUrlencodeFixPartsWithQueryArray(): void
    {
        $uri = new Uri('https://example.com/path');
        $uri->setQuery(['key' => 'value with spaces']);

        $result = UrlHelper::urlencodeFixParts($uri, ['queryarray']);

        $this->assertfalse($result);
    }

    public function testUrlencodeFixPartsWithMultipleParts(): void
    {
        $uri = new Uri('https://example.com/path with spaces?key=value#section');

        $result = UrlHelper::urlencodeFixParts($uri, ['path', 'query', 'fragment']);

        $this->assertTrue($result);
        $this->assertStringNotContainsString(' ', $uri->getPath());
        $this->assertStringNotContainsString(' ', $uri->getQuery());
    }

    public function testUrlencodeFixPartsWithInvalidParts(): void
    {
        $uri = new Uri('https://example.com/path with spaces');

        // Should only process valid parts
        $result = UrlHelper::urlencodeFixParts($uri, ['path', 'invalid', 'fake']);

        $this->assertTrue($result);
    }

    public function testUrlencodeFixPartsWithEmptyPath(): void
    {
        $uri = new Uri('https://example.com');

        $result = UrlHelper::urlencodeFixParts($uri, ['path']);

        $this->assertFalse($result);
    }

    public function testUrlencodeFixPartsWithNullFragment(): void
    {
        $uri = new Uri('https://example.com/path');

        $result = UrlHelper::urlencodeFixParts($uri, ['fragment']);

        $this->assertFalse($result);
    }

    public function testUrlencodeFixPartsPreservesSafeCharacters(): void
    {
        $uri = new Uri('https://example.com/path-with_safe.chars~123');

        $result = UrlHelper::urlencodeFixParts($uri, ['path']);

        $this->assertFalse($result);
        $this->assertEquals('/path-with_safe.chars~123', $uri->getPath());
    }

    public function testUrlencodeFixPartsWithUnicodeCharacters(): void
    {
        $uri = new Uri('https://example.com/path/café');

        $result = UrlHelper::urlencodeFixParts($uri, ['path']);

        $this->assertTrue($result);
        $path = $uri->getPath();
        $this->assertStringNotContainsString('é', $path);
    }

    public function testUrlencodeFixPartsWithComplexQuery(): void
    {
        $uri = new Uri('https://example.com/path');
        $uri->setQuery([
            'key1' => 'value with spaces',
            'key2' => 'special&chars',
            'key3' => 'already%20encoded',
        ]);

        $result = UrlHelper::urlencodeFixParts($uri, ['queryarray']);

        $this->assertfalse($result);
    }



    public function testRoundTripPunycodeConversion(): void
    {
        $original = 'münchen.de';
        $punycode = UrlHelper::hostToPunycode($original);
        $url      = 'https://' . $punycode . '/path';
        $result   = UrlHelper::urlToUTF8($url);

        $this->assertStringContainsString($original, $result);
    }

    public function testCompleteUrlProcessing(): void
    {
        $uri = new Uri('https://münchen.de/path with spaces?key=value#section');

        // Convert host to punycode
        $host         = $uri->getHost();
        $punycodeHost = UrlHelper::hostToPunycode($host);
        $uri->setHost($punycodeHost);

        // Fix encoding
        UrlHelper::urlencodeFixParts($uri, ['path', 'query', 'fragment']);

        $result = $uri->toString();

        $this->assertStringContainsString('xn--', $result);
        $this->assertStringNotContainsString(' ', $result);
    }

    public function testUrlWithPortAndUserInfo(): void
    {
        $result = UrlHelper::urlToUTF8('https://user:pass@xn--mnchen-3ya.de:8080/path');

        $this->assertStringContainsString('münchen.de', $result);
        $this->assertStringContainsString('user:pass', $result);
        $this->assertStringContainsString(':8080', $result);
    }



    public function testHostToPunycodeWithSingleCharacter(): void
    {
        $result = UrlHelper::hostToPunycode('a');

        $this->assertEquals('a', $result);
    }

    public function testHostToPunycodeWithNumbers(): void
    {
        $result = UrlHelper::hostToPunycode('123.456.789');

        $this->assertEquals('123.456.789', $result);
    }

    public function testHostToPunycodeWithHyphens(): void
    {
        $result = UrlHelper::hostToPunycode('my-domain.com');

        $this->assertEquals('my-domain.com', $result);
    }

    public function testUrlencodeFixPartsDoesNotDoubleEncode(): void
    {
        $uri = new Uri('https://example.com/path%20already%20encoded');

        $result = UrlHelper::urlencodeFixParts($uri, ['path']);

        // Should not change already encoded path
        $this->assertFalse($result);
        $this->assertEquals('/path%20already%20encoded', $uri->getPath());
    }

    public function testUrlencodeFixPartsWithEmptyQuery(): void
    {
        $uri = new Uri('https://example.com/path');

        $result = UrlHelper::urlencodeFixParts($uri, ['query']);

        $this->assertFalse($result);
    }

    public function testUrlToUTF8WithAlreadyUTF8Host(): void
    {
        $url    = 'https://example.com/path';
        $result = UrlHelper::urlToUTF8($url);

        $this->assertEquals($url, $result);
    }

    #[Attributes\DataProvider('specialCharacterProvider')]
    public function testUrlencodeFixPartsWithVariousSpecialCharacters(
        string $input,
        bool $shouldChange
    ): void {
        $uri = new Uri('https://example.com' . $input);

        $result = UrlHelper::urlencodeFixParts($uri, ['path']);

        $this->assertEquals($shouldChange, $result);
    }

    public static function specialCharacterProvider(): array
    {
        return [
            'safe characters'         => ['/path/safe-chars_123.txt', false],
            'space'                   => ['/path with space', true],
            'unicode'                 => ['/path/café', true],
            'brackets'                => ['/path/{id}', true],
            'percent already encoded' => ['/path%20encoded', false],
            'question mark'           => ['/path?', false],
            'hash'                    => ['/path#', false],
            'ampersand'               => ['/path&param', false],
            'equals'                  => ['/path=value', false],
        ];
    }

    #[Attributes\DataProvider('unicodeHostProvider')]
    public function testHostToPunycodeWithVariousUnicodeHosts(
        string $host,
        string $expectedPrefix
    ): void {
        $result = UrlHelper::hostToPunycode($host);

        $this->assertStringStartsWith($expectedPrefix, $result);
    }

    public static function unicodeHostProvider(): array
    {
        return [
            'german'      => ['münchen.de', 'xn--'],
            'french'      => ['café.fr', 'xn--'],
            'russian'     => ['пример.рф', 'xn--'],
            'arabic'      => ['مثال.com', 'xn--'],
            'mixed ascii' => ['example.com', 'example'],
        ];
    }

    public function testConstantValues(): void
    {
        $reflection = new \ReflectionClass(UrlHelper::class);
        $constants  = $reflection->getConstants();

        $this->assertArrayHasKey('PUNYCODE_PREFIX', $constants);
        $this->assertEquals('xn--', $constants['PUNYCODE_PREFIX']);
    }

    public function testUrlencodeFixPartsReturnsFalseWhenNoChanges(): void
    {
        $uri = new Uri('https://example.com/safe-path');

        $result = UrlHelper::urlencodeFixParts($uri, ['path', 'fragment', 'query']);

        $this->assertFalse($result);
    }

    public function testUrlencodeFixPartsWithOnlyFragment(): void
    {
        $uri = new Uri('https://example.com/path#section with spaces');

        // Fragment changes don't affect return value
        $result = UrlHelper::urlencodeFixParts($uri, ['fragment']);

        // Fragment is fixed but doesn't return true
        $this->assertFalse($result);
        $this->assertStringNotContainsString(' ', $uri->getFragment());
    }
}
