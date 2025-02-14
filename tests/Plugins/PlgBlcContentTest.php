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

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Plugin\Blc\Content\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Content\Extension\ContentChecker;
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
class PlgBlcContentTest extends UnitTestCase
{
    private string $folder         = 'blc';
    private string $element        = 'content';
    protected string $fieldContext = 'com_content.categories';

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled($this->folder, $this->element);
    }


    public function testCanBoot()
    {
        $this->checkPluginEnabled($this->folder, $this->element);
        $plugin =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'content'));
        $this->assertInstanceOf(BlcPluginActor::class, $plugin);
        $this->assertMessageQueue();
    }


    public function testLinkExtraction(): array
    {
        //the extractor is booted from the system/blc plugin.

        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $this->assertNotFalse($model);
        $links = $this->assertTestPage($model);

        return $links;
    }

    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $this->assertLinksReplace($urls);
    }

    public function testCanCheckInternal()
    {
        $link           = $this->getSomeLink(destination: 'internal');
        $contentChecker = $this->bootChecker();
        $canCheck       = $contentChecker->canCheckLink($link);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);
    }
    protected function bootChecker()
    {
        $plugin         =  $this->bootPlugin(BlcPluginActor::class, (array)PluginHelper::getPlugin('blc', 'content'));
        $contentChecker = ContentChecker::getInstance();
        $contentChecker->setParams($plugin->params);
        $contentChecker->setParent($plugin);
        return $contentChecker;
    }

    public function testCannotCheckExternal()
    {
        $linkItem       = $this->getSomeLink(destination: 'external');
        $contentChecker = $this->bootChecker();
        $canCheck       = $contentChecker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $canCheck);
    }

    public function testCanCheckLink()
    {
        $url            = $this->getContentLink();
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker = $this->bootChecker();
        $contentChecker->checkLink($linkItem);
        //   print "\na: $url}\n{$linkItem->internal_url}\n";
        return $linkItem->internal_url;
    }
    #[Attributes\Depends('testCanCheckLink')]
    public function testCanFixCatid($correctUrl)
    {
        $url            = $this->getContentLink(forceCatId: 99995);
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker = $this->bootChecker();
        $contentChecker->checkLink($linkItem);
        // print "\nb: {$url}\n{$linkItem->internal_url}\n";
        $this->assertSame($correctUrl, $linkItem->internal_url);
    }

    public function testReportBrokenUnknownId()
    {
        $url            = $this->getContentLink(forceId: 99996);
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker = $this->bootChecker();
        $contentChecker->checkLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_JOOMLA_ITEM_NOT_FOUND, $linkItem->http_code);
        $this->assertSame(HTTPCODES::BLC_BROKEN_TRUE, $linkItem->broken);
    }


    public function testReportBrokenUnknownIdBlcCheckLink()
    {
        $url      = $this->getContentLink(forceId: 99997);

        $linkItem = $this->loadLinkItem($url);

        $this->checkLinkWrapped($linkItem);
        $this->assertSame(HTTPCODES::BLC_JOOMLA_ITEM_NOT_FOUND, $linkItem->http_code);
        $this->assertSame(HTTPCODES::BLC_BROKEN_TRUE, $linkItem->broken);
    }


    #[Attributes\Depends('testCanCheckLink')]
    public function testCanFixCatidBlcCheckLink($correctUrl)
    {
        $url      = preg_replace('#catid=[0-9]+#', 'catid=999998', $correctUrl);
        $linkItem = $this->loadLinkItem($url);

        $this->checkLinkWrapped($linkItem);
        $this->assertSame($correctUrl, $linkItem->internal_url);
    }

    protected function getContentLink(?int $forceId = null, ?int $forceCatId = null)
    {
        $model       = $this->getModel('com_content', 'Article');
        $contentItem = $this->getTestItem($model);
        $catId       = $forceCatId ?: $contentItem->catid;
        $id          = $forceId ?: $contentItem->id;

        return "index.php?option=com_content&amp;view=article&amp;catid={$catId}&amp;id={$id}";
    }
}
