<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Traits;

use Blc\Component\Blc\Administrator\Traits\BlcSplitOptionTrait;
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

class BlcSplitOptionTraitTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    protected function bootTrait()
    {
        $trait = new class () {
            use BlcSplitOptionTrait {
                BlcSplitOptionTrait::splitOption as private traitSplitOption;
            }

            public function splitOption($optionsString) //no types here
            {
                return $this->traitSplitOption($optionsString);
            }
        };

        return $trait;
    }





    /**
     *
     *
     * @since __DEPLOY_VERSION__
     *
     */
    public static function seperatorProvider(): array
    {
        return   [
            [";"],
            [","],
            ["\r\n"],
            ["\r"],
            ["\n"],
        ];
    }
    /**
     *
     *
     * @since __DEPLOY_VERSION__
     *
     */
    #[Attributes\DataProvider('seperatorProvider')]
    public function testSplitOption($sep)
    {

        $input  = ['a', 'b', 'c'];
        $string = join($sep, $input);
        $trait  = $this->bootTrait();
        $result = $trait->splitOption($string);
        $this->assertSame($input, $result);
    }

    /**
     *
     *
     * @since __DEPLOY_VERSION__
     *
     */
    #[Attributes\DataProvider('seperatorProvider')]
    public function testSplitOptionMixed($sep)
    {

        $input    = ['a;d', 'b,e', "c\nf"];
        $expected = ['a', 'd', 'b', 'e', 'c', 'f'];
        $string   = join($sep, $input);

        $trait  = $this->bootTrait();
        $result = $trait->splitOption($string);
        $this->assertSame($expected, $result);
    }

    public function testSplitOptionNull()
    {


        $this->expectException(\TypeError::class);
        $string = null;
        $trait  = $this->bootTrait();
        $trait->splitOption($string);
    }

    public function testSplitOptionFalse()
    {

        $this->expectException(\TypeError::class);
        $string = false;
        $trait  = $this->bootTrait();
        $trait->splitOption($string);
    }

    public function testSplitOptionInt()
    {
        $this->expectException(\TypeError::class);
        $string = 0;
        $trait  = $this->bootTrait();
        $trait->splitOption($string);
    }
}
