<?php

declare(strict_types=1);

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
use Blc\Component\Blc\Administrator\Event;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Extension\DummyPlugin;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Input\Input;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use PHPUnit\Framework\TestCase;

/**
 * Base Unit Test case for common behaviour across unit tests
 *
 * @since   4.0.0
 */
abstract class UnitTestCase extends TestCase
{
    protected $lastQueryInfo    = [];
    protected string $folder    = '';
    protected string $element   = '';
    protected string $class     = '';
    protected DatabaseInterface $db;
    protected ?CMSApplicationInterface $app = null;
    protected DispatcherInterface $dispatcher;
    protected Container $container;
    protected string $fieldContext = '';
    public function getDispatcher()
    {

        return $this->dispatcher;
    }

    public function getContainer()
    {

        return $this->container;
    }

    public function getApplication()
    {
        return $this->app;
    }

    public function getDatabase()
    {
        return $this->db;
    }
    public function tearDown(): void
    {
        BlcMessages::resetInstance();
        $this->closeApplication();
    }

    public function setup(): void
    {
        $this->initApplication();
    }


    protected function getApplicationWithoutExit()
    {

        if ($this->container->has(Input::class) !== false) {
            $input = $this->container->get(Input::class);
        } else {
            $input = new Input();
        }

        $app =  new class($input, $this->container->get('config'), null, $this->container) extends CMSApplication {
            public function close($code = 0)
            {
                return $code;
            }

            protected function doExecute()
            {
                // Initialise the application
                $this->initialiseApp();



                // Route the application
                //  $this->route();

                // Mark afterRoute in the profiler.




                // Dispatch the application
                $this->dispatch();
            }
        };

        $lang       = $this->container->get(LanguageFactoryInterface::class)->createLanguage($this->app->get('language'), $this->app->get('debug_lang'));

        // Load the language to the API
        $app->loadLanguage($lang);
        return $app;
    }

    protected function closeApplication(): void
    {
        unset($this->db, $this->container, $this->app);


        $this->app = null;
    }


    protected function getFieldValues(string $context = '')
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__fields'))
            ->where($db->quoteName('state') .  ' = 1 ')
            ->select($db->quoteName(['id', 'context', 'type', 'title', 'item_id']))
            ->select($db->quoteName('value', 'rawvalue'))
            ->Innerjoin($db->quoteName('#__fields_values'), $db->quoteName('field_id') . ' = ' . $db->quoteName('id'))
            ->group($db->quoteName(['id', 'item_id']));

        if ($context) {
            $query->where($db->quoteName('context') . ' = :context')->bind(':context', $context);
        }
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        foreach ($rows as &$row) {
            $query = $db->getQuery(true);
            $query->from($db->quoteName('#__fields_values'))
                ->select($db->quoteName('value'))
                ->where($db->quoteName('field_id') . ' = :field_id')->bind(':field_id', $row->id)
                ->where($db->quoteName('item_id') . ' = :item_id')->bind(':item_id', $row->item_id);
            $db->setQuery($query);
            $results = $db->loadColumn();
            if (\count($results) > 1) {
                $row->rawvalue = $results;
            }
        }
        return $rows;
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

        $this->db         = $this->container->get(DatabaseInterface::class);
        $this->dispatcher = $this->container->get(DispatcherInterface::class);
        //to prevent a warning: Test code or tested code did not close its own output buffers
        $this->app->set('debug', false);
        // Load the behaviour plugins
        //  PluginHelper::importPlugin('behaviour', null, true, $this->getDispatcher());



        PluginHelper::importPlugin('system', null, true, $this->getDispatcher());
        PluginHelper::importPlugin('behaviour', null, true, $this->getDispatcher());
        // Trigger the onAfterInitialise event.
        if (version_compare(JVERSION, '5.0', '<')) {
            /** @disregard */
            $this->app->triggerEvent('onAfterInitialise');
        } else {
            $this->getDispatcher()->dispatch(
                'onAfterInitialise',
                new AfterInitialiseEvent('onAfterInitialise', ['subject' => $this->app])
            );
        }
        $this->clearMessageQueue();

        error_reporting(E_ALL & ~E_DEPRECATED);
    }

    protected function getDispatcherMock()
    {
        $mock = $this->createStub(DispatcherInterface::class);
        return $mock;
    }

    protected function setUser($user = 'phpunit', $action = null, $assetKey = null): void
    {

        $user = $this->container->get(UserFactoryInterface::class)->loadUserByUsername($user);
        $this->app->getSession()->set('user', $user);
        $this->app->loadIdentity($user);
        if ($action) {
            $can = (bool) Access::check($user->id, $action, $assetKey);
            $this->assertTrue($can, 'User has not the right access right for:' . $action);
        }
    }


    public function getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
    {

        $mvcFactory    = $this->app->bootComponent($component)->getMVCFactory();
        $modelInstance = $mvcFactory->createModel($model, $client, $config);
        $this->assertNotNull($modelInstance, 'Model not found:' . $component . ' - ' . $model);
        $this->assertNotFalse($modelInstance, 'Model not found:' . $component . ' - ' . $model);
        return $modelInstance;
    }

    protected function getController($component, $name, $client = 'Administrator')
    {
        $controller =  $this->app->bootComponent($component)->getMVCFactory()->createController(
            $name,
            $client,
            ['option' => $component],
            $this->app,
            $this->app->getInput()
        );
        return $controller;
    }

    protected function assertMessageQueue($type = 'error', $empty = true, mixed $msg = '')
    {

        BlcMessages::getInstance()->moveToApplication($this->app);
        $messages = $this->getMessageQueue($type);


        if ($empty === true) {
            $this->assertEmpty($messages, "Messages '$type' found:\n " .  json_encode([$this->getMessageQueue(''), $msg], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        } elseif ($empty === false) {
            $this->assertNotEmpty($messages, "Messages '$type' not found:\n " . json_encode([$this->getMessageQueue(''), $msg], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        } else {
            $messageString = $messages[0] ?? '';
            $this->assertStringContainsString($empty, $messageString, "Messages '$empty' not found:\n " .  json_encode([$this->getMessageQueue(''), $msg], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }
    protected function getMessageQueue($type = 'error')
    {
        $queue = $this->app->getMessageQueue();

        if ($type) {
            $typed = array_filter($queue, fn($item) => $item['type'] == $type);
            $typed = array_column($typed, 'message');

            return $typed;
        }
        return $queue;
    }

    protected function clearMessageQueue()
    {
        $this->app->getMessageQueue(true);
        BlcMessages::resetInstance();
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
                static::$instances = [];
            }
        );
        $protectedMethod->call(new Uri());
    }

    protected function getSubscribedEvents()
    {

        $plugin =   $this->bootPlugin();
        $events = $plugin::getSubscribedEvents();

        return $events;
    }

    protected function assertSubscribedEvents(bool $empty = false)
    {


        $events = $this->getSubscribedEvents();
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

    public function assertOnBlcCheckerRequest()
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
        $event    = new Event\BlcEvent('onBlcCheckerRequest', $arguments);
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
        $event    = new Event\BlcParserRequestEvent('onBlcParserRequest', $arguments);
        $plugin->onBlcParserRequest($event);
    }


    protected function cleanLanguageStrings(): Language
    {
        $lang            = $this->getApplication()->getLanguage();
        $protectedMethod = function (): void {

            $this->strings = [];
            $this->paths   = [];
        };
        $protectedMethod->call($lang);
        return $lang;
    }


    protected function checkLinkWrapped(LinkTable &$linkItem)
    {

        $checkLink  = BlcCheckLink::getInstance();
        //linkitem is alreadsy  a reference
        $protectedMethod = function (LinkTable $linkItem): void {


            $parsedItem = new Uri($linkItem->toCheck);
            $host       = UrlHelper::hostToPunycode($parsedItem->getHost());
            BlcTransientManager::getInstance()->delete($host);

            //reset the checkers

            $this->requestCheckers();
            $this->checkLink($linkItem);
        };
        $protectedMethod->call($checkLink, $linkItem);
    }

    protected function setComponentOption(string $option, string $key, mixed $value)
    {

        ComponentHelper::getComponent($option)->params->set($key, $value);
    }

    protected function assertloadLinkItemID(int $id)
    {
        $linkItem = $this->loadLinkItemID($id);

        $this->assertNotNull($linkItem, 'LinkItem not found:' . $id);
        $this->assertNotSame(0, $linkItem->id, 'LinkItem not found:' . $id);
        return $linkItem;
    }

    protected function loadLinkItemID(int $id)
    {
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->load([
            'id' => $id,

        ]);


        return $linkItem;
    }




    protected function loadLinkItem($url, $create = true, int|bool $http_code = HTTPCODES::BLC_CHECK_UNSET, $config = [])
    {
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->load([
            'url' => $url,

        ]);
        if (!$linkItem->id && $create) {
            $linkItem->bind([
                'url' => $url,

            ]);
            $linkItem->save();
        }
        if ($http_code !== false) {
            $linkItem->http_code = $http_code;
        }

        if ($config) {
            $reflection = new \ReflectionClass($linkItem);
            $property   = $reflection->getProperty('componentConfig');

            $componentConfig = $property->getValue($linkItem);
            foreach ($config as $key => $value) {
                $componentConfig->set($key, $value);
            }
            $linkItem->resetInternalUrl();
        }

        return $linkItem;
    }


    protected function deleteLink(string $url)
    {
        $linkItem = $this->loadLinkItem($url, create: false);

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
        $linkItem = $this->loadLinkItem($url, create: false);

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
    protected function assertParserExists(string $parser, int $link_id = 0, bool $empty = false): int
    {

        $parserItem = new InstanceTable($this->getDatabase(), $this->getDispatcher());
        $pks        = [

            'parser' => $parser,
        ];
        if ($link_id) {
            $pks['link_id'] = $link_id;
        }
        $parserItem->load(
            $pks,
            false
        );

        if ($empty) {
            $this->assertSame(0, $parserItem->id, "Anchor '$parser' Found:");
        } else {
            $this->assertNotSame(0, $parserItem->id, "Anchor '$parser' Not Found:");
        }
        return  $parserItem->id ?? 0;
    }

    /**
     * no search for the correct container or item.
     * ensure the anchor is unique
     * @since 25.44.7562
     */
    protected function assertFieldExists(string $field, int $link_id = 0, bool $empty = false): int
    {

        $fieldItem  = new InstanceTable($this->getDatabase(), $this->getDispatcher());
        $pks        = [

            'field' => $field,
        ];
        if ($link_id) {
            $pks['link_id'] = $link_id;
        }
        $fieldItem->load(
            $pks,
            false
        );

        if ($empty) {
            $this->assertSame(0, $fieldItem->id, "Field '$field' Found:");
        } else {
            $this->assertNotSame(0, $fieldItem->id, "Field '$field' Not Found:");
        }
        return  $fieldItem->id ?? 0;
    }
    /**
     * dpes on angor exists
     *   * @since 25.44.7562
     */
    protected function assertAnchorExists(string $anchor, int $link_id = 0, bool $empty = false): int
    {

        $anchorItem = new InstanceTable($this->getDatabase(), $this->getDispatcher());
        $pks        = [

            'link_text' => $anchor,
        ];
        if ($link_id) {
            $pks['link_id'] = $link_id;
        }
        $anchorItem->load(
            $pks,
            false
        );

        if ($empty) {
            $this->assertSame(0, $anchorItem->id, "Anchor '$anchor' Found:");
        } else {
            $this->assertNotSame(0, $anchorItem->id, "Anchor '$anchor' Not Found:");
        }
        return  $anchorItem->id ?? 0;
    }

    protected function checkPluginEnabled(?string $folder = null, ?string $element = null)
    {
        $folder  ??= $this->folder;
        $element ??= $this->element;
        if (! PluginHelper::getPlugin($folder, $element)) {
            $this->markTestSkipped(
                "Plugin $folder/$element not enabled",
            );
        }
    }

    protected function assertMagicGet()
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
    private function dump($query)
    {

        return  str_replace(["\n", '#__'], [' ', $this->getDatabase()->getPrefix()], (string) $query);
    }

    protected function getRandomLink($ext = '', $code = 200)
    {


        $id = uniqid();
        return match ($ext) {
            'href'      => "https://phpunit.$code.invalid/{$id}/{$ext}.html",
            'html'      => "https://phpunit.$code.invalid/{$id}/{$ext}.html",
            'img'       => "https://phpunit.$code.invalid/{$id}/{$ext}.webp",
            'xml'       => "https://phpunit.$code.invalid/{$id}/{$ext}.xml",
            'youtube'   => "https://www.youtube.com/watch?v={$id}",
            'avsplayer' => "https://www.youtu.be/{$id}",
            'aimyvideo' => "https://www.youtu.be/{$id}",
            'vimeo'     => "https://vimeo.com/{$id}",
            default     => "https://phpunit.$code.invalid/{$id}/{$ext}.php"
        };
    }

    protected function getRandomAlt(): string
    {
        return 'This is a test alt: ' . uniqid();
    }

    protected function getRandomTitle(): string
    {
        return 'This is a test title: ' . uniqid();
    }

    private function getlinkPattern(string $parser)
    {
        return match ($parser) {
            'aimyvideo' => '', //any
            default     => '%invalid%',
        };
    }
    protected function setLastSynch(int $container_id = 0, int $synch_id = 0, ?string $date = null)
    {
        $synchItem = new SynchTable($this->getDatabase(), $this->getDispatcher());
        $pks       = [];
        if ($container_id) {
            $pks['container_id'] = $container_id;
        }
        if ($synch_id) {
            $pks['id'] = $synch_id;
        }
        $synchItem->load($pks);

        $synchItem->last_synch = $date ?? Factory::getDate('01-01-2021')->toSql();
        $synchItem->store();
    }

    protected function getSomeLinkQuery(string $parser = 'href', string $plugin = 'content', array $fields = ['fulltext', 'introtext'], $destination = '', ?string $linkPattern = null, int $container_id = 0)
    {
        $linkPattern ??= $this->getlinkPattern($parser);
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName('l.id', 'link_id'))
            ->select($this->db->quoteName('s.container_id', 'container_id'))
            ->select($this->db->quoteName('i.field', 'field'))
            ->select($this->db->quoteName('s.id', 'synch_id'))
            ->select($this->db->quoteName('i.parser', 'parser'))
            ->select($this->db->quoteName('i.id', 'instance_id'))
            ->from('`#__blc_links` `l`')
            ->join('INNER', '`#__blc_instances` `i`', '`l`.`id` = `i`.`link_id`')
            ->join('INNER', '`#__blc_synch` `s`', '`i`.`synch_id` = `s`.`id`')
            ->order($this->db->quoteName('s.last_synch'));


        if ($container_id) {
            $query->where('`s`.`container_id` = ' . $this->db->quote($container_id));
        } else {
            $query->where('`s`.`container_id` != 0');
        }

        if ($parser) {
            $query->where('`i`.`parser` = ' . $this->db->quote($parser));
        }

        if ($plugin) {
            $query->where('`s`.`plugin_name` = ' . $this->db->quote($plugin));
        } else {
            $query->whereNotIn('`s`.`plugin_name`', ['phpunit', 'external'], ParameterType::STRING);
        }
        $fields = array_filter($fields);
        if ($fields) {
            $ors = [];
            //easier for debug
            foreach ($fields as $field) {
                $ors[] = '`i`.`field` = ' . $this->db->quote($field);
            }
            $query->extendWhere('AND', $ors, 'OR');
        }

        if ($destination) {
            switch ($destination) {
                case 'internal':
                    $query->where('`l`.`internal_url` != ""');
                    break;
                case 'external':
                    $query->where('`l`.`internal_url` = ""');

                    break;
                default:
                    //none
            }
        }

        if ($linkPattern) {
            $query->where('`l`.`url` like ' . $this->db->quote($linkPattern));
        }

        $this->lastQueryInfo =
            [
                $this->dump($query),
                $fields,
            ];

        return $query;
    }
    protected function getAllLinkIds(string $parser = '', string $plugin = '', array $fields = [], $destination = '', ?string $linkPattern = '', int $container_id = 0)
    {
        $query               = $this->getSomeLinkQuery($parser, $plugin, $fields, $destination, $linkPattern, $container_id);
        $linkObjects         = $this->db->setquery($query)->loadObjectList();


        return $linkObjects;
    }


    protected function getSomeLinkId(string $parser = 'href', string $plugin = 'content', array $fields = ['fulltext', 'introtext'], $destination = '', ?string $linkPattern = null, int $container_id = 0)
    {

        $query = $this->getSomeLinkQuery($parser, $plugin, $fields, $destination, $linkPattern, $container_id);
        $query->setLimit(1);

        $linkObject          = $this->db->setquery($query)->loadObject();


        return $linkObject;
    }



    public function assertAltString(string $altText, int $linkId = 0, bool $exists = true)
    {
        $query = $this->db->getQuery(true);
        $query
            ->select('count(*) as `count`')
            ->from('`#__blc_instances` `i`')
            ->where('`i`.`link_text` = ' . $this->db->quote($altText));
        if ($linkId) {
            $query->where('`i`.`link_id` = ' . $this->db->quote($linkId));
        }
        $count          = \intval($this->db->setquery($query)->loadResult());
        $msg            = "Alt text '$altText'";
        if ($exists) {
            $msg .= ' should exist';
        } else {
            $msg .= ' should not exist';
        }
        if ($linkId) {
            $msg .= ' for linkId ' . $linkId;
        }
        $msg .= '. Query: ' . $this->dump($query) . ' ' . json_encode($this->app->getMessageQueue());

        $this->assertSame(\intval($exists), $count, $msg);
    }
    /**
     * @var string $parser
     * @var array $fields
     * @var string $destination internal or external
     * @var string $linkPattern part of string the link must contain. Add %
     *
     */
    protected function assertGetSomeLink(string $parser = 'href', string $plugin = 'content', array $fields = ['fulltext', 'introtext'], $destination = '', ?string $linkPattern = null)
    {
        $linkId = $this->getSomeLinkId($parser, $plugin, $fields, $destination, $linkPattern)->link_id ?? null;
        $this->assertNotNull($linkId, 'No link found for:' . json_encode(\func_get_args()) . "\n" . json_encode($this->lastQueryInfo) . ' ' . json_encode($this->app->getMessageQueue()));

        $linkItem = $this->assertloadLinkItemID($linkId);


        return $linkItem;
    }


    protected function assertReplaceLink($field, $parser)
    {

        $plugin = $this->bootPlugin();
        $this->app->bootComponent('com_blc')->getMVCFactory();
        $fields = [$field];
        $link   = $this->getSomeLinkId(parser: $parser, plugin: $this->element, fields: $fields);
        $this->assertNotNull($link, "No link found to test: ({$this->element}: " . json_encode(\func_get_args()) . ' ' . json_encode($this->lastQueryInfo));
        $linkItem = $this->assertloadLinkItemID($link->link_id);
        $newLink  = $this->getRandomLink(ext: $parser);
        $plugin->replaceLink($linkItem, $link, $newLink);
        $this->assertMessageQueue('success', empty: false, msg: [$link, $linkItem->url, $newLink]);
        $newLinkItem = $this->assertGetSomeLink(parser: $parser, plugin: $this->element, fields: $fields, linkPattern: $newLink);
        $this->assertEquals($newLinkItem->url, $newLink);
    }



    protected function assertOnBlcExtract()
    {

        $this->isSubscribed('onBlcExtract');
        $plugin = $this->bootPlugin();
        $event  =  $this->ensureExtracted($plugin);

        //  $plugin->onBlcExtract($event);

        $link   = $this->getSomeLinkId(parser: '', plugin: $this->element, fields: []);
        $this->assertNotNull($link->link_id, 'No link found');
        $linkItem        = $this->loadLinkItemID($link->link_id);
        $testUrl         = $linkItem->url;
        $origContainerId = $link->container_id;




        //delete the sync.   this will delete instances as well
        //this triggers a reparse
        $this->deleteSynch($link->container_id, $this->element);
        $link = $this->getSomeLinkId(parser: '', plugin: $this->element, fields: [], linkPattern: $testUrl, container_id: $link->container_id);
        $this->assertNull($link, 'Synch not cleared:' .  $linkItem->url);

        $plugin->onBlcExtract($event);

        $this->assertNotEquals(0, $event->getDidExtract());
        $link = $this->getSomeLinkId(parser: '', plugin: $this->element, fields: [], linkPattern: $testUrl);
        $this->assertNotNull($link, 'Link not re-extracted after deletion:' . $testUrl . ' ' . $plugin::class . ' container_id: ' . $origContainerId);
        $parsed = $event->getDidExtract();
        $this->assertNotEquals($parsed, 0);
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



        $plugin     = new $class($config);
        $plugin->setApplication($this->app);
        if ($plugin instanceof DatabaseAwareInterface) {
            $plugin->setDatabase($this->db);
        }

        if ($plugin instanceof DispatcherAwareInterface) {
            $plugin->setDispatcher($this->container->get(DispatcherInterface::class));
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


    /**
     * @param string  $itemString
     * @return array[
     *  'itemString' => string,
     *  'links' => array,
     *  'anchors' => array
     * ]
     */



    protected function injectLinks(string $itemString): array
    {

        $anchors = [];
        //reset
        $pattern    = '#phpunit(?:\-[a-z0-9]+)?(?:\.[0-9]{3})?.(jpg|png|text|anchor|invalid)#';
        $itemString = (string) preg_replace($pattern, "phpunit.$1", $itemString);

        $itemString = (string)  preg_replace_callback(
            '#phpunit.(text|jpg|png|invalid)#',
            fn($m) => 'phpunit-' . uniqid() . '.200.' . $m[1],
            $itemString
        );

        $itemString = (string)  preg_replace_callback(
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
    /**
     *
     * ensure data is extracted
     */
    protected function ensureExtracted(?CMSPlugin $plugin = null)
    {
        $plugin ??= $this->bootPlugin();

        $onBlcExtractarguments =
            [
                'maxExtract' => 10,
            ];

        $extractEvent = new Event\BlcExtractEvent('onBlcExtract', $onBlcExtractarguments);


        $plugin->onBlcExtract($extractEvent);
        return $extractEvent;
    }

    protected function assertOnBlcContainerChanged()
    {
        $this->clearMessageQueue();
        $this->isSubscribed('onBlcContainerChanged');


        $itemTest =  $this->getSomeLinkId(parser: '', plugin: $this->element, fields: []);
        $plugin   = $this->bootPlugin();

        $onBlcContainerChangedarguments =
            [
                'context' => $this->context,
                'id'      => $itemTest->container_id,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $onBlcContainerChangedarguments);

        $plugin->params->set('onsave', 'parse');
        $plugin->onBlcContainerChanged($event);

        $this->assertMessageQueue('info', false);


        $plugin->params->set('onsave', 'delete');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', false);
        //reparse the container

        $extractEvent =        $this->ensureExtracted($plugin);
        $plugin->onBlcExtract($extractEvent);

        $plugin->params->set('onsave', 'nothing');
        $plugin->onBlcContainerChanged($event);
        $this->assertMessageQueue('info', false);
    }

    protected function getTestItem($model = null, $pks = [])
    {
        if (! $model) {
            $this->assertNotEmpty($this->context, 'Context not set');
            [$option, $part] = explode('.', $this->context);
            $model           = $this->getModel($option, $part);
            $this->assertNotEmpty($model);
        }
        if (! $pks) {
            $testTitle =  JTEST_TITLE . ' Test';
            $pks       = ['title' => $testTitle];
        }

        $itemTest =   $model->getItem($pks); //object

        $this->assertNotEmpty($itemTest, 'A item with pks: ' . json_encode($pks) . ' is needed');
        $this->assertFalse((bool)$itemTest->checked_out, 'Item is checked out');

        return $itemTest;
    }
    /**
     *  this tests the call off onBlcExtensionAfterSave and via the onExtensionAfterSave Event
     *  more detailed tests are in the test of the trait
     *
     */
    protected function assertOnExtensionAfterSave()
    {
        //this avoids that the purge is actually performded
        $this->setUser('guest');
        //code covage and code validation
        $plugin        = $this->bootPlugin();
        $tableStub     = new \Joomla\CMS\Table\Extension($this->getDatabase());

        $tableStub->type     = 'plugin';
        $tableStub->title    = 'phpunit test stub';
        $tableStub->element  = $this->element;
        $tableStub->folder   = $this->folder;
        if ($plugin->params instanceof Registry) {
            $tableStub->params = clone $plugin->params;
        } else {
            $tableStub->params = new Registry($plugin->params);
        }

        $tableStub->enabled  = 1;
        $tableStub->params->set('deleteonsavepugin', 1);
        $tableStub->params->set('dummy', 1); //ensure the params are different
        $tableStub->id = -1;

        $arguments =
            [
                'context' => $this->context,
                'subject' => $tableStub,
                'event'   => 'onextension',
            ];


        $event     = new Event\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->clearMessageQueue();
        $plugin->onBlcExtensionAfterSave($event);
        //the messages are queed from the link model where the purge is not execute due to the quest user..
        $this->assertMessageQueue('info', false);



        if (version_compare(JVERSION, '5.0', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onExtensionAfterSave', $arguments);
        } else {
            $event     = new Model\AfterSaveEvent('onExtensionAfterSave', $arguments);
        }
        $this->clearMessageQueue();
        $this->getDispatcher()->dispatch('onExtensionAfterSave', $event);
        $this->assertMessageQueue('info', false);
    }

    protected function assertContentEvents($model = null)
    {
        if (! $model) {
            [$option, $part] = explode('.', $this->context);
            $model           = $this->getModel($option, $part);
        }
        $this->isSubscribed('onBlcContainerChanged');
        $plugin = $this->bootPlugin();
        $this->assertOnContentAfterSave($model);
        $this->ensureExtracted($plugin);
        $this->assertOnContentAfterDelete($model);
        $this->ensureExtracted($plugin);
        $this->assertOnContentChangeState($model);
        $this->ensureExtracted($plugin);
    }

    protected function assertOnContentAfterSave($model)
    {

        $this->clearMessageQueue();
        $table     = $this->getSavedTestTable($model);

        $arguments =  [
            'context' => $this->context,
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
        $messagePart = "{$this->context} {$table->id} action: onsave";
        $this->assertMessageQueue('error', true);
        $this->assertMessageQueue('info', $messagePart);
    }

    protected function assertOnContentChangeState($model)
    {

        $this->clearMessageQueue();
        $table                                                              = $this->getSavedTestTable($model);

        $arguments = [
            'context' => $this->context,
            'subject' => [$table->id],
            'value'   => 1,
        ];

        if (version_compare(JVERSION, '5.0', '<')) {
            /* this is close to the behavior if triggerEvent J4 */
            $event     = new \Joomla\Event\Event('onContentChangeState', $arguments);
        } else {
            $event     = new Model\AfterChangeStateEvent('onContentChangeState', $arguments);
        }
        $this->getDispatcher()->dispatch('onContentChangeState', $event);
        //change state does a ondelete
        $messagePart = "{$this->context} {$table->id} action: ondelete";
        $this->assertMessageQueue('info', $messagePart);
    }


    public function assertOnContentAfterDelete($model)
    {

        $this->ensureExtracted();
        $this->clearMessageQueue();
        $table                                                              = $this->getSavedTestTable($model);
        $arguments                                                          = [
            'context' => $this->context,
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

        $messagePart = "{$this->context} {$table->id} action: ondelete";
        $this->assertMessageQueue('info', $messagePart);
    }
    /**
     *
     * this mimics the save function in the admin model where all values are strings
     */
    protected function getSavedTestTable($model)
    {
        $table      = $model->getTable();
        $tableName  = $table->getTableName();
        $primaryKey = $table->getKeyName(true);

        //we need an random item but it must be a random one for onContentChangeState
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName($primaryKey))
            ->from($tableName)
            ->setLimit(1);

        $id = $db->setQuery($query)->loadAssoc();

        $this->assertNotNull($id, "Failed to get item for $tableName");
        $table->load($id);

        $data = get_object_vars($table);

        $data = array_map(function ($item) {
            if (\is_int($item)) {
                return (string)$item;
            }
            return $item;
        }, $data);
        $data = array_filter($data, fn($item) => !\is_null($item));


        $table->bind($data);
        return $table;
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

        $itemTest = json_decode((string)$itemString, true);

        $input   = $this->getApplication()->getInput();
        $input->post->set('jform', $itemTest);


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

    /**
     *
     * @since 25.44.7562
     * @param int $id - instance id
     */
    protected function deleteInstance(int $id)
    {


        $instanceTable = new InstanceTable($this->getDatabase());
        $pk            = [
            'id' => $id,

        ];
        $instanceTable->delete($pk);
    }

    /**
     *
     * @since 25.44.7562
     * @param int $id - container id !!
     */

    protected function deleteSynch(int $id, string $plugin)
    {


        $synchTable = new SynchTable($this->getDatabase());
        $pk         = [
            'container_id' => $id,
            'plugin_name'  => $plugin,
        ];
        $synchTable->load($pk);

        $this->assertNotEquals(0, $synchTable->id, "Failed to load synchtable for:" .  json_encode($pk, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($synchTable->id) {
            $synchTable->delete();
        }
    }

    protected function assertgetEditLink()
    {
        $instance                                                              =  $this->getSomeLinkId(parser: '', plugin: $this->element, fields: [], destination: '', linkPattern: '');
        $this->assertNotNull($instance->container_id);
        $plugin                                                                = $this->bootPlugin();
        $link                                                                  = $plugin->getEditLink($instance);
        $this->assertNotEmpty($link);
    }

    public function assertgetViewLink()
    {

        $instance                                                              =  $this->getSomeLinkId(parser: '', plugin: $this->element, fields: [], destination: '', linkPattern: '');
        $this->assertNotNull($instance->container_id);
        $plugin                                                                = $this->bootPlugin();
        $link                                                                  = $plugin->getViewLink($instance);
        $this->assertNotEmpty($link);
    }

    public function assertGetTitle()
    {
        $instance                                                              =  $this->getSomeLinkId(parser: '', plugin: $this->element, fields: [], destination: '', linkPattern: '');

        $this->assertNotNull($instance, "No link found assertGetTitle: {$this->element}");
        $plugin                                                                = $this->bootPlugin();
        $link                                                                  = $plugin->getTitle($instance);
        $this->assertNotEmpty($link);
    }

    public function assertgetHelpLink()
    {
        $this->assertNotEmpty($this->class, 'class not set');
        $link   = $this->class::getHelpLink();
        $this->assertStringStartsWith('https://', $link);
    }


    public function assertgetHelpHtml()
    {
        self::getModel('com_blc', 'Links'); //load HTML Helper
        $html   = $this->class::getHelpHtml();
        $this->assertStringStartsWith('<a', $html);
    }

    protected function assertExtractfromSource($class, $source, $expected)
    {
        //this test does not care about the validitie of te links.
        $parser =  $class::getInstance();
        $links  = $parser->extractfromSource($source);

        $this->assertContains($expected, array_column($links, 'url'), 'Links found: ' . json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function assertReplaceInSource($class, $source, $oldUrl, $ext = '', ?string $newUrl = null)
    {
        $this->assertExtractfromSource($class, $source, $oldUrl);
        $newUrl ??= $this->getRandomLink($ext);
        //this test does not care about the validitie of te links.
        $parser  =  $class::getInstance();
        $source  = $parser->replaceInSource($source, $oldUrl, $newUrl);
        $this->assertExtractfromSource($class, $source, $newUrl);
    }



    protected function getBlcCheckLink()
    {

        //do not load as singleton to have a blank parser
        $checker =  BlcCheckLink::getInstance(false);

        $protectedMethod = function (): void {
            //avoid throttle while testing

            $this->internalThrottle = 0;
            $this->externalThrottle = 0;
        };
        $protectedMethod->call($checker);
        return $checker;
    }

    protected function testBootPluginService()
    {
        $provider = include(JPATH_ROOT . "/plugins/{$this->folder}/{$this->element}/services/provider.php");
        $provider->register($this->container);
        $plugin = $this->container->get(PluginInterface::class);
        $this->assertInstanceOf(PluginInterface::class, $plugin);

        $this->container->set(PluginInterface::class, null); //remove
    }
}
