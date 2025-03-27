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
    protected string $folder       = 'blc';
    protected string $element      = 'external';
    protected string $class        = BlcPluginActor::class;
    protected string $context      = 'com_blc.external';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }



    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin();
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
        return $plugin;
    }
    public static function formatProvider(): array
    {

        return [
            ['csv', 'text/csv'],
            ['json', 'application/json'],
            ['xml', 'text/xml'],
            ['html', 'sitemap/html'],
            ['csv', ''],
            ['json', ''],
            ['xml', ''],
            ['html', ''],

        ];
    }
    #[Attributes\DataProvider('formatProvider')]
    public function testonBlcExtract($format, $mime)
    {
        $testLink = 'https://external.200.invalid/external-link-' . $format;


        $config   = (array)PluginHelper::getPlugin('blc', 'external');
        $params   = new Registry($config['params']);
        $params->set('freq', 1 / (3600 * 24));
        $url       = new \StdClass();
        $url->mime = $mime;
        $url->name = 'Test link:' . $format;
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
        if ($format !== 'xml') {
            $anchor   = 'Link from external.' . $format;
            $this->assertAnchorExists($anchor);
        }
        $this->assertMessageQueue();
    }

    public function testMagicGet()
    {
        $this->doMagicGetTest();
    }
    public function testonBlcExtractJson()
    {

        $urls     = ['url', 'link', 'u'];
        $anchors  = ['name', 'title', 'l', 'plaats'];
        $config   = (array)PluginHelper::getPlugin('blc', 'external');
        $params   = new Registry($config['params']);
        $params->set('freq', 1 / (3600 * 24));
        $url       = new \StdClass();
        $url->name = 'Test link Json all';
        $url->url  = 'blc/tests/assets/external-all.json';
        $params->set('urls', [$url]);
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);

        //assume blc plugin group is loaded
        $arguments =
            [
                'maxExtract' => 10,
            ];
        foreach ($urls as $url) {
            foreach ($anchors as $anchor) {
                $this->deleteLink("https://external.200.invalid/external-link-json-$url-$anchor");
            }
        }

        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);

        foreach ($urls as $url) {
            foreach ($anchors as $anchor) {
                $this->assertLinkExists("https://external.200.invalid/external-link-json-$url-$anchor");
                $this->assertAnchorExists("$url-$anchor");
            }
        }


        $this->assertMessageQueue();
    }

    public function testgetEditLink()
    {

        $plugin                                                                      = $this->bootPlugin();

        $instance = new \stdClass();

        $link = $plugin->getEditLink($instance);
        $this->assertEmpty($link);
    }

    public function testgetViewLink()
    {

        $plugin                                                                = $this->bootPlugin();

        $instance = new \stdClass();

        $link = $plugin->getViewLink($instance);
        $this->assertEmpty($link);
    }




    public function testgetTitle()
    {

        $plugin                                                                = $this->bootPlugin();

        $instance        = new \stdClass();
        $instance->field = uniqid();

        $link = $plugin->getTitle($instance);
        $this->assertEquals($instance->field, $link);
    }
}
