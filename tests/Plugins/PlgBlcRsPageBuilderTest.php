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

use Blc\Plugin\Blc\RsPageBuilder\Extension\BlcPluginActor;
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
class PlgBlcRsPageBuilderTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;

    protected string $folder  = 'blc';
    protected string $element = 'rspagebuilder';
    protected string $class   = BlcPluginActor::class;
    protected string $context = 'com_rspagebuilder.page';



    public function setUp(): void
    {
        parent::setUp();
        $this->checkPluginEnabled();
    }
    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }
    public static function fieldProvider()
    {
        return [
            ['rspagebuilder-links', 'links'],
            ['rspagebuilder-content', 'href'],
            ['rspagebuilder-content', 'img'],


        ];
    }

    public function getModel($component, $model, $client = 'Administrator', array $config = ['ignore_request' => true])
    {
        if ($component == 'com_rspagebuilder') {
            $client     = 'RSPageBuilderModel';
            $model      = 'Page';
            $modelFile  = JPATH_ADMINISTRATOR . '/components/com_rspagebuilder/models/page.php';
            $tableFile  = JPATH_ADMINISTRATOR . '/components/com_rspagebuilder/tables/page.php';
            if (!class_exists('RspagebuilderTablePage') && file_exists($tableFile)) {
                require_once  $tableFile;
            }
            if (!class_exists('RspagebuilderModelPage') && file_exists($modelFile)) {
                require_once  $modelFile;
            }
        }
        return parent::getModel($component, $model, $client, $config);
    }
}
