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

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Plugin\Blc\Unsef\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
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
class PlgBlcUnsefTest extends UnitTestCase
{
    protected string $folder  = 'blc';
    protected string $element = 'unsef';
    protected string $class   = BlcPluginActor::class;
    protected string $context = 'unsef';



    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }


    public function testCanBoot()
    {
        $this->bootPlugin(assert: true);
    }



    public function testgetSubscribedEvents()
    {
        $this->getSubscribedEvents();
    }

    public function testonBlcCheckerRequest()
    {
        $this->assertOnBlcCheckerRequest();
    }

    public function testcanCheckLink()
    {
        $plugin        = $this->bootPlugin();
        $linkTableStub = $this->getMockBuilder(LinkTable::class)
            ->disableOriginalConstructor()
            ->getMock();
        $linkTableStub->method('isInternal')
            ->willReturnOnConsecutiveCalls(true, false);
        $res = $plugin->canCheckLink($linkTableStub);
        $this->assertSame(BlcCheckerInterface::BLC_CHECK_TRUE, $res);
        $res = $plugin->canCheckLink($linkTableStub);
        $this->assertSame(BlcCheckerInterface::BLC_CHECK_FALSE, $res);
    }

    public function testcheckLink()
    {
        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);
        $link     = "index.php?option=com_content&view=article&id={$testItem->id}&catid={$testItem->catid}";

        $routedLink =  ltrim(Route::link('site', $link), '/');
        $linkItem   = $this->loadLinkItem($routedLink);

        $app = Factory::getContainer()->get(SiteApplication::class);

        $app->set('sef', 0);
        $plugin->checkLink($linkItem);
        $this->assertSame($routedLink, $linkItem->internal_url);

        $app->set('sef', 1);
        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink from $link");
    }


    public function testcheckLinkCat()
    {
        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);
        $link     = "index.php?option=com_content&view=category&id={$testItem->catid}";

        $routedLink =  ltrim(Route::link('site', $link), '/');
        if ($routedLink === '') {
            $routedLink = '/';
        }

        $linkItem = $this->loadLinkItem($routedLink);

        $app = Factory::getContainer()->get(SiteApplication::class);

        $app->set('sef', 0);
        $plugin->checkLink($linkItem);
        $this->assertSame($routedLink, $linkItem->internal_url);

        $app->set('sef', 1);
        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink");
    }


    public function testcheckLinkIndexPhp()
    {
        $app = Factory::getContainer()->get(SiteApplication::class);
        $app->set('sef', 1);

        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);
        $link     = "index.php?option=com_content&view=article&id={$testItem->id}&catid={$testItem->catid}";

        $routedLink = 'index.php/' . ltrim(Route::link('site', $link), '/');
        $linkItem   = $this->loadLinkItem($routedLink);

        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink");
    }

    public function testcheckLinkHtmlSuffix()
    {
        $app = Factory::getContainer()->get(SiteApplication::class);
        $app->set('sef', 1);

        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);
        $link     = "index.php?option=com_content&view=article&id={$testItem->id}&catid={$testItem->catid}";

        $routedLink =  ltrim(Route::link('site', $link), '/') . '.html';
        $linkItem   = $this->loadLinkItem($routedLink);

        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink");
    }

    public function testcheckLinkRawSuffix()
    {
        $app = Factory::getContainer()->get(SiteApplication::class);
        $app->set('sef', 1);

        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);
        $link     = "index.php?option=com_content&view=article&id={$testItem->id}&format=raw&catid={$testItem->catid}";

        $routedLink =  ltrim(Route::link('site', $link), '/');
        $linkItem   = $this->loadLinkItem($routedLink);

        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink");
    }


    public function testcheckLinkID()
    {
        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);

        $link = "index.php?option=com_content&view=article&id={$testItem->id}&catid={$testItem->catid}";

        $routedLink =  ltrim(Route::link('site', $link), '/');
        $routedLink = preg_replace("#{$testItem->alias}$#", "{$testItem->id}-{$testItem->alias}", $routedLink);


        $linkItem = $this->loadLinkItem($routedLink);

        $app = Factory::getContainer()->get(SiteApplication::class);
        $app->set('sef', 0);
        $plugin->checkLink($linkItem);
        $this->assertSame($routedLink, $linkItem->internal_url);

        $app->set('sef', 1);
        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink");
    }

    public function testcheckLinkIDSuffix()
    {
        $plugin   = $this->bootPlugin();
        $model    = $this->getModel('com_content', 'Article');
        $testItem = $this->getTestItem($model);

        $link = "index.php?option=com_content&view=article&id={$testItem->id}&format=raw&catid={$testItem->catid}";

        $routedLink =  ltrim(Route::link('site', $link), '/');
        $routedLink = preg_replace("#{$testItem->alias}\?#", "{$testItem->id}-{$testItem->alias}?", $routedLink);


        $linkItem = $this->loadLinkItem($routedLink);

        $app = Factory::getContainer()->get(SiteApplication::class);


        $app->set('sef', 1);
        $plugin->checkLink($linkItem);
        $this->assertSame($link, $linkItem->internal_url, "Link not unseffed:$routedLink");
    }


    public function testMagicGet()
    {
        $this->assertMagicGetTest();
    }
}
