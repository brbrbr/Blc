<?php

/**
 * @version   __DEPLOY_VERSION__
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
    private string $folder  = 'blc';
    private string $element = 'yootheme';
    protected string $fieldContext = 'com_content.categories';

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }



    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'yootheme'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }


    public function testLinkExtraction(): array
    {

        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model,JTEST_TITLE. ' yootheme');

        $templateTitle = JTEST_TITLE . ' yootheme Template';
   
        $itemTemplate = $model->getItem(['title' => $templateTitle]); //object
     
        $this->assertNotEmpty($itemTemplate->id, 'A item with title: ' . $templateTitle . ' is needed');
        //we want to test the json tree in fulltext, not the teaser in introtext
        preg_match('/^<!-- (\{.*\}) -->/',$itemTemplate->fulltext,$m);
        $this->assertNotEmpty($m,'No yoothem template');
        $jsonString=json_encode(json_decode($m[1]),JSON_UNESCAPED_SLASHES);
        $this->assertNotEmpty($jsonString,'No yootheme json');
      
        unset($itemTemplate->articletext);
        $itemTemplate->introtext='';
        $itemTemplate->fulltext="<!-- {$jsonString} -->";
        $links= $this->assertTestHtml($model,  $itemTemplate);
        $this->assertNotEmpty($links,'No links found, fill the template');
      
        return $links;
       
    }


    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $this->assertLinkReplace($urls);
    }


    public function testModuleLinkExtraction()
    {
        //the extractor is booted from the system/blc plugin.
        $this->testCanBoot();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_modules', 'module');
        $this->assertNotFalse($model);
        $templateId=201;
        $testId=199;
    
        $itemTemplate = $model->getItem($templateId); //object

        preg_match('/^(?:<!-- )?(\{.*\})(?: -->)?$/',$itemTemplate->content,$m);

        $this->assertNotEmpty($m,'No yoothem template');
        $jsonString=json_encode(json_decode($m[1]),JSON_UNESCAPED_SLASHES);
        $this->assertNotEmpty($jsonString,'No yootheme json');
      
  
        $itemTemplate->content="{$jsonString}";
     
        $this->assertNotEmpty($itemTemplate->id, 'A item with id: ' . $templateId . ' is needed');

        $links = $this->assertTestHtml($model,  $itemTemplate,$testId);
       
    
        return $links;
   
    }
    #[Attributes\Depends('testModuleLinkExtraction')]
    public function testModuleLinkReplace(array $urls)
    {
        $this->assertLinkReplace($urls);
    }
        
}
