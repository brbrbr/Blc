<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Table;

use Blc\Component\Blc\Administrator\Table\SynchTable;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

#[Attributes\CoversClass(SynchTable::class)]
class SynchTableTest extends UnitTestCase
{
    private SynchTable $table;

    protected function setUp(): void
    {
        $this->initApplication();
        $this->table = new SynchTable($this->getDatabase(), $this->getDispatcher());
    }




    public function testConstructor()
    {
        $this->assertInstanceOf(SynchTable::class, $this->table);
        $this->assertEquals('com_blc.synch', $this->table->typeAlias);
        $this->assertEquals('#__blc_synch', $this->table->getTableName());
        $this->assertEquals('id', $this->table->getKeyName());
    }

    public function testDefaultValues()
    {
        $this->assertEquals('[]', $this->table->data);
        $this->assertNull($this->table->id);
        $this->assertNull($this->table->plugin_name);
        $this->assertNull($this->table->container_id);
        $this->assertNull($this->table->synched);
        $this->assertNull($this->table->last_synch);
    }

    public function testSetSynched()
    {
        $this->table->plugin_name  = 'phpunit';
        $this->table->container_id = 0;
        $this->table->setSynched();

        $this->assertEquals(1, $this->table->synched);
        $this->assertNotNull($this->table->last_synch);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $this->table->last_synch
        );
        $this->table->delete(
            ['plugin_name' => 'phpunit', 'container_id' => 0]
        );
    }

    public function testJsonEncodeSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_jsonEncode');
        $property->setAccessible(true);
        $this->assertEquals(['data'], $property->getValue($this->table));
    }

    public function testTableKeys()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_tbl_keys');
        $property->setAccessible(true);

        $this->assertEquals(
            ['id', 'plugin_name', 'container_id'],
            $property->getValue($this->table)
        );
    }
}
