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
use Blc\Plugin\Blc\Hikashop\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Path;
use PHPUnit\Framework\Attributes;

// phpcs:disable PSR1.Files.SideEffects


// phpcs:enable PSR1.Files.SideEffects

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */


#[Attributes\TestDox('Test of the BLC - Hikashop Plugin')]
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcHikashopTest extends UnitTestCase
{
    protected string $folder   = 'blc';
    protected string $element  = 'hikashop';
    protected string $class    = BlcPluginActor::class;
    private $hikeConfig;
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, 'hikashop');
    }

    protected function getHikaItem()
    {
        $testTitle =  JTEST_TITLE . ' Test';
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true);
        $query->select("*")
            ->from($db->quoteName(hikashop_table('product')))
            ->where($db->quoteName('product_name') . ' = ' . $db->quote($testTitle));
        $query->setLimit(1);
        $db->setQuery($query);
        $itemTest =   $db->loadObject();

        $this->assertNotEmpty($itemTest, 'A item with title: ' . $testTitle . ' is needed');

        return $itemTest;
    }

    protected function updateTestItem($itemTest): void
    {
        $db     = $this->getDatabase();
        $result =  $db->updateObject(hikashop_table('product'), $itemTest, 'product_id');

        $this->assertTrue($result);
    }


    protected function setTestItem()
    {
        $itemTest =  $this->getHikaItem();

        ['itemString' => $itemString, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemTest->product_description);

        //hikashop used time that is 'gmt'
        $itemTest->product_modified    = time();
        $itemTest->product_description = $itemString;
        $itemTest->product_url         = 'https://phpunit-brandurl.200.invalid/' . uniqid() . '.html';
        $links[]                       = $itemTest->product_url;
        $this->updateTestItem($itemTest);

        return ['link' => $links, 'anchors' => $anchors];
    }

    private function getFiles(int $id = 0)
    {
        if (!$id) {
            $productItem = $this->getHikaItem();
            $id          = $productItem->product_id;
        }
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName("a.file_path"))
            ->select($db->quoteName("a.file_id"))
            ->select($db->quoteName("a.file_type"))
            ->from($db->quoteName(hikashop_table('file'), 'a'))
            ->where($db->quoteName("a.file_ref_id") . ' = :id')
            ->bind(':id', $id)
            ->whereIn($db->quoteName("a.file_type"), ['product'], ParameterType::STRING);
        $db->setQuery($query);

        return $db->loadObjectList();
    }

    private function getHikaConfig()
    {
        include_once(rtrim(JPATH_ADMINISTRATOR, '/') . '/components/com_hikashop/helpers/helper.php');
        $this->hikeConfig ??= hikashop_config();
    }

    private function getFilesUrl(int $id = 0)
    {

        $this->getHikaConfig();
        $uploadFolder = trim(Path::clean(html_entity_decode($this->hikeConfig->get('uploadfolder'))), '/');
        $uploadFolder .= '/';

        $files = $this->getFiles();

        return array_map(fn ($item) => $uploadFolder . $item->file_path, $files);
    }

    public function testCanBoot()
    {
        $this->bootPlugin(assert:true);
    }

    public function testgetSubscribedEvents()
    {

        $this->getSubscribedEvents();
    }

    public function testCanExtractEvent()
    {
        $plugin                                   =  $this->bootPlugin();
        ['link' => $links, 'anchors' => $anchors] = $this->setTestItem();
        //assume blc plugin group is loaded
        $arguments =
            [
                'maxExtract' => 10,
            ];

        $event = new Event\BlcExtractEvent('onBlcExtract', $arguments);
        $plugin->onBlcExtract($event);
        $parsed = $event->getDidExtract();
        $this->assertNotEquals($parsed, 0);
        $this->assertMessageQueue();

        $this->assertLinksExists($links);

        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }
        return $links;
    }


    #[Attributes\Depends('testCanExtractEvent')]
    public function testLinkReplace(array $links)
    {

        $this->assertLinksReplace($links);
    }

    public function testCanFindFiles()
    {



        $files = $this->getFilesUrl();

        foreach ($files as $file) {
            $this->assertLinkExists($file);
        }
    }
    public function testCanReplaceFile()
    {

        $files = $this->getFilesUrl();
        $file  = end($files);
        $this->assertLinkReplace($file, 'images/com_hikashop/upload/phpunit' . uniqid() . '.jpg');
    }

    public function testLinkReplaceInvalidInstance()
    {
        $files = $this->getFilesUrl();
        $file  = end($files);
        $this->assertLinkReplaceInvalidInstance($file);
    }


    public function testLinkReplaceNoneExistinglink()
    {
        $files = $this->getFilesUrl();
        $file  = end($files);
        $this->asserLinkReplaceNoneExistingLink($file);
    }
}
