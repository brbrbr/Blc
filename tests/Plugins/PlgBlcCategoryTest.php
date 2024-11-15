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

use Blc\Plugin\Blc\Category\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
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

    protected string $fieldContext = 'com_content.categories';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }

    public function testCanBoot()
    {
    
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'category'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }

    public function testLinkExtraction()
    {
        //the extractor is booted from the system/blc plugin.
        $this->testCanBoot();
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_categories', 'Category');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model);
        return $links;
    }
    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }
}
