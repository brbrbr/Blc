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
        $this->table->check();
        $this->assertTrue($this->table->isInternal());
        //expected,actual
        $this->assertEquals($data['url'], $this->table->toString(), 'toString() should return the original url');
        $this->assertEquals($root, $this->table->toString(sef: true), 'toString() should return the root url');
        $this->assertEquals('/', $this->table->toString(sef: true, absolute: false), 'toString() should return the relativ root url');
    }

    public function testCheckNullDate()
    {


        $this->table->reset();
        $this->table->first_failure      = '';
        $this->table->last_success       = 'invlaid';
        $this->table->last_check         = '1970';
        $this->table->last_check_attempt = 'boe';
        $nullDate                        =  $this->getDatabase()->getNullDate();
        $this->table->check();


        $this->assertEquals($nullDate, $this->table->first_failure, 'first_failure  date not checked and set to nulldate');
        $this->assertEquals($nullDate, $this->table->last_success, 'last_success  date not checked and set to nulldate');
        $this->assertEquals($nullDate, $this->table->last_check, 'last_check  date not checked and set to nulldate');
        $this->assertEquals($nullDate, $this->table->last_check_attempt, 'last_check_attempt date not checked and set to nulldate');
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

        //Uri::IsInternal does not detect these links
        $data = [
            'url' => '/hello-world',
        ];

        $this->table->bind($data);


        $this->assertTrue($this->table->isInternal());
        //expected,actual
        $this->assertEquals($data['url'], $this->table->toString(absolute: false), 'toString() should return the original url');
        $this->assertEquals(rtrim($root, '/') . '/' . ltrim($data['url'], '/'), $this->table->toString(sef: true, absolute: true), 'toString() should return the relativ root url');
        $this->assertEquals(rtrim($root, '/') . '/' . ltrim($data['url'], '/'), (string)$this->table, 'toString() should return the relativ root url');
    }


    public function testGetterSetters()
    {
        $url = 'https://external-site.com/' . uniqid();
        $this->table->reset();
        $this->table->toCheck = $url;
        $this->assertSame($url, $this->table->toCheck);
        unset($this->table->toCheck);
        $this->assertSame('', $this->table->toCheck);
        $url  = 'https://external-site.com/' . uniqid();
        $data = [
            'url' => $url,
        ];


        $this->table->bind($data);
        $this->assertSame($url, $this->table->toCheck);
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
        $this->assertFalse($this->table->isInternal());
        $this->assertEquals($data['url'], $this->table->toString());
    }

    public function testNullValueSupport()
    {
        $reflection = new \ReflectionClass($this->table);
        $property   = $reflection->getProperty('_supportNullValue');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($this->table));
    }

    public function testStorage()
    {


        $log = [
            'a' => uniqid(),
            'b' => uniqid(),
        ];
        $data = [
            'a' => uniqid(),
            'b' => uniqid(),

        ];

        $pks = [
            'url' => 'https://external-site.com/' . __FUNCTION__ . uniqid(),
        ];
        $this->table->reset();
        $this->table->load($pks);

        $this->table->log  = $log;
        $this->table->data = json_encode($data);
        $this->table->saveStorage();
        $this->assertSame($log, $this->table->log);
        //$id = 0 - so the data is not converted to array
        $this->assertSame(json_encode($data), $this->table->data);

        //not saved
        $this->table->reset();
        $this->table->load($pks);
        $this->assertSame([], $this->table->log);
        //$id = 0 - so the data is not converted to array
        $this->assertSame([], $this->table->data);


        $this->table->save($pks);
        $this->assertNotSame(0, $this->table->id);

        $this->table->log  = $log;
        $this->table->data = json_encode($data);
        $this->table->saveStorage();

        //save so loaded and converted to array
        $this->table->reset();
        $this->table->load($pks);
        $this->table->loadStorage();
        $this->assertSame($log, $this->table->log);
        $this->assertSame($data, $this->table->data);

        $this->table->reset();
        $this->table->load($pks);

        $data['query']['id'] = 'x';
        $this->table->log    = $log;
        $this->table->data   = $data;
        $this->table->saveStorage();
        //x i sconverted to zero
        $this->assertNotSame($data, $this->table->data);

        $data['query']['id'] = '999';

        $this->table->data = $data;
        $this->table->saveStorage();
        //string is converted to inval
        $this->assertSame((int)$data['query']['id'], $this->table->data['query']['id']);
    }

    public function testGetReplaceUrl()
    {
        $url = 'https://external-site.com/url/' . __FUNCTION__ . uniqid();

        $data = [
            'url' => $url
        ];

        $this->table->reset();
        $this->table->bind($data);


        $replaceUrl = $this->table->getReplaceUrl();
        $this->assertSame($url, $replaceUrl);
        $finalUrl = 'https://external-site.com/final/' . __FUNCTION__ . uniqid();

        $this->table->final_url = $finalUrl;
        $replaceUrl = $this->table->getReplaceUrl();
        $this->assertSame($finalUrl, $replaceUrl);


        $url = "index.php";
        $this->table->reset();
        $data = [
            'url' => 'index.php',
        ];
        $this->table->final_url = $finalUrl;
        $this->table->bind($data);
        $this->table->check();
        $replaceUrl = $this->table->getReplaceUrl();
        $this->assertSame($url, $replaceUrl);
    }
}
