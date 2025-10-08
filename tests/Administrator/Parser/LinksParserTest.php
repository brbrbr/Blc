<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Parser;

use Blc\Component\Blc\Administrator\Parser\LinksParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/LinksParser

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(LinksParser::class)]
class LinksParserTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }
    static public function linkProvider()
    {
        return [
            ['https://example.com', 'https://example.com'],
            [' https://example.com ', 'https://example.com'],
            ['example.com', '']


        ];
    }
    #[Attributes\DataProvider('linkProvider')]
    public function testextractfromSource($url, $same)
    {
        $parser = LinksParser::getInstance();

        $out = $parser->extractfromSource($url);

        $this->assertSame($same, $out[0] ?? '');
    }

    public function testreplaceInSource()
    {
        $parser = LinksParser::getInstance();
        $old = 'https://example.com/old-url';
        $new = 'https://example.com/new-url';
        $source = $old;
        $out = $parser->replaceInSource(source: $source, oldUrl: $old, newUrl: $new);
        $source .= 'not-same';
        $out = $parser->replaceInSource(source: $source, oldUrl: $old, newUrl: $new);
        $this->assertSame($out, $source);
    }
}
