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

use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Plugin\Blc\Yootheme\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Yootheme\Extension\YoothemeParser;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Table\Module as BaseTable;
use Joomla\Database\DatabaseDriver;
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
#[Attributes\CoversClass(YoothemeParser::class)]
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcYoothemeTest extends UnitTestCase
{
    protected string $folder       = 'blc';
    protected string $element      = 'yootheme';
    protected string $class        = BlcPluginActor::class;
    protected string $fieldContext = 'com_content.categories';


    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
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

        $this->assertNotNull($this->class);
        $plugin = $this->bootPlugin(assert: true);
        $this->assertInstanceOf($this->class, $plugin);
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

    public function testextractfromSource()
    {
        $data   = file_get_contents(JPATH_ROOT . '/blc/tests/assets/yootheme.json');
        $data   = json_encode(json_decode($data)); //make it a one liner
        $parser = $this->testCanParser();
        $links  = $parser->extractfromSource($data);

        $cLinks = \count($links);
        $this->assertGreaterThan(0, $cLinks, 'No links found');
        $data   = '<!-- ' . $data . ' -->';
        $links  = $parser->extractfromSource($data);
        $cLinks = \count($links);
        $this->assertGreaterThan(0, $cLinks, 'No links found');
        return [$links, $data];
    }

    public static function fieldProvider()
    {
        return [


            ['fulltext', 'Yootheme'],



        ];
    }

    /* the yootheme parser is not an extractor. Here we test the connection between a changed content item and the yootheme parser */
    #[Attributes\DataProvider('fieldProvider')]
    public function testreplaceLink($field, $parser)
    {
        $element       = $this->element;
        $class         = $this->class;
        $this->class   = \Blc\Plugin\Blc\Content\Extension\BlcPluginActor::class;
        $this->element = 'content';

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $this->assertReplaceLink($field, $parser);
        $this->element = $element;
        $this->class   = $class;
    }


    #[Attributes\Depends('testextractfromSource')]
    public function testreplaceInSource(array $data)
    {
        [$links, $source] = $data;
        $parser           = $this->testCanParser();
        foreach ($links as $oldLink) {
            $newLink                   = $this->getRandomLink();
            $newSource                 = $parser->replaceInSource($source, $oldLink['url'], $newLink);
            preg_match('/^<!-- (\{.*\}) -->/', $newSource, $m);
            $this->assertNotEmpty($m, 'No yoothem json');
            //make it searchanle
            $newSource = json_encode(json_decode($m[1]), JSON_UNESCAPED_SLASHES);
            $this->assertStringContainsString($newLink, $newSource);
        }
    }


    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();
    }
    public function testonBlcParserRequest()
    {
        $this->checkonBlcParserRequest();
    }
}
