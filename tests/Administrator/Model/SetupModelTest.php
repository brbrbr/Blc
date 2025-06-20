<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Model;

use Blc\Component\Blc\Administrator\Model\SetupModel;
use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Model/SetupModel

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(SetupModel::class)]
class SetupModelTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function bootModel()
    {
        $model = new SetupModel(['ignore-request' => true]);
        $this->assertInstanceOf(BaseDatabaseModel::class, $model);
    }

    public function testgetTable()
    {
        $model = new SetupModel(['ignore-request' => true]);

        $table = $model->getTable();
        $this->assertInstanceOf(SynchTable::class, $table);
        $table = $model->getTable('Link');
        $this->assertInstanceOf(LinkTable::class, $table);

        $table = $model->getTable('Instance');
        $this->assertInstanceOf(InstanceTable::class, $table);

        $table = $model->getTable('Synch');
        $this->assertInstanceOf(SynchTable::class, $table);

        $this->expectException(\Exception::class);

        $table = $model->getTable('Article');
    }
    public function testgetStatsHtml()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetStats()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetCountSynch()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testgetCountLinks()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testlastAction()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testcronEstimate()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
