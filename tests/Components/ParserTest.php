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
class ParserTest extends UnitTestCase
{
    protected $wrappedClass;
    protected string $fieldContext = 'com_content.article';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testCanIframe()
    {
        $src = 'https://phpunit.invalid/iframe-link';
        $text = '<iframe src="' . $src . '" poster=""></iframe>';
        $parser =  Parser\IframeParser::getInstance();
        $protectedMethod = function ($string) {
            /** @phpstan-ignore method.notFound */
            return $this->extractLinks($string);
        };
        $links =  $protectedMethod->call($parser, $text);
        $this->assertSame($src, $links[0]['url']);
        
        $this->assertTestTag($text);
    }

    public function testCanVideo()
    {
        $src = 'https://phpunit.invalid/video-link';
        $text = '<video src="' . $src . '" poster=""></video>';
        $parser =  Parser\VideoParser::getInstance();
        $protectedMethod = function ($string) {
            /** @phpstan-ignore method.notFound */
            return $this->extractLinks($string);
        };
        $links =  $protectedMethod->call($parser, $text);
        $this->assertSame($src, $links[0]['url']);

        $this->assertTestTag($text);
    }

    public function testCanA()
    {
        $src = 'https://phpunit.invalid/a-link';
        $anchor = 'phpunit.anchor';
        $text = '<a href="' . $src . '" >' . $anchor . '</a>';
        $parser =  Parser\HrefParser::getInstance();
        $protectedMethod = function ($string) {
            /** @phpstan-ignore method.notFound */
            return $this->extractLinks($string);
        };
        $links =  $protectedMethod->call($parser, $text);
        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);

        $this->assertTestTag($text);
    }
    public function testCanImg()
    {
        $src = 'https://phpunit.invalid/imgage.jpg';
        $anchor = 'phpunit.anchor';
        $text = '<img src="' . $src . '" alt="' . $anchor . '"/>';
        $parser =  Parser\ImgParser::getInstance();
        $protectedMethod = function ($string) {
            /** @phpstan-ignore method.notFound */
            return $this->extractLinks($string);
        };
        $links =  $protectedMethod->call($parser, $text);
        $this->assertSame($src, $links[0]['url']);
        $this->assertSame($anchor, $links[0]['anchor']);

        $this->assertTestTag($text);
    }

    public function estDummy()
    {
       
      
        $text = '<video src="https://672e271484a27-gen.invalid/video-link" poster=""></video>
<div>

    <img src="https://dev.projecten.dev/images/672e2714849f9.jpg" alt="">

        <video src="https://dev.projecten.dev/images/hovervideo.jpg"></video>
    
<h3>Foto 3 Vrouwen Aan Tafel</h3>


<div><p>Dit is content bij een afbeelding in een <a href="https://672e271484a28-gen.invalid/">gallery</a></p></div>

<p><a href="https://672e271484a29-gen.invalid/link-bij-een-gallery">Link text</a></p>

</div>
<div><p>Dit is text in <a href="https://672e271484a2a-gen.invalid/">een text</a> element</p></div>
<div>

    <a href="https://672e271484a2b-gen.invalid/a-link">Link in HTML element</a>
</div>
<h1><a href="https://672e271484a2c-gen.invalid/headline-link-link">Link in <a href="https://672e271484a2d-gen.invalid/headline-content-link">Headline</a></a></h1>
<ul>
        <li><a href="https://672e271484a2e-gen.invalid/headline-link-link">social</a>
</li>
    </ul>
';
        $parser =  Parser\HrefParser::getInstance();
        $protectedMethod = function ($string) {
            /** @phpstan-ignore method.notFound */
            return $this->extractLinks($string);
        };
        $links =  $protectedMethod->call($parser, $text);
        var_dump($links);
        $this->assertEmtpy($links);
      
    }
}
