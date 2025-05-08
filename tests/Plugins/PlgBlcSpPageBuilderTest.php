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

use Blc\Plugin\Blc\SpPageBuilder\Extension\BlcPluginActor;
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
class PlgBlcSpPageBuilderTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;

    protected string $folder  = 'blc';
    protected string $element = 'sppagebuilder';
    protected string $class   = BlcPluginActor::class;


    protected $context     = 'com_sppagebuilder.editor';

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }

    protected function assertContentEvents($model = null)
    {
        if (! $model) {
            [$option, $part] = explode('.', $this->context);
            $model           = $this->getModel($option, $part);
        }
        $this->assertOnContentAfterSave($model);
        $this->assertOnContentAfterDelete($model);
        //    $this->assertOnContentChangeState($model); -- not triggered
    }


    public function getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
    {
        if ($component == 'com_sppagebuilder') {
            $client     = 'SppagebuilderModel';
            $model      = 'page';
            $modelFile  = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/models/page.php';
            $tableFile  = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/tables/page.php';
            if (!class_exists('SppagebuilderTablePage') && file_exists($tableFile)) {
                require_once  $tableFile;
            }
            if (!class_exists('SppagebuilderModelPage') && file_exists($modelFile)) {
                require_once  $modelFile;
            }
        }
        return parent::getModel($component, $model, $client, $config);
    }
    public static function fieldProvider()
    {
        return [
            ['content', 'href'],
            ['content', 'links'],
            ['content', 'img'],




        ];
    }
}
