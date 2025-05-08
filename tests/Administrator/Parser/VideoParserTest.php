<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Parser;

use Blc\Component\Blc\Administrator\Parser\VideoParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/VideoParser

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(VideoParser::class)]
class VideoParserTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testExtractAndReplacefromSourceVideo()
    {
        $src    = 'https://phpunit.invalid/video-link';
        $text   = '<video src="' . $src . '" poster=""></video>';
        $this->assertReplaceInSource(VideoParser::class, $text, $src, 'youtube');
    }
}
