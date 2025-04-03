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

use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Parser/BlcParser

 *
 * @since       __DEPLOY_VERSION__
 */

#[Attributes\CoversClass(BlcParser::class)]
class BlcParserTest extends UnitTestCase
{


    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testgetInstance() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }

public function testgetName() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }

public function testextractfromSources() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }
}
