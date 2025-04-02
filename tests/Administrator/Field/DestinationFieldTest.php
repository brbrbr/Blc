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

use Blc\Component\Blc\Administrator\Field\DestinationField;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/DestinationField

 *
 * @since       __DEPLOY_VERSION__
 */

#[Attributes\CoversClass(DestinationField::class)]
class DestinationFieldTest extends UnitTestCase
{


    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testDummy() {
            $this->markTestIncomplete(
             'This test has not been implemented yet.'
           );
           }

}
