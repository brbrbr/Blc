<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Blc\BlcMessages;
use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcParserRequestEvent;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Extension\DummyPlugin;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\ArrayHelper;
use PHPUnit\Framework\TestCase;

/**
 * Base Unit Test case for common behaviour across unit tests
 *
 * @since   4.0.0
 */
abstract class UnitTestCase extends TestCase
{
    protected string $folder  = '';
    protected string $element = '';
    protected string $class   = '';
    protected DatabaseInterface $db;
    protected ?CMSApplicationInterface $app = null;
    protected DispatcherInterface $dispatcher;
    protected Container $container;
    protected string $fieldContext = '';
    protected function getDispatcher()
    {

        return $this->dispatcher;
    }

    protected function getContainer()
    {

        return $this->container;
    }

    protected function getApplication()
    {
        return $this->app;
    }

    protected function getDatabase()
    {
        return $this->db;
    }
    public function tearDown(): void
    {
        $this->closeApplication();
    }

    protected function closeApplication(): void
    {
        unset($this->db, $this->container, $this->app);


        $this->app = null;
    }

    protected function initApplication(string $client = 'administrator'): void
    {

        if ($this->app instanceof Application) {
            return;
        }

        $_SERVER['HTTP_HOST']   = 'www.example.com:443';
        $_SERVER['SCRIPT_NAME'] = '/';
        $_SERVER['PHP_SELF']    = '/index.php';
        $_SERVER['REQUEST_URI'] = '/';

        $this->container = Factory::getContainer();
        $this->container->alias('session', 'session.cli')
            ->alias('JSession', 'session.cli')
            ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
            ->alias(\Joomla\Session\Session::class, 'session.cli')
            ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
        if ($client == 'administrator') {
            $this->app  = $this->container->get(AdministratorApplication::class);
        } else {

            $this->app  = $this->container->get(SiteApplication::class);
        }
        $lang       = $this->container->get(LanguageFactoryInterface::class)->createLanguage($this->app->get('language'), $this->app->get('debug_lang'));

        // Load the language to the API
        $this->app->loadLanguage($lang);
        $lang      = $this->app->getLanguage();
        $lang->load('com_blc', JPATH_ADMINISTRATOR);

        // Register the language object with Factory
        // Factory::$language = $this->app->getLanguage();
        Factory::$application = $this->app;
        $this->app->loadDocument();

        $this->db         = Factory::getContainer()->get(DatabaseInterface::class);
        $this->dispatcher = $this->container->get(DispatcherInterface::class);
        //to prevent a warning: Test code or tested code did not close its own output buffers
        $this->app->set('debug', false);
        // Load the behaviour plugins
        PluginHelper::importPlugin('behaviour', null, true, $this->getDispatcher());

        // Trigger the onAfterInitialise event.
        PluginHelper::importPlugin('system', null, true, $this->getDispatcher());
        if (version_compare(JVERSION, '5.0', '<')) {
            /** @disregard */
            $this->app->triggerEvent('onAfterInitialise');
        } else {
            $this->getDispatcher()->dispatch(
                'onAfterInitialise',
                new AfterInitialiseEvent('onAfterInitialise', ['subject' => $this->app])
            );
        }

        
      
    }

    protected function setUser($user = 'phpunit', $action = null, $assetKey = null): void
    {
        $isUser = $this->app->loadIdentity();
        if (! $isUser->id ?? false) {
            $user = $this->container->get(UserFactoryInterface::class)->loadUserByUsername($user);
            $this->app->getSession()->set('user', $user);
            $this->app->loadIdentity($user);
            if ($action) {
                $can = (bool) Access::check($user->id, $action, $assetKey);
                $this->assertTrue($can, 'User has not the right access right for:' . $action);
            }
        }
    }


    public function getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
    {
        $mvcFactory = $this->app->bootComponent($component)->getMVCFactory();
        return $mvcFactory->createModel($model, $client, $config);
    }


    protected function assertMessageQueue($type = 'error', $empty = true)
    {

        BlcMessages::getInstance()->moveToApplication($this->app);
        $messages = $this->getMessageQueue($type);

        if ($empty) {
            $this->assertEmpty($messages, "Messages '$type' found:\n " . implode("\n ", $messages) . "\n");
        } else {
            $this->assertNotEmpty($messages, "Messages '$type' not found:\n " . implode("\n ", $messages) . "\n");
        }
    }
    protected function getMessageQueue($type = 'error')
    {
        $queue = $this->app->getMessageQueue();
        $this->clearMessageQueue();
        $typed = array_filter($queue, fn($item) => $item['type'] == $type);
        $typed = array_column($typed, 'message');
        return $typed;
    }

    protected function clearMessageQueue()
    {
        $this->app->getMessageQueue(true);
        BlcMessages::getInstance()->getMessageQueue(true);
    }
    public function getHelpLink()
    {
        $plugin =   $this->bootPlugin();
        $link = $plugin::getHelpLink();
        $this->assertStringStartsWith('https://', $link);
    }

    protected function enableBlc($enabled)
    {
        $protectedMethod = (
            function ($enabled = true) {
                static::$components['com_blc']->enabled = $enabled;
            }
        );
        $protectedMethod->call(new ComponentHelper(), $enabled);
    }

    protected function resetUriInstances()
    {
        //Uri:reset has site effect on the SiteRouter
        $protectedMethod = (
            function () {
                static::$instances=[];;
            }
        );
        $protectedMethod->call(new Uri());
    }

    public function getSubscribedEvents(bool $empty = false)
    {
        $this->clearMessageQueue();
        $plugin =   $this->bootPlugin();
        $events = $plugin::getSubscribedEvents();
        if ($empty) {
            $this->assertEmpty($events);
        } else {
            $this->assertNotEmpty($events);
        }
        $this->assertMessageQueue();
    }

    protected function isSubscribed(string $event)
    {
        $events = $this->class::getSubscribedEvents();
        $this->assertArrayHasKey($event, $events);
    }

    public function checkBlcCheckerRequest()
    {
        $this->isSubscribed('onBlcCheckerRequest');

        $mock = $this->createMock(BlcCheckLink::class);
        $mock->expects($this->atLeastOnce())->method('registerChecker')->with(
            $this->IsInstanceOf(HTTPCODES::class),
            $this->greaterThan(0)
        );
        $arguments              = [
            'item' => $mock,
        ];
        $plugin   = $this->bootPlugin();
        $event    = new BlcEvent('onBlcCheckerRequest', $arguments);
        $plugin->onBlcCheckerRequest($event);
    }


    public function checkonBlcParserRequest()
    {
        $this->isSubscribed('onBlcParserRequest');

        $mock = $this->createMock(BlcParseController::class);
        $mock->expects($this->atLeastOnce())->method('registerParser')->with(
            $this->IsInstanceOf(BlcParserInterface::class)
        );
        $arguments              = [
            'subject' => $mock,
        ];
        $plugin   = $this->bootPlugin();
        $event    = new BlcParserRequestEvent('onBlcParserRequest', $arguments);
        $plugin->onBlcParserRequest($event);
    }


    protected function cleanLanguageStrings(): Language
    {
        $lang      = $this->getApplication()->getLanguage();
        $protectedMethod = function (): void {

            $this->strings = [];
            $this->paths = [];
        };
        $protectedMethod->call($lang);
        return $lang;
    }

    protected function checkLinkWrapped(&$linkItem)
    {

        $checkLink  = BlcCheckLink::getInstance();

        $protectedMethod = function (&$linkItem): void {


            $parsedItem = new Uri($linkItem->toCheck);
            $host       = UrlHelper::hostToPunnycode($parsedItem->getHost());
            BlcTransientManager::getInstance()->delete($host);

            //reset the checkers

            $this->requestCheckers();
            $this->checkLink($linkItem);
        };
        $protectedMethod->call($checkLink, $linkItem);
    }

    protected function setComponentOption(string $option, string $key, mixed $value)
    {
        ComponentHelper::getComponent('com_content')->params->set($key, $value);
    }



    protected function loadLinkItem($url, $create = true, int|bool $http_code = HTTPCODES::BLC_CHECK_UNSET)
    {
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->load([
            'url' => $url,

        ]);
        if (!$linkItem->id && $create) {
            $linkItem->bind([
                'url' => $url,

            ]);
        }
        if ($http_code !== false) {
            $linkItem->http_code = $http_code;
        }
        return $linkItem;
    }

    protected function deleteLink(string $url)
    {
        $linkItem = $this->loadLinkItem($url);

        if ($linkItem->id) {
            $linkItem->delete();
        }
    }
    protected function assertLinksExists(array $links, bool $empty = false, string $msg = '')
    {
        foreach ($links as $link) {
            $this->assertLinkExists($link, $empty, $msg);
        }
    }

    protected function assertLinkExists(string $url, bool $empty = false, string $msg = ''): ?LinkTable
    {
        $linkItem = $this->loadLinkItem($url);

        if ($empty) {
            $this->assertSame(0, $linkItem->id, "Link '$url' Found. $msg");
        } else {
            //  echo $url;
            // var_dump(get_object_vars($linkItem));

            $this->assertNotSame(0, $linkItem->id, "Link '$url' Not Found. $msg");
        }
        return  $linkItem;
    }
    /**
     * no search for the correct container or item.
     * ensure the anchor is unique
     */
    protected function assertAnchorExists(string $anchor, bool $empty = false): int
    {

        $anchorItem = new InstanceTable($this->getDatabase(), $this->getDispatcher());
        $anchorItem->load([

            'link_text' => $anchor,
        ], false);

        if ($empty) {
            $this->assertSame(0, $anchorItem->id, "Anchor '$anchor' Found:");
        } else {
            $this->assertNotSame(0, $anchorItem->id, "Anchor '$anchor' Not Found:");
        }
        return  $anchorItem->id ?? 0;
    }

    protected function checkPluginEnabled(string $folder, string $element)
    {
        if (! PluginHelper::getPlugin($folder, $element)) {
            $this->markTestSkipped(
                "Plugin $folder/$element not enabled",
            );
        }
    }

    protected function BlcPlugin__get()
    {
        $this->assertNotNull($this->context, 'context not set');
        $plugin   = $this->bootPlugin();

        $context = $plugin->context;
        $this->assertSame($this->context, $context);

        $element = $plugin->name;
        $this->assertSame($this->element, $element);

        $context = $plugin->any;
        $this->assertNull($context);
    }

    protected function getSomeSynch()
    {
        $query = $this->db->getQuery(true);
        $query->select('`id`')
            ->from('`#__blc_synch` `s`');

        $synchId = $this->db->setquery($query)->loadResult();
        $this->assertNotNull($synchId, 'No linkId found to test:' . $query->dump());

        $synchItem = new synchTable($this->getDatabase(), $this->getDispatcher());
        $synchItem->load([
            'id' => $synchId,

        ]);
        $this->assertNotNull($synchItem, 'No linkItem found to test:' . $query->dump());

        return $synchItem;
    }

    /**
     * @var string $parser
     * @var array $fields
     * @var string $destination internal or external
     * @var string $linkPattern part of string the link must contain. Add %
     *
     */
    protected function getSomeLink(string $parser = 'href', array $fields = ['fulltext', 'introtext'], $destination = '', $linkPattern = '')
    {
        $query = $this->db->getQuery(true);
        $query->select('`l`.`id`')
            ->from('`#__blc_links` `l`')

            ->join('INNER', '`#__blc_instances` `i`', '`l`.`id` = `i`.`link_id`')
            ->join('INNER', '`#__blc_synch` `s`', '`i`.`synch_id` = `s`.`id` and `plugin_name` = "content" AND `container_id` != 0')
            ->setLimit(1);
        if ($parser) {
            $query->where('`i`.`parser` = ' . $this->db->quote($parser));
        }
        if ($fields) {
            $query->whereIN('`i`.`field`', $fields);
        }

        if ($destination) {
            switch ($destination) {
                case 'internal':
                    $query->where('`l`.`internal_url` != ""');
                    break;
                case 'external':
                    $query->where('`l`.`internal_url` = ""');
                    if (!$linkPattern) {
                        $query->where('`l`.`url` like ' . $this->db->quote('%.invalid%'));
                    }
                    break;
                default:
                    //none
            }
        }

        if ($linkPattern) {
            $query->where('`l`.`url` like ' . $this->db->quote($linkPattern));
        }


        $linkId = $this->db->setquery($query)->loadResult();
        $this->assertNotNull($linkId, 'No linkId found to test:' . $query->dump() . ' - ' . json_encode($fields));

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->load([
            'id' => $linkId,

        ]);
        $this->assertNotNull($linkItem, 'No linkItem found to test:' . $query->dump());

        return $linkItem;
    }
    /**
     * this reloads the plugin into the joomla application
     */
    protected function importPlugin(?string $folder = null, ?string $element = null)
    {

        $element ??= $this->element;
        $folder  ??= $this->folder;
        PluginHelper::importPlugin($folder, $element);


        $plugin =  ExtensionHelper::$extensions[PluginInterface::class]["$element:$folder"] ?? null;
        $this->assertNotNull($plugin);
        $this->assertNotInstanceOf(DummyPlugin::class, $plugin);
        return $plugin;
    }
    /**
     * this loads the plugin stand outside the joomla application
     */
    protected function bootPlugin(?string $class = null, ?array $config = null, bool $assert = false)
    {
        if ($assert) {
            $this->checkPluginEnabled($this->folder, $this->element);
        }
        $class ??= $this->class;

        if (!$class) {
            throw new \RuntimeException('bootPlugin called without class');
        }
        if (!$config) {
            if (!$this->folder) {
                throw new \RuntimeException('bootPlugin called without folder');
            }
            if (!$this->element) {
                throw new \RuntimeException('bootPlugin called without element');
            }
            $config =  (array)PluginHelper::getPlugin($this->folder, $this->element) ?? [];
        }
        $dispatcher = $this->getDispatcher();

        $plugin     = new $class($dispatcher, $config);
        $plugin->setApplication($this->app);
        if (method_exists($plugin, 'setDatabase')) {
            $plugin->setDatabase($this->db);
        }
        if ($assert) {
            $this->assertInstanceOf($class, $plugin);
            $this->assertMessageQueue();
        }

        return $plugin;
    }

    public function assertLinkReplaceInvalidInstance(string $url, ?string $plugin = null)
    {
        $this->clearMessageQueue();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_blc', 'Link');

        $plugin ??= $this->name ?? null;

        //valid item
        $linkItem   = $this->loadLinkItem($url);
        //valid synch
        $synch      = $model->getSynch($linkItem->id, plugin: $plugin);
        $this->assertNotEmpty($synch);
        $unique     = uniqid();
        $code       = floor(rand(200, 999));

        $newUrl            = "https://phpunit-replaced.$code.invalid/replaced-$unique";
        $row               = end($synch);
        $row->container_id = -99;
        $sourcePlugin      = $row->plugin;
        $activePlugin      = $model->getPlugin($sourcePlugin);
        if ($activePlugin) {
            $activePlugin->replaceLink($linkItem, $row, $newUrl);
        }

        $this->assertMessageQueue(empty: false);
    }


    public function asserLinkReplaceNoneExistingLink(string $url, ?string $plugin = null)
    {
        $this->clearMessageQueue();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_blc', 'Link');

        $plugin ??= $this->name ?? null;

        //valid item
        $linkItem   = $this->loadLinkItem($url);
        //valid synch
        $synch      = $model->getSynch($linkItem->id, plugin: $plugin);
        $this->assertNotEmpty($synch);
        $unique        = uniqid();
        $code          = floor(rand(200, 999));
        $newUrl        = "https://phpunit-replaced.$code.invalid/replaced-$unique";
        $linkItem->url = $newUrl . '-old';

        $row = end($synch);

        $sourcePlugin = $row->plugin;
        $activePlugin = $model->getPlugin($sourcePlugin);
        if ($activePlugin) {
            $activePlugin->replaceLink($linkItem, $row, $newUrl);
        }

        $this->assertMessageQueue('warning', empty: false);
    }


    public function assertLinkReplace(string $url, ?string $newUrl = null, $empty = false, ?string $plugin = null)
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_blc', 'Link');

        $plugin ??= $this->name ?? null;


        $linkItem   = $this->loadLinkItem($url);
        //valid synch)
        $synch      = $model->getSynch($linkItem->id, plugin: $plugin);
        if ($empty) {
            $this->assertEmpty($synch);
        } else {
            $this->assertNotEmpty($synch);
        }
        $unique     = uniqid();
        $code       = floor(rand(200, 999));

        $newUrl ??= "https://phpunit-replaced.$code.invalid/replaced-$unique";

        foreach ($synch as $row) {
            $sourcePlugin = $row->plugin;
            $activePlugin = $model->getPlugin($sourcePlugin);
            if ($activePlugin) {
                $activePlugin->replaceLink($linkItem, $row, $newUrl);
            }
        }
        $this->assertLinkExists($newUrl, empty: $empty, msg: "old: $url");
    }


    public function assertLinksReplace(array $urls, ?string $newUrl = null, $empty = false, ?string $plugin = null)
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');


        foreach ($urls as $url) {
            $this->assertLinkReplace($url, $newUrl, $empty, $plugin);
        }
    }

    protected function assertTestTag(string $html = '')
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');

        $item              = new \stdClass();
        $item->articletext = $html . '<hr id="system-readmore">' . $html;
        return $this->assertTestHtml($model, $item);
    }
    protected function injectLinks(string $itemString): array
    {
        $anchors = [];
        //reset
        $pattern    = '#phpunit(?:\-[a-z0-9]+)?(?:\.[0-9]{3})?.(jpg|png|text|anchor|invalid)#';
        $itemString = preg_replace($pattern, "phpunit.$1", $itemString);

        $itemString = preg_replace_callback(
            '#phpunit.(text|jpg|png|invalid)#',
            fn($m) => 'phpunit-' . uniqid() . '.200.' . $m[1],
            $itemString
        );

        $itemString = preg_replace_callback(
            '#phpunit.anchor#',
            function ($m) use (&$anchors) {
                $anchor    = 'phpunit-' . uniqid() . '-anchor';
                $anchors[] = $anchor;
                return $anchor;
            },
            $itemString
        );
        //} is for in
        $url_regexp =  '#(?:https?://[^"]+)#';
        //this excluded the (joomla) media links with a space. Technicaly those are wrong anyway.
        $url_regexp =  '#(?:https?://[^" {}>\']+)#';
        preg_match_all($url_regexp, $itemString, $m);

        $links = array_map(fn($e) => rtrim(stripslashes($e), '\\'), $m[0]);

        $links = array_filter(array_unique($links));
        return ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors];
    }
    protected function assertTestHtmlSelf($model, $id)
    {

        $itemTemplate =  $model->getItem($id); //object
        $this->assertNotEmpty($itemTemplate->id, 'A item with id: ' . $id . ' is needed');
        return $this->assertTestHtml($model, $itemTemplate, $id);
    }

    protected function getTestItem($model, $pks = [])
    {

        if (! $pks) {
            $testTitle =  JTEST_TITLE . ' Test';
            $pks       = ['title' => $testTitle];
        }

        $itemTest =   $model->getItem($pks); //object

        $this->assertNotEmpty($itemTest, 'A item with pks: ' . json_encode($pks) . ' is needed');
        $this->assertFalse((bool)$itemTest->checked_out, 'Item is checked out');

        return $itemTest;
    }


    protected function assertTestHtml($model, object $item, $pks = [])
    {


        unset($item->catid, $item->id, $item->alias, $item->tagsHelper, $item->asset_id, $item->title, $item->assignment, $item->xml, $item->lft, $item->rgt, $item->parent);

        //modules come with this crap

        if (empty($item->articletext)) {
            $item->articletext = $item->introtext . '<hr id="system-readmore">' . $item->fulltext ?? '';
        }
        unset($item->fulltext, $item->introtext);


        $itemTest = $this->getTestItem($model, $pks);

        unset($itemTest->tagsHelper, $itemTest->fulltext, $itemTest->introtext, $itemTest->assignment, $itemTest->xml);


        //modules come with this crap


        foreach ($item as $property => $value) {
            $itemTest->$property = $value;
        }

        $itemString  = json_encode($itemTest, JSON_UNESCAPED_SLASHES);

        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);
        $this->assertNotNull($links, 'No links found');

        $itemTest = json_decode($itemString, true);

        $input   = $this->getApplication()->getInput();
        $input->post->set('jform', $itemTest);
        //print $itemString;

        $model->save($itemTest);
        $this->assertempty($model->getError(), $model->getError());
        $this->assertLinksExists($links);

        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }

        return $links;
    }

    protected function assertTestPage($model, string $titlePrefix = '')
    {

        $templateTitle = ($titlePrefix ?: JTEST_TITLE) . ' Template';

        $itemTemplate = $model->getItem(['title' => $templateTitle]); //object

        $this->assertNotEmpty($itemTemplate->id, 'A item with title: ' . $templateTitle . ' is needed');
        $com_fields = [];
        if ($this->fieldContext) {
            $rows = FieldsHelper::getFields($this->fieldContext, $itemTemplate);
            foreach ($rows as $row) {
                //we don't test the field parser just the connection from the parent.
                if (\in_array($row->type, ['editor', 'url'])) {
                    $com_fields[$row->name] = $row->rawvalue;
                }
            }

            $itemTemplate->com_fields = ArrayHelper::toObject($com_fields);
        }
        unset($itemTemplate->articletext);
        $itemTemplate->introtext = '';
        return $this->assertTestHtml($model, $itemTemplate);
    }

    public function isSingeTon(mixed $class)
    {
        if (!\is_string($class)) {
            $moduleInstance = $class;
            $className      = $class::class;
        } else {
            $className      = $class;
            $moduleInstance = $className::getInstance();
        }

        $objectHash1 = spl_object_hash($moduleInstance);
        unset($moduleInstance);
        $moduleInstance = $className::getInstance();
        $objectHash2    = spl_object_hash($moduleInstance);
        $this->assertSame($objectHash1, $objectHash2);
        unset($moduleInstance);
        $moduleInstance = $className::getInstance(false);
        $objectHash2    = spl_object_hash($moduleInstance);
        $this->assertNotSame($objectHash1, $objectHash2);
    }

    protected function clearSynch(int $id, string $plugin)
    {

        $synchTable = new SynchTable($this->getDatabase());
        $pk         = [
            'container_id' => $id,
            'plugin_name'  => $plugin,
        ];
        $synchTable->load($pk);
        if ($synchTable->id) {
            $synchTable->delete();
        }
    }
}
