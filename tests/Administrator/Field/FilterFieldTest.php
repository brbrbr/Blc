<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Field;

use Blc\Component\Blc\Administrator\Field\FilterField;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/FilterField

 *
 * @since       __DEPLOY_VERSION__
 */

#[Attributes\CoversClass(FilterField::class)]
class FilterFieldTest extends UnitTestCase
{


    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testgetAttribute() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }

public function testsetup() {
         $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        }
}
