<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Controller;

use Blc\Component\Blc\Administrator\Controller\DisplayController;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Controller/DisplayController

 *
 * @since       25.44.7398
 */

// phpcs:disable PSR1.Files.SideEffects
if (! \defined('JPATH_COMPONENT')) {
    \define('JPATH_COMPONENT', JPATH_ROOT . '/administrator/components/com_blc');
}
// phpcs:enable PSR1.Files.SideEffects

#[Attributes\CoversClass(DisplayController::class)]
class DisplayControllerTest extends UnitTestCase
{
 

    protected function bootController()
    {
        $mvcFactory = $this->getApplication()->bootComponent('com_blc')->getMVCFactory();
        $controller = new DisplayController(factory: $mvcFactory, app: $this->getApplication());
        return $controller;
    }


    public function testCanBoot()
    {
        $controller = $this->bootController();
        $this->assertInstanceOf(DisplayController::class, $controller);
    }

    public function testBlcModuleDisabled()
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        $db      = $this->getDatabase();
        $query   = $db->createQuery();
        $query->select('count(*)')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_blc'))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $c = $db->loadResult();
        $this->assertSame($c, 0, 'The BLC Admin pseudo cron should be unpublished');
    }


    public function testdisplay()
    {
        $controller = $this->bootController();
        ob_start();
        $controller->display();
        $result =   ob_get_clean();
        $this->assertStringContainsString('<form', $result);
    }
}
