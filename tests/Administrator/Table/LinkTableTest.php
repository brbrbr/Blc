<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Table;

use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use PHPUnit\Framework\Attributes;

#[Attributes\CoversClass(LinkTable::class)]
class LinkTableTest extends UnitTestCase
{
    private LinkTable $table;


    public function setUp(): void
    {
        $this->initApplication();

        // Create table instance
        $this->table = new LinkTable($this->getDatabase(), $this->getDispatcher());
    }

    public function testResetValues()
    {
        $refernenceTable = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $data            = [
            'id' => $this->assertGetSomeLink()->id,
        ];

        $this->table->load($data);
        $this->table->reset($data);

        $this->assertSame(get_object_vars($refernenceTable), get_object_vars($this->table));
    }

    public function testBindGeneratesHashForUrl()
    {
        $data = [
            'url' => 'https://example.com',
        ];

        $this->table->bind($data);

        $this->assertEquals(md5($data['url']), $this->table->md5sum);
    }

    public function testCannotChangeUrl()
    {
        $this->expectException(\RuntimeException::class);
        $data = [
            'url' => 'https://example.com',
        ];

        $this->table->load($data);
        //ensure the link exists
        $this->table->save($data);


        $data = [
            'url' => 'https://example.com/2',
        ];
        $this->table->bind($data);
    }

    public function testIsInternalReturnsTrueForInternalUrlIndex()
    {

        $app = Factory::getContainer()->get(SiteApplication::class);
        $sef = $app->get('sef');
        if ($sef == 0) {
            $this->markTestSkipped(
                "SEF is disabled",
            );
        }

        $root = Uri::root();



        $this->table->reset();
        $data = [
            'url' => 'index.php',
        ];

        $this->table->bind($data);
        $this->assertTrue($this->table->isInternal());
        //expected,actual
        $this->assertEquals($data['url'], $this->table->toString(), 'toString() should return the original url');
        $this->assertEquals($root, $this->table->toString(sef: true), 'toString() should return the root url');
        $this->assertEquals('/', $this->table->toString(sef: true, absolute: false), 'toString() should return the relativ root url');
    }


    public function testIsInternalReturnsTrueForInternalUrlPath()
    {

        $app = Factory::getContainer()->get(SiteApplication::class);
        $sef = $app->get('sef');
        if ($sef == 0) {
            $this->markTestSkipped(
                "SEF is disabled",
            );
        }

        $root = Uri::root();


        $this->table->reset();
        $data = [
            'url' => '/hello-world',
        ];

        $this->table->bind($data);

        $this->assertTrue($this->table->isInternal());
        //expected,actual
        $this->assertEquals($data['url'], $this->table->toString(absolute: false), 'toString() should return the original url');
        $this->assertEquals(rtrim($root, '/') . '/' . ltrim($data['url'], '/'), $this->table->toString(sef: true, absolute: true), 'toString() should return the relativ root url');
    }





    public function testIsInternalReturnsFalseForExternalUrl()
    {
        $this->table->reset();
        $data = [
            'url' => 'https://external-site.com',
        ];
        $this->table->bind($data);


        $this->assertFalse($this->table->isInternal());
    }

    public function testSave()
    {
        $this->table->reset();

        $data = [
            'url' => 'https://external-site.com',
        ];
        $this->table->load($data);
        $this->table->save($data);
        $this->assertNotSame(0, $this->table->id);
    }

    public function testNotParsebleUrl()
    {
        $this->table->reset();

        $data = [
            'url' => 'https://external:site.com/' . uniqid(),
        ];
        $this->table->load($data);
        $this->table->save($data);
        $this->assertNotSame(0, $this->table->id);
        $this->assertNotSame(404, $this->table->http_code);
    }

    public function testEmpty()
    {
        $this->expectException(\RuntimeException::class);
        $this->table->reset();

        $data = [
            'url' => '',
        ];

        $this->table->save($data);
    }

    public function testInvalidSave()
    {
        $this->expectException(\RuntimeException::class);
        $data = 'https://external-site.com';
        $this->table->save($data);
    }

    public function testDelete()
    {
        $this->table->reset();

        $data = [
            'url' => 'https://to-delete.com',
        ];
        $this->table->load($data);
        $this->table->save($data);
        $this->assertNotSame(0, $this->table->id);

        $this->assertTrue($this->table->delete());

        $table = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $table->load($data);
        $this->assertSame(0, $table->id);
    }


    public function testDeleteNoneExisting()
    {
        $this->table->reset();

        $data = [
            'url' => uniqid(),
        ];


        $this->assertFalse($this->table->delete($data));
    }

    public function testToStringReturnsOriginalUrlForExternalUrls()
    {
        $data = [
            'url' => 'https://external-site.com',
        ];
        $this->table->bind($data);
        $this->assertEquals($data['url'], $this->table->toString());
    }

    public function testToNoProtocol()
    {
        $data = [
            'url' => '//external-site.com',
        ];
        $this->table->bind($data);
        $this->assertTrue($this->table->isInternal());
        $this->assertEquals($data['url'], $this->table->toString());
    }

    public function testNullValueSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_supportNullValue');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($this->table));
    }
}
