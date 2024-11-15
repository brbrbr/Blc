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

use Blc\Plugin\Blc\ModCustom\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\Table\Module as BaseTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Database\DatabaseInterface;

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
class PlgBlcModcustomTest extends UnitTestCase
{
    private string $folder  = 'blc';
    private string $element = 'modcustom';

    protected string $fieldContext = 'com_content.categories';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }

    public function testCanBoot()
    {
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'modcustom'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }
    public static function getModulesWithContent()
    {

        //new PlgBlcModcustomTest();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('`id`')->from('`#__modules`')
            ->where('`content` != ""');
        $list = $db->setQuery($query)->loadAssocList();
        return $list;
    }


    public function wrapTable()
    {
        return new  class($this->getDatabase(), $this->getDispatcher(), $this) extends BaseTable {
            protected $parent;
            function getItem($pks)
            {
                $this->load($pks);
                return (object) get_object_vars($this);
            }
            public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null, UnitTestCase $parent = null)
            {

                $this->parent = $parent;
                parent::__construct($db, $dispatcher);
            }

            function save($src, $orderingFilter = '', $ignore = '')
            {
                $model = $this->parent->getModel('com_modules', 'Module');
                $res = $model->save($src);
                if (!$res) {

                    throw new Execption($model->getError());
                }

                return $res;
            }
        };
    }

    #[Attributes\DataProvider('getModulesWithContent')]
    public function testLinkExtraction(int $id)
    {
        //the extractor is booted from the system/blc plugin.
        $this->testCanBoot();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->wrapTable();

        $this->assertNotFalse($model);
        return $this->assertTestHtmlSelf($model, $id);
    }

    #[Attributes\DataProvider('getModulesWithContent')]
    public function testLinkReplace(int $id)
    {
        $urls = $this->testLinkExtraction($id);
        $this->assertLinksReplace($urls);
    }
}
