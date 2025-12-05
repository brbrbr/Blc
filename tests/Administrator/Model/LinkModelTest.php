<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Model;

use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Model\LinkModel;
use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(LinkModel::class)]
#[Attributes\TestDox('Test of the System - BLC Plugin')]
class LinkModelTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function bootModel()
    {
        $model = new LinkModel(['ignore-request' => true]);
        $this->assertInstanceOf(BaseDatabaseModel::class, $model);
    }

    public function testgetTable()
    {
        $model = new LinkModel(['ignore-request' => true]);

        $table = $model->getTable();




        $this->assertInstanceOf(LinkTable::class, $table);
        $table = $model->getTable('Link');
        $this->assertInstanceOf(LinkTable::class, $table);

        $table = $model->getTable('Instance');
        $this->assertInstanceOf(InstanceTable::class, $table);

        $table = $model->getTable('Synch');
        $this->assertInstanceOf(SynchTable::class, $table);

        $this->expectException(\Exception::class);

        $table = $model->getTable('Article');
    }

    public function testgetForm()
    {

        $model = new LinkModel(['ignore-request' => true]);
        $form  = $model->getForm();
        $this->assertFalse($form);
    }

    public function testgetItem()
    {
        $model = new LinkModel();

        $linkItemHref = $this->assertGetSomeLink(parser: 'href'); //this should be a link with instances
        Factory::getApplication()->getInput()->set('id', $linkItemHref->id);

        //from request
        $item = $model->getItem();
        $this->assertInstanceOf(LinkTable::class, $item);

        $this->assertSame($item->url, $linkItemHref->url);

        //request is only used once
        $linkItemImg = $this->assertGetSomeLink(parser: 'img');
        Factory::getApplication()->getInput()->set('id', $linkItemHref->id);
        $item = $model->getItem();

        $this->assertSame($item->url, $linkItemHref->url);

        $item = $model->getItem($linkItemImg->id);

        $this->assertSame($item->url, $linkItemImg->url);

        //stored link
        $item = $model->getItem();
        $this->assertSame($item->url, $linkItemImg->url);

        //get a 'new' link
        $item = $model->getItem($linkItemHref->id);
        $this->assertSame($item->url, $linkItemHref->url);
    }

    public function testgetPlugin()
    {
        $model = new LinkModel(['ignore-request' => true]);

        $plugin = $model->getPlugin('content'); //extractor
        $this->assertInstanceOf(BlcExtractInterface::class, $plugin);

        $plugin = $model->getPlugin('invalid'); //not an extractor
        $this->assertFalse($plugin);
    }





    public function testgetSynch()
    {
        $model     = new LinkModel(['ignore-request' => true]);
        $linkItem  = $this->getSomeLinkId(); //this should be a link with instances
        $instances = $model->getSynch($linkItem->link_id);
        $this->assertNotEmpty($instances);
    }
}
