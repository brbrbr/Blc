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

use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/BlcParser

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcParser::class)]
class BlcParserTest extends UnitTestCase
{
    public function testcanNotBoot()
    {
        //abstract class
        $this->expectException(\Error::class);
        BlcTagParser::getInstance();
    }
}
