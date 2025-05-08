<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Model;

use Blc\Component\Blc\Administrator\Model\LinkModel;
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
#[Attributes\CoversClass(LinkModel::class)]
#[Attributes\TestDox('Test of the System - BLC Plugin')]
class LinkModelTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }
    public function testgetTable()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetForm()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetItem()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetPlugin()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testtrashit()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetInstances()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetSynch()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
