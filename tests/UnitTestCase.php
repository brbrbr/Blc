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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\DI\Container;
use Joomla\Event\DispatcherInterface;
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
        PluginHelper::importPlugin('system', null, true, $this->getDispatcher());
    }

    protected function setUser($user = 'phpunit', $action = null, $assetKey = null): void
    {
        $isUser = $this->app->loadIdentity();
        if (! $isUser) {
            $user = $this->container->get(UserFactoryInterface::class)->loadUserByUsername($user);
            $this->app->getSession()->set('user', $user);
            $this->app->loadIdentity($user);
            if ($action) {
                $can = (bool) Access::check($user->id, $action, $assetKey);
                $this->assertTrue($can, 'User has not the right access right for:' . $action);
            }
        }
    }


    protected function getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
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
            $this->assertNotEmpty($messages, "Messages '$type' found:\n " . join("\n ", $messages) . "\n");
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

    protected function bootPlugin(string $class, $config = [])
    {

        $dispatcher = $this->getDispatcher();
        $plugin     = new $class($dispatcher, $config ?? []);
        $plugin->setApplication($this->app);
        $plugin->setDatabase($this->db);
        return $plugin;
    }


    public function assertLinkReplace(array $urls)
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_blc', 'Link');

        foreach ($urls as $url) {
            $linkItem   = $this->assertLinkExists($url);
            $synch      = $model->getSynch($linkItem->id);
            $unique     = uniqid();

            $newUrl = "https://phpunit.invalid/replaced-$unique";

            foreach ($synch as $row) {
                $sourcePlugin = $row->plugin;
                $activePlugin = $model->getPlugin($sourcePlugin);
                if ($activePlugin) {
                    ob_start();
                    $activePlugin->replaceLink($linkItem, $row, $newUrl);
                    ob_get_clean();
                }
            }
            $this->assertLinkExists($newUrl, msg: "old: $url");

            $synch = $model->getSynch($linkItem->id);
        }
    }


    protected function assertTestPage($model, string $titlePrefix = '')
    {
        $links = [];
        $anchors = [];
        $templateTitle = ($titlePrefix ?: JTEST_TITLE) . ' Template';
        $testTitle =  ($titlePrefix ?: JTEST_TITLE) . ' Test';

        $itemTemplate = $model->getItem(['title' => $templateTitle]); //object
        $this->assertNotEmpty($itemTemplate, 'A item with title: ' . $templateTitle . ' is needed');

        if ($this->fieldContext) {
            $rows = FieldsHelper::getFields($this->fieldContext, $itemTemplate);
            $com_fields = [];
            foreach ($rows as $row) {
                //we don't test the field parser just the connection from the parent.
                if (in_array($row->type, ['editor', 'url'])) {
                    $com_fields[$row->name] = $row->rawvalue;
                }
            }

            $itemTemplate->com_fields = ArrayHelper::toObject($com_fields);
        }

        if (isset($itemTemplate->introtext)) {
            $itemTemplate->articletext = $itemTemplate->introtext . '<hr id="system-readmore">' . $itemTemplate->fulltext;
            unset($itemTemplate->fulltext);
            unset($itemTemplate->introtext);
        }
        $itemString = json_encode($itemTemplate, JSON_UNESCAPED_SLASHES);
        $itemString = preg_replace_callback(
            '#phpunit.(text|jpg|png)#',
            function ($m) {
                return uniqid() . '.' . $m[1];
            },
            $itemString
        );

        $itemString = preg_replace_callback(
            '#phpunit.invalid#',
            function ($m) {
                return uniqid() . '-gen.invalid';
            },
            $itemString
        );


        $itemString = preg_replace_callback(
            '#phpunit.anchor#',
            function ($m) use (&$anchors) {
                $anchor = uniqid() . ' Generated Anchor';
                $anchors = $anchor;
            },
            $itemString
        );
        preg_match_all('#(https://(.*?)\.(com|dev|invalid)[a-z0-9\-/./]+)#u', $itemString, $m);

        $links = $m[1];

        $itemTemplate = json_decode($itemString, true); //array
        $itemTest = $model->getItem(['title' => $testTitle]); //object

        $this->assertNotEmpty($itemTest, 'A Categorie item with title: ' . $testTitle . ' is needed');

        if ((bool)$itemTest->checked_out === true) {

            $this->markTestSkipped(
                "Warning test item is checkedout",
            );
            return [];
        }
        $itemTemplate['id'] = $itemTest->id;
        $itemTemplate['alias'] = $itemTest->alias;
        $itemTemplate['title'] = $itemTest->title; // must be $itemTest
        $input                                     = $this->getApplication()->getInput();
        $input->post->set('jform', $itemTemplate);

        $model->save($itemTemplate);

        foreach ($links as $link) {
            $this->assertLinkExists($link);
        }
        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }

        return $links;
    }
}
