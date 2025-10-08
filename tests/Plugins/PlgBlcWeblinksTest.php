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

use Blc\Plugin\Blc\Weblinks\Extension\BlcPluginActor;
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
class PlgBlcWeblinksTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;

    protected string $folder  = 'blc';
    protected string $element = 'weblinks';
    protected string $class   = BlcPluginActor::class;


    protected string $context      = 'com_weblinks.weblink';

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }
   public function testBootPluginService()
    {
      parent::testBootPluginService();
    }
    public static function fieldProvider()
    {
        return [
            ['url', 'links'],
            ['image_first', 'links'],
            ['image_second', 'links'],



        ];
    }
}
