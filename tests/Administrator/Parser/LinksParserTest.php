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

use Blc\Component\Blc\Administrator\Parser\LinksParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/LinksParser

 *
 * @since       __DEPLOY_VERSION__
 */

#[Attributes\CoversClass(LinksParser::class)]
class LinksParserTest extends UnitTestCase
{


    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testextractfromSource() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }

public function testreplaceInSource() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }
}
