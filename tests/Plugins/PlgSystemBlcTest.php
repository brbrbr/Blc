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

use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Plugin\System\Blc\Extension\Blc;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Event\Model;
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


    public function testgetSubscribedEvents()
    {
        $this->clearMessageQueue();
        $plugin =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));
        $events = $plugin::getSubscribedEvents();
        $this->assertNotEmpty($events);
        $this->assertMessageQueue();
    }

    public function testonGetIcons()
    {
        $this->clearMessageQueue();
        $plugin =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));
        $events = $plugin::getSubscribedEvents();
        $this->assertArrayHasKey('onGetIcons', $events);

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
}
