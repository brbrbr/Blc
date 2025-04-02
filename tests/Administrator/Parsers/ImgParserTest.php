<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Parsers;

use Blc\Component\Blc\Administrator\Parser;
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

#[Attributes\CoversClass(Parser\ImgParser::class)]
class ImgParserTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';

    public function setUp(): void
    {
        $this->initApplication();
    }



    public function testCanImg()
    {
        $src    = 'https://phpunit.invalid/imgage.jpg';
        $anchor = 'phpunit.anchor';
        $text   = '<img src="' . $src . '" alt="' . $anchor . '"/>';
        $parser =  Parser\ImgParser::getInstance();

        $links = $parser->extractfromSource($text);

        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);

    }

    public function testIgnoreComment()
    {

        $text   = '<!-- <img class=\" uk-text-success\" src=\"images\/yootheme\/pricing-check.svg\" uk-svg><\/td>-->';
        $parser =  Parser\ImgParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links);
        $text = '<!-- <img class=\" uk-text-success\" src=\"images\/yootheme\/pricing-check.svg\" uk-svg><\/td>--><img class="uk-text-success" src="images/yootheme/pricing-check.svg">';

        $links  = $parser->extractfromSource($text);
        $this->assertNotEmpty($links);
    }
}
