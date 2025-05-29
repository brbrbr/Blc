<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Parser;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
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
#[Attributes\CoversClass(Parser\HrefParser::class)]
#[Attributes\TestDox('Test A (href) Parser')]
class HrefParserTest extends UnitTestCase
{
    protected string $fieldContext       = 'com_content.article';
    protected string $emptyReturnString  = HTTPCODES::BLC_EMPTY_LINK_CODE;
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
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
        $text   = '<a href=\'' . $src . '\' >' . $anchor . '</a>';
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
        $oldText   = '<a href=\'' . $oldUrl . '\'>' . $anchor . '</a>';
        $newText   = '<a href=\'' . $newUrl . '\'>' . $anchor . '</a>';
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
        $this->assertSame($this->emptyReturnString, $links[0]['url']);
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
        $this->assertSame($this->emptyReturnString, $links[0]['url']);
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
        $this->assertSame($this->emptyReturnString, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);
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
}
