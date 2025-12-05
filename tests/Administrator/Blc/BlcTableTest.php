<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Blc/BlcTable

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcTable::class)]
class BlcTableTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testSetUp()
    {
        $table = new BlcTable('#__blc_links', 'id', $this->getDatabase(), $this->getDispatcher());
        $this->assertInstanceOf(BlcTable::class, $table, 'Table should be an instance of BlcTable');
        return $table;
    }
    #[Attributes\Depends('testSetUp')]
    public function testSave(BlcTable $table)
    {
        $this->expectNotToPerformAssertions();
        $pks = ['url' => 'blctabletest','md5sum' => 'test', 'title' => 'Test Title'];

        $table->save($pks);
        return $table;
    }
    #[Attributes\Depends('testSetUp')]
    public function testReset(BlcTable $table)
    {
        $this->expectNotToPerformAssertions();
        $table->reset();
    }
    #[Attributes\Depends('testSave')]
    public function testDelete(BlcTable $table)
    {
        $this->expectNotToPerformAssertions();
        $table->delete();
    }
    #[Attributes\Depends('testSetUp')]
    public function testSetDatabase(BlcTable $table)
    {
        $this->expectNotToPerformAssertions();
        $table->setDatabase($this->getDatabase());
    }
}
