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
        $this->table->link_id=$this->getSomeLink()->id;
        $this->table->synch_id=$this->getSomeSynch()->id;
        
        $result = $this->table->store();
        $this->assertTrue($result);
        $this->assertEquals(512, strlen($this->table->link_text));
    }

    public function testStoreWithShortText()
    {
        $originalText = 'Short text';
        $this->table->link_text = $originalText;
        $this->table->link_id=$this->getSomeLink()->id;
        $this->table->synch_id=$this->getSomeSynch()->id;

        $result = $this->table->store();

        $this->assertTrue($result);
        $this->assertEquals($originalText, $this->table->link_text);
    }

    public function testDefaultValues()
    {
        $this->assertEquals('[]', $this->table->data);
        $this->assertNull($this->table->id);
        $this->assertNull($this->table->link_id);
        $this->assertNull($this->table->synch_id);
        $this->assertNull($this->table->field);
        $this->assertNull($this->table->link_text);
        $this->assertNull($this->table->parser);
    }

    public function testJsonEncodeSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property = $reflection->getProperty('_jsonEncode');
        $property->setAccessible(true);
        
        $this->assertEquals(['data'], $property->getValue($this->table));
    }

    public function testNullValueSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property = $reflection->getProperty('_supportNullValue');
        $property->setAccessible(true);
        
        $this->assertTrue($property->getValue($this->table));
    }
}