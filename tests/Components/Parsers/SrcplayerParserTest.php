<?php

/**
 * @version   __DEPLOY_VERSION__
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Tests\UnitTestCase;
use Blc\Component\Blc\Administrator\Parser;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

#[Attributes\TestDox('Test Embed Parser')]
class SrcplayerParserTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testCanFindYoutubeSrcPlayer()
    {
        //this test does not care about the validitie of te links. 
        $src = 'https://phpunit.invalid/?v=phpunit.text';
        $text = '<p>Extra</p>{youtube src='.$src.'}<p>Extra</p>';
        $parser =  Parser\SrcplayerParser::getInstance();
        $links = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $text;
    }

    #[Attributes\Depends('testCanFindYoutubeSrcPlayer')]
    public function testCanFindContentYoutubeSrcPlayer($text)
    {
        $this->assertTestTag($text);
    }

    public function testCanFindVimeoSrcPlayer()
    {
        $src = 'https://phpunit.invalid/?v=phpunit.text';
        $text = '<p>Extra</p>{vimeo src='.$src.'}<p>Extra</p>';
        $parser =  Parser\SrcplayerParser::getInstance();
        $links = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $text;
    }

    #[Attributes\Depends('testCanFindVimeoSrcPlayer')]
    public function testCanFindContentVimeoSrcPlayer($text)
    {
        $this->assertTestTag($text);
    }

    public function testCanFindAvsSrcPlayer()
    {
        $src = 'https://phpunit.invalid/?v=phpunit.text';
        $text = '<p>Extra</p>{avsplayer src='.$src.'}<p>Extra</p>';
        $parser =  Parser\SrcplayerParser::getInstance();
        $links = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $text;
    }

    #[Attributes\Depends('testCanFindAvsSrcPlayer')]
    public function testCanFindContentAvsSrcPlayer($text)
    {
        $this->assertTestTag($text);
    }
  
}
