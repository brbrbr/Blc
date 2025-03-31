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

use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\CMSPlugin;
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
#[Attributes\CoversClass(BlcHelpTrait::class)]
class BlcHelpTraitTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }


    protected function bootTrait()
    {
        $this->getModel('com_blc', 'Links'); //load HTML Helper
        $config = (array)PluginHelper::getPlugin('blc', 'content');
        $plugin = new class ($this->getDispatcher(), $config) extends CMSPlugin {
            use BlcHelpTrait;

            public const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-content';
        };
        $plugin->setApplication($this->app);

        return $plugin;
    }

    protected function bootTraitEmpty()
    {
        $this->getModel('com_blc', 'Links'); //load HTML Helper
        $config = (array)PluginHelper::getPlugin('blc', 'content');
        $plugin = new class ($this->getDispatcher(), $config) extends CMSPlugin {
            use BlcHelpTrait;
        };
        $plugin->setApplication($this->app);

        return $plugin;
    }





    public function testCanBoot()
    {
        $plugin = $this->bootTrait();
        $this->assertInstanceOf(CMSPlugin::class, $plugin);
    }

    public function testgetHelpLink()
    {
        $plugin =   $this->bootTrait();
        $link   = $plugin::getHelpLink();
        $this->assertStringStartsWith('https://', $link);
    }





    public function testgetHelpHtml()
    {
        $plugin =   $this->bootTrait();
        $html   = $plugin::getHelpHtml();
        $this->assertStringStartsWith('<a', $html);
    }

    public function testgetHelpHtmlAnchor()
    {
        $anchor = uniqid();
        $plugin =   $this->bootTrait();
        $html   = $plugin::getHelpHtml($anchor);
        $this->assertStringContainsString($anchor, $html);
    }


    public function testgetHelpLinkEmpty()
    {
        $plugin =   $this->bootTraitEmpty();
        $link   = $plugin::getHelpLink();
        $this->assertEmpty($link);
    }

    public function testgetHelpHtmlEmpty()
    {
        $plugin =   $this->bootTraitEmpty();
        $html   = $plugin::getHelpHtml();
        $this->assertEmpty($html);
    }


    public function testgetHelpHtmlEmptyAnchor()
    {
        $anchor = uniqid();
        $plugin =   $this->bootTraitEmpty();
        $html   = $plugin::getHelpHtml($anchor);
        $this->assertEquals($anchor, $html);
    }
}
