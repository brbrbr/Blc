<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Table;

use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

#[Attributes\CoversClass(BlcTable::class)]
#[Attributes\CoversClass(SynchTable::class)]
class SynchTableTest extends UnitTestCase
{
    private SynchTable $table;

    public function setUp(): void
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

    public function testResetValues()
    {
        $refernenceTable = new SynchTable($this->getDatabase(), $this->getDispatcher());
        $data            = [
            'id' => $this->getSomeSynch()->id,
        ];

        $this->table->load($data);
        $this->table->reset($data);

        $this->assertSame(get_object_vars($refernenceTable), get_object_vars($this->table));
    }

    public function testSetSynched()
    {
        $data = [
            'plugin_name'  => 'phpunit',
            'container_id' => 1,
        ];
        $nullDate           = $this->getDatabase()->getNullDate();
        $this->table->load($data);


        $this->table->setSynched($data);

        $this->assertNotSame(0, $this->table->id);
        $this->assertEquals(1, $this->table->synched);
        $this->assertNotEquals($nullDate, $this->table->last_synch);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $this->table->last_synch
        );
        $this->table->delete(
            $data
        );
    }

    public function testSave()
    {
        $data = [
            'plugin_name'  => 'phpunit',
            'container_id' => 0,
        ];
        $this->table->load($data);

        $this->table->save($data);


        $this->assertNotSame(0, $this->table->id);
    }

    public function testJsonEncodeSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_jsonEncode');

        $this->assertEquals(['data'], $property->getValue($this->table));
    }

    public function testTableKeys()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_tbl_keys');


        $this->assertEquals(
            ['id', 'plugin_name', 'container_id'],
            $property->getValue($this->table)
        );
    }
    public function testCanNotFieldNull()
    {
        $this->expectException(\TypeError::class);
        $this->table->reset();

        $data = [
            'plugin_name' => uniqid(),
        ];

        $this->table->load($data);
        $this->table->container_id = json_decode(json_encode(null)); //to fool inteliphense
    }

    public function testDelete()
    {
        $this->table->reset();

        $data = [
            'plugin_name'  => uniqid(),
            'container_id' => 1,
        ];
        $this->table->load($data);
        $this->table->save($data);
        $this->assertNotSame(0, $this->table->id);
        $this->assertTrue($this->table->delete());

        $table = new SynchTable($this->getDatabase(), $this->getDispatcher());
        $table->load($data);
        $this->assertSame(0, $table->id);
    }

    public function testNullValueSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_supportNullValue');


        $this->assertFalse($property->getValue($this->table));
    }
}
