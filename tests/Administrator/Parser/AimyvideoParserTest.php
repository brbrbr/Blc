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



#[Attributes\CoversClass(Parser\AimyvideoParser::class)]
class AimyvideoParserTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function videoLinks()
    {

        $token  = uniqid();
        return [
            ["<p>Upper Token</p>{YouTube}$token{/YouTube}<p>Extra</p>","https://www.youtube.com/watch?v=$token"],
            ["<p>Upper Token</p>{YouTube}$token|600|450|1{/YouTube}<p>Extra</p>","https://www.youtube.com/watch?v=$token"], //allvideo
            ["<p>Upper Url</p>{YouTube}https://www.youtube.com/watch?v=$token{/YouTube}<p>Extra</p>","https://www.youtube.com/watch?v=$token"],
            ["<p>Lower Token</p>{youTube}$token{/youTube}<p>Extra</p>","https://www.youtube.com/watch?v=$token"],
            ["<p>Lower Url</p>{youTube}https://www.youtube.com/watch?v=$token{/youTube}<p>Extra</p>","https://www.youtube.com/watch?v=$token"],
            ["<p>Upper Token</p>{Vimeo}$token{/Vimeo}<p>Extra</p>","https://vimeo.com/$token"],
            ["<p>Upper Url</p>{Vimeo}https://vimeo.com/$token{/Vimeo}<p>Extra</p>","https://vimeo.com/$token"],
            ["<p>Lower Token</p>{vimeo}$token{/vimeo}<p>Extra</p>","https://vimeo.com/$token"],
            ["<p>Lower Url</p>{vimeo}https://vimeo.com/$token{/vimeo}<p>Extra</p>","https://vimeo.com/$token"],
        ];
    }

    #[Attributes\DataProvider('videoLinks')]
    public function testExtractAndReplaceInsource($text, $src)
    {
        preg_match('#(youtube|vimeo)#', $src, $m);
        $type = $m[1] ?? 'video';
        $this->assertReplaceInSource(Parser\AimyvideoParser::class, $text, $src, $type);
    }

    public function testExtractAndReplaceInsourceAllVideo()
    {
        $token    = uniqid();
        $source   = "<p>Some Token</p>{YouTube}$token|600|450|1{/YouTube}<p>Extra</p>";
        $oldUrl   = "https://www.youtube.com/watch?v=$token"; //already tested
        $token    = uniqid();
        $newUrl   = "https://www.youtube.com/watch?v=$token";
        $expected = "<p>Some Token</p>{YouTube}$token|600|450|1{/YouTube}<p>Extra</p>";
        //this test does not care about the validitie of te links.
        $parser    =  Parser\AimyvideoParser::getInstance();
        $replaced  = $parser->replaceInSource($source, $oldUrl, $newUrl);
        $this->assertEquals($expected, $replaced);
    }
}
