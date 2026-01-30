<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Plugins;

use Blc\Component\Blc\Administrator\Event\BlcReportEvent;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Plugin\System\Blc\CliCommand;
use Blc\Plugin\System\Blc\Extension\Blc;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Event\Extension\AfterUninstallEvent;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
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
#[Attributes\CoversClass(CliCommand\CheckCommand::class)]
#[Attributes\CoversClass(CliCommand\ExtractCommand::class)]
#[Attributes\CoversClass(CliCommand\ReportCommand::class)]
#[Attributes\CoversClass(CliCommand\PurgeCommand::class)]
#[Attributes\TestDox('Test of the System - BLC Plugin')]
class PlgSystemBlcTest extends UnitTestCase
{
    protected string $folder  = 'system';
    protected string $element = 'blc';
    protected string $context = '';
    protected string $class   = Blc::class;
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        parent::setUp();
        $this->checkPluginEnabled();
    }

    #[Attributes\TestDox('boot the plugin')]
    public function testCanBoot()
    {
        $this->clearMessageQueue();
        $plugin =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));
        $this->assertInstanceOf(Blc::class, $plugin);
        $this->assertMessageQueue();
    }


    public function testBootService()
    {
        $provider = include(JPATH_ROOT . '/plugins//system/blc/services/provider.php');
        $provider->register($this->container);
        $plugin = $this->container->get(PluginInterface::class);

        $this->assertInstanceOf(Blc::class, $plugin);

        unset($component);
    }
    /**
     *
     * this test will impact all other funciotnality should should run importBlcPlugin but fail to do so.
     * since plugins can only be loaded once in joomla, are stored static on can't be unloaded
     * therefor all tests calling the importVBlcPlugins function should be     #[Attributes\RunInSeparateProcess]
     */
    #[Attributes\RunInSeparateProcess]
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


        $protectedMethod = (
            fn () => /** @phpstan-ignore method.notFound */
            $this->importBlcPlugins()
        );
        $protectedMethod->call($plugin, '');

        $allPlugins = array_keys(ExtensionHelper::$extensions[PluginInterface::class]);
        $blcPlugins = array_filter(
            $allPlugins,
            fn ($key) => str_ends_with((string) $key, ':blc')
        );

        $this->assertNotEmpty($blcPlugins);
        $this->assertMessageQueue();
    }
    #[Attributes\RunInSeparateProcess]
    public function testonBlcParserRequest()
    {
        $this->checkonBlcParserRequest();
    }
    #[Attributes\RunInSeparateProcess]
    public function testonBlcCheckerRequest()
    {
        $this->assertOnBlcCheckerRequest();
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
    #[Attributes\RunInSeparateProcess]
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
        $arguments = [
            'context' => 'phpunit.test',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ];


        if (version_compare(JVERSION, '5.0', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onContentAfterSave', $arguments);
        } else {
            $event     = new Model\AfterSaveEvent('onContentAfterSave', $arguments);
        }
        $this->getDispatcher()->dispatch('onContentAfterSave', $event);
        $this->getDispatcher()->removeListener('onBlcContainerChanged', [$mock, 'onBlcContainerChanged']);
    }
    #[Attributes\RunInSeparateProcess]
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
        $arguments = [
            'context' => 'phpunit.test',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ];


        if (version_compare(JVERSION, '5.0', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onContentAfterDelete', $arguments);
        } else {
            $event     = new Model\AfterDeleteEvent('onContentAfterDelete', $arguments);
        }
        $this->getDispatcher()->dispatch('onContentAfterDelete', $event);
        $this->getDispatcher()->removeListener('onBlcContainerChanged', [$mock, 'onBlcContainerChanged']);
    }
    #[Attributes\RunInSeparateProcess]
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
        $arguments =  [
            'context' => 'phpunit.test',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ];

        if (version_compare(JVERSION, '5.0', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onExtensionAfterSave', $arguments);
        } else {
            $event     = new Model\AfterSaveEvent('onExtensionAfterSave', $arguments);
        }

        $this->getDispatcher()->dispatch('onExtensionAfterSave', $event);
        $this->getDispatcher()->removeListener('onBlcExtensionAfterSave', [$mock, 'onBlcExtensionAfterSave']);
    }

    public function testonContentPrepareFormrepareFormEvent()
    {
        $this->isSubscribed('onContentPrepareForm');
        $plugin    =  $this->bootPlugin(Blc::class, (array)PluginHelper::getPlugin('system', 'blc'));
        $eventData = (object)['name' => 'plg_blc_test'];

        $form  =  $this->createStub(\Joomla\CMS\Form\Form::class);
        //     ->setConstructorArgs(['name' => 'TestForm'])
        //   ->getMock();



        $lang   =   $this->cleanLanguageStrings();
        $this->assertFalse($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));
        $plugin->onContentPrepareForm($form, $eventData);
        $this->assertTrue($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));


        $this->cleanLanguageStrings();
        $this->assertFalse($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));
        $arguments = [
            'subject' => $form,
            'data'    => $eventData,
        ];
        if (version_compare(JVERSION, '5', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onExtensionAfterSave', $arguments);
        } else {
            $event     = new Model\PrepareFormEvent('onExtensionAfterSave', $arguments);
        }
        $plugin->onContentPrepareForm($event);
        $this->assertTrue($lang->hasKey('COM_BLC_PLUGIN_ACCESS_LBL'));
    }


    public function testonAjaxBlcUpdate()
    {
        if (version_compare(JVERSION, '5', '<')) {
            $this->markTestSkipped('onAjaxBlcUpdate not implemented for Joomla 4');
        }
        $this->expectNotToPerformAssertions();
        $plugin =  $this->bootPlugin();

        $event     = new AjaxEvent('onAjaxEvent', [
            'subject' => $this->getApplication(),
        ]);


        $plugin->onAjaxBlcUpdate($event);
    }
    #[Attributes\RunInSeparateProcess]
    public function testonAjaxBlcReport()
    {
        $config              = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc');
        $plugin              =  $this->bootPlugin();

        $event     = new AjaxEvent('onAjaxEvent', [
            'subject' => $this->getApplication(),
        ]);
        $input = $this->getApplication()->getInput();
        $input->set('token', $config->get('token', null));
        $input->set('format', 'json');



        $plugin->onAjaxBlcReport($event);

        $result = $event->getArgument('result', null);

        $this->assertIsArray($result);
        $input->set('format', 'html');



        $plugin->onAjaxBlcReport($event);

        $result = $event->getArgument('result', null);

        $this->assertIsString($result);
    }







    #[Attributes\RunInSeparateProcess]
    public function testonContentChangeState()
    {
        $this->context = 'com_content.article';
        $model         = $this->getModel('com_content', 'article');
        $this->assertOnContentChangeState($model);
    }

    public function testonInstallerBeforePackageDownload()
    {
        $url     = 'https://downloads.brokenlinkchecker.dev';
        $headers = [];
        $event   = new \Joomla\CMS\Event\Installer\BeforePackageDownloadEvent('onInstallerBeforePackageDownload', [
            'url'     => $url, // @todo: Remove reference in Joomla 6, see BeforePackageDownloadEvent::__constructor()
            'headers' => $headers, // @todo: Remove reference in Joomla 6, see BeforePackageDownloadEvent::__constructor()
        ]);

        $plugin =  $this->bootPlugin();



        $plugin->onInstallerBeforePackageDownload($event);
        $newUrl = $event->getUrl();
        $this->assertStringContainsString('dlid', $newUrl);
        $newHeaders = $event->getHeaders();

        $this->assertArrayHasKey('X-BLC-KEY', $newHeaders);


        $url     = 'https://www.brokenlinkchecker.dev';
        $headers = [];
        $event   = new \Joomla\CMS\Event\Installer\BeforePackageDownloadEvent('onInstallerBeforePackageDownload', [
            'url'     => $url, // @todo: Remove reference in Joomla 6, see BeforePackageDownloadEvent::__constructor()
            'headers' => $headers, // @todo: Remove reference in Joomla 6, see BeforePackageDownloadEvent::__constructor()
        ]);
        $plugin->onInstallerBeforePackageDownload($event);
        $newUrl = $event->getUrl();
        $this->assertStringNotContainsString('dlid', $newUrl);
        $newHeaders = $event->getHeaders();

        $this->assertArrayNotHasKey('X-BLC-KEY', $newHeaders);
    }


    #[Attributes\Group('CLI')]
    public function testregisterCommands()
    {

        $app = $this->container->get(ConsoleApplication::class);


        $plugin =  $this->bootPlugin();
        //  $plugin->setApplication($app);

        $event     = new AjaxEvent('onAjaxEvent', [
            'subject' => $app,
        ]);


        $this->expectNotToPerformAssertions();

        $plugin->registerCommands($event);
    }
    #[Attributes\Group('CLI')]
    public function testExecutePurgeCommand()
    {

        $outputMock = $this->getOutputMock();
        $cmd        = new CliCommand\PurgeCommand();

        $inputMock = $this->createStub(\Symfony\Component\Console\Input\InputInterface::class);
        $result    = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::FAILURE, $result);


        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->expects($this->exactly(2))->method('getOption')
            ->willReturnOnConsecutiveCalls('orphans', 'phpunit');


        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::SUCCESS, $result);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->expects($this->exactly(2))->method('getOption')
            ->willReturnOnConsecutiveCalls('phpunit', 'phpunit');
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::INVALID, $result);
    }

    #[Attributes\Group('CLI')]
    public function testExecuteCheckCommand()
    {
        $app        = $this->container->get(ConsoleApplication::class);
        $outputMock = $this->getOutputMock();
        $cmd        = new CliCommand\CheckCommand();
        $cmd->setApplication($app);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);

        $inputMock->expects($this->exactly(3))->method('getOption')
            ->willReturnOnConsecutiveCalls(1, false, false);
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::SUCCESS, $result);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);

        $inputMock->expects($this->exactly(3))->method('getOption')
            ->willReturnOnConsecutiveCalls(1, -1, false);
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::FAILURE, $result);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);

        $inputMock->expects($this->exactly(3))->method('getOption')
            ->willReturnOnConsecutiveCalls(1, 999, false);
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::SUCCESS, $result);
    }


    #[Attributes\Group('CLI')]
    public function testExecuteExtractCommand()
    {
        $app        = $this->container->get(ConsoleApplication::class);
        $outputMock = $this->getOutputMock();
        $cmd        = new CliCommand\ExtractCommand();
        $cmd->setApplication($app);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);

        $inputMock->expects($this->once())->method('getOption')
            ->willReturnOnConsecutiveCalls(1);
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::SUCCESS, $result);
    }
    protected function getOutputMock()
    {
        $outputMock          = $this->createStub(\Symfony\Component\Console\Output\OutputInterface::class);
        $outputInterfaceMock = $this->createStub(\Symfony\Component\Console\Formatter\OutputFormatterInterface::class);
        $outputMock->method('getFormatter')
            ->willReturn($outputInterfaceMock);
        $outputInterfaceMock->method('isDecorated')
            ->willReturn(true);
        return $outputMock;
    }

    #[Attributes\Group('CLI')]
    public function testExecuteReportCommand()
    {
        $app        = $this->container->get(ConsoleApplication::class);
        $outputMock = $this->getOutputMock();
        $cmd        = new CliCommand\ReportCommand();
        $cmd->setApplication($app);

        $inputMock = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $inputMock->expects($this->exactly(2))->method('getOption')
            ->willReturnOnConsecutiveCalls(true, false);
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::SUCCESS, $result);
        $result = $cmd->execute($inputMock, $outputMock);
        $this->assertEquals(\Symfony\Component\Console\Command\Command::SUCCESS, $result);
    }

    public function testonAjaxBlcCheck()
    {

        $app = $this->getApplicationWithoutExit();


        $plugin =  $this->bootPlugin();
        $plugin->setApplication($app);

        $this->expectNotToPerformAssertions();
        ob_start();
        $plugin->onAjaxBlcCheck();
        ob_get_clean();
    }

    public function testonBlcReport()
    {
        $arguments =
            [
                'action' => 'check',
                'client' => 'CLI',
                'format' => 'json',
            ];


        $event = new BlcReportEvent('onBlcReport', $arguments);
        $this->getApplication()->getDispatcher()->dispatch('onBlcReport', $event);
        $data = $event->getReport();
        $this->assertIsArray($data);
    }
    /**
     *
     * code coverage
     */

    public function testonAjaxBlcExtract()
    {

        $app = $this->getApplicationWithoutExit();

        $config              = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc');
        $plugin              =  $this->bootPlugin();
        $plugin->setApplication($app);

        $event     = new AjaxEvent('onAjaxEvent', [
            'subject' => $app,
        ]);
        $input = $app->getInput();
        $input->set('token', $config->get('token', null));
        $input->set('format', 'json');

        $this->expectNotToPerformAssertions();
        ob_start();
        $input->set('format', 'json');
        $plugin->onAjaxBlcExtract($event);
        $input->set('format', 'html');
        $plugin->onAjaxBlcExtract($event);
        $input->set('format', 'raw');
        $plugin->onAjaxBlcExtract($event);
        $input->set('format', '');
        $plugin->onAjaxBlcExtract($event);
        ob_get_clean();
    }



    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }


    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();
        $this->enableBlc(false);
        $this->assertSubscribedEvents(true);
        $this->enableBlc(true);
    }



    public function testonExtensionAfterUninstall()
    {
        $plugin    =  $this->bootPlugin();

        $installer = $this->createStub(Installer::class);

        //coverage for folder
        $event = $this->createMock(AfterUninstallEvent::class);
        $event->expects($this->once())->method('getInstaller')->willReturn($installer);
        $plugin->onExtensionAfterUninstall($event);

        $installer->extension = (object) [
            'folder'  => 'blc',
            'element' => 'phpunit',

        ];
        $event = $this->createMock(AfterUninstallEvent::class);
        $event->expects($this->once())->method('getInstaller')->willReturn($installer);

        $plugin->onExtensionAfterUninstall($event);
        $this->assertMessageQueue('info', empty: false);
    }

    public function testonExtensionAfterUninstallJ4()
    {
        $plugin =  $this->bootPlugin();
        //code coverage
        $event = $this->createMock(Event::class);
        $event->expects($this->once())->method('getArguments')->willReturn([]);
        $plugin->onExtensionAfterUninstall($event);

        $event     = $this->createMock(Event::class);
        $installer = $this->createStub(Installer::class);
        $event->expects($this->once())->method('getArguments')->willReturn([$installer]);
        //  $this->expectNotToPerformAssertions();

        $plugin->onExtensionAfterUninstall($event);
    }
}
