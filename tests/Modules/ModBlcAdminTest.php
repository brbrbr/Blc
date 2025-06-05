<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Modules;

use Blc\Module\Blc\Administrator\Dispatcher\Dispatcher;
use Blc\Tests\UnitTestCase;

#[Attributes\CoversClass(Dispatcher::class)]
class ModBlcAdminTest extends UnitTestCase
{
    private $testModule = [
        'id'        => 171,
        'title'     => 'BLC  Status',
        'module'    => 'mod_blc',
        'position'  => 'status',
        'content'   => null,
        'showtitle' => 0,
        'menuid'    => 0,
        'name'      => 'blc',
        'style'     => null,
    ];
    private $testParams = [
        'interval'        => 5,
        'layout'          => '_:status',
        'moduleclass_sfx' => '',
        'module_tag'      => 'div',
        'bootstrap_size'  => '0',
        'header_tag'      => 'h3',
        'header_class'    => '',
        'style'           => '0',
    ];


    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testboot()
    {

        $this->setUser('phpunit');
        $app            = $this->getApplication();
        $moduleInstance = $app->bootModule('mod_blc', 'administrator');
        $scope          = $app->scope;

        $module     = (object)$this->testModule;
        $app->scope = $module->module;
        $params     = $this->testParams;
        //default layout - empty
        unset($params['layout']);
        $module->params = json_encode($params);


        // Set scope to component name

        $dispatcher =  $moduleInstance->getDispatcher($module, $app);
        ob_start();
        $dispatcher->dispatch();
        $content = ob_get_clean();
        $this->assertEmpty($content, var_export($content, true));


        foreach (['_:status', '_:cron', '_:menu'] as $status) {
            $params['layout'] = $status;
            $module->params   = json_encode($params);


            // Set scope to component name

            $dispatcher =  $moduleInstance->getDispatcher($module, $app);
            ob_start();
            $dispatcher->dispatch();
            $nextContent = ob_get_clean();
            $this->assertNotEmpty($nextContent, var_export($nextContent, true));
            $this->assertNotEquals($nextContent, $content, $status);
            $content = $nextContent;
        }


        $this->enableBlc(false);

        $dispatcher =  $moduleInstance->getDispatcher($module, $app);
        ob_start();
        $dispatcher->dispatch();
        $content = ob_get_clean();
        $this->assertEmpty($content);

        $this->enableBlc(true);

        $this->getApplication()->logout();
        $this->app->loadIdentity();
        $dispatcher =  $moduleInstance->getDispatcher($module, $app);
        ob_start();
        $dispatcher->dispatch();
        $content = ob_get_clean();
        $this->assertEmpty($content);




        $app->scope = $scope;
    }
}
