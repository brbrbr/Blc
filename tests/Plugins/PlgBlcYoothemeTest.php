<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Plugin\Blc\Yootheme\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
use PHPUnit\Framework\Attributes;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Plugin\Blc\Yootheme\Extension\YoothemeParser;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcPluginActor::class)]
#[Attributes\TestDox('Test of the BLC - Content Plugin')]
class PlgBlcYoothemeTest extends UnitTestCase
{
    private string $folder         = 'blc';
    private string $element        = 'yootheme';
    protected string $fieldContext = 'com_content.categories';

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }



    public function testCanBoot()
    {
      
        $dispatcher = $this->getDispatcher();
        $plugin     = new BlcPluginActor($dispatcher, (array)PluginHelper::getPlugin('blc', 'yootheme'));
        $plugin->setApplication($this->app);

        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
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


    public function testLinkExtraction(): array
    {

        $parser = $this->testCanParser();
        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $this->assertNotFalse($model);

        $templateTitle = JTEST_TITLE . ' yootheme Template';
        $itemTemplate = $model->getItem(['title' => $templateTitle]); //object
        $this->assertNotEmpty($itemTemplate->id, 'A item with title: ' . $templateTitle . ' is needed');
        //we want to test the json tree in fulltext, not the teaser in introtext
        preg_match('/^<!-- (\{.*\}) -->/', $itemTemplate->fulltext, $m);
        $this->assertNotEmpty($m, 'No yoothem template');
        $jsonString = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
        $this->assertNotEmpty($jsonString, 'No yootheme json');

        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] =  $this->injectLinks("<!-- {$jsonString} -->");
        $foundLinks                   = $parser->extractfromSource($itemString);

       
        $flatfoundLinks = array_column($foundLinks, 'url');
        $flatfoundAnchors = array_column($foundLinks, 'anchor');

        $this->assertNotEmpty($flatfoundLinks, 'No links found, fill the template');

        foreach ($anchors as $anchor) {
            $this->assertContains($anchor, $flatfoundAnchors);
        }

        foreach ($links as $link) {
            $this->assertContains($link, $flatfoundLinks);
        }

        return [$links, $itemString];
    }


    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $data)
    {
        [$links, $source] = $data;
        $parser = $this->testCanParser();
        foreach ($links as $oldLink) {
            ['itemString' => $newLink] =  $this->injectLinks($oldLink);
            $newSource = $parser->replaceInSource($source, $oldLink, $newLink);
            preg_match('/^<!-- (\{.*\}) -->/', $newSource, $m);
            $this->assertNotEmpty($m, 'No yoothem json');
            //make it searchanle
            $newSource = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
            $this->assertStringContainsString($newLink, $newSource);
        }
    }

    public function testContentExtraction(): array
    {
        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model, JTEST_TITLE . ' yootheme');

        return $links;
    }

    #[Attributes\Depends('testContentExtraction')]
    public function testContentLinkReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }


    public function testModuleLinkExtraction()
    {
   

        //the extractor is booted from the system/blc plugin.
        $this->testCanBoot();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_modules', 'module');
        $this->assertNotFalse($model);
        $templateId = 201;
        $testId     = 199;

        $itemTemplate = $model->getItem($templateId); //object
   
      //  preg_match('/^(?:<!-- )?(\{.*\})(?: -->)?$/', $itemTemplate->content, $m);

     //   $this->assertNotEmpty($m, 'No yoothem template');
     //  $jsonString = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
     //   $this->assertNotEmpty($jsonString, 'No yootheme json');

     //   $itemTemplate->content = "{$jsonString}";
    
        $this->assertNotEmpty($itemTemplate->id, 'A item with id: ' . $templateId . ' is needed');

        $links = $this->assertTestHtml($model, $itemTemplate, $testId);

        return $links;
    }
    #[Attributes\Depends('testModuleLinkExtraction')]
    public function testModuleLinkReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }
}
