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

use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\DispatcherInterface;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(CustomFieldsTrait::class)]
#[Attributes\TestDox('Test of the Custom Fields Trait')]
class CustomFieldsTraitTest extends UnitTestCase
{
    protected $wrappedClass;
    protected string $fieldContext = 'com_content.article';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }




    public function testCanBoot(?array $config = null)
    {
        $config ??= (array)PluginHelper::getPlugin('blc', 'content');
        $plugin = new class($this->getDispatcher(), $config) extends CMSPlugin {
            use DatabaseAwareTrait;
            use BlcExtractTrait;
            use CustomFieldsTrait {
                parseCustomField as public;
                CustomFieldsTrait::__construct as private __cftConstruct;
            }

            protected string $fieldContext = '';

            public function __get($name)
            {
                switch ($name) {
                    case 'contentFields':
                    case 'contentLinks':
                    case 'parseAllowedFields':
                    case 'replaceAllowedFields':
                    case 'extraUrlIds':
                    case 'newUrl':
                    case 'oldUrl':
                    case 'parserInstance':
                        return $this->$name;
                        break;

                    case 'fieldToType':
                        $this->loadFieldToType();
                        return $this->fieldToType;
                        break;
                    default:
                        return null;
                }
            }

            public function __construct(DispatcherInterface $dispatcher, array $config = [])
            {
                parent::__construct($dispatcher, $config);
                $this->fieldContext = 'com_content.article';
                $this->__cftConstruct();
            }
        };
        $plugin->setApplication($this->app);
        $plugin->setDatabase($this->getDatabase());
        $this->assertInstanceOf(CMSPlugin::class, $plugin);
        return $plugin;
    }
    #[Attributes\Depends('testCanBoot')]
    public function estloadFieldToType($plugin)
    {

        $this->markTestIncomplete(
            'This test has not been implemented yet.',
        );
    }

    public static function parseSubformProvider(): array
    {
        return   [
            ['cf' => ['text' => 0, 'textarea' => 0, 'editor' => 0, 'url' => 0, 'media' => 0, 'mediajce' => 0, 'subform' => 1],  'linkCount' => 0, 'textCount' => 0],
            ['cf' => ['text' => 1, 'textarea' => 0, 'editor' => 0, 'url' => 0, 'media' => 0, 'mediajce' => 0, 'subform' => 1],  'linkCount' => 0, 'textCount' => 0],
            ['cf' => ['text' => 0, 'textarea' => 1, 'editor' => 0, 'url' => 0, 'media' => 0, 'mediajce' => 0, 'subform' => 1],  'linkCount' => 0, 'textCount' => 0],
            ['cf' => ['text' => 0, 'textarea' => 0, 'editor' => 1, 'url' => 0, 'media' => 0, 'mediajce' => 0, 'subform' => 1],  'linkCount' => 0, 'textCount' => 2],
            ['cf' => ['text' => 0, 'textarea' => 0, 'editor' => 0, 'url' => 1, 'media' => 0, 'mediajce' => 0, 'subform' => 1],  'linkCount' => 3, 'textCount' => 0],
            ['cf' => ['text' => 0, 'textarea' => 0, 'editor' => 0, 'url' => 0, 'media' => 1, 'mediajce' => 0, 'subform' => 1],  'linkCount' => 2, 'textCount' => 0],
            ['cf' => ['text' => 0, 'textarea' => 0, 'editor' => 0, 'url' => 0, 'media' => 0, 'mediajce' => 1, 'subform' => 1],  'linkCount' => 1, 'textCount' => 0],
            ['cf' => ['text' => 1, 'textarea' => 1, 'editor' => 1, 'url' => 1, 'media' => 1, 'mediajce' => 1, 'subform' => 1],  'linkCount' => 6, 'textCount' => 2],
        ];
    }

    #[Attributes\DataProvider('parseSubformProvider')]
    public function testParseSubform($cf, $linkCount, $textCount)
    {
        $config = (array)PluginHelper::getPlugin('blc', 'content');

        $config['params'] = json_encode(['cf' => $cf, 'enablecf' => 1], JSON_PRETTY_PRINT);
        $plugin           = $this->testCanBoot($config);
        $plugin->fieldToType; //ensure the types are loaded
        $model = $this->getModel('com_content', 'Article');
  

        $templateTitle =  JTEST_TITLE . ' Template';

        $item = $model->getItem(['title' => $templateTitle]); //object
        $this->assertNotNull($item, 'Article 185 is needed for the test');

        $rows = FieldsHelper::getFields($this->fieldContext, $item);
      
        $found = null;
        foreach ($rows as $row) {
            if ($row->id == 17) {
                $found = $row;
                break;
            }
        }

        $this->assertNotNull($found, 'Field 17 for content is missing. Needed for test');
        $plugin->parseCustomField($found);
        $this->assertCount($linkCount, $plugin->contentLinks, "Config {$config['params']} geeft verkeerd aantal links");
        $this->assertCount($textCount, $plugin->contentFields, "Config {$config['params']} geeft verkeerd aantal Text Velden");
    }
}
