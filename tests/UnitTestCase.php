<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;

use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\AdministratorApplication as Application;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Application\AfterInitialiseEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
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
        unset($this->db);
        unset($this->container);
        unset($this->app);
        $this->app = null;
    }

    protected function initApplication(): void
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
        $this->app  = $this->container->get(Application::class);
        $lang       = $this->container->get(LanguageFactoryInterface::class)->createLanguage($this->app->get('language'), $this->app->get('debug_lang'));

        // Load the language to the API
        $this->app->loadLanguage($lang);

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
            /** @disregard P1007 deprecated.intelephense*/
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
        $messages = $this->getMessageQueue($type);
        if ($empty) {
            $this->assertEmpty($messages, "Messages '$type' found:\n " . join("\n ", $messages) . "\n");
        } else {
            $this->assertNotEmpty($messages, "Messages '$type' not found:\n " . join("\n ", $messages) . "\n");
        }
    }
    protected function getMessageQueue($type = 'error')
    {
        $queue = $this->app->getMessageQueue();
        $typed = array_filter($queue, function ($item) use ($type) {
            return $item['type'] == $type;
        });
        $typed = array_column($typed, 'message');

        return $typed;
    }

    protected function assertLinkExists(string $url, bool $empty = false, string $msg = ''): ?LinkTable
    {
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->load([
            'url' => $url,
        ]);
        

        if ($empty) {
            $this->assertNull($linkItem->id, "Link '$url' Found.$msg");
        } else {
            //  echo $url;
            // var_dump(get_object_vars($linkItem));

            $this->assertNotNull($linkItem->id, "Link '$url' Not Found.$msg");
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
        ]);

        if ($empty) {
            $this->assertNull($anchorItem->id, "Anchor '$anchor' Found");
        } else {
            $this->assertNotNull($anchorItem->id, "Anchor '$anchor' Not Found");
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
    protected function getSomeLink(string $parser = 'href', array  $fields = ['fulltext', 'introtext'])
    {
        $query = $this->db->getQuery(true);
        $query->select('`l`.*')
            ->from('`#__blc_links` `l`')
            ->where('`url` like ' . $this->db->quote('%.invalid%'))
            ->join('INNER', '`#__blc_instances` `i`', '`l`.`id` = `i`.`link_id`')
            ->join('INNER', '`#__blc_synch` `s`', '`i`.`synch_id` = `s`.`id` and `plugin_name` = "content" AND `container_id` != 0')
            ->setLimit(1);
        if ($parser) {
            $query->where('`i`.`parser` = ' . $this->db->quote($parser));
        }
        if ($fields) {
            $query->whereIN('`i`.`field`', $fields);
        }


        $link = $this->db->setquery($query)->loadObject();
        $this->assertNotNull($link, 'No link found to test');

        return $link;
    }

    protected function bootPlugin(string $class, $config = [])
    {

        $dispatcher = $this->getDispatcher();
        $plugin     = new $class($dispatcher, $config ?? []);
        $plugin->setApplication($this->app);
        $plugin->setDatabase($this->db);
        return $plugin;
    }

    public function assertLinkReplace(string $url, ?string $newUrl = null)
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_blc', 'Link');

        $linkItem   = $this->assertLinkExists($url);
        $synch      = $model->getSynch($linkItem->id);
        $unique     = uniqid();
        $code = floor(rand(200, 999));

        $newUrl ??= "https://phpunit.$code.invalid/replaced-$unique";

        foreach ($synch as $row) {
            $sourcePlugin = $row->plugin;
            $activePlugin = $model->getPlugin($sourcePlugin);
            if ($activePlugin) {
                $activePlugin->replaceLink($linkItem, $row, $newUrl);
            }
        }
        $this->assertLinkExists($newUrl, msg: "old: $url");

        $synch = $model->getSynch($linkItem->id);
    }


    public function assertLinksReplace(array $urls)
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');


        foreach ($urls as $url) {
            $this->assertLinkReplace($url);
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

        $itemString = preg_replace('#phpunit\-[a-z0-9]+(?:\.[0-9]{3})?.(jpg|png|text|anchor|invalid)#', "phpunit.$1", $itemString);

        $itemString = preg_replace_callback(
            '#phpunit.(text|jpg|png|invalid)#',
            function ($m) {
                return 'phpunit-' . uniqid() . '.200.' . $m[1];
            },
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

        $links = array_map(function ($e) {
            return  stripslashes($e);
        }, $m[0]);

        $links = array_filter(array_unique($links));
        return ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors];
    }
    protected function assertTestHtmlSelf($model, $id)
    {

        $itemTemplate =  $model->getItem($id); //object
        $this->assertNotEmpty($itemTemplate->id, 'A item with id: ' . $id . ' is needed');
        return $this->assertTestHtml($model, $itemTemplate, $id);
    }

    protected function assertTestHtml($model, object $item, $pks = [])
    {

        unset($item->id);
        unset($item->alias);
        unset($item->tagsHelper);
        unset($item->asset_id);
        unset($item->title);
        unset($item->assignment); //modules come with this crap
        unset($item->xml);
        if (empty($item->articletext)) {
            $item->articletext = $item->introtext . '<hr id="system-readmore">' . $item->fulltext ?? '';
        }
        unset($item->fulltext);
        unset($item->introtext);
        if (! $pks) {
            $testTitle =  JTEST_TITLE . ' Test';
            $pks       = ['title' => $testTitle];
        }

        $itemTest =  $model->getItem($pks); //object

        $this->assertNotEmpty($itemTest, 'A item with pks: ' . json_encode($pks) . ' is needed');
        $this->assertFalse((bool)$itemTest->checked_out, 'Item is checked out');
        unset($itemTest->tagsHelper);
        unset($itemTest->fulltext);
        unset($itemTest->introtext);
        unset($itemTest->assignment); //modules come with this crap
        unset($itemTest->xml);

        foreach ($item as $property => $value) {
            $itemTest->$property = $value;
        }

        $itemString  = json_encode($itemTest, JSON_UNESCAPED_SLASHES);
        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);

        $itemTest = json_decode($itemString, true);

        $input   = $this->getApplication()->getInput();
        $input->post->set('jform', $itemTest);
        //print $itemString;
        $model->save($itemTest);
        $this->assertempty($model->getError(), $model->getError());

        // return;
        foreach ($links as $link) {
        
            $this->assertLinkExists($link);
        }
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
}
