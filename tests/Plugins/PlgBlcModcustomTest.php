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

use Blc\Plugin\Blc\ModCustom\Extension\BlcPluginActor;
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
class PlgBlcModcustomTest extends UnitTestCase
{
    private string $folder  = 'blc';
    private string $element = 'modcustom';

    protected string $fieldContext = 'com_content.categories';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'modcustom'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }

    public function testLinkExtraction()
    {
        //the extractor is booted from the system/blc plugin.
        $this->testCanBoot();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_modules', 'module');
        $this->assertNotFalse($model);
        $templateId=198;
        $testId=199;
    
        $itemTemplate = $model->getItem($templateId); //object
     
        $this->assertNotEmpty($itemTemplate->id, 'A item with id: ' . $templateId . ' is needed');

        $links = $this->assertTestHtml($model,  $itemTemplate,$testId);
    
        return $links;
   
    }
    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $this->assertLinkReplace($urls);
    }
}



