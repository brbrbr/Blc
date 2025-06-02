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

use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Traits;
use Blc\Plugin\Blc\Category\Extension\BlcPluginActor;
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

#[Attributes\CoversClass(Traits\BlcExtractTrait::class)]
#[Attributes\CoversClass(Traits\CustomFieldsTrait::class)]
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcCategoryTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;

    protected string $folder  = 'blc';
    protected string $element = 'category';
    protected string $class   = BlcPluginActor::class;

    protected string $fieldContext = 'com_content.categories';
    protected string $context      = 'com_categories.category';

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }

    public static function fieldProvider()
    {
        return [
            ['Fields', 'links'],
            ['description', 'href'],
            ['image', 'links'],


        ];
    }

    public function testgetExtension()
    {
        $itemTest                                                                   = $this->getTestItem();
        $plugin                                                                     = $this->bootPlugin();
        $instance                                                                   = new \stdClass();
        $instance->container_id                                                     = $itemTest->id;
        $extension                                                                  = $plugin->getExtension($instance);
        $this->assertSame($extension, $itemTest->extension);
    }


    /**
     * This is to test the correct return values  for empty anchors and alt attributes
     * It should be enough to test the special fields only as the html fields are tested in various other tests as are the parsers
     * Still, the basics are tested here as well.
     *
     * @since __DEPLOY_VERSION__
     */
    public function testparseContainerFields()
    {
        $plugin = $this->bootPlugin(assert: false);
        $row    = $this->getTestItem();
        //   var_dump($row);
        //var_export($row);
        $url              =  $this->getRandomLink(ext: 'php');
        $img              =  $this->getRandomLink(ext: 'php');
        $img_alt          = 'phpunit.anchor.' . uniqid();
        $url_anchor_1     =  'URL Anchor.' . uniqid();
        $row->description = '<a href="' . $url . '">' . $url_anchor_1 . '</a> <img src="' . $img . '" alt="' . $img_alt . '" /><a href="' . $url . '"></a> <img src="' . $img . '" />';
        $image            =  $this->getRandomLink(ext: 'png');

        $row->params = json_encode(
            [
                'image'     => $image,
                'image_alt' => '',

            ]
        );

        $protectedMethod = (
            fn ($row) => /** @phpstan-ignore method.notFound */
        $this->parseContainerFields($row)
        );
        $protectedMethod->call($plugin, $row);
        $linkItem = $this->assertLinkExists($url);
        $this->assertAnchorExists($url_anchor_1, $linkItem->id);
        $this->assertAnchorExists(BlcParserInterface::BLC_EMPTY_ANCHOR, $linkItem->id);

        $linkItem =   $this->assertLinkExists($img);
        $this->assertAnchorExists(BlcParserInterface::BLC_EMPTY_ALT, $linkItem->id);
        $this->assertAnchorExists($img_alt, $linkItem->id);


        $linkItem =  $this->assertLinkExists($image);
        $this->assertAnchorExists(BlcParserInterface::BLC_EMPTY_ALT, $linkItem->id);
        $this->resetExtracted($row->id);
    }
}
