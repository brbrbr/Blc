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

use Blc\Component\Blc\Administrator\Event;
use Blc\Plugin\Blc\Category\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
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
class PlgBlcCategoryTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'category';
    protected string $class   = BlcPluginActor::class;

    protected string $fieldContext = 'com_content.categories';
    protected string $context      = 'com_categories.category';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }

    public function testLinkExtraction()
    {
        //the extractor is booted from the system/blc plugin.
        $this->bootPlugin();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_categories', 'Category');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model);
        return $links;
    }


    #[Attributes\Depends('testLinkExtraction')]
    public function testreplaceLink(array $urls)
    {
        $this->assertLinksReplace($urls);
    }



    public function testonBlcExtract()
    {
        $this->isSubscribed('onBlcExtract');
        $model                                                                 = $this->getModel('com_categories', 'Category');
        $plugin                                                                = $this->importPlugin(element: $this->element);
        $itemTest                                                              = (object)$this->getTestItem($model);
        $this->assertNotNull($itemTest);
        //rsevents do not have a modified date
        $this->clearSynch($itemTest->id, 'category');

        $arguments =
            [
                'maxExtract' => 10,
            ];

        $event = new Event\BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);
        $parsed = $event->getDidExtract();
        $this->assertNotEquals($parsed, 0);
        $this->assertMessageQueue();
    }


    public function testgetSubscribedEvents()
    {
        $this->getSubscribedEvents();
    }
    protected function getCategoryTestItem()
    {
        $model                                                                 = $this->getModel('com_categories', 'Category');
        $itemTest                                                              = (object)$this->getTestItem($model);
        $this->assertNotNull($itemTest);
        return $itemTest;
    }


    public function testgetExtension()
    {
        $itemTest                                                                   = $this->getCategoryTestItem();
        $plugin                                                                     = $this->bootPlugin();

        $instance               = new \stdClass();
        $instance->container_id = $itemTest->id;
        $extension              = $plugin->getExtension($instance);
        $this->assertSame($extension, $itemTest->extension);
    }

    public function testgetEditLink()
    {
        $itemTest                                                                    = $this->getCategoryTestItem();
        $plugin                                                                      = $this->bootPlugin();

        $instance               = new \stdClass();
        $instance->container_id = $itemTest->id;
        $link                   = $plugin->getEditLink($instance);
        $this->assertNotEmpty($link);
    }

    public function testgetViewLink()
    {
        $itemTest                                                              = $this->getCategoryTestItem();
        $plugin                                                                = $this->bootPlugin();

        $instance               = new \stdClass();
        $instance->container_id = $itemTest->id;
        $link                   = $plugin->getViewLink($instance);
        $this->assertNotEmpty($link);
    }

    public function testMagicGet()
    {
        $this->doMagicGetTest();
    }



    public function testgetTitle()
    {
        $itemTest                                                              = $this->getCategoryTestItem();
        $plugin                                                                = $this->bootPlugin();
        $instance                                                              = new \stdClass();
        $instance->container_id                                                = $itemTest->id;
        $link                                                                  = $plugin->getTitle($instance);
        $this->assertNotEmpty($link);
    }



    public function testonBlcContainerChanged()
    {
        $this->clearMessageQueue();
        $this->isSubscribed('onBlcContainerChanged');
        $itemTest                                                              = $this->getCategoryTestItem();
        $plugin                                                                = $this->bootPlugin();


        $arguments =
            [
                'context' => $this->context,
                'id'      => $itemTest->id,
                'event'   => 'onsave', // treat as a delete. So we do not have to worry about the current state. The next extract will figure it out
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);
        $plugin->params->set('onsave', 'parse');
        $plugin->onBlcContainerChanged($event);
        $plugin->params->set('onsave', 'delete');
        $plugin->onBlcContainerChanged($event);
        $plugin->params->set('onsave', 'nothing');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', false);
    }
    public function testgetHelpLink()
    {
        $this->getHelpLink();
    }


    public function testreplaceCustomFieldLink()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonBlcExtensionAfterSave()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
