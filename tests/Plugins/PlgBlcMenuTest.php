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

use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Plugin\Blc\Menu\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
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

class PlgBlcMenuTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;

    protected string $folder  = 'blc';
    protected string $element = 'menu';
    protected string $class   = BlcPluginActor::class;
    protected string $context = 'com_menus.item';




    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }

    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }
    public static function fieldProvider()
    {
        return [
            ['link', 'links'],
            ['image', 'links'],



        ];
    }

    /**
     * This is to test the correct return values  for empty anchors and alt attributes
     * It should be enough to test the special fields only as the html fields are tested in various other tests as are the parsers
     * Still, the basics are tested here as well.
     *
     * @since 25.44.7545
     */
    public function testparseContainerFields()
    {
        $plugin = $this->bootPlugin(assert: false);
        $row    = $this->getTestItem();
        //   var_dump($row);
        //menu has always a title so no need to test for empty title
        $url   =  $this->getRandomLink(ext: 'php');
        $title = 'Test Link:' . uniqid();

        $row->link  = $url;
        $row->title = $title;

        $image =  $this->getRandomLink(ext: 'png');

        $row->params = json_encode(
            [
                'menu_image'        => $image,
                'menu-anchor_title' => '',

            ]
        );

        $protectedMethod = (
            fn ($row) => /** @phpstan-ignore method.notFound */
            $this->parseContainerFields($row)
        );
        $protectedMethod->call($plugin, $row);
        $linkItem = $this->assertLinkExists($url);
        $this->assertAnchorExists($title, $linkItem->id);

        $linkItem =   $this->assertLinkExists($image);
        $this->assertAnchorExists(PARSE_STRINGS::BLC_EMPTY_ALT, $linkItem->id);
        $this->resetExtracted($row->id);
    }
}
