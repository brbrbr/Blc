<?php

/**
 * @version   __DEPLOY_VERSION__
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Plugin\Blc\Category\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Utilities\ArrayHelper;
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
class PlgBlcCategoryTest extends UnitTestCase
{
    private string $folder  = 'blc';
    private string $element = 'category';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }






    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'category'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }

    public function testLinkExtraction()
    {
        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_categories', 'Category');
        $this->assertNotFalse($model);
        $item = ArrayHelper::fromObject($model->getItem(['title' => JTEST_TITLE]));
        $this->assertNotEmpty($item, 'A Categorie item with title ' . JTEST_TITLE . ' is needed');
        $unique          = uniqid();
        $descriptionLink = "https://example.com/description-$unique";
        $anchorText      = "Anchor for a link $unique";

        $fieldLink                   = "https://example.com/url-field-$unique";
        $imageLink                   = "https://example.com/image-$unique.jpg";
        $imageAlt                    = "ALT VALUE $unique";
        $item['description']         = "<p>Generated: <a href=\"$descriptionLink\">$anchorText</a></p>";
        $item['params']['image']     = $imageLink;
        $item['params']['image_alt'] = $imageAlt;
        //this is not an in depth check of the custom fields parser
        $item['com_fields']['cat-phpunit-url'] = $fieldLink;

        $model->save($item);
        $linkItem = $this->assertLinkExists($descriptionLink);
        $this->assertAnchorExists($linkItem->id, $anchorText);

        $linkItem = $this->assertLinkExists($imageLink);
        $this->assertAnchorExists($linkItem->id, $imageAlt);

        $this->assertLinkExists($fieldLink);
        $this->assertMessageQueue();
        return [$descriptionLink, $fieldLink, $imageLink];
    }
    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $this->assertLinkReplace($urls);
    }
}
