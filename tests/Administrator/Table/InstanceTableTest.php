<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Table;

use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

#[Attributes\CoversClass(InstanceTable::class)]
class InstanceTableTest extends UnitTestCase
{
    private InstanceTable $table;


    protected function setUp(): void
    {
        $this->initApplication();
        $this->table = new InstanceTable($this->getDatabase(), $this->getDispatcher());
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(InstanceTable::class, $this->table);
        $this->assertEquals('com_blc.instances', $this->table->typeAlias);
        $this->assertEquals('#__blc_instances', $this->table->getTableName());
        $this->assertEquals('id', $this->table->getKeyName());
    }

    public function testStore()
    {
        $this->table->link_text = str_repeat('a', 600);
        $this->table->parser    = 'phpunit';
        $this->table->field     = uniqid();
        $this->table->link_id   = $this->getSomeLink()->id;
        $this->table->synch_id  = $this->getSomeSynch()->id;
        $this->table->field     = uniqid();
        $result                 = $this->table->save();
        $this->assertTrue($result);
        $this->assertEquals(512, \strlen($this->table->link_text));
    }

    public function testSAveWithShortText()
    {
        $originalText           = 'Short text';
        $this->table->link_text = $originalText;
        $this->table->parser    = 'phpunit';
        $this->table->field     = uniqid();
        $this->table->link_id   = $this->getSomeLink()->id;
        $this->table->synch_id  = $this->getSomeSynch()->id;

        $result = $this->table->save();

        $this->assertTrue($result);
        $this->assertEquals($originalText, $this->table->link_text);
    }


    public function testResetValues()
    {
        $refernenceTable = new InstanceTable($this->getDatabase(), $this->getDispatcher());
        $data            = [
            'synch_id' => $this->getSomeSynch()->id,

        ];

        $this->table->load($data);
        $this->table->reset($data);

        $this->assertSame(get_object_vars($refernenceTable), get_object_vars($this->table));
    }

    public function testDefaultValues()
    {
        $this->table->reset();
        $this->assertEquals('[]', $this->table->data);
        $this->assertEquals(0, $this->table->id);
        $this->assertEquals(0, $this->table->link_id);
        $this->assertEquals(0, $this->table->synch_id);

        $this->assertEquals('', $this->table->field);
        $this->assertEquals('', $this->table->link_text);
        $this->assertEquals('', $this->table->parser);
    }

    public function testJsonEncodeSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_jsonEncode');
        $property->setAccessible(true);

        $this->assertEquals(['data'], $property->getValue($this->table));
    }

    public function testNullValueSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_supportNullValue');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($this->table));
    }

    public function testCanNotFieldNull()
    {
        $this->expectException(\TypeError::class);
        $this->table->reset();

        $data = [
            'link_text' => uniqid(),
        ];

        $this->table->load($data);
        $test                   = null; //this is to silense intelephense
        $this->table->link_text = &$test;
    }
}
