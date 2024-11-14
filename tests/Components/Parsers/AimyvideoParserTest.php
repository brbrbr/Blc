<?php

/**
 * @version   __DEPLOY_VERSION__
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

#[Attributes\TestDox('Test Embed Parser')]
class AimyvideoParserTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testCanFindYoutubeId()
    {
        //this test does not care about the validitie of te links.
        $token  = uniqid();
        $src    = 'https://www.youtube.com/watch?v=' . $token;
        $text   = '<p>Extra</p>{YouTube}' . $token . '{/YouTube}<p>Extra</p>';
        $parser =  Parser\AimyvideoParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $src;
    }

    #[Attributes\Depends('testCanFindContentYoutube')]
    public function testCanReplaceYoutubeViaPlugin($oldUrl)
    {
        $newToken = uniqid();
        //Aimy video parser always return full www.youtube.com links
        $newUrl = 'https://www.youtube.com/watch?v=' . $newToken;
        $this->assertLinkReplace($oldUrl, $newUrl);
    }

    public function testCanFindYoutubeLink()
    {
        //this test does not care about the validitie of te links.
        $token  = uniqid();
        $src    = 'https://www.youtube.com/watch?v=' . $token;
        $text   = '<p>Extra</p>{YouTube}' . $src . '{/YouTube}<p>Extra</p>';
        $parser =  Parser\AimyvideoParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $text;
    }

    #[Attributes\Depends('testCanFindYoutubeLink')]
    public function testCanFindContentYoutube($text)
    {
        $oldToken = uniqid();
        $oldUrl   = 'https://www.youtube.com/watch?v=' . $oldToken;
        $oldText  = '<p>Extra</p>{YouTube}' . $oldToken . '{/YouTube}<p>Extra</p>';
        $this->assertTestTag($text . $oldText);
        return $oldUrl;
    }

    public function testCanReplaceYoutube()
    {
        $oldToken   = uniqid();
        $newToken   = uniqid();
        $oldUrl     = 'https://www.youtube.com/watch?v=' . $oldToken;
        $newUrl     = 'https://youtube.be/watch?v=' . $newToken;
        $oldText    = '<p>Extra</p>{YouTube}' . $oldToken . '{/YouTube}<p>Extra</p>';
        $wantedText = '<p>Extra</p>{YouTube}' . $newToken . '{/YouTube}<p>Extra</p>';
        $parser     =  Parser\AimyvideoParser::getInstance();
        $newText    = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($wantedText, $newText);
    }

    public function testCanFindVimeoId()
    {
        $token  = uniqid();
        $src    = 'https://vimeo.com/' . $token;
        $text   = '<p>Video</p>{Vimeo}' . $token . '{/Vimeo}<p>Extra</p>';
        $parser =  Parser\AimyvideoParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $text;
    }

    public function testCanFindVimeoLink()
    {
        $token  = uniqid();
        $src    = 'https://vimeo.com/' . $token;
        $text   = '<p>Video</p>{Vimeo}' . $src . '{/Vimeo}<p>Extra</p>';
        $parser =  Parser\AimyvideoParser::getInstance();
        $links  = $parser->extractfromSource($text);
        $this->assertSame($src, $links[0]['url']);
        return $text;
    }

    #[Attributes\Depends('testCanFindVimeoLink')]
    public function testCanContentVimeo($text)
    {

        $oldToken = uniqid();
        $oldUrl   = 'https://vimeo.com/' . $oldToken;
        $oldText  = '<p>Extra</p>{Vimeo}' . $oldToken . '{/Vimeo}<p>Extra</p>';
        $this->assertTestTag($text . $oldText);
        return $oldUrl;
    }

    #[Attributes\Depends('testCanContentVimeo')]
    public function testCanReplaceVimeoViaPlugin($oldUrl)
    {
        $newToken = uniqid();
        //Aimy video parser always returns the vimeo.com links
        $newUrl = 'https://vimeo.com/' . $newToken;
        $this->assertLinkReplace($oldUrl, $newUrl);
    }

    public function testCanReplaceVimeo()
    {
        $oldToken   = uniqid();
        $newToken   = uniqid();
        $oldUrl     = 'https://vimeo.com/' . $oldToken;
        $newUrl     = 'https://www.vimeo.com/' . $newToken;
        $oldText    = '<p>Extra</p>{Vimeo}' . $oldToken . '{/Vimeo}<p>Extra</p>';
        $wantedText = '<p>Extra</p>{Vimeo}' . $newToken . '{/Vimeo}<p>Extra</p>';
        $parser     =  Parser\AimyvideoParser::getInstance();
        $newText    = $parser->replaceInSource($oldText, $oldUrl, $newUrl);
        $this->assertSame($newText, $wantedText);
    }
}
