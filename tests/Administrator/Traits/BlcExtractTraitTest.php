<?php

declare(strict_types=1);

namespace Blc\Tests\Administrator\Traits;

use Blc\Component\Blc\Administrator\Event;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

class BlcExtractTraitTest extends UnitTestCase
{
    protected string $class          = BlcExtractTraitTest::class;
    protected string $folder         = 'blc';
    protected string $element        = 'phpunit';
    protected string $context        = 'blc.phpunit';

    public function setUp(): void
    {
        $this->initApplication();
    }
    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();
    }

    public function testMagicGet()
    {
        $this->assertMagicGet();
    }


    /**
     * BlcExtractInterface
     *
     */
    public function testgetViewLink()
    {
        $this->expectException(\RuntimeException::class);
        $instance                                                              = new \stdClass();
        $instance->container_id                                                = 999;
        $plugin                                                                = $this->bootPlugin();
        $plugin->getViewLink($instance);
    }

    public function testgetEditLink()
    {
        $this->expectException(\RuntimeException::class);
        $instance                                                              = new \stdClass();
        $instance->container_id                                                = 999;
        $plugin                                                                = $this->bootPlugin();
        $plugin->getEditLink($instance);
    }

    public function testgetTitle()
    {
        $this->expectException(\RuntimeException::class);
        $instance                                                              = new \stdClass();
        $instance->container_id                                                = 999;
        $plugin                                                                = $this->bootPlugin();
        $plugin->getTitle($instance);
    }
    /**
     *
     * test for code coverage as we can't recheck an item without a component item
     */
    public function testonBlcExtract()
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $this->isSubscribed('onBlcExtract');
        $plugin = $this->bootPlugin();



        $arguments =
            [
                'maxExtract' => 10,
            ];

        $event = new Event\BlcExtractEvent('onBlcExtract', $arguments);
        $this->expectException(\RuntimeException::class);
        $plugin->onBlcExtract($event);
    }
    /**
     *
     * code coverage as we can't recheck an item without a component item
     */
    public function testonBlcContainerChanged()
    {

        $this->clearMessageQueue();
        $this->isSubscribed('onBlcContainerChanged');

        $plugin                                                                = $this->bootPlugin();

        $arguments =
            [
                'context' => $this->context,
                'id'      => 99999,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);

        $plugin->params->set('onsave', 'nothing');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', false);

        $plugin->params->set('onsave', 'delete');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', false);

        $this->expectException(\RuntimeException::class);
        $plugin->params->set('onsave', 'parse');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', false);
    }

    /**
     *
     * code coverage as we can't recheck an item without a component item
     */
    public function testonBlcContainerChangedNoId()
    {

        $this->clearMessageQueue();
        $this->isSubscribed('onBlcContainerChanged');

        $plugin                                                                = $this->bootPlugin();

        $arguments =
            [
                'context' => $this->context,
                'id'      => 0,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);

        $plugin->params->set('onsave', 'nothing');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', true);
    }

    /**
     *
     * code coverage as we can't recheck an item without a component item
     */
    public function testonBlcContainerChangedContext()
    {

        $this->clearMessageQueue();
        $this->isSubscribed('onBlcContainerChanged');

        $plugin                                                                = $this->bootPlugin();

        $arguments =
            [
                'context' => 'blc.system',
                'id'      => 99999,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);

        $plugin->params->set('onsave', 'nothing');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', true);
    }

    protected function bootPlugin(?string $class = null, ?array $config = null, bool $assert = false)
    {

        $class ??= $this->class;
        if ($class !== $this->class) {
            parent::bootPlugin($class, $config, $assert);
        }


        $config = [
            'type'   => $this->folder,
            'name'   => $this->element,
            'params' => '{"check_catid":1,"article_alias":2,"category_alias":2,"check_lang":1,"enablecf":1,"cf":{"editor":2,"textarea":2,"text":2,"url":2,"extraurl":["14"],"media":2,"mediajce":2,"subform":2},"access":-1,"published":-1,"onsave":"-1","ondelete":"-1","deleteonsavepugin":1}',
            'id'     => 10285,
        ];

        $plugin = new class ($config) extends CMSPlugin {
            use DatabaseAwareTrait;
            use BlcExtractTrait;

            protected string $context        = 'blc.phpunit';
            public $componentConfig;

            public function __construct(array $config = [])
            {
                if (version_compare(JVERSION, '5.3', '<')) {
                    $subject =  Factory::getApplication()->getDispatcher();
                    parent::__construct($subject, $config);
                } else {
                    parent::__construct($config);
                }
                $this->params          = new Registry();
                $this->componentConfig =  ComponentHelper::getParams('com_blc');
            }
        };
        $plugin->setApplication($this->app);
        $plugin->setDatabase($this->getDatabase());

        return $plugin;
    }

    public function testonBlcExtensionAfterSave()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin = $this->bootPlugin();

        $tableStub     = $this->createStub(\Joomla\CMS\Table\Extension::class);

        $tableStub->type    = 'plugin';
        $tableStub->element = $this->element;
        $tableStub->folder  = $this->folder;
         if ($plugin->params instanceof Registry) {
            $tableStub->params = clone $plugin->params;
        } else {
            $tableStub->params = new Registry($plugin->params);
        }

        $tableStub->enabled = 0;
        $tableStub->id      = -1;

        $arguments =
            [
                'context' => $this->context,
                'item'    => $tableStub,
                'event'   => 'onextension',
            ];


        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', false);

        $tableStub->enabled = 1;

        $arguments =
            [
                'context' => $this->context,
                'item'    => $tableStub,
                'event'   => 'onextension',
            ];
        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);

        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', true);
    }


    public function testonBlcExtensionAfterSaveWrongType()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin = $this->bootPlugin();

        $tableStub     = $this->createStub(\Joomla\CMS\Table\Extension::class);


        $tableStub->type    = 'module';
        $tableStub->element = $this->element;
        $tableStub->folder  = $this->folder;
        $tableStub->params  = new Registry($plugin->params);
        $tableStub->enabled = 1;
        $tableStub->id      = -1;

        $arguments =
            [
                'context' => $this->context,
                'item'    => $tableStub,
                'event'   => 'onextension',
            ];


        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', true);
    }

    public function testonBlcExtensionAfterSaveWrongElement()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin = $this->bootPlugin();

        $tableStub     = $this->createStub(\Joomla\CMS\Table\Extension::class);

        $tableStub->type    = 'plugin';
        $tableStub->element = 'x' . $this->element;
        $tableStub->folder  = $this->folder;
       if ($plugin->params instanceof Registry) {
            $tableStub->params = clone $plugin->params;
        } else {
            $tableStub->params = new Registry($plugin->params);
        }

        $tableStub->enabled = 1;
        $tableStub->id      = -1;

        $arguments =
            [
                'context' => $this->context,
                'item'    => $tableStub,
                'event'   => 'onextension',
            ];


        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', true);
    }

    public function testonBlcExtensionAfterSaveWrongFolder()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin = $this->bootPlugin();

        $tableStub     = $this->createStub(\Joomla\CMS\Table\Extension::class);


        $tableStub->type    = 'plugin';
        $tableStub->element = $this->element;
        $tableStub->folder  = 'x' . $this->folder;
         if ($plugin->params instanceof Registry) {
            $tableStub->params = clone $plugin->params;
        } else {
            $tableStub->params = new Registry($plugin->params);
        }

        $tableStub->enabled = 1;
        $tableStub->id      = -1;

        $arguments =
            [
                'context' => $this->context,
                'item'    => $tableStub,
                'event'   => 'onextension',
            ];


        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', true);
    }

    public function testonBlcExtensionAfterSaveEmptyParams()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin = $this->bootPlugin();

        $tableStub     = $this->createStub(\Joomla\CMS\Table\Extension::class);

        $tableStub->type    = 'plugin';
        $tableStub->element = $this->element;
        $tableStub->folder  = $this->folder;
        unset($tableStub->params, $plugin->params);

        $tableStub->enabled = 1;
        $tableStub->id      = -1;

        $arguments =
            [
                'context' => $this->context,
                'item'    => $tableStub,
                'event'   => 'onextension',
            ];


        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', true);
    }

    public function testonExtensionAfterSave()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin = $this->bootPlugin();

        $tableStub     = $this->createStub(\Joomla\CMS\Table\Extension::class);
        $tableStub->type    = 'plugin';
        $tableStub->element = $this->element;
        $tableStub->folder  = $this->folder;
        if ($plugin->params instanceof Registry) {
            $tableStub->params = clone $plugin->params;
        } else {
            $tableStub->params = new Registry($plugin->params);
        }

        $tableStub->enabled = 0;
        $tableStub->id      = -1;
        $tableStub->params->set('deleteonsavepugin', 1);
        $tableStub->params->set('dummy', 1); //ensure the params are different

        $arguments =  [
            'context' => $this->context,
            'subject' => $tableStub,
            'isNew'   => false,
            'data'    => [],
        ];

        if (version_compare(JVERSION, '5.0', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onExtensionAfterSave', $arguments);
        } else {
            $event     = new Model\AfterSaveEvent('onExtensionAfterSave', $arguments);
        }
        $this->clearMessageQueue();

        $this->getDispatcher()->addListener('onBlcExtensionAfterSave', [$plugin, 'onBlcExtensionAfterSave']);
        $this->getDispatcher()->dispatch('onExtensionAfterSave', $event);
        $this->getDispatcher()->removeListener('onBlcExtensionAfterSave', [$plugin, 'onBlcExtensionAfterSave']);
        $this->assertMessageQueue('info', false);
    }

    public function testpluginCanReplaceLink()
    {
        $plugin = $this->bootPlugin();
        $plugin->params->set('plugin_can_replace_link', 0);
        $this->assertFalse($plugin->pluginCanReplaceLink());

        $plugin->componentConfig->set('plugin_can_replace_link', 1);
        $this->assertFalse($plugin->pluginCanReplaceLink());

        $plugin->params->set('plugin_can_replace_link', 1);
        $this->assertTrue($plugin->pluginCanReplaceLink());

        $plugin->componentConfig->set('plugin_can_replace_link', 0);
        $this->assertTrue($plugin->pluginCanReplaceLink());

        $plugin->params->set('plugin_can_replace_link', -1);

        $plugin->componentConfig->set('plugin_can_replace_link', 0);
        $this->assertFalse($plugin->pluginCanReplaceLink());

        $plugin->componentConfig->set('plugin_can_replace_link', 1);
        $this->assertTrue($plugin->pluginCanReplaceLink());
    }
}
