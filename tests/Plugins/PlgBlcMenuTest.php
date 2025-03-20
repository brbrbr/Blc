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


use Blc\Plugin\Blc\Menu\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Blc\Component\Blc\Administrator\Event;
/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcPluginActor::class)]
#[Attributes\TestDox('Test of the BLC - Invalid Plugin')]
class PlgBlcMenuTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'menu';
    protected string $class   = BlcPluginActor::class;


   
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }


    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }
    public function testLinkExtraction()
    {
        //the extractor is booted from the system/blc plugin.
        $plugin = $this->importPlugin();
        $plugin->params->set('onsave','parse');
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_menus', 'Item');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model);
        return $links;
    }
   
    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $plugin = $this->importPlugin();
        $plugin->params->set('onsave','parse');
        $this->assertLinksReplace($urls);
    }

    public function testgetSubscribedEvents()
    {
        $this->getSubscribedEvents();
    }


    public function testCanExtractEvent()
    {
        $model = $this->getModel('com_menus', 'Item');
        $this->assertNotNull($model);
        $plugin                                                                = $this->importPlugin(element: $this->element);
        $itemTest                                                              = (object)$this->getTestItem($model);
        $this->assertNotNull($itemTest);
        $this->assertNotEquals(0,$itemTest->id);
        //rsevents do not have a modified date
        $this->clearSynch($itemTest->id, 'menu');

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





}
