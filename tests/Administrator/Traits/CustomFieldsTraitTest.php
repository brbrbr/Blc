<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Traits;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;

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
class CustomFieldsTraitTest extends UnitTestCase
{
    protected string $fieldContext = 'com_content.article';
    protected string $context = 'com_content.article';
    protected string $folder         = 'blc';
    protected string $element        = 'content';
    private $testFields            = ['editor' => 1, 'url' => 1, 'mediajce' => 1, 'media' => 1, 'subform' => 1, 'text' => 1, 'textarea' => 1];
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function tearDown(): void
    {
        FieldsHelper::clearFieldsCache();
    }
    protected function addMediaJCE()
    {
        if (PluginHelper::getPlugin('fields', 'mediajce')) {
            $this->testFields['mediajce'] = 1;
        } else {
            unset($this->testFields['mediajce']);
        }
    }

    protected function bootTrait(?array $config = null)
    {

        $config ??= (array)PluginHelper::getPlugin($this->folder, $this->element);
        $plugin = new class($this->getDispatcher(), $config) extends CMSPlugin {
            use DatabaseAwareTrait;
            use BlcExtractTrait;
            use CustomFieldsTrait {
                parseCustomField as public;
                CustomFieldsTrait::__construct as private __cftConstruct;
            }

            protected string $fieldContext = '';
            private $parser;
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


            public function __set($name, $value)
            {
                switch ($name) {

                    case 'extraUrlIds':
                        $this->extraUrlIds = $value;

                    default:
                        return null;
                }
            }

            public function __construct(DispatcherInterface $dispatcher, array $config = [])
            {
                parent::__construct($dispatcher, $config);
                $this->fieldContext = 'com_content.article';
                $this->__cftConstruct();
                $this->textParsers =  BlcParseController::getInstance();
            }
        };
        $plugin->setApplication($this->app);
        $plugin->setDatabase($this->getDatabase());

        return $plugin;
    }


    public function testCanBoot(?array $config = null)
    {
        $plugin = $this->bootTrait($config);
        $this->assertInstanceOf(CMSPlugin::class, $plugin);
    }

  

    public function testParseFields()
    {
        $this->addMediaJCE();
        $toTest = $this->testFields;

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $config           = (array)PluginHelper::getPlugin('blc', 'content');
        $config['params'] = json_encode(['cf' => array_map(fn() => 2, $this->testFields), 'enablecf' => 1], JSON_PRETTY_PRINT);

        $plugin           = $this->bootTrait($config);
        $plugin->fieldToType; //ensure the types are loaded



        $protectedparseCustomField = function ($row): array {
            $this->contentFields = [];
            $this->contentLinks = [];
          
            /** @phpstan-ignore method.notFound */
            $this->parseCustomField($row);
            $links = [];
            if ($this->contentFields) {
                //intentialy not translatable
                $links = array_merge(...array_values($this->textParsers->extractAndStoreLinks(implode('', $this->contentFields), meta: ['field' => 'phpunit'], store: false)));
            }
            if ($this->contentLinks) {

                //intentialy not translatable
                $links = array_merge($links, $this->contentLinks);
            }
            return $links;
        };

        $protectedreplaceCustomField = function ($row, $oldUrl, $newUrl): \stdClass {
            $this->oldUrl = $oldUrl;
            $this->newUrl = $newUrl;
            /** @phpstan-ignore method.notFound */
            $replacedValue = $this->replaceCustomField($row);

            if ($replacedValue) {
                if (!\is_string($replacedValue)) {
                    $replacedValue = json_encode($replacedValue);
                }
                $row->rawvalue = $replacedValue;
            }

            return $row;
        };

        $rows       = $this->getFieldValues();


        /**
         * 
         * will contain a list of links that are present in the custom fields
         * As we do not actually update the database these are still in the field values
         */
        $currentLinks = [];
        foreach ($rows as $row) {

            if (!\array_key_exists($row->type, $this->testFields)) {
                continue;
            }

            if ( $row->type=='text') {
               if ( !str_starts_with($row->rawvalue,'http')) {
                continue;
               }
                $plugin->extraUrlIds = [$row->id];
            }


          
            $extractedLinks = $protectedparseCustomField->call($plugin, $row);


            //we don't need links an all fields. Just ensrure that all fields are tested with the assert 'Not all fields tested' below
            if ($extractedLinks) {
              
               
                unset($toTest[$row->type]);
                foreach ($extractedLinks as $link) {
                    $newUrl = $this->getRandomLink();
                    $currentLinks[] = $link['url'];
                    $replacedRow = $protectedreplaceCustomField->call($plugin, $row, $link['url'], $newUrl);
                    $extractedLinks = $protectedparseCustomField->call($plugin, $replacedRow);
                    //link extraxction works otherwise we wouldn't be ehre. Does't harm to test 
                    $this->assertNotEmpty($extractedLinks, 'No links found in Field ' . $row->type . '/' . $row->title . ', please add them for testing');
                    $extractedUrls = array_column($extractedLinks, 'url');
                    $this->assertContains($newUrl, $extractedUrls, 'No links replaced in Field ' . $row->type . '/' . $row->title . "\nIn:{$link['url']} expected:{$newUrl}\n");
                }
            }
        }

        $this->assertEmpty($toTest, 'Not all fields tested:' . implode(',', array_keys($toTest)));
    }
}
