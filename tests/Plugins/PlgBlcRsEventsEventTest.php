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

use Blc\Component\Blc\Administrator\Event;
use Blc\Plugin\Blc\RsEventsEvent\Extension\BlcPluginActor as BlcPluginActor;

use Blc\Tests\UnitTestCase;
use Joomla\CMS\Factory;
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
class PlgBlcRsEventsEventTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'rseventsevent';
    protected string $class   = BlcPluginActor::class;

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
       
    }

       public function testBootPluginService()
    {
      parent::testBootPluginService();
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
    protected function getTranslationWithContent(string $reference)
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
        $plugin =       $this->importPlugin(element: 'rseventsevent');

        $arguments =
            [
                'context' => 'com_rseventspro.event',
                'id'      => $table->id,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);
        $plugin->onBlcContainerChanged($event);
    }

    protected function saveTranslation($table, $context)
    {
        //new PlgBlcModcustomTest();
        $db    = $this->getDatabase();
        if (! $db->updateObject('#__rseventspro_translations', $table, 'id', false)) {
            throw new GenericDataException($db->getError(), 500);
        }



        $plugin =      match ($context) {
            'event'    => $this->importPlugin(element: 'rseventsevent'),
            'location' => $this->importPlugin(element: 'rseventslocation'),
            default    => null,
        };

        $arguments =
            [
                'context' => 'com_rseventspro.' . $context,
                'id'      => $table->reference_id,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);
        $plugin->onBlcContainerChanged($event);
    }

    protected function saveLocation($table)
    {
        //new PlgBlcModcustomTest();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        if (! $db->updateObject('#__rseventspro_locations', $table, 'id', false)) {
            throw new GenericDataException($db->getError(), 500);
        }

        $plugin =       $this->importPlugin(element: 'rseventslocation');

        $arguments =
            [
                'context' => 'com_rseventspro.location',
                'id'      => $table->id,
                'event'   => 'onsave',
            ];

        $event = new Event\BlcEvent('onBlcContainerChanged', $arguments);
        $plugin->onBlcContainerChanged($event);
    }

 


    public function importPlugins()
    {

        $this->importPlugin(element: 'rseventsevent');
        $this->importPlugin(element: 'rseventslocation');
    }

    public function testCanBoot()
    {
        $this->bootPlugin(assert:true);
    }

    public function testEventExtraction()
    {
        $this->bootPlugin();
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






 

    public function testTranslationEventExtraction()
    {
        $this->translationEventExtraction('event', 1);
    }
    #[Attributes\Depends('testTranslationEventExtraction')]
    public function testTranslationEventReplace()
    {
        $url =  $this->translationEventExtraction('event', 1);
        $this->assertLinkReplace($url);
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

    private function translationEventExtraction(string $reference, int $enabled)
    {
        $this->setTranslationEnabled($enabled);
        $this->bootPlugin();

        $itemTest                                                              = (object)$this->getTranslationWithContent($reference);
        $link                                                                  = 'https://phpunit-' . uniqid() . '.200.invalid/' . $reference;
        $anchor                                                                = 'anchor-' . uniqid();
        $itemTest->value                                                       = '<p>Deze heeft een <a href="' . $link . '">' . $anchor . '</a></p>';
        $this->saveTranslation($itemTest, $reference);
        $this->assertLinkExists($link, empty:$enabled !== 1);
        $this->assertAnchorExists($anchor, empty:$enabled !== 1);
        return $link;
    }

    public function testTranslationDisabledEventExtraction()
    {
        $this->translationEventExtraction('event', 0);
    }





    public function testCanExtractEventEvent()
    {
        $plugin                                                                = $this->importPlugin(element: 'rseventsevent');
        $itemTest                                                              = (object)$this->getEventWithContent();
        $this->assertNotNull($itemTest);
        //rsevents do not have a modified date
        $this->deleteSynch($itemTest->id, 'rseventsevent');

        $arguments =
            [
                'maxExtract' => 10,
            ];

        $event = new Event\BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);
        $parsed = $event->getDidExtract();
        $this->assertNotEquals($parsed, 0);
        $this->assertMessageQueue();
    }

}
