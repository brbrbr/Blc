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


use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Plugin\Blc\Content\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Content\Extension\ContentChecker;
use Blc\Component\Blc\Administrator\Traits;
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
#[Attributes\CoversClass(Traits\BlcExtractTrait::class)]
#[Attributes\CoversClass(Traits\CustomFieldsTrait::class)]
#[Attributes\CoversClass(ContentChecker::class)]
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcContentTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;
    use \Blc\Tests\CustomFieldsTraitTestsTrait;
    
    protected string $folder         = 'blc';
    protected string $element        = 'content';
    protected string $class          = BlcPluginActor::class;
    protected string $fieldContext   = 'com_content.articles';
    protected string $context        = 'com_content.article';

    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
        
    }





    public static function fieldProvider()
    {
        return [
            ['Fields', 'links'],
              ['introtext', 'href'],
            ['fulltext', 'href'],
            ['introtext', 'img'],
            ['fulltext', 'img'],
            ['image_intro', 'links'],
            ['image_fulltext', 'links'],
            ['urla', 'links'],
            ['urlb', 'links'],
            ['urlb', 'links'],


        ];
    }


   


   
    public function testCanCheckInternal()
    {
        $link           = $this->assertGetSomeLink(destination: 'internal' ,linkPattern : '');
        $contentChecker = $this->bootChecker();
        $canCheck       = $contentChecker->canCheckLink($link);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);
    }
   
    protected function bootChecker()
    {
        $plugin         =  $this->bootPlugin();
        $contentChecker = ContentChecker::getInstance();
        $contentChecker->setDatabase($this->getDatabase());
        $contentChecker->setParams($plugin->params);

        return $contentChecker;
    }
   
    public function testCannotCheckExternal()
    {
        $linkItem       = $this->assertGetSomeLink(destination: 'external');
        $contentChecker = $this->bootChecker();
        $canCheck       = $contentChecker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_FALSE, $canCheck);
    }
   
    public function testcanCheckLink()
    {
        $url            = $this->getContentLink();
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker = $this->bootChecker();
        $canCheck       = $contentChecker->canCheckLink($linkItem);
        $this->assertSame(HTTPCODES::BLC_CHECK_TRUE, $canCheck);
        $contentChecker->checkLink($linkItem);
        //   print "\na: $url}\n{$linkItem->internal_url}\n";
        return $linkItem->internal_url;
    }
   
    #[Attributes\Depends('testcanCheckLink')]
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

   
    #[Attributes\Depends('testcanCheckLink')]
    public function testCanFixCatidBlcCheckLink($correctUrl)
    {
        $url      = preg_replace('#catid=[0-9]+#', 'catid=999998', $correctUrl);
        $linkItem = $this->loadLinkItem($url);

        $this->checkLinkWrapped($linkItem);
        $this->assertSame($correctUrl, $linkItem->internal_url);
    }
    /**
     *
     * code coverage for checkLink not yet tested.
     */
   
    public function testcheckLink()
    {


        $contentChecker = $this->bootChecker();
        $contentItem    = $this->getTestItem();
        $catId          = $contentItem->catid;
        $id             = $contentItem->id;

        $url            = "option=com_content&view=article&catid={$catId}&id={$id}";
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);

        $url            = "index.php?option=com_phpunit&view=view&catid={$catId}&id={$id}";
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);

        $url            = "index.php?option=com_content&view=article&catid={$catId}";
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);

        $Langurl        = "index.php?option=com_content&view=article&catid={$catId}&id={$id}&lang=nl";
        $linkItem       = $this->loadLinkItem($Langurl);
        $contentChecker->checkLink($linkItem);
        $this->assertNotSame($url, $linkItem->internal_url);

        $contentChecker->setParamsOption('check_lang', 2);
        $Langurl        = "index.php?option=com_content&view=article&catid={$catId}&id={$id}&lang=nl";
        $linkItem       = $this->loadLinkItem($Langurl);
        $contentChecker->checkLink($linkItem);
        $this->assertNotSame($url, $linkItem->internal_url);


        $contentChecker->setParamsOption('category_alias', 1);
        $contentChecker->setParamsOption('article_alias', 1);

        $url            = "index.php?option=com_content&view=article&catid={$catId}&id={$id}";
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertNotSame($url, $linkItem->internal_url);

        $contentChecker->setParamsOption('category_alias', 0);
        $contentChecker->setParamsOption('article_alias', 0);
        $urlWithAlias   = $linkItem->internal_url;
        $linkItem       = $this->loadLinkItem($urlWithAlias);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($urlWithAlias, $linkItem->internal_url);

        $contentChecker->setParamsOption('category_alias', 2);
        $contentChecker->setParamsOption('article_alias', 2);
        $linkItem       = $this->loadLinkItem($urlWithAlias);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);
    }


   
    protected function getContentLink(?int $forceId = null, ?int $forceCatId = null)
    {

        $contentItem = $this->getTestItem();
        $catId       = $forceCatId ?: $contentItem->catid;
        $id          = $forceId ?: $contentItem->id;

        return "index.php?option=com_content&amp;view=article&amp;catid={$catId}&amp;id={$id}";
    }








    public function testonBlcCheckerRequest()
    {
        $this->assertOnBlcCheckerRequest();
    }






}
