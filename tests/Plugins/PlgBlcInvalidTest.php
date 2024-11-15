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

use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Plugin\Blc\Invalid\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
use PHPUnit\Framework\Attributes;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcPluginActor::class)]
#[Attributes\TestDox('Test of the BLC - Invalid Plugin')]
class PlgBlcInvalidTest extends UnitTestCase
{
    private string $folder  = 'blc';
    private string $element = 'invalid';

    protected string $fieldContext = 'com_content.categories';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }

    protected function bootPlugin(string $class, $config = [])
    {

        $dispatcher = $this->getDispatcher();
        $plugin     = new $class($dispatcher, $config ?? []);
        $plugin->setApplication($this->getApplication());
        return $plugin;
    }

    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'category'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
        return $plugin;
    }

    public function testCanNotCheckCom()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://domain.com'
        ]);
        $plugin = $this->testCanBoot();
        $result = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $result);
    }
    public function testCanCheckInvalid()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://domain.invalid'
        ]);
        $plugin = $this->testCanBoot();
        $result = $plugin->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $result);
    }
    public function testCheckInvalidDefault()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://domain.invalid'
        ]);
        $linkItem->_toCheck = $linkItem->url;
        $plugin = $this->testCanBoot();
        $results = [];
        $results = $plugin->checkLink($linkItem, $results);
        $this->assertSame($results['http_code'], 206);
        $this->assertSame($results['broken'], 0);
    }
    public function testCheckInvalid200()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://domain.200.invalid'
        ]);
        $linkItem->_toCheck = $linkItem->url;
        $plugin = $this->testCanBoot();
        $results = [];
        $results = $plugin->checkLink($linkItem, $results);
        $this->assertSame($results['http_code'], 200);
        $this->assertSame($results['broken'], 0);
    }


    public function testCheckInvalid301()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://domain.301.invalid'
        ]);
        $linkItem->_toCheck = $linkItem->url;
        $plugin = $this->testCanBoot();
        $results = [];
        $results = $plugin->checkLink($linkItem, $results);
        $this->assertSame($results['http_code'], 301);
        $this->assertSame($results['broken'], 0);
        $this->assertSame($results['redirect_count'], 1);
        $this->assertSame($results['final_url'], $linkItem->url . '-pseude-redirect-301');
    }


    public function testCheckInvalid302()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://new.302.invalid'
        ]);
        $linkItem->_toCheck = $linkItem->url;
        $plugin = $this->testCanBoot();
        $results = [];
        $results = $plugin->checkLink($linkItem, $results);
        $this->assertSame($results['http_code'], 302);
        $this->assertSame($results['redirect_count'], 1);
        $this->assertSame($results['broken'], 0);
        $this->assertSame($results['final_url'], $linkItem->url . '-pseude-redirect-302');
    }
    public function testCheckInvalid404()
    {

        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => 'https://domain.404.invalid'
        ]);
        $linkItem->_toCheck = $linkItem->url;
        $plugin = $this->testCanBoot();
        $results = [];
        $results = $plugin->checkLink($linkItem, $results);
        $this->assertSame($results['http_code'], 404);
        $this->assertSame($results['broken'], 1);
    }
}
