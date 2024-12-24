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

use Blc\Plugin\Blc\RsEventsEvent\Extension\BlcPluginActor as RsEventsEventActor;
use Blc\Plugin\Blc\RsEventsLocation\Extension\BlcPluginActor as RsEventsLocation;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Event as CMSEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
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
#[Attributes\TestDox('Test of the BLC - Content Plugin')]
class PlgBlcRsEventTest extends UnitTestCase
{
    private string $folder  = 'blc';

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, 'rseventsevent');
        $this->checkPluginEnabled($this->folder, 'rseventslocation');
    }

    /**
     *
     * test all with content. not just the custem html ones
     */
    protected function getEventWithContent()
    {

        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('*')
            ->from('`#__rseventspro_events`')
            ->where('`URL` != ""')
            ->where('`description` like "%href%"')
            ->setLimit(1);
        $event = $db->setQuery($query)->loadAssoc();
        $this->assertNotempty($event, 'Some events with urls are needed');

        return $event;
    }

    /**
     *
     * test all with content. not just the custem html ones
     */
    protected function getLocationWithContent()
    {
        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('*')
            ->from('`#__rseventspro_locations`')
            ->where('`url` != ""')
            ->where('`description` like "%href%"')
            ->setLimit(1);

        $location = $db->setQuery($query)->loadAssoc();

        $this->assertNotempty($location, 'Some locations with urls are needed');
        return $location;
    }
    /**
     *
     * test all with content. not just the custem html ones
     */
    protected function getTranslationWithContent($reference)
    {
        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true);

        $query->select($db->quoteName('id'))
            ->select($db->quoteName('property'))
            ->select($db->quoteName('reference_id'))
            ->select($db->quoteName('value'))
            ->where($db->quoteName('reference') . '= :reference')
            ->bind(':reference', $reference)

            ->whereIn($db->quoteName('property'), ['description', 'URL'], ParameterType::STRING)
            ->from($db->quoteName('#__rseventspro_translations'))
            ->setLimit(1);


        $translation = $db->setQuery($query)->loadAssoc();

        $this->assertNotempty($translation, 'Some locations with urls are needed');
        return $translation;
    }

    protected function saveEvent($table)
    {
        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        if (! $db->updateObject('#__rseventspro_events', $table, 'id', false)) {
            throw new GenericDataException($db->getError(), 500);
        }

        $this->getDispatcher()->dispatch('onContentAfterSave', new CMSEvent\Model\AfterSaveEvent('onContentAfterSave', [
            'context' => 'com_rseventspro.event',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ]));
    }

    protected function saveTranslation($table, $context)
    {
        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        if (! $db->updateObject('#__rseventspro_translations', $table, 'id', false)) {
            throw new GenericDataException($db->getError(), 500);
        }
        $row     = new \stdClass();
        $row->id = $table->reference_id;

        $this->getDispatcher()->dispatch('onContentAfterSave', new CMSEvent\Model\AfterSaveEvent('onContentAfterSave', [
            'context' => 'com_rseventspro.' . $context,
            'subject' => $row,
            'isNew'   => false,
            'data'    => [],
        ]));
    }

    protected function saveLocation($table)
    {
        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        if (! $db->updateObject('#__rseventspro_locations', $table, 'id', false)) {
            throw new GenericDataException($db->getError(), 500);
        }

        $this->getDispatcher()->dispatch('onContentAfterSave', new CMSEvent\Model\AfterSaveEvent('onContentAfterSave', [
            'context' => 'com_rseventspro.location',
            'subject' => $table,
            'isNew'   => false,
            'data'    => [],
        ]));
    }


    public function testCanBootEvent()
    {
        $plugin =  $this->bootPlugin(RsEventsEventActor::class, (array)PluginHelper::getPlugin('blc', 'rseventsevent'));
        $this->assertInstanceOf(RsEventsEventActor::class, $plugin);
        $this->assertMessageQueue();
    }

    public function testCanBootLocation()
    {
        $plugin =  $this->bootPlugin(RsEventsLocation::class, (array)PluginHelper::getPlugin('blc', 'rseventslocation'));
        $this->assertInstanceOf(RsEventsLocation::class, $plugin);
        $this->assertMessageQueue();
    }

    public function testEventExtraction()
    {
        $plugin =  $this->bootPlugin(RsEventsEventActor::class, (array)PluginHelper::getPlugin('blc', 'rseventsevent'));
        $this->assertInstanceOf(RsEventsEventActor::class, $plugin);
        $this->assertMessageQueue();
        $itemTest                                                              = $this->getEventWithContent();
        $itemString                                                            = json_encode($itemTest, JSON_UNESCAPED_SLASHES);
        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);
        $this->assertNotNull($links, 'No links found');

        $itemTest = json_decode($itemString, false);
        $this->saveEvent($itemTest);

        $this->assertLinksExists($links);
        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }
        return $links;
    }

    #[Attributes\Depends('testEventExtraction')]
    public function testEventReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }

    public function testLocationExtraction()
    {
        $plugin =  $this->bootPlugin(RsEventsEventActor::class, (array)PluginHelper::getPlugin('blc', 'rseventsevent'));
        $this->assertInstanceOf(RsEventsEventActor::class, $plugin);
        $this->assertMessageQueue();
        $itemTest                                                              = $this->getLocationWithContent();
        $itemString                                                            = json_encode($itemTest, JSON_UNESCAPED_SLASHES);
        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);
        $this->assertNotNull($links, 'No links found');

        $itemTest = json_decode($itemString, false);
        $this->saveLocation($itemTest);
        $this->assertLinksExists($links);
        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }
        return $links;
    }

    #[Attributes\Depends('testLocationExtraction')]
    public function testLocationReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }

    public function testTranslationLocationExtraction()
    {
        $this->setTranslationEnabled(1);
        $plugin =  $this->bootPlugin(RsEventsEventActor::class, (array)PluginHelper::getPlugin('blc', 'rseventsevent'));
        $this->assertInstanceOf(RsEventsEventActor::class, $plugin);
        $this->assertMessageQueue();
        $itemTest                                                              = $this->getTranslationWithContent('location');
        $itemString                                                            = json_encode($itemTest, JSON_UNESCAPED_SLASHES);
        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);
        $this->assertNotNull($links, 'No links found');

        $itemTest = json_decode($itemString, false);
        $this->saveTranslation($itemTest, 'location');
        $this->assertLinksExists($links);
        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }
        return $links;
    }

    #[Attributes\Depends('testTranslationLocationExtraction')]
    public function testTranslationLocationReplace(array $urls)
    {
        $this->setTranslationEnabled(1);
        $this->assertLinksReplace($urls);
    }

    protected function setTranslationEnabled(int $value)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->set($db->quoteName('value') . '= :value')
            ->bind(':value', $value)
            ->where($db->quoteName('name') . '= ' . $db->quote('multilanguage'))
            ->update($db->quoteName('#__rseventspro_config'));
        $db->setQuery($query)->execute();
    }


    public function testTranslationEventExtraction()
    {
        $this->setTranslationEnabled(1);
        $plugin =  $this->bootPlugin(RsEventsEventActor::class, (array)PluginHelper::getPlugin('blc', 'rseventsevent'));
        $this->assertInstanceOf(RsEventsEventActor::class, $plugin);
        $this->assertMessageQueue();
        $itemTest                                                              = $this->getTranslationWithContent('event');
        $itemString                                                            = json_encode($itemTest, JSON_UNESCAPED_SLASHES);
        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);
        $this->assertNotNull($links, 'No links found');

        $itemTest = json_decode($itemString, false);
        $this->saveTranslation($itemTest, 'event');
        $this->assertLinksExists($links);

        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }
        return $links;
    }

    #[Attributes\Depends('testTranslationEventExtraction')]
    public function testTranslationEventReplace(array $urls)
    {
        $this->setTranslationEnabled(1);
        $this->assertLinksReplace($urls);
    }

    public function testTranslationDisabledEventExtraction()
    {
        $this->setTranslationEnabled(0);
        $itemTest    = $this->getTranslationWithContent('event');
        $itemString  = json_encode($itemTest, JSON_UNESCAPED_SLASHES);

        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);

        $this->assertNotNull($links, 'No links found');
        $itemTest = json_decode($itemString, false);
        $this->saveTranslation($itemTest, 'event');
        $this->assertLinksExists($links, empty: true);
        $this->assertLinksReplace($links, empty: true);

        return $links;
    }
}
