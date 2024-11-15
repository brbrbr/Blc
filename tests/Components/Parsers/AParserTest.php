<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin\Parsers;

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

#[Attributes\TestDox('Test A (href) Parser')]
class AParserTest extends UnitTestCase
{
    protected $wrappedClass;
    protected string $fieldContext = 'com_content.article';
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
        $this->assertTestTag($text);
    }


    public function testCanReplaceADoubleQuote()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href="' . $oldUrl . '" >' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '" >' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }

    public function testCanReplaceAToTarget()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = '#named-id';
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href="' . $oldUrl . '" >' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '" >' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }
    //accademic - component does not allow empty links as new link
    public function testCanReplaceAToEmpty()
    {
        $oldUrl    = 'https://phpunit.invalid/a-old';
        $newUrl    = '';
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href="' . $oldUrl . '" >' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '" >' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
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
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href=\'' . $oldUrl . '\'>' . $anchor . '</a>';
        $newText   = '<a href=\'' . $newUrl . '\'>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
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
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href=' . $oldUrl . '>' . $anchor . '</a>';
        $newText   = '<a href=' . $newUrl . '>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($text, $newText);
    }

    public function testCanABlankHref()
    {
        $src    = 'Empty attribute href on a';
        $anchor = 'phpunit.anchor';
        $text   = '<a href="">' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, 'Empty attribute href on a');
        $this->assertSame($anchor, $links[0]['anchor']);
    }

    public function testCanReplaceABlankHref()
    {
        $oldUrl    = '';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href="">' . $anchor . '</a>';
        $newText   = '<a href="' . $newUrl . '">' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);

        $this->assertSame($newText, $text);
    }

    public function testCanAEmptyHref()
    {
        $src    = 'Empty attribute href on a';
        $anchor = 'phpunit.anchor';
        $text   = '<a href>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, 'Empty attribute href on a');
        $this->assertSame($anchor, $links[0]['anchor']);
    }


    public function testCanNotReplaceAEmptyHref()
    {
        $oldUrl    = '';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor = 'phpunit.anchor';
        $oldText   = '<a href>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($oldText, $text);
    }

    public function testCanANoHREF()
    {
        $src    = 'Empty attribute href on a';
        $anchor = 'phpunit.anchor';
        $text   = '<a>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, 'Empty attribute href on a');
        $this->assertSame($anchor, $links[0]['anchor']);
    }

    public function testCanNotReplaceANoHref()
    {
        $oldUrl    = '';
        $newUrl    = 'https://phpunit.invalid/a-new';
        $anchor = 'phpunit.anchor';
        $oldText   = '<a>' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $text  = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($oldText, $text);
    }



}
