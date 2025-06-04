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
    #[Attributes\Group('setAlt')]
    public function testGetCanSetAlt()
    {
        $parser         =  YoothemeParser::getInstance();
        $canSetAlt      = $parser->getCanSetAlt();
        $this->assertTrue($canSetAlt, 'YoothemeParser should be able to replace alt attributes');
    }

    #[Attributes\Group('setAlt')]
    #[Attributes\Depends('testextractfromSource')]
    public function testCanSetalt($data)
    {
        [$links, $source] = $data;
        $parser         =  YoothemeParser::getInstance();
        foreach ($links as $link) {
            if ($link['suffix'] === 'img') {
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
        $linkObject = $this->getSomeLinkId('yootheme',  plugin: 'content', fields: ['fulltext.img']);


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
           $expectedLinks = include (JPATH_ROOT . '/blc/tests/assets/expectedYoothemeLinks.php');
        $expected = \count($expectedLinks);
        
        $jsonContent     = file_get_contents(JPATH_ROOT . '/blc/tests/assets/yootheme.json');
        $jsonContent     = json_encode(json_decode($jsonContent)); //make it a one liner
        $parser   = $this->testCanParser();

        $links  = $parser->extractfromSource($jsonContent);
        $urls = array_filter(array_column($links, 'url'));

        $cLinks = \count($urls);
        $this->assertEquals($expected, $cLinks, 'Incorrect number of links found:' . json_encode(array_diff($expectedLinks,$urls), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


        $jsonContent   = '<!-- ' . $jsonContent . ' -->';
        $links  = $parser->extractfromSource($jsonContent);
        $urls = array_filter(array_column($links, 'url'));

        $cLinks = \count($urls);
        $this->assertEquals($expected, $cLinks, 'Incorrect number of links found:' . json_encode(array_diff( $expectedLinks,$urls), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


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
        return include (JPATH_ROOT . '/blc/tests/assets/expectedYoothemePairs.php');
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

        $this->assertNotEmpty($res,"$url - $anchor not found");
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
}
