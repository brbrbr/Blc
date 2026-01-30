<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Parser;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
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
#[Attributes\CoversClass(Parser\BlcParser::class)]
#[Attributes\CoversClass(Parser\ImgParser::class)]
class ImgParserTest extends UnitTestCase
{
    protected string $fieldContext      = 'com_content.article';
    protected string $emptyReturnString = PARSE_STRINGS::BLC_EMPTY_ALT;



    public function testCanNotBoot()
    {
        $parser           =  Parser\ImgParser::getInstance();
        $result           = $parser->getcanSetAlt();
        $this->assertTrue($result);
    }

    public function testInit()
    {
        $parser           =  Parser\ImgParser::getInstance();
        $name             = $parser->getName();
        $this->assertSame('img', $name);
    }

    #[Attributes\DataProvider('srcProvider')]
    public function testCanImgSrc($oldTemplate, $expectedAnchorTemplate)
    {
        $oldSrc    = 'https://phpunit.invalid/old.jpg';
        $newSrc    = 'https://phpunit.invalid/new.jpg';
        $oldAnchor = 'phpunit anchor old';

        $oldText          = \sprintf($oldTemplate, $oldSrc, $oldAnchor);
        $expectedAnchor   = \sprintf($expectedAnchorTemplate, $oldAnchor, $this->emptyReturnString);
        $parser           =  Parser\ImgParser::getInstance();

        $links = $parser->extractfromSource($oldText);


        $this->assertSame($oldSrc, $links[0]['url']);
        $this->assertSame($expectedAnchor, $links[0]['anchor']);

        $newText = $parser->replaceInSource($oldText, $oldSrc, $newSrc);
        $links   = $parser->extractfromSource($newText);
        $this->assertSame($newSrc, $links[0]['url']);
    }



    public static function srcProvider(): array
    {


        $set = [
            ['<img data-src data-id="34" src="%1$s" alt="%2$s"/>', '%1$s'], //full
            ['<img src="%1$s" alt="%2$s">', '%1$s'],
            ['<img src="%1$s" alt = "%2$s">', '%1$s'],
            ['<img src="%1$s"alt="%2$s">', '%1$s'],
            ['<img src="%1$s" alt=""/>', '%2$s'],
            ['<img src="%1$s" alt/>', '%2$s'],
            ['<img alt src="%1$s"/>', '%2$s'],
            ['<img src="%1$s"/>', '%2$s'],
            ['<img src="%1$s" alt="">', '%2$s'],
            ['<img src="%1$s" alt>', '%2$s'],
            ['<img alt="%2$s" src="%1$s" alt="%2$s"/>', '%1$s'],


        ];
        foreach ($set as $item) {
            $item[0] = str_replace('"', "'", $item[0]);
            $set[]   = $item;
        }
        return $set;
    }

    public static function altProvider(): array
    {


        $set = [
            ['<img data-src data-id="34" src="%1$s" alt="%2$s"/>', '<img alt="%2$s" data-src data-id="34" src="%1$s"/>'], //full
            ['<img src="%1$s" alt="%2$s">', '<img alt="%2$s" src="%1$s">'], //not closed

            ['<img src="%1$s" alt = "%2$s">', '<img alt="%2$s" src="%1$s">'], //spaces
            ['<img src="%1$s"alt="%2$s">', '<img alt="%2$s" src="%1$s">'], //malformed
            ['<img src="%1$s" alt="alt with /">', '<img alt="%2$s" src="%1$s">'], //not closed
            ['<img src="%1$s" notalt="alt with /">', '<img alt="%2$s" src="%1$s" notalt="alt with /">'], //not alt tag

            ['<img src="%1$s" notalt=" alt with /">', '<img alt="%2$s" src="%1$s" notalt=" alt with /">'], //with attribute in other tag
            ['<img src="%1$s" alt=""/>', '<img alt="%2$s" src="%1$s"/>'], //empty alt
            ['<img src="%1$s" alt/>', '<img alt="%2$s" src="%1$s"/>'], //no alt value
            ['<img alt src="%1$s"/>', '<img alt="%2$s" src="%1$s"/>'], //no alt value
            ['<img src="%1$s"/>', '<img alt="%2$s" src="%1$s"/>'], //no alt
            ['<img src="%1$s" alt="">', '<img alt="%2$s" src="%1$s">'], //empty alt not closed
            ['<img src="%1$s" alt>', '<img alt="%2$s" src="%1$s">'], //no alt value not closed
            ['<img alt="%2$s" src="%1$s" alt="%2$s"/>', '<img alt="%2$s" src="%1$s"/>'], //double alt


            ['<img data-src data-id="34" src="%1$s" alt="%2$s"/>', '<img alt="%2$s" data-src data-id="34" src="%1$s"/>'], //full
            ['<img src="%1$s" alt=\'%2$s\'>', '<img alt="%2$s" src="%1$s">'], //not closed

            ['<img src="%1$s" alt = \'%2$s\'>', '<img alt="%2$s" src="%1$s">'], //spaces
            ['<img src="%1$s"alt=\'%2$s\'>', '<img alt="%2$s" src="%1$s">'], //malformed
            ['<img src="%1$s" alt=\'alt with /\'>', '<img alt="%2$s" src="%1$s">'], //not closed
            ['<img src="%1$s" notalt=\'alt with /\'>', '<img alt="%2$s" src="%1$s" notalt=\'alt with /\'>'], //not alt tag

            ['<img src="%1$s" notalt=\' alt with /\'>', '<img alt="%2$s" src="%1$s" notalt=\' alt with /\'>'], //with attribute in other tag
            ['<img src="%1$s" alt=\'\'/>', '<img alt="%2$s" src="%1$s"/>'], //empty alt


            ['<img src="%1$s" alt=\'\'>', '<img alt="%2$s" src="%1$s">'], //empty alt not closed

            ['<img alt=\'%2$s\' src="%1$s" alt=\'%2$s\'/>', '<img alt="%2$s" src="%1$s"/>'], //double alt


        ];


        return $set;
    }

    public function getCanSetAlt()
    {
        $parser         =  Parser\ImgParser::getInstance();
        $canSetAlt      = $parser->getCanSetAlt();
        $this->assertTrue($canSetAlt, 'ImgParser should be able to replace alt attributes');
    }

    public function testDoesRegisterWithController()
    {
        $parsers = BlcParseController::getInstance();
        $parser  = $parsers->getParser('img');
        $this->assertInstanceOf(Parser\ImgParser::class, $parser, 'ImgParser should be registered with the controller');
    }

    #[Attributes\DataProvider('altProvider')]
    public function testCanAltImg($oldTemplate, $expectedTemplate)
    {

        $src            = $this->getRandomLink();
        $oldAnchor      =  $this->getRandomAlt();
        $newAnchor      =  $this->getRandomAlt();
        $oldText        = \sprintf($oldTemplate, $src, $oldAnchor);
        $expectedText   = \sprintf($expectedTemplate, $src, $newAnchor);
        $parser         =  Parser\ImgParser::getInstance();

        $newText = $parser->setAltInSource($oldText, $src, $newAnchor);
        $this->assertSame($expectedText, $newText);
        $links = $parser->extractfromSource($newText);

        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($newAnchor, $links[0]['anchor']);
    }

    public function testCanNotAltImgNoSrcUrl()
    {
        [$oldTemplate] = self::altProvider()[0];

        $src            = $this->getRandomLink();
        $oldAnchor      =  $this->getRandomAlt();
        $newAnchor      =  $this->getRandomAlt();
        $oldText        = \sprintf($oldTemplate, $src, $oldAnchor);

        $parser         =  Parser\ImgParser::getInstance();

        $newText = $parser->setAltInSource($oldText, '', $newAnchor);
        $this->assertSame($oldText, $newText);
        $links = $parser->extractfromSource($newText);

        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($oldAnchor, $links[0]['anchor']);
    }

    public function testIgnoreComment()
    {

        $text   = '<!-- <img class="uk-text-success" src="images/yootheme/pricing-check.svg" uk-svg/>-->';
        $parser =  Parser\ImgParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links);
        $text = '<!-- <img class="uk-text-success" src="images/yootheme/pricing-check.svg" uk-svg></td>--><img class="uk-text-success" src="images/yootheme/pricing-check.svg">';

        $links  = $parser->extractfromSource($text);
        $this->assertNotEmpty($links);
    }
    /**
     * assert  no tags ( coverage)
     */
    public function testNoTags()
    {

        $text   = '<a class="uk-text-success" href="images/yootheme/pricing-check.svg" uk-svg>Hello</a>-->';
        $parser =  Parser\ImgParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links);
    }
}
