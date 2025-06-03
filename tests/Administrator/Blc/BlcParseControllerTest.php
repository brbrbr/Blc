<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Parser\LinksParser;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Blc\Tests\UnitTestCase;
use Joomla\Database\ParameterType;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\TestDox('Test of the BlcExtract Controller')]
#[Attributes\CoversClass(BlcParseController::class)]
class BlcParseControllerTest extends UnitTestCase
{
    private $testData  = [];
    private $testUrl   = '';
    private $testImage = '';

    public function setUp(): void
    {
        $this->initApplication();


        $this->testUrl   = $this->getRandomLink('html');
        $this->testImage = $this->getRandomLink('img');
        $this->testData  = [
            'field1' => '<a href="' . $this->testUrl . '">example anchor</a>',
            'field2' => '<img src="' . $this->testImage . '" alt="example image"  /><a href="' . $this->testUrl . '">example anchor</a>',
        ];
        $this->clearLinks();
    }


    public function tearDown(): void
    {
        $parser =  BlcParseController::getInstance();
        //this will reset the instance
        $parser->setConfigOption('dummy', 'dummy', true);
    }
    protected function clearLinks()
    {
        $this->deleteLink($this->testImage);
        $this->deleteLink($this->testUrl);
    }
    protected function getItemSynch($create = true): SynchTable
    {

        $synchTable = new SynchTable($this->getDatabase());
        $pk         = [
            'container_id' => 9999,
            'plugin_name'  => 'phpunit',
        ];
        $synchTable->load($pk);
        if ($create && !$synchTable->id) {
            //  $pk['data'] = [];

            $synchTable->save($pk);
        }
        return $synchTable;
    }

    protected function getBlcParseController()
    {
        return BlcParseController::getInstance(false);
    }


    public function testCanBoot()
    {

        $BlcParseController = BlcParseController::getInstance();
        $this->assertSame(BlcParseController::class, $BlcParseController::class);
        $this->isSingeTon($BlcParseController);
    }

    public function testgetParsers()
    {
        $parsers = $this->getBlcParseController()->getParsers();
        $this->assertNotEmpty($parsers);
    }
    public function testcanRegisterParsers()
    {
        $name1              = 'fooParser1';
        $name2              = 'fooParser2';
        $BlcParseController = $this->getBlcParseController();

        $BlcParseController->unRegisterParser($name1);
        $BlcParseController->unRegisterParser($name2);
        $BlcParseController->unRegisterParser('links');


        $parserStub = $this->getMockBuilder(BlcParserInterface::class)->getMock();

        $parserStub->method('getName')
            ->willReturnOnConsecutiveCalls($name1, $name2);

        $BlcParseController->registerParsers(
            [
                $parserStub,
                $parserStub,
                LinksParser::getInstance(),
            ]
        );
        $this->assertInstanceOf($parserStub::class, $BlcParseController->getParser($name1));
        $this->assertInstanceOf($parserStub::class, $BlcParseController->getParser($name2));

        $BlcParseController->unRegisterParser($name1);
        $BlcParseController->unRegisterParser($name2);
        $BlcParseController->unRegisterParser('links');

        $this->assertNull($BlcParseController->getParser($name1));
        $this->assertNull($BlcParseController->getParser($name2));
    }

    public function testcannotRegisterAnyClass()
    {
        $this->expectException(\TypeError::class);
        $BlcParseController = $this->getBlcParseController();
        $BlcParseController->registerParser($this);
    }

    public function testcannotRegisterAnyClassByString()
    {
        $this->expectException(\Error::class);
        $BlcParseController = $this->getBlcParseController();
        $BlcParseController->registerParser(static::class);
    }



    public function testcanRegisterParser()
    {
        $name               = 'fooParser';
        $BlcParseController = $this->getBlcParseController();
        $BlcParseController->unRegisterParser($name);

        $parserStub = $this->getMockBuilder(BlcParserInterface::class)->getMock();
        $parserStub->method('getName')
            ->willReturn($name);
        $BlcParseController->registerParser($parserStub);
        $this->assertInstanceOf($parserStub::class, $BlcParseController->getParser($name));
        $BlcParseController->unRegisterParser($name);
        $this->assertNull($BlcParseController->getParser($name));

        $BlcParseController->registerParser($parserStub);
        $BlcParseController->clearParsers();
        $this->assertNull($BlcParseController->getParser($name));
    }

    public function testclearParsers()
    {

        $BlcParseController = $this->getBlcParseController();
        $BlcParseController->clearParsers();

        $protectedMethod = (
            fn() =>
            /** @phpstan-ignore method.notFound */
            $this->parsers
        );
        $parsers = $protectedMethod->call($BlcParseController);
        $this->assertEmpty($parsers);

        $parsers = $BlcParseController->getParsers();
        $this->assertNotEmpty($parsers);
    }

    public function testcanNotRegisterParserTwice()
    {
        $name               = 'fooParser';
        $BlcParseController = $this->getBlcParseController();
        $BlcParseController->unRegisterParser($name);
        $parserStub = $this->getMockBuilder(BlcParserInterface::class)->getMock();
        $parserStub->method('getName')
            ->willReturn($name);
        $BlcParseController->registerParser($parserStub);
        $this->expectException(\Exception::class);
        $BlcParseController->registerParser($parserStub);
    }
    /**
     * purpose is not to test the parsers those are in the parser tests
     */
    public function testextractAndStoreLinksNoStore()
    {
        $BlcParseController = $this->getBlcParseController();
        $links = $BlcParseController->extractAndStoreLinks($this->testData, [], false);
        $this->assertIsArray($links);
        $this->assertEquals(2, \count($links));
        $this->assertArrayHasKey('field1', $links);
        $this->assertArrayHasKey('field2', $links);
        $this->assertNotEmpty($links['field1']);
        $this->assertNotEmpty($links['field2']);
        $this->assertEquals(1, \count($links['field1']));
        $this->assertEquals(2, \count($links['field2']));
        $this->assertSame($this->testUrl, $links['field1'][0]['url']);
        //the href parser is first so the href link should be first
        $this->assertSame($this->testUrl, $links['field1'][0]['url']);
        $this->assertSame($this->testImage, $links['field2'][1]['url']);
        //store:false
        $this->assertLinkExists($this->testUrl, true);
        $this->assertLinkExists($this->testImage, true);
    }


    public function testextractAndStoreLinksNoStoreEmptySource()
    {
        $BlcParseController = $this->getBlcParseController();
        $data               = $this->testData;
        $data['field4']     = '';
        $links              = $BlcParseController->extractAndStoreLinks($this->testData, [], false);
        $this->assertIsArray($links);
        $this->assertEquals(2, \count($links));
    }

    /**
     * purpose is not to test the parsers those are in the parser tests
     */
    public function testextractAndStoreLinksNoStoreFromStringNoMeta()
    {

        $BlcParseController = $this->getBlcParseController();
        $links              = $BlcParseController->extractAndStoreLinks($this->testData['field2'], [], false);

        $this->assertIsArray($links);
        $this->assertEquals(1, \count($links));
        $this->assertArrayHasKey('generic', $links);
        $this->assertEquals(2, \count($links['generic']));
        $this->assertSame($this->testUrl, $links['generic'][0]['url']);
        $this->assertSame($this->testImage, $links['generic'][1]['url']);
    }

    public function testextractAndStoreLinksNoStoreFromStringMeta()
    {
        $meta               = ['field' => 'field3'];
        $BlcParseController = $this->getBlcParseController();
        $links              = $BlcParseController->extractAndStoreLinks($this->testData['field2'], $meta, false);

        $this->assertIsArray($links);
        $this->assertEquals(1, \count($links));
        $this->assertArrayHasKey('field3', $links);
        $this->assertEquals(2, \count($links['field3']));
        $this->assertSame($this->testUrl, $links['field3'][0]['url']);
        $this->assertSame($this->testImage, $links['field3'][1]['url']);
    }
    /**
     * purpose is not to test the parsers those are in the parser tests
     */
    public function testextractAndStoreLinksStoreNoSynch()
    {
        $this->expectException(\RuntimeException::class);
        $BlcParseController = $this->getBlcParseController();

        $BlcParseController->extractAndStoreLinks($this->testData, [], true);
    }


    /**
     * purpose is not to test the parsers those are in the parser tests
     */
    public function testextractAndStoreLinksStore()
    {
        $synchItem = $this->getItemSynch();
        //start with clean


        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_instances'))
            ->where($db->quoteName('synch_id') . ' = :id')
            ->bind(':id', $synchItem->id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();


        $BlcParseController = $this->getBlcParseController();
        $parsers            = $BlcParseController->getParsers();
        $this->assertNotEmpty($parsers);


        $meta               = [
            'synchId' => $synchItem->id,
        ];

        $BlcParseController->extractAndStoreLinks($this->testData, $meta, true);

        $linkItem = $this->loadLinkItem($this->testUrl, false);


        $this->assertNotEquals(0, $linkItem->id);
        $linkItem = $this->loadLinkItem($this->testImage, false);
        $this->assertNotEquals(0, $linkItem->id);

        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances'))
            ->select('count(*)')
            ->where($db->quoteName('synch_id') . ' = :id')
            ->bind(':id', $synchItem->id, ParameterType::INTEGER);

        $result = $db->setQuery($query)->loadResult();
        $this->assertSame(3, $result);

        $this->clearLinks();

        //database table contraint test.
        //instance table should be  empty after deleting links
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances'))
            ->select('count(*)')
            ->where($db->quoteName('synch_id') . ' = :id')
            ->bind(':id', $synchItem->id, ParameterType::INTEGER);

        $result = $db->setQuery($query)->loadResult();
        $this->assertSame(0, $result);


        //database table contraint test.
        //instance table should be  empty after deleting synch
        $BlcParseController->extractAndStoreLinks($this->testData, $meta, true);
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances'))
            ->select('count(*)')
            ->where($db->quoteName('synch_id') . ' = :id')
            ->bind(':id', $synchItem->id, ParameterType::INTEGER);

        $result = $db->setQuery($query)->loadResult();
        $this->assertSame(3, $result);

        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $synchItem->id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances'))
            ->select('count(*)')
            ->where($db->quoteName('synch_id') . ' = :id')
            ->bind(':id', $synchItem->id, ParameterType::INTEGER);

        $result = $db->setQuery($query)->loadResult();
        $this->assertSame(0, $result);
    }
    public function testStoreLinks()
    {
        $links = [
            [
                'url' => $this->getRandomLink(),
                'anchor' => $this->getRandomTitle()
            ],
            [
                'url' => $this->getRandomLink(),
                'anchor' => $this->getRandomTitle(),
                'suffix' => 'test'
            ],

        ];
        $parser = 'TestParser';
        $field = 'TestField';

        $meta = [
            'synchId' =>  $this->getItemSynch()->id,
            'parser' => $parser,
            'field' => $field
        ];
        $BlcParseController = $this->getBlcParseController();
        $BlcParseController->storeLinks($links, $meta);
        $linkItem = $this->assertLinkExists($links[0]['url'], false);
        $this->assertAnchorExists($links[0]['anchor'], $linkItem->id);
        $this->assertFieldExists($field, $linkItem->id);
        $this->assertParserExists($parser, $linkItem->id);
        $linkItem =  $this->assertLinkExists($links[1]['url'], false);
        $this->assertAnchorExists($links[1]['anchor'], $linkItem->id);
        $this->assertFieldExists("{$field}-test", $linkItem->id);
        $this->assertParserExists($parser, $linkItem->id);
    }
}
