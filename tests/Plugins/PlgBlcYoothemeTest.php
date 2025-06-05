<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugins;

use Blc\Component\Blc\Administrator\Event\BlcInstanceDisplayEvent;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Plugin\Blc\Content\Extension\BlcPluginActor as ContentPluginActor;
use Blc\Plugin\Blc\Yootheme\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Yootheme\Extension\YoothemeParser;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(YoothemeParser::class)]
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcYoothemeTest extends UnitTestCase
{
    protected string $folder       = 'blc';
    protected string $element      = 'yootheme';
    protected string $class        = BlcPluginActor::class;

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }


    public function testCanBoot()
    {

        $this->assertNotNull($this->class);
        $plugin = $this->bootPlugin(assert: true);
        $this->assertInstanceOf($this->class, $plugin);
        $this->assertMessageQueue();
        return $plugin;
    }


    public function testCanParser()
    {

        $parser = YoothemeParser::getInstance();
        $this->assertInstanceOf(BlcParserInterface::class, $parser);
        $this->assertMessageQueue();
        return $parser;
    }
    public static function canSetAltProvider()
    {
        return [

            ['',  true],
            ['fullext',  false],
            ['fullext.' . YoothemeParser::ALT_TYPE,  true],

            ['fullext.' . YoothemeParser::ALT_TYPE,  true],
            ['introtext',  false], //should never happpen in real live
            ['introtext.' . YoothemeParser::ALT_TYPE,  true], //should never happpen in real live
            ['content',  false],
            ['content.' . YoothemeParser::ALT_TYPE,  true],


            //yootheme - actually the parser will return 'true' on any field while the only field containing a yootheme layout is 'fulltext'



        ];
    }

    public static function canSetAltProviderTypeError()
    {
        return [

            [null],
            [false],
            [new \stdClass()],



            //yootheme - actually the parser will return 'true' on any field while the only field containing a yootheme layout is 'fulltext'



        ];
    }


    #[Attributes\DataProvider('canSetAltProviderTypeError')]

    public function testGetCanSetAltExecption($field)
    {
        $this->expectException(\TypeError::class);
        $parser         =  YoothemeParser::getInstance();
        $parser->getCanSetAlt($field);
    }



    #[Attributes\DataProvider('canSetAltProvider')]
    #[Attributes\Group('setAlt')]
    public function testGetCanSetAlt($field, $expected)
    {
        $parser         =  YoothemeParser::getInstance();
        $canSetAlt      = $parser->getCanSetAlt($field);
        $not = $expected ? ' ' : ' not ';
        $this->assertSame($expected, $canSetAlt, "YoothemeParser should{$not}be able to replace alt attributes for field '{$field}'");
    }

    #[Attributes\Group('setAlt')]
    #[Attributes\Depends('testextractfromSource')]
    public function testCanSetalt($data)
    {
        [$links, $source] = $data;
        $parser         =  YoothemeParser::getInstance();
        foreach ($links as $link) {
            if ($link['suffix'] === YoothemeParser::ALT_TYPE) {
                $newAnchor = $this->getRandomAlt();
                $newSource = $parser->setAltInSource($source, $link['url'], $newAnchor);
                $this->assertStringContainsString($newAnchor, $newSource, "Unable to set alt attribute $newAnchor for {$link['url']}");
            }
        }
    }
    #[Attributes\Group('extract')]
    #[Attributes\Group('setAlt')]
    public function testSetAltContent()
    {
        $config =  (array)PluginHelper::getPlugin('blc', 'content');
        $plugin         =  $this->bootPlugin(ContentPluginActor::class, $config);
        $this->app->bootComponent('com_blc')->getMVCFactory();
        //default to content just what we need
        $linkObject = $this->getSomeLinkId('yootheme',  plugin: 'content', fields: ['fulltext.' . YoothemeParser::ALT_TYPE]);


        $this->assertNotNull($linkObject, 'Link object should not be null');
        $newAlt    = $this->getRandomAlt();
        $linkItem  = $this->assertloadLinkItemID($linkObject->link_id);


        $plugin->setAlt($linkItem, $linkObject, $newAlt);

        $this->assertAltString($newAlt, $linkObject->link_id, true);
    }
    #[Attributes\Group('extract')]
    #[Attributes\Group('setAlt')]
    public function testextractfromSource()
    {
        $expectedLinks = include(JPATH_ROOT . '/blc/tests/assets/expectedYoothemeLinks.php');
        $expected = \count($expectedLinks);

        $jsonContent     = file_get_contents(JPATH_ROOT . '/blc/tests/assets/yootheme.json');
        $jsonContent     = json_encode(json_decode($jsonContent)); //make it a one liner
        $parser   = $this->testCanParser();

        $links  = $parser->extractfromSource($jsonContent);
        $urls = array_filter(array_column($links, 'url'));

        $cLinks = \count($urls);
        $this->assertEquals($expected, $cLinks, 'Incorrect number of links found:' . json_encode(array_diff($expectedLinks, $urls), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


        $jsonContent   = '<!-- ' . $jsonContent . ' -->';
        $links  = $parser->extractfromSource($jsonContent);
        $urls = array_filter(array_column($links, 'url'));

        $cLinks = \count($urls);
        $this->assertEquals($expected, $cLinks, 'Incorrect number of links found:' . json_encode(array_diff($expectedLinks, $urls), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


        return [$links, $jsonContent];
    }

    public static function fieldProvider()
    {
        return [
            ['fulltext', 'Yootheme'],

        ];
    }

    public static function pairProvider()
    {
        return include(JPATH_ROOT . '/blc/tests/assets/expectedYoothemePairs.php');
    }

    /* the yootheme parser is not an extractor. Here we test the connection between a changed content item and the yootheme parser */
    #[Attributes\Group('extract')]
    #[Attributes\DataProvider('fieldProvider')]
    public function testreplaceLink($field, $parser)
    {
        $element       = $this->element;
        $class         = $this->class;
        $this->class   = ContentPluginActor::class;
        $this->element = 'content';

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $this->assertReplaceLink($field, $parser);
        $this->element = $element;
        $this->class   = $class;
    }
    #[Attributes\Group('extract')]
    #[Attributes\DataProvider('pairProvider')]
    #[Attributes\Depends('testextractfromSource')]
    public function testcheckExtracted($url, $anchor, array $data)
    {
        [$links] = $data;

        $res =   array_filter(
            $links,
            fn($item) => $item['url'] == $url && $item['anchor'] == $anchor
        );

        $this->assertNotEmpty($res, "$url - $anchor not found");
    }

    #[Attributes\Group('extract')]
    #[Attributes\Depends('testextractfromSource')]
    public function testreplaceInSource(array $data)
    {
        [$links, $source] = $data;
        $parser           = $this->testCanParser();
        foreach ($links as $oldLink) {
            $newLink                   = $this->getRandomLink();
            $newSource                 = $parser->replaceInSource($source, $oldLink['url'], $newLink);
            preg_match('/^<!-- (\{.*\}) -->/', $newSource, $m);
            $this->assertNotEmpty($m, 'No yoothem json');
            //make it searchanle
            $newSource = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
            $this->assertStringContainsString($newLink, $newSource, "Can not replace {$oldLink['url']} with $newLink");
        }
    }


    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();
    }
    public function testonBlcParserRequest()
    {
        $this->checkonBlcParserRequest();
    }

    public static function instancesProvider()
    {
        $introtextInstanceNotYootheme = (object)['field' => 'introtext', 'parser' => 'img'];
        $fulltextInstanceNotYootheme  = (object)['field' => 'fulltext', 'parser' => 'img'];
        $fulltextInstanceYootheme  = (object)['field' => 'fulltext', 'parser' => YoothemeParser::getInstance()->getName()];
        $contentInstanceYootheme  = (object)['field' => 'fulltext', 'parser' => YoothemeParser::getInstance()->getName()];
        $contentInstanceNotYootheme  = (object)['field' => 'fulltext', 'parser' => 'href'];

        return [
            [[$introtextInstanceNotYootheme, $fulltextInstanceNotYootheme], 2],
            [[$introtextInstanceNotYootheme, $fulltextInstanceYootheme], 1],
            [[$introtextInstanceNotYootheme, $fulltextInstanceYootheme,  $fulltextInstanceYootheme], 2], //should not happen in real life
            [[$contentInstanceYootheme, $contentInstanceNotYootheme], 2], //should not happen in real life as the module will no return links for the json content

        ];
    }
    /**
     * 
     * @since 25.44.7562
     */

    #[Attributes\DataProvider('instancesProvider')]

    public function testonBlcInstanceBeforeDisplayEvent(array $instances, int $expected)
    {
        $plugin = $this->bootPlugin();
        $arguments              = [
            'subject' => $instances,
        ];
        $event = new BlcInstanceDisplayEvent('onBlcInstanceBeforeDisplayEvent', $arguments);
        $plugin->onBlcInstanceBeforeDisplayEvent($event);
        $instances = $event->getInstances();

        $this->assertCount($expected, $instances);
    }


    /**
     * 
     * @since 25.44.7562
     */

    #[Attributes\DataProvider('instancesProvider')]

    public function testonDispatchBlcInstanceBeforeDisplayEvent(array $instances, int $expected)
    {
        $this->importPlugin();

        $arguments              = [
            'subject' => $instances,
        ];
        $event = new BlcInstanceDisplayEvent('onBlcInstanceBeforeDisplayEvent', $arguments);
        $this->getDispatcher()->dispatch('onBlcInstanceBeforeDisplayEvent', $event);
        $instances = $event->getInstances();

        $this->assertCount($expected, $instances);
    }
}
