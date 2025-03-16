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

use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Plugin\Blc\Yootheme\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Yootheme\Extension\YoothemeParser;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Module as BaseTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
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
class PlgBlcYoothemeTest extends UnitTestCase
{
    protected string $folder       = 'blc';
    protected string $element      = 'yootheme';
    protected string $class        = BlcPluginActor::class;
    protected string $fieldContext = 'com_content.categories';

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }
    public function wrapTable()
    {
        return new class ($this->getDatabase(), $this->getDispatcher(), $this) extends BaseTable {
            protected $parent;
            public function getItem($pks)
            {
                $this->load($pks);
                $c             = json_decode($this->content);
                $this->content = json_encode($c, JSON_UNESCAPED_SLASHES);
                return (object) get_object_vars($this);
            }
            public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null, ?UnitTestCase $parent = null)
            {

                $this->parent = $parent;
                parent::__construct($db, $dispatcher);
            }

            public function save($src, $orderingFilter = '', $ignore = '')
            {
                $c              = json_decode($src['content']);
                $src['content'] = json_encode($c);
                $model          = $this->parent->getModel('com_modules', 'Module');
                $res            = $model->save($src);
                if (!$res) {
                    throw new Execption($model->getError());
                }

                return $res;
            }
        };
    }


    public function testCanBoot()
    {

        $dispatcher = $this->getDispatcher();
        $plugin     = new BlcPluginActor($dispatcher, (array)PluginHelper::getPlugin('blc', 'yootheme'));
        $plugin->setApplication($this->app);

        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
        return $plugin;
    }


    public function testCanParser()
    {

        $parser = YoothemeParser::getInstance();
        $this->assertInstanceOf(BlcParserInterface::class, $parser);

        $this->assertMessageQueue();
        return $parser;
    }


    public function testLinkExtraction(): array
    {

        $parser = $this->testCanParser();
        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $this->assertNotFalse($model);

        $templateTitle = JTEST_TITLE . ' yootheme Template';
        $itemTemplate  = $model->getItem(['title' => $templateTitle]); //object
        $this->assertNotEmpty($itemTemplate->id, 'A item with title: ' . $templateTitle . ' is needed');
        //we want to test the json tree in fulltext, not the teaser in introtext
        preg_match('/^<!-- (\{.*\}) -->/', $itemTemplate->fulltext, $m);
        $this->assertNotEmpty($m, 'No yoothem template');
        $jsonString = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
        $this->assertNotEmpty($jsonString, 'No yootheme json');

        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] =  $this->injectLinks("<!-- {$jsonString} -->");
        $foundLinks                                                            = $parser->extractfromSource($itemString);


        $flatfoundLinks   = array_column($foundLinks, 'url');
        $flatfoundAnchors = array_column($foundLinks, 'anchor');

        $this->assertNotEmpty($flatfoundLinks, 'No links found, fill the template');

        foreach ($anchors as $anchor) {
            $this->assertContains($anchor, $flatfoundAnchors);
        }

        foreach ($links as $link) {
            $this->assertContains($link, $flatfoundLinks);
        }

        return [$links, $itemString];
    }


    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $data)
    {
        [$links, $source] = $data;
        $parser           = $this->testCanParser();
        foreach ($links as $oldLink) {
            ['itemString' => $newLink] =  $this->injectLinks($oldLink);
            $newSource                 = $parser->replaceInSource($source, $oldLink, $newLink);
            preg_match('/^<!-- (\{.*\}) -->/', $newSource, $m);
            $this->assertNotEmpty($m, 'No yoothem json');
            //make it searchanle
            $newSource = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
            $this->assertStringContainsString($newLink, $newSource);
        }
    }

    public function testContentExtraction(): array
    {
        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model, JTEST_TITLE . ' yootheme');

        return $links;
    }

    #[Attributes\Depends('testContentExtraction')]
    public function testContentLinkReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }

    public static function getYoothemeModulesWithContent()
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('`id`')->from('`#__modules`')
            ->where('`content` != ""')
            ->where('`module` ="mod_yootheme_builder"'); // yootheme
        $list = $db->setQuery($query)->loadAssocList();
        return $list;
    }
    /**
     *
     * also tested in Modcustom as that one tests all modules with content
     */
    #[Attributes\DataProvider('getYoothemeModulesWithContent')]
    public function testYoothemeModuleLinks(int $id)
    {

        $this->testCanBoot();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->wrapTable();
        $this->assertNotFalse($model);
        $itemTemplate = $model->getItem($id); //object
        $links        = $this->assertTestHtml($model, $itemTemplate, $id);
        $this->assertLinksReplace($links);
        return $links;
    }
}
