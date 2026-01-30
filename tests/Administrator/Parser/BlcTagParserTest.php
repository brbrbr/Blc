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

use Blc\Component\Blc\Administrator\Parser\BlcTagParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/BlcTagParser

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcTagParser::class)]
class BlcTagParserTest extends UnitTestCase
{
 
    public function testcanNotBoot()
    {
        //abstract class
        $this->expectException(\Error::class);
        BlcTagParser::getInstance();
    }

    public function testreplaceInSource()
    {
        $this->expectNotToPerformAssertions(
            'This code is part of an abstract class and tested in other classes'
        );
    }

    public function testextractfromSource()
    {
        $this->expectNotToPerformAssertions(
            'This code is part of an abstract class and tested in other classes'
        );
    }
}
