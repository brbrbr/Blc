<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Model;

use Blc\Component\Blc\Administrator\Model\ExploreModel;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes;

// phpcs:disable PSR1.Files.SideEffects
if (! \defined('JPATH_COMPONENT')) {
    \define('JPATH_COMPONENT', JPATH_ROOT . '/administrator/components/com_blc');
}
// phpcs:enable PSR1.Files.SideEffects

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Model/ExploreModel

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(ExploreModel::class)]
class ExploreModelTest extends UnitTestCase
{


    public function bootModel()
    {
        $model = new ExploreModel(['ignore-request' => true]);

        return $model;
    }

    public function testsetUp()
    {
        $model = $this->bootModel();
        $this->assertInstanceOf(BaseDatabaseModel::class, $model);
    }

    public function testgetFilterForm()
    {
        $model  =  $this->bootModel();
        $result = $model->getFilterForm();
        $this->assertInstanceOf(\Joomla\CMS\Form\Form::class, $result);
    }

    public function testgetTotal()
    {
        $model  =  $this->bootModel();
        $result =   $model->getTotal();
        $this->assertIsNumeric($result);
        //coverage cache
        $result =   $model->getTotal();
        $this->assertIsNumeric($result);
    }

    public function testgetItems()
    {
        $model  =  $this->bootModel();
        $result =   $model->getItems();
        $this->assertIsArray($result);
    }
}
