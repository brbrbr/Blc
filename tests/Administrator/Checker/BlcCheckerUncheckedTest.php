<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerUnchecked;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Checker/BlcCheckerUnchecked

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcCheckerUnchecked::class)]
class BlcCheckerUncheckedTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testcanCheckLink()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testcheckLink()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
