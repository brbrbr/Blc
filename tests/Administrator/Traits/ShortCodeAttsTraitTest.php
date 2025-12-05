<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Traits;

use Blc\Component\Blc\Administrator\Traits\ShortCodeAttsTrait;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Traits/ShortCodeAttsTrait

 *
 * @since       25.44.7398
 */


class ShortCodeAttsTraitTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function stringProvider(): array
    {


        $utf8_nbsp = html_entity_decode('&nbsp;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $utf8_zero = html_entity_decode('&ZeroWidthSpace;', ENT_QUOTES | ENT_HTML5, 'UTF-8');


        return [
            ['one', ['one']], //8


            ["one{$utf8_nbsp}two", ['one', 'two']],
            ["one{$utf8_zero}two", ['one', 'two']],
            ["\"one{$utf8_zero}two\"", ['one two']], //7
            ["'one two'", ['one two']], //8


            ["one-x=1{$utf8_nbsp}two", ['one-x' => '1', 'two']],
            ["one-x='1{$utf8_nbsp}two'", ['one-x' => '1 two']],
            ['one-x=1', ['one-x' => '1']], //1
            ['one_x=2', ['one_x' => '2']],
            ['"one"="2"', ['"one"="2"']],
            ['one="1" two="2" three', ['one' => '1', 'two' => '2', 'three']], //2 9
            ['one= "1" two= \'2\' three', ['one' => '1', 'two' => '2', 'three']], //2 3 9
            ['one="\'1\'" two=\'"2"\' three', ['one' => "'1'", 'two' => '"2"', 'three']],
            ['one=1 two=2 three four', ['one' => '1', 'two' => '2', 'three', 'four']], //5 9
            ["one='1' two='2' three", ['one' => '1', 'two' => '2', 'three']],
            ["one='1' two=\"2\" three", ['one' => '1', 'two' => '2', 'three']],
            ["one='1\" two='2' three", ["one='1\"", 'two' => '2', 'three']],
            ["one='<p 1'", ['one' => '']],
            ["", ['param' => '']],

        ];
    }
    #[Attributes\DataProvider('stringProvider')]
    public function testPatterns($string, $expected)
    {

        $trait  = $this->bootTrait();
        $result = $trait->shortcodeParseAtts($string);
        $this->assertSame($expected, $result);
    }

    protected function bootTrait()
    {
        $trait = new class () {
            use ShortCodeAttsTrait {
                ShortCodeAttsTrait::shortcodeParseAtts as private traitshortcodeParseAtts;
            }

            public function shortcodeParseAtts(string $text): array
            {
                return $this->traitshortcodeParseAtts($text);
            }
        };

        return $trait;
    }
}
