<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Table;

use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use PHPUnit\Framework\Attributes;

#[Attributes\CoversClass(BlcTable::class)]
#[Attributes\CoversClass(LinkTable::class)]
class LinkTableTest extends UnitTestCase
{
    private LinkTable $table;


    public function setUp(): void
    {
        parent::setUp();

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

    public function testCannotChangeUrlObject()
    {
        $this->table->reset();

        $data =  (object) [
            'url' => 'https://example.com',
        ];

        $this->table->bind($data);
        //ensure the link exists

        $this->expectException(\RuntimeException::class);

        $data = (object) [
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
        $this->assertTrue($this->table->isInternal(true));
        //expected,actual
        $this->assertEquals($data['url'], $this->table->toString(absolute: false), 'toString() should return the original url');
        $this->assertEquals($root, $this->table->toString(sef: true), 'toString() should return the root url');
        $this->assertEquals('/', $this->table->toString(sef: true, absolute: false), 'toString() should return the relativ root url');
    }

    public function testCheckNullDate()
    {


        $this->table->reset();
        $this->table->first_failure      = '';
        $this->table->last_success       = 'invlaid';
        $this->table->last_check         = '1960';
        $this->table->last_check_attempt = 'boe';
        $nullDate                        = $this->getDatabase()->getNullDate();
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
        $this->assertFalse($this->table->isInternal(true));
        //expected,actual
        $this->assertEquals(ltrim($data['url'], '/'), $this->table->toString(absolute: false), 'toString() should return the reltive url');
        $this->assertEquals(rtrim($root, '/') . '/' . ltrim($data['url'], '/'), $this->table->toString(sef: true, absolute: true), 'toString() should return the relativ root url');
        $this->assertEquals(rtrim($root, '/') . '/' . ltrim($data['url'], '/'), (string)$this->table, 'toString() should return the relativ root url');



        $this->table->reset();

        //Uri::IsInternal does not detect these links
        $data = [
            'url' => '/hello-world/index.php',
        ];

        $this->table->bind($data);

        $this->assertTrue($this->table->isInternal());
        //expected,actual
        $this->assertEquals(ltrim($data['url'], '/'), $this->table->toString(absolute: false), 'toString() should return the reltive url');
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

    public function testGetDefault()
    {
        $this->expectException(\RuntimeException::class);
        $this->table->reset();
        $this->table->_tbl_keys;
    }

    public function testSetDefault()
    {
        $this->expectException(\RuntimeException::class);
        $this->table->reset();
        $this->table->_tbl_keys = ['dummy'];
    }
    public function testToStringWithRouteArgs()
    {
        $this->expectException(\TypeError::class);
        $this->table->toString('https://example.com', true, true, true);
    }
    public function testUnsetDefault()
    {
        $this->expectException(\RuntimeException::class);
        $this->table->reset();
        unset($this->table->_tbl_keys);
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

    public function testToStringReturnsOriginalUrlForNoneHTTP()
    {
        $data = [
            'url' => str_replace('https://', 'ftp://', Uri::root()) . 'file.txt',
        ];
        $this->table->bind($data);
        $this->assertFalse($this->table->isInternal(), $data['url'] . ' should be internal');
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


        $this->assertFalse($property->getValue($this->table));
    }

    public function testAbsoluteUrl()
    {

        $table      = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $reflection = new \ReflectionClass($table);
        $property   = $reflection->getProperty('componentConfig');



        $componentConfig = $property->getValue($table);
        $componentConfig->set('internal_absolute', 0);

        $url = '/hello-world';

        $table->reset();

        //Uri::IsInternal does not detect these links
        $data = [
            'url' => $url,
        ];
        $table->load($data);
        $table->save($data);

        $this->assertTrue($table->isInternal());
        $this->assertSame(ltrim($url, '/'), $table->internal_url);

        $root = Uri::root();

        $componentConfig->set('internal_absolute', 1);
        $table->load($data);
        $table->save($data); ///trigger set prefered internal
        $this->assertSame($root . ltrim($url, '/'), $table->internal_url, json_encode($table->log));
    }


    public function testRelativeUrl()
    {

        $table      = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $reflection = new \ReflectionClass($table);
        $property   = $reflection->getProperty('componentConfig');



        $componentConfig = $property->getValue($table);
        $componentConfig->set('internal_absolute', 0);

        $url = 'hello-world';

        $table->reset();

        //Uri::IsInternal does not detect these links
        $data = [
            'url' => $url,
        ];
        $table->load($data);
        $table->save($data);

        $this->assertTrue($table->isInternal());
        $this->assertSame($url, $table->internal_url);

        $root = Uri::root();

        $componentConfig->set('internal_absolute', 1);
        $table->load($data);
        $table->save($data); //trigger set prefered internal
        $this->assertSame($root . ltrim($url, '/'), $table->internal_url);
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
            'url' => $url,
        ];

        $this->table->reset();
        $this->table->bind($data);


        $replaceUrl = $this->table->getReplaceUrl();
        $this->assertSame($url, $replaceUrl);
        $finalUrl = 'https://external-site.com/final/' . __FUNCTION__ . uniqid();

        $this->table->final_url = $finalUrl;
        $replaceUrl             = $this->table->getReplaceUrl();
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
    public static function urlProvider(): array
    {
        $uniqid        = uniqid();
        $xhtmlInternal = 'index.php?option=com_content&amp;view=article&amp;u=' . $uniqid;
        $plainInternal = 'index.php?option=com_content&view=article&u=' . $uniqid;
        $mixedInternal = 'index.php?option=com_content&amp;view=article&u=' . $uniqid;


        return [
            [$plainInternal,  $xhtmlInternal, 1], //internal &
            [$xhtmlInternal, $xhtmlInternal, 1], //internal &amp;
            [$mixedInternal, $xhtmlInternal, 1], //internal mixed;
            [$plainInternal,  $plainInternal, 0], //internal &
            [$xhtmlInternal, $plainInternal, 0], //internal &amp;
            [$mixedInternal, $plainInternal, 0], //internal mixed;
            //misformed queries should be untouched
            [str_replace('index.php', '', $plainInternal), str_replace('index.php', '', $plainInternal), 1], //internal &
            [str_replace('index.php', '', $xhtmlInternal), str_replace('index.php', '', $xhtmlInternal), 1], //internal &amp;
            [str_replace('index.php', '', $mixedInternal), str_replace('index.php', '', $xhtmlInternal), 1], //internal mixed; fixed to xhtml
            [str_replace('index.php', '', $plainInternal), str_replace('index.php', '', $plainInternal), 0], //internal &
            [str_replace('index.php', '', $xhtmlInternal), str_replace('index.php', '', $xhtmlInternal), 0], //internal &amp;
            [str_replace('index.php', '', $mixedInternal), str_replace('index.php', '', $xhtmlInternal), 0], //internal mixed; fixed to xhtml


        ];
    }

    #[Attributes\DataProvider('urlProvider')]
    public function testInternalUrls(string $url, string $internal, int $xhtml)
    {

        $table = $this->loadLinkItemXHtml($url, $xhtml);
        $this->assertNotSame(0, $table->id, 'Table not saved correctly' . json_encode($table->log));
        $this->assertSame($url, $table->url, "The stored url should be unchanged: {$table->id}");
        $this->assertSame($internal, $table->internal_url, "The stored internal_url should be updated for $xhtml: " . json_encode($table->log));

        $fromToString = $table->toString(false, (bool)$xhtml, false);
        if (str_starts_with($url, 'index.php')) {
            $this->assertSame($internal, $fromToString);
        }



        if (!str_starts_with($url, 'https://')) {
            $root        = Uri::root();
            $urlWithHost = $root . $url;
            $table       = $this->loadLinkItemXHtml($urlWithHost, $xhtml);

            $this->assertSame($internal, $table->internal_url, "The stored internal_url should be unchanged: {$table->id}");


            $this->assertSame($urlWithHost, $table->url, "The stored url should be unchanged: {$table->id}");



            $root        = 'https:://example.com/';
            $urlWithHost = $root . $url;
            $table       = $this->loadLinkItemXHtml($urlWithHost, $xhtml);

            $this->assertSame($urlWithHost, $table->url);
        }
    }

    public static function specialUrlProvider(): array
    {
        return [
            ['mailto:dummy@example.com'], //no internal
        ];
    }

    #[Attributes\DataProvider('specialUrlProvider')]
    public function testSpecialUrls(string $url)
    {

        $table = $this->loadLinkItem($url);
        $this->assertNotSame(0, $table->id, 'Table not saved correctly' . json_encode($table->log));
        $this->assertSame($url, $table->url, "The stored url should be unchanged: {$table->id}");
        $this->assertSame('', $table->internal_url, "The stored internal_url should be empty: " . json_encode($table->log));
        $fromToString = $table->toString();
        $this->assertSame($url, $fromToString, "The toStrong url should be unchanged: {$table->id}");
    }

    protected function loadLinkItemXHtml($url, int $xhtml = 1): LinkTable
    {
        $config = [
            'internal_absolute' => 0,
            'internal_sef'      => 0,
            'internal_xhtml'    => $xhtml,
        ];


        $linkItem = $this->loadLinkItem($url, config: $config);



        return $linkItem;
    }
}
