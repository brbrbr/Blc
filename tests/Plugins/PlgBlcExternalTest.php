<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Plugins;

use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Plugin\Blc\External\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Language\Text;
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
class PlgBlcExternalTest extends UnitTestCase
{
    protected string $folder       = 'blc';
    protected string $element      = 'external';
    protected string $class        = BlcPluginActor::class;
    protected string $context      = 'com_blc.external';
    public function setUp(): void
    {
        parent::setUp();
        $this->checkPluginEnabled();
    }

    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }

    public function testCanBoot()
    {

        $plugin =  $this->bootPlugin(assert: true);
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

        $url       = new \StdClass();
        $url->mime = $mime;
        $url->name = 'Test link:' . $format;
        $url->url  = BlcHelper::root('blc/tests/assets/external.' . $format . '?test=' . $format); //ensure unique url for the synchtable
        $params->set('urls', [$url]);
        $params->set('freq', -1); // re extract
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);

        //assume blc plugin group is loaded
        $arguments =
            [
                'maxExtract' => 100,
            ];
        $this->deleteLink($testLink);

        $container_id            = crc32($this->element . $url->url);
        $this->setLastSynch(container_id: $container_id);

        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);


        $this->assertLinkExists($testLink, msg: "Link import from {$url->url} failed ($container_id)");
        if ($format !== 'xml') {
            $anchor   = 'Link from external.' . $format;
            $this->assertAnchorExists($anchor);
        } else {
            $testImage   = $testLink . '.webp';
            $this->assertLinkExists($testImage, msg: "Link import from {$url->url} failed ($container_id)");
        }
        $this->assertMessageQueue();
    }



    public function testonBlcExtractPing()
    {
        $format   = 'csv';
        $mime     = 'text/csv';
        $testLink = 'https://external.200.invalid/external-link-' . $format;


        $config   = (array)PluginHelper::getPlugin('blc', 'external');
        $params   = new Registry($config['params']);

        $url       = new \StdClass();
        $url->mime = $mime;
        $url->name = 'Test link:' . $format;
        $url->url  =  BlcHelper::root('blc/tests/assets/external.' . $format . '?test=' . $format); //ensure unique url for the synchtable
        $params->set('urls', [$url]);
        $params->set('freq', -1); // re extract
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);

        //assume blc plugin group is loaded
        $arguments =
            [
                'maxExtract' => 100,
            ];
        $this->deleteLink($testLink);

        $container_id            = crc32($this->element . $url->url);
        $this->setLastSynch(container_id: $container_id);

        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);
        $linkItem = $this->assertLinkExists($testLink, msg: "Link import from {$url->url} failed");
        if ($format !== 'xml') {
            $anchor   = 'Link from external.' . $format;
            $this->assertAnchorExists($anchor);
        }
        $this->assertMessageQueue();


        $this->clearMessageQueue();
        $link           = $this->getSomeLinkId(parser: '', plugin: $this->element, fields: [], linkPattern: $testLink);
        $this->assertNotNull($link, "No link found to test ({$this->element}): " . ' ' . json_encode($this->lastQueryInfo));
        $this->setLastSynch(container_id: $container_id);
        $newLink            = $this->getRandomLink();
        $plugin->replaceLink($linkItem, $link, $newLink);
        $this->assertMessageQueue('warning', empty:Text::_('PLG_BLC_EXTERNAL_EXTRACT_NO_REPLACE'));

        $this->clearMessageQueue();
        //replace with ping
        $url->ping = BlcHelper::root();
        $params->set('urls', [$url]);
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);
        $plugin->replaceLink($linkItem, $link, $newLink);
        $this->assertMessageQueue('success', empty: 'External ping - link hidden');


        $this->clearMessageQueue();
        //replace with ping
        $url->ping = BlcHelper::root() . '/non-existing-page-' . uniqid();
        $params->set('urls', [$url]);
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);
        $plugin->replaceLink($linkItem, $link, $newLink);
        $this->assertMessageQueue('warning', empty: 'External ping - Failed');


        $this->clearMessageQueue();
        //replace with ping
        $url->ping = "dummy://example.com/" . uniqid();
        $params->set('urls', [$url]);
        $config['params'] = (string)$params;
        $plugin           =  $this->bootPlugin(BlcPluginActor::class, $config);
        $plugin->replaceLink($linkItem, $link, $newLink);
        $this->assertMessageQueue('error', empty: 'External ping - Failed');
    }


    public function testMagicGet()
    {
        $this->assertMagicGet();
    }
    public function testonBlcExtractJson()
    {

        $urls     = ['url', 'link', 'u'];
        $anchors  = ['name', 'title', 'l', 'plaats'];
        $config   = (array)PluginHelper::getPlugin('blc', 'external');
        $params   = new Registry($config['params']);

        $url       = new \StdClass();
        $url->name = 'Test link Json all';
        $url->url  = 'blc/tests/assets/external-all.json';
        $params->set('urls', [$url]);
        $params->set('freq', -1); // re extract
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
        //special case
        $this->deleteLink("https://external:200.invalid/external-link-json-u-invalid");

        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);

        foreach ($urls as $url) {
            foreach ($anchors as $anchor) {
                $this->assertLinkExists("https://external.200.invalid/external-link-json-$url-$anchor");
                $this->assertAnchorExists("$url-$anchor");
            }
        }

        //special case
        $this->assertLinkExists("https://external:200.invalid/external-link-json-u-invalid");
        $this->assertAnchorExists("u-invalid");


        $this->assertMessageQueue();
    }

    public function testgetEditLink()
    {
        $plugin                                                                      = $this->bootPlugin();
        $instance                                                                    = new \stdClass();
        $link                                                                        = $plugin->getEditLink($instance);
        $this->assertEmpty($link);
    }

    public function testgetViewLink()
    {
        $plugin                                                                = $this->bootPlugin();
        $instance                                                              = new \stdClass();
        $link                                                                  = $plugin->getViewLink($instance);
        $this->assertEmpty($link);
    }

    public function testgetTitle()
    {

        $plugin                                                                = $this->bootPlugin();
        $instance                                                              = new \stdClass();
        $instance->field                                                       = uniqid();
        $link                                                                  = $plugin->getTitle($instance);
        $this->assertEquals($instance->field, $link);
    }

    /**
     *  this tests the call off onBlcExtensionAfterSave and via the onExtensionAfterSave Event
     *  more detailed tests are in the test of the trait
     *
     */
    public function testOnExtensionAfterSave()
    {
        $this->assertOnExtensionAfterSave();
    }
    /**
     * This tests is for code coverage and code validation
     */
    public function testOnBlcContainerChanged()
    {
        $this->clearMessageQueue();
        $this->isSubscribed('onBlcContainerChanged');


        $itemTest =  $this->getSomeLinkId(parser: '', plugin: $this->element, fields: []);
        $plugin   = $this->bootPlugin();

        $onBlcContainerChangedarguments =
            [
                'context' => $this->context,
                'id'      => $itemTest->container_id,
                'event'   => 'onsave',
            ];

        $event = new BlcEvent('onBlcContainerChanged', $onBlcContainerChangedarguments);

        $plugin->params->set('onsave', 'parse');
        $plugin->onBlcContainerChanged($event);

        $this->assertMessageQueue('info', true);
    }

    public function xtestonBlcExtractDummy()
    {
        $plugin        = $this->bootPlugin();

        $params = new Registry($plugin->params);
        var_dump($params->get('urls'));
    }
}
