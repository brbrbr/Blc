<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Component\Parsers;

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

#[Attributes\CoversClass(Parser\IframeParser::class)]
#[Attributes\CoversClass(Parser\VideoParser::class)]
class ParserTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';

    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testExtractAndReplacefromSourceVideo()
    {
        $src    = 'https://phpunit.invalid/video-link';
        $text   = '<video src="' . $src . '" poster=""></video>';
        $this->assertReplaceInSource(Parser\VideoParser::class,$text, $src,'youtube');
    }

    public function testExtractAndReplacefromSourceIFrame()
    {
        $src    = 'https://phpunit.invalid/iframe-link';
        $text   = '<iframe src="' . $src . '" poster=""></iframe>';
        $this->assertReplaceInSource(Parser\IframeParser::class,$text, $src,'youtube');
    }
}
