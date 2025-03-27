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

use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Plugin\System\Blc\Extension\Blc;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Extension\PluginInterface;
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
#[Attributes\CoversClass(Blc::class)]
#[Attributes\TestDox('Test of the System - BLC Plugin')]
class PlgSystemBlcTest extends UnitTestCase
{
    protected string $folder  = 'system';
    protected string $element = 'blc';
    protected string $class   = Blc::class;
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }

    #[Attributes\TestDox('boot the plugin')]
    public function testCanBoot()
    {
        $this->clearMessageQueue();
        $plugin =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));
        $this->assertInstanceOf(Blc::class, $plugin);
        $this->assertMessageQueue();
    }

    public function testimportBlcPlugins()
    {
        $this->clearMessageQueue();
        /* doesn't work as ExtensionHelper is preserved between test calls
          $allPlugins = array_keys(ExtensionHelper::$extensions[PluginInterface::class]);
          $blcPlugins = array_filter(
              $allPlugins,
              function ($key) {
                  return str_ends_with($key, ':blc');
              }
          );

          $this->assertEmpty($blcPlugins, var_export($blcPlugins, true));
          */
        $plugin =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));


        $protectedMethod = (fn () => /** @phpstan-ignore method.notFound */
        $this->importBlcPlugins());
        $protectedMethod->call($plugin, '');

        $allPlugins = array_keys(ExtensionHelper::$extensions[PluginInterface::class]);
        $blcPlugins = array_filter(
            $allPlugins,
            fn ($key) => str_ends_with($key, ':blc')
        );

        $this->assertNotEmpty($blcPlugins);
        $this->assertMessageQueue();
    }

    public function testonBlcParserRequest()
    {
        $this->checkonBlcParserRequest();
    }

    public function testcheckBlcCheckerRequest()
    {
        $this->checkBlcCheckerRequest();
    }
    public function testonGetIcons()
    {
        $this->clearMessageQueue();
        $plugin =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));

        $this->isSubscribed('onGetIcons');

        $event = new \Joomla\Module\Quickicon\Administrator\Event\QuickIconsEvent('onGetIcons', ['context' => 0]);
        $plugin->onGetIcons($event);
        $this->assertEmpty($event->getArgument('result'), "testonGetIcons context = 0");

        $event = new \Joomla\Module\Quickicon\Administrator\Event\QuickIconsEvent('onGetIcons', ['context' => '2wefsdfwerwerwer']);
        $plugin->onGetIcons($event);
        $this->assertEmpty($event->getArgument('result'), "testonGetIcons context = nonexsisten");

        $context = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc')->get('quickicon', 'system_quickicon');
        if ($context !== 0) {
            $event = new \Joomla\Module\Quickicon\Administrator\Event\QuickIconsEvent('onGetIcons', ['context' => $context]);
            $plugin->onGetIcons($event);
            $this->assertNotEmpty($event->getArgument('result'), "testonGetIcons context = $context");
        }

        $this->getDispatcher()->dispatch('onGetIcons', $event);
        $this->assertMessageQueue();
    }
    #[Attributes\Group('BlcExtractInterface')]
    public function testonContentAfterSave()
    {
        $this->isSubscribed('onContentAfterSave');
        $mock = $this->getMockBuilder(BlcExtractInterface::class)->getMock();
        $mock->expects($this->once())->method('onBlcContainerChanged')
            ->willReturnCallback(
                function ($event) {
                    $this->assertSame('phpunit.test', $event->getContext());
                    $this->assertSame('onsave', $event->getEvent());
                    $this->assertSame(-1, $event->getId());
                    return;
                }
            );


        $this->getDispatcher()->addListener('onBlcContainerChanged', [$mock, 'onBlcContainerChanged']);
        $table     = $this->createStub(\Joomla\CMS\Table\Table::class);
        $table->id = -1;
        $event     = new Model\AfterSaveEvent('onContentAfterSave', [
            'context' => 'phpunit.test',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ]);

        $this->getDispatcher()->dispatch('onContentAfterSave', $event);
        $this->getDispatcher()->removeListener('onBlcContainerChanged', [$mock, 'onBlcContainerChanged']);
    }

    #[Attributes\Group('BlcExtractInterface')]
    public function testonContentAfterDelete()
    {
        $this->isSubscribed('onContentAfterDelete');
        $mock = $this->getMockBuilder(BlcExtractInterface::class)->getMock();
        $mock->expects($this->once())->method('onBlcContainerChanged')->willReturnCallback(
            function ($event) {
                $this->assertSame('phpunit.test', $event->getContext());
                $this->assertSame('ondelete', $event->getEvent());
                $this->assertSame(-1, $event->getId());
                return;
            }
        );

        $this->getDispatcher()->addListener('onBlcContainerChanged', [$mock, 'onBlcContainerChanged']);
        $table     = $this->createStub(\Joomla\CMS\Table\Table::class);
        $table->id = -1;
        $event     = new Model\AfterDeleteEvent('onContentAfterDelete', [
            'context' => 'phpunit.test',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ]);

        $this->getDispatcher()->dispatch('onContentAfterDelete', $event);
        $this->getDispatcher()->removeListener('onBlcContainerChanged', [$mock, 'onBlcContainerChanged']);
    }

    #[Attributes\Group('BlcExtractInterface')]
    public function testonExtensionAfterSave()
    {
        $this->isSubscribed('onExtensionAfterSave');
        $mock = $this->getMockBuilder(BlcExtractInterface::class)->getMock();
        $mock->expects($this->once())->method('onBlcExtensionAfterSave')
            ->willReturnCallback(
                function ($event) {
                    $this->assertSame('phpunit.test', $event->getContext());
                    $this->assertSame('onextension', $event->getEvent());
                    $this->assertSame(-1, $event->getItem()->id);
                    return;
                }
            );

        $this->getDispatcher()->addListener('onBlcExtensionAfterSave', [$mock, 'onBlcExtensionAfterSave']);
        $table     = $this->createStub(\Joomla\CMS\Table\Table::class);
        $table->id = -1;
        $event     = new Model\AfterSaveEvent('onExtensionAfterSave', [
            'context' => 'phpunit.test',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ]);

        $this->getDispatcher()->dispatch('onExtensionAfterSave', $event);

        $this->getDispatcher()->removeListener('onBlcExtensionAfterSave', [$mock, 'onBlcExtensionAfterSave']);
    }

    public function testonContentPrepareFormrepareFormEvent()
    {
        $this->isSubscribed('onContentPrepareForm');
        $plugin    =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));
        $eventData = (object)['name' => 'plg_blc_test'];

        $form  =  $this->getMockBuilder(\Joomla\CMS\Form\Form::class)
            ->setConstructorArgs(['name' => 'TestForm'])
            ->getMock();

        $lang   =   $this->cleanLanguageStrings();
        $this->assertFalse($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));

        $event     = new Model\PrepareFormEvent('onExtensionAfterSave', [
            'context' => '',
            'subject' => $form,
            'name'    => 'onContentPrepareForm',
            'data'    => $eventData,
        ]);
        $plugin->onContentPrepareForm($event);
        $this->assertTrue($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));

        $lang   =   $this->cleanLanguageStrings();
        $this->assertFalse($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));
        $plugin->onContentPrepareForm($form, $eventData);



        if (version_compare(JVERSION, '5', 'lt')) {
            $this->cleanLanguageStrings();
            $this->assertFalse($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));

            $event     = new \Joomla\Event\Event('onExtensionAfterSave', [
                $form,
                $eventData,
            ]);
            $plugin->onContentPrepareForm($event);
            $this->assertTrue($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));
        }
    }


    public function testonAjaxBlcUpdate()
    {
        $this->expectNotToPerformAssertions();
        $plugin =  $this->bootPlugin();

        $event     = new AjaxEvent('onAjaxEvent', [
            'subject' => $this->getApplication(),
        ]);


        $plugin->onAjaxBlcUpdate($event);
    }

    public function testonAjaxBlcReport()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testenhanceTaskItemForm()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testadvertiseRoutines()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function teststandardRoutineHandler()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testsetDatabase()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonContentChangeState()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonInstallerBeforePackageDownload()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonBlcCheckerRequest()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testregisterCommands()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonAjaxBlcCheck()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonBlcReport()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonAjaxBlcExtract()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }





    public function testgetSubscribedEvents()
    {
        $this->getSubscribedEvents();
        $this->enableBlc(false);
        $this->getSubscribedEvents(true);
        $this->enableBlc(true);
    }

    public function testonContentPrepareForm()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testonExtensionAfterUninstall()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}
