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
    private $wrappedClass;
    protected string $fieldContext = 'com_content.article';
    private $testFields            = ['editor' => 1, 'url' => 1, 'mediajce' => 1, 'media' => 1, 'subform' => 1];
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }




    public function testCanBoot(?array $config = null)
    {
        $config ??= (array)PluginHelper::getPlugin('blc', 'content');
        $plugin = new class ($this->getDispatcher(), $config) extends CMSPlugin {
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

    public function estloadFieldToType($plugin)
    {

        $this->markTestIncomplete(
            'This test has not been implemented yet.',
        );
    }

    public function testParseFields()
    {
        $toTest = $this->testFields;
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $config           = (array)PluginHelper::getPlugin('blc', 'content');
        $config['params'] = json_encode(['cf' => $this->testFields, 'enablecf' => 1], JSON_PRETTY_PRINT);
        $plugin           = $this->testCanBoot($config);
        $plugin->fieldToType; //ensure the types are loaded
        $model = $this->getModel('com_content', 'Article');


        $templateTitle =  JTEST_TITLE . ' Template';

        $item = $model->getItem(['title' => $templateTitle]); //object
        $this->assertNotNull($item, 'Article ' . $templateTitle . ' is needed for the test');



        $fieldModel =  $this->getModel('com_fields', 'Field');
        $rows       = FieldsHelper::getFields($this->fieldContext, $item);


        foreach ($rows as $row) {
            $in = $row->rawvalue;
            if (\in_array($row->type, ['media', 'subform'])) {
                $itemString =  json_encode(json_decode($row->rawvalue), JSON_UNESCAPED_SLASHES);
            } else {
                $itemString = $row->rawvalue;
            }



            ['itemString' => $replacedValue, 'link' => $links, 'anchors' => $anchors] = $this->injectLinks($itemString);

            if ($replacedValue && $in != $replacedValue) {
                unset($toTest[$row->type]);
                $fieldModel->setFieldValue($row->id, $item->id, $replacedValue);
                $row->rawvalue = $replacedValue;

                $protectedMethod = function ($row) {
                    $id         = $rows[0]->id ?? 0; // TODO bail out
                    $synchTable = $this->getItemSynch($id);
                    $synchId    = $synchTable->id;
                    /** @phpstan-ignore method.notFound */
                    $this->parseCustomField($row);
                    if ($this->contentLinks) {
                        //intentialy not translatable
                        $this->processLinks($this->contentLinks, 'Fields', $synchId);
                    }
                    if ($this->contentFields) {
                        //intentialy not translatable
                        $this->processText(join('', $this->contentFields), 'Fields', $synchId);
                    }
                };
                $protectedMethod->call($plugin, $row);

                foreach ($links as $link) {
                    $this->assertLinkExists($link);
                }
                foreach ($anchors as $anchor) {
                    $this->assertAnchorExists($anchor);
                }
            }
        }
        $this->assertEmpty($toTest, 'Not all fields tested:' . join(',', array_keys($toTest)));
    }
}
