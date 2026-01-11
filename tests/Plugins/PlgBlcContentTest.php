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

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Plugin\Blc\Content\Extension\BlcPluginActor;
use Blc\Plugin\Blc\Content\Extension\ContentChecker;
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


#[Attributes\CoversClass(ContentChecker::class)]
#[Attributes\CoversClass(BlcPluginActor::class)]
class PlgBlcContentTest extends UnitTestCase
{
    use \Blc\Tests\BlcExtractTraitTestsTrait;
    use \Blc\Tests\BlcSetAltTraitTestsTrait;
    use \Blc\Tests\CustomFieldsTraitTestsTrait;

    protected string $folder         = 'blc';
    protected string $element        = 'content';
    protected string $class          = BlcPluginActor::class;
    protected string $fieldContext   = 'com_content.article';
    protected string $context        = 'com_content.article';
    protected int $jsonFlags         = JSON_PRETTY_PRINT;

    private $config = [
        'internal_absolute' => 0,
        'internal_sef'      => 0,
        'internal_xhtml'    => 0,
    ];



    public function setUp(): void
    {
        $this->initApplication();
        $this->checkPluginEnabled();
    }


    public static function setAltProvider()
    {
        return [
            ['introtext', 'href', false],
            ['fulltext', 'href', false],
            ['introtext', 'img', true],
            ['fulltext', 'img', true],
            ['introtext', '', false],
            ['fulltext', '', false],
            ['image_intro', 'links', true],
            ['image_intro', '', false],
            ['image_fulltext', '', false],
            ['urla', 'links', false],
            ['urlb', 'links', false],
            ['urlb', 'links', false],
        ];
    }

    public static function canSetAltProvider()
    {
        return [
            [null, 'img', false],
            ['xxx', 'img', false],

            ['introtext', 'href', false],
            ['fulltext', 'href', false],
            ['introtext', 'img', true],
            ['fulltext', 'img', true],

            ['introtext', null, false],
            ['fulltext', null, false],

            ['image_intro', 'links', true],
            ['image_intro', 'xx', true],
            ['image_intro', null, true],
            ['image_fulltext', null, true],
            ['image_fulltext', 'xx', true],

            ['urla', 'links', false],
            ['urlb', 'links', false],
            ['urlb', 'links', false],

            //yootheme - actually the parser will return 'true' on any field while the only field containing a yootheme layout is 'fulltext'

        ];
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

    public function testBootPluginService()
    {
        parent::testBootPluginService();
    }

    public function testCanCheckInternal()
    {
        $link           = $this->assertGetSomeLink(destination: 'internal', linkPattern: '');
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
    }


    public function testCanFixCatid()
    {
        $correctUrl     = $this->getContentLink();
        $url            = preg_replace('#catid=[0-9]+#', 'catid=999998', (string) $correctUrl);
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



    public function testCanFixCatidBlcCheckLink()
    {
        $correctUrl = $this->getContentLink();
        $url        = preg_replace('#catid=[0-9]+#', 'catid=999998', (string) $correctUrl);

        $linkItem = $this->loadLinkItem($url, config: $this->config);

        $this->checkLinkWrapped($linkItem);
        $this->assertSame($correctUrl, $linkItem->internal_url, json_encode($linkItem->log, $this->jsonFlags));
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

        //defaults
        $contentChecker->setParamsOption('category_alias', 0);
        $contentChecker->setParamsOption('article_alias', 0);



        //ignored no index.php
        $url            = "option=com_content&view=article&catid={$catId}&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url, json_encode($linkItem->log));


        $url            = "option=com_content&view=article&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);


        //ignored wrong context
        $url            = "index.php?option=com_phpunit&view=view&catid={$catId}&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);


        //corrected added missing catid
        $url            = "index.php?option=com_content&view=article&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $correctedUrl = "index.php?option=com_content&view=article&id={$id}&catid={$catId}"; // catid is appended
        $this->assertSame($correctedUrl, $linkItem->internal_url);


        $url            = "index.php?option=com_content&view=article&catid=&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $correctedUrl = "index.php?option=com_content&view=article&catid={$catId}&id={$id}"; // catid is replaced
        $this->assertSame($correctedUrl, $linkItem->internal_url);

        $wrongCatId     = $catId + 9999;
        $url            = "index.php?option=com_content&view=article&catid=$wrongCatId&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $correctedUrl = "index.php?option=com_content&view=article&catid={$catId}&id={$id}"; // catid is replaced
        $this->assertSame($correctedUrl, $linkItem->internal_url);

        $wrongCatId     = 'some';
        $url            = "index.php?option=com_content&view=article&catid=$wrongCatId&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $correctedUrl = "index.php?option=com_content&view=article&catid={$catId}&id={$id}"; // catid is replaced
        $this->assertSame($correctedUrl, $linkItem->internal_url);

        //corrected unchanged
        $url            = "index.php?option=com_content&view=article&catid={$catId}&id={$id}";
        $linkItem       = $this->loadLinkItem($url, config: $this->config);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($correctedUrl, $linkItem->internal_url);


        // missing id.
        //reported as broken
        $url            = "index.php?option=com_content&view=article&catid={$catId}";
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);
        $this->assertSame(HTTPCODES::BLC_JOOMLA_ITEM_NOT_FOUND, $linkItem->http_code);

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

        $this->assertNotSame($url, $linkItem->internal_url, json_encode($linkItem->log));

        $contentChecker->setParamsOption('category_alias', 0);
        $contentChecker->setParamsOption('article_alias', 0);
        $urlWithAlias   = $linkItem->internal_url;
        $linkItem       = $this->loadLinkItem($urlWithAlias, json_encode($linkItem->log));
        $contentChecker->checkLink($linkItem);
        $this->assertSame($urlWithAlias, $linkItem->internal_url);

        $contentChecker->setParamsOption('category_alias', 2);
        $contentChecker->setParamsOption('article_alias', 2);
        $linkItem       = $this->loadLinkItem($urlWithAlias);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);

        $wrongCatId     = 'some:alias';
        $url            = "index.php?option=com_content&view=article&catid=$wrongCatId&id={$id}";
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $correctedUrl = "index.php?option=com_content&view=article&catid={$catId}&id={$id}"; // catid is replaced
        $this->assertSame($correctedUrl, $linkItem->internal_url);
    }
    /**
     * This is to test the correct return values  for empty anchors and alt attributes
     * It should be enough to test the special fields only as the html fields are tested in various other tests as are the parsers
     * Still, the basics are tested here as well.
     *
     * @since 25.44.7545
     */
    public function testparseContainerFields()
    {
        $plugin = $this->bootPlugin();
        $row    = $this->getTestItem();
        //var_export($row);
        $url                =  $this->getRandomLink(ext: 'php');
        $img                =  $this->getRandomLink(ext: 'php');
        $img_alt            = 'phpunit.anchor.' . uniqid();
        $url_anchor_1       =  'URL Anchor.' . uniqid();
        $row->introtext     = '<a href="' . $url . '">' . $url_anchor_1 . '</a> <img src="' . $img . '" alt="' . $img_alt . '" />';
        $row->fulltext      = '<a href="' . $url . '"></a> <img src="' . $img . '" />';
        $image_intro        =  $this->getRandomLink(ext: 'png');
        $image_fulltext     =  $this->getRandomLink(ext: 'png');
        $image_fulltext_alt = 'phpunit.anchor.' . uniqid();
        $row->images        = json_encode(
            [
                'image_intro'         => $image_intro,
                'image_intro_alt'     => '',
                'float_intro'         => '',
                'image_intro_caption' => '',
                'image_fulltext'      => $image_fulltext,
                'image_fulltext_alt'  => $image_fulltext_alt,
                'float_fulltext'      => '',

            ]
        );
        $urla      =  $this->getRandomLink(ext: 'html');
        $urlb      =  $this->getRandomLink(ext: 'html');
        $urlc      =  $this->getRandomLink(ext: 'html');
        $urlatext  = 'A URL Text  phpunit.text.' . uniqid();
        $urlbtext  = 'B URL Text  phpunit.text.' . uniqid();
        $urlctext  = 'C URL Text  phpunit.text.' . uniqid();
        $row->urls = json_encode(
            [
                'urla'     => $urla,
                'urlatext' => $urlatext,
                'targeta'  => '',
                'urlb'     => $urlb,
                'urlbtext' => $urlbtext,
                'targetb'  => '',
                'urlc'     => $urlc,
                'urlctext' => $urlctext,
                'targetc'  => '',

            ]
        );
        $protectedMethod = (
            fn($row) =>
            /** @phpstan-ignore method.notFound */
            $this->parseContainerFields($row)
        );
        $protectedMethod->call($plugin, $row);
        $linkItem = $this->assertLinkExists($url);
        $this->assertAnchorExists($url_anchor_1, $linkItem->id);
        $this->assertAnchorExists(BlcParserInterface::BLC_EMPTY_ANCHOR, $linkItem->id);

        $linkItem =   $this->assertLinkExists($img);
        $this->assertAnchorExists(BlcParserInterface::BLC_EMPTY_ALT, $linkItem->id);
        $this->assertAnchorExists($img_alt, $linkItem->id);

        $this->assertLinkExists($urla);
        $this->assertLinkExists($urlb);
        $this->assertLinkExists($urlc);
        $linkItem =  $this->assertLinkExists($image_intro);
        $this->assertAnchorExists(BlcParserInterface::BLC_EMPTY_ALT, $linkItem->id);
        $linkItem =  $this->assertLinkExists($image_fulltext);
        $this->assertAnchorExists($image_fulltext_alt, $linkItem->id);
        $this->resetExtracted($row->id);
    }

    protected function getContentLink(?int $forceId = null, ?int $forceCatId = null)
    {

        $contentItem = $this->getTestItem();
        $catId       = $forceCatId ?: $contentItem->catid;
        $id          = $forceId ?: $contentItem->id;

        return "index.php?option=com_content&view=article&catid={$catId}&id={$id}";
    }

    public function testCheckLang()
    {
        $url = $this->getContentLink();
        $contentChecker = $this->bootChecker();
        $contentChecker->setParamsOption('check_lang', 1);
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);

        $contentChecker->setParamsOption('check_lang', 2);
        $linkItem       = $this->loadLinkItem($url);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);

        $urlSomeLang= $url . '&lang=' . uniqid();
        $contentChecker->setParamsOption('check_lang', 1);
        $linkItem       = $this->loadLinkItem($urlSomeLang);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_JOOMLA_ITEM_CHANGED);

        $urlSomeLang= $url . '&lang=' . uniqid();
        $contentChecker->setParamsOption('check_lang', 2);
        $linkItem       = $this->loadLinkItem($urlSomeLang);
        $contentChecker->checkLink($linkItem);
        $this->assertSame($url, $linkItem->internal_url);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_JOOMLA_ITEM_CHANGED);
    }


    public function testonBlcCheckerRequest()
    {
        $this->assertOnBlcCheckerRequest();
    }
}
