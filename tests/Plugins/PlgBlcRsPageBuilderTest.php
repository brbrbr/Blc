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
        $this->initApplication();
        $this->checkPluginEnabled();
    }

    public static function fieldProvider()
    {
        return [
            ['rspagebuilder-links', 'links'],
            ['rspagebuilder-content', 'href'],
            ['rspagebuilder-content', 'img'],


        ];
    }


  
}
