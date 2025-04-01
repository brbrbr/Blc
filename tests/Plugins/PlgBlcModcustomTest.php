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

use Blc\Plugin\Blc\ModCustom\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Factory;
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

class PlgBlcModcustomTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;
    protected string $folder  = 'blc';
    protected string $element = 'modcustom';
    protected string $class   = BlcPluginActor::class;
    protected string $context = 'com_modules.module';



    public function setUp(): void
    {
        $this->initApplication();

        $this->checkPluginEnabled();
    }




    public function  getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
    {
        return new class($this->getDatabase(), $this->getDispatcher(), $this) extends BaseTable {
            protected $parent;
            public function getItem($pks)
            {

                $this->load($pks);
                return (object) get_object_vars($this);
            }

            public function getTable($type = 'Module', $prefix = '\\Joomla\\CMS\\Table\\')
            {
                $tableClass = $prefix  . ucfirst($type);
                return new $tableClass($this->parent->getDatabase(), $this->parent->getDispatcher());
            }
            public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null, ?UnitTestCase $parent = null)
            {

                $this->parent = $parent;
                parent::__construct($db, $dispatcher);
            }

            public function save($src, $orderingFilter = '', $ignore = '')
            {
                $model = $this->parent->getModel('com_modules', 'Module');
                $res   = $model->save($src);
                if (!$res) {
                    throw new Execption($model->getError());
                }

                return $res;
            }
        };
    }




    public static function fieldProvider()
    {
        return [

            ['content', 'href'],
            ['backgroundimage', 'links'],


        ];
    }
}
