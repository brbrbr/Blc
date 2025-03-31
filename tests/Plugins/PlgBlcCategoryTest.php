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
#[Attributes\CoversClass(BlcPluginActor::class)]

class PlgBlcCategoryTest extends UnitTestCase
{
    use \Blc\Tests\CommonPluginTestsTrait;
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
        $instance               = new \stdClass();
        $instance->container_id = $itemTest->id;
        $extension              = $plugin->getExtension($instance);
        $this->assertSame($extension, $itemTest->extension);
    }




 
}
