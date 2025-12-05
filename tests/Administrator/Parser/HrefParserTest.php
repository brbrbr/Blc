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
#[Attributes\CoversClass(Parser\HrefParser::class)]
class HrefParserTest extends UnitTestCase
{
    protected string $fieldContext       = 'com_content.article';
    protected string $emptyReturnString  = PARSE_STRINGS::BLC_EMPTY_ANCHOR;
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testCanNotBoot()
    {
        $parser           =  Parser\HrefParser::getInstance();
        $result           = $parser->getcanSetAlt();
        $this->assertFalse($result);
    }

    public function testInit()
    {
        $parser           =  Parser\HrefParser::getInstance();
        $name             = $parser->getName();
        $this->assertSame('href', $name);
    }


    public function testCanADoubleQuote()
    {
        $src    = 'https://phpunit.invalid/a-link';
        $anchor = 'phpunit.anchor';
        $text   = '<a href="' . $src . '" >' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
    }


    public function testCanReplaceADoubleQuote()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a href="' . $oldUrl . '" >' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '" >' . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }

    public function testCanReplaceAToTarget()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = '#named-id';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a href="' . $oldUrl . '" >' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '" >' . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }
    //accademic - component does not allow empty links as new link
    public function testCanReplaceAToEmpty()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = '';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a href="' . $oldUrl . '" >' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '" >' . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }

    public function testCanASingleQuote()
    {
        $src    = 'https://phpunit.invalid/a-link';
        $anchor = 'phpunit.anchor';
        $text   = "<a href='" . $src . "' >" . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
    }

    public function testCanReplaceASingleQuote()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor    = 'phpunit.anchor';
        $oldText   = "<a href='" . $oldUrl . "'>" . $anchor . '</a>';
        $newText   = "<a href='" . $newUrl . "'>" . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }

    public function testCanANoQuote()
    {
        $src    = 'https://phpunit.invalid/a-link';
        $anchor = 'phpunit.anchor';
        $text   = '<a href=' . $src . '>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
    }

    public function testCanReplaceANoQuote()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a href=' . $oldUrl . '>' . $anchor . '</a>';
        $newText   = '<a href=' . $newUrl . '>' . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }

    public function testCanABlankHref()
    {

        $anchor = 'phpunit.anchor';
        $text   = '<a href="">' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
    }

    public function testCanNotReplaceABlankHref()
    {
        $oldUrl    = '';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a href="">' . $anchor . '</a>';
        $newText   = $oldText;
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);

        $this->assertSame($newText, $text);
    }

    public function testCanAEmptyHref()
    {

        $anchor = 'phpunit.anchor';
        $text   = '<a href>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
    }


    public function testCanNotReplaceAEmptyHref()
    {
        $oldUrl    = '';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a href>' . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($oldText, $text);
    }



    public function testCanANoHREF()
    {
        $anchor = 'phpunit.anchor';
        $text   = '<a>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
    }

    public function testCanNoAnchor()
    {

        $text   = '<a href="bla.html"></a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame("bla.html", $links[0]['url']);
        $this->assertSame($this->emptyReturnString, $links[0]['anchor']);
    }


    public function testCanANoHREFnoAnchor()
    {

        $text   = '<a></a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links[0]['url']);
        $this->assertSame($this->emptyReturnString, $links[0]['anchor']);
    }
    public function testCanNotReplaceANoHref()
    {
        $oldUrl    = '';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor    = 'phpunit.anchor';
        $oldText   = '<a>' . $anchor . '</a>';
        $parser    =  Parser\HrefParser::getInstance();
        $text      = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($oldText, $text);
    }

    public function testIgnoreComment()
    {

        $text   = '<!-- <a class="uk-text-success" href="images/yootheme/pricing-check.svg">anchor</a> -->';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertEmpty($links);

        $text   = '<!-- <img class=\" uk-text-success\" href=\"images\/yootheme\/pricing-check.svg\" uk-svg>anchor</a><\/td>--><a class="uk-text-success" href="images/yootheme/pricing-check.svg">anchor</a>';
        $links  = $parser->extractfromSource($text);
        $this->assertNotEmpty($links);

        $text   = '<a class="uk-text-success" href="images/yootheme/pricing-check.svg">anchor</a><!-- <img class=\" uk-text-success\" href=\"images\/yootheme\/pricing-check.svg\" uk-svg>anchor</a><\/td>-->';
        $links  = $parser->extractfromSource($text);
        $this->assertNotEmpty($links);
    }

    public function getCanNotSetAlt()
    {
        $parser         =  Parser\HrefParser::getInstance();
        $canSetAlt      = $parser->getCanSetAlt();
        $this->assertFalse($canSetAlt, 'HrefParser should be able to replace alt attributes');
    }

    public function testDoesRegisterWithController()
    {
        $parsers = BlcParseController::getInstance();
        $parser  = $parsers->getParser('href');
        $this->assertInstanceOf(Parser\HrefParser::class, $parser, 'HrefParser should be registered with the controller');
    }
}
