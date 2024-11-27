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

use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Plugin\Blc\External\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
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
#[Attributes\TestDox('Test of the BLC - Invalid Plugin')]
class PlgBlcExternalTest extends UnitTestCase
{
    private string $folder  = 'blc';
    private string $element = 'invalid';

    protected string $fieldContext = 'com_content.categories';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }

    protected function bootPlugin(string $class, $config = [])
    {

        $dispatcher = $this->getDispatcher();
        $plugin     = new $class($dispatcher, $config ?? []);
        $plugin->setApplication($this->getApplication());
        $plugin->setDatabase($this->getDatabase());
        return $plugin;
    }

    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'external'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
        return $plugin;
    }

    public function testCanExtractEvent($format = 'csv')
    {
        $testLink = 'https://external.200.invalid/external-link-' . $format;
        $anchor   = 'Link from external.' . $format;
        $config   = (array)PluginHelper::getPlugin('blc', 'external');
        $params   = new Registry($config['params']);
        $params->set('freq', 1 / (3600 * 24));
        $url       = new \StdClass();
        $url->mime = 'text/csv';
        $url->name = 'Test link';
        $url->url  = 'blc/tests/assets/external.' . $format;
        $params->set('urls', [$url]);
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);

        //assume blc plugin group is loaded
        $arguments =
            [
                'maxExtract' => 10,
            ];
        $this->deleteLink($testLink);
        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);
        $this->assertLinkExists($testLink, msg: "Link import from {$url->url} failed");
        $this->assertAnchorExists($anchor);
        $this->assertMessageQueue();
    }
}
