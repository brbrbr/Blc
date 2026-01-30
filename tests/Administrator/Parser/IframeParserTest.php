<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Parser;

use Blc\Component\Blc\Administrator\Parser\IframeParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/IframeParser

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(IframeParser::class)]
class IframeParserTest extends UnitTestCase
{


    public function testExtractAndReplacefromSourceIFrame()
    {
        $src    = 'https://phpunit.invalid/iframe-link';
        $text   = '<iframe src="' . $src . '" poster=""></iframe>';
        $this->assertReplaceInSource(IframeParser::class, $text, $src, 'youtube');
    }
}
