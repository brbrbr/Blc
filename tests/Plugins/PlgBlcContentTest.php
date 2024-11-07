<?php

/**
 * @version   __DEPLOY_VERSION__
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Plugin\Blc\Content\Extension\BlcPluginActor;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Utilities\ArrayHelper;
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
    private string $folder  = 'blc';
    private string $element = 'content';

    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
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
        $item = ArrayHelper::fromObject($model->getItem(['title' => JTEST_TITLE]));

        $this->assertNotEmpty($item, 'A Categorie item with title ' . JTEST_TITLE . ' is needed');
        $unique          = uniqid();
        $introLink       = "https://example.com/introtext-$unique";
        $introAnchorText = "Anchor introtext $unique";

        $fullLink       = "https://example.com/fulltext-$unique";
        $fullAnchorText = "Anchor fulltext $unique";

        $fieldLink = "https://example.com/url-field-$unique";

        $urlAlink      = "https://example.com/url-a-$unique";
        $urlBlink      = "https://example.com/url-b-$unique";
        $urlClink      = "https://example.com/url-c-$unique";
        $urlAnchorText = "URL Text $unique";

        $imageIntroLink = "https://example.com/intro-$unique.jpg";
        $imageAlt       = "ALT VALUE $unique";

        $imageFullLink = "https://example.com/full-$unique.jpg";

        $item['introtext']                 = "<p>Generated: <a href=\"$introLink\">$introAnchorText</a></p>";
        $item['fulltext']                  = "<p>Generated: <a href=\"$fullLink\">$fullAnchorText</a></p>";
        $item['articletext']               = $item['introtext'] . '<hr id="system-readmore">' . $item['fulltext'];
        $item['images']['image_intro']     = $imageIntroLink;
        $item['images']['image_intro_alt'] = "Intro: $imageAlt";

        $item['images']['image_fulltext']     = $imageFullLink;
        $item['images']['image_fulltext_alt'] = "Full: $imageAlt";

        $item['urls'] =
            [
                'urla'     => $urlAlink,
                'urlatext' => 'A ' . $urlAnchorText,
                'targeta'  => '',
                'urlb'     => $urlBlink,
                'urlbtext' => 'A ' . $urlAnchorText,
                'targetb'  => '',
                'urlc'     => $urlClink,
                'urlctext' => 'A ' . $urlAnchorText,
                'targetc'  => '',
            ];

        //this is not an in depth check of the custom fields parser
        $item['com_fields']['content-phpunit-url'] = $fieldLink;
        $input                                     = $this->getApplication()->getInput();
        $input->post->set('jform', $item);
        $model->save($item);

        $linkItem = $this->assertLinkExists($introLink);
        $this->assertAnchorExists($linkItem->id, $introAnchorText);

        $linkItem = $this->assertLinkExists($fullLink);
        $this->assertAnchorExists($linkItem->id, $fullAnchorText);

        $linkItem = $this->assertLinkExists($imageIntroLink);
        $this->assertAnchorExists($linkItem->id, "Intro: $imageAlt");

        $linkItem = $this->assertLinkExists($imageFullLink);
        $this->assertAnchorExists($linkItem->id, "Full: $imageAlt");
        $this->assertLinkExists($urlAlink);
        $this->assertLinkExists($urlBlink);
        $this->assertLinkExists($urlClink);
        $this->assertLinkExists($fieldLink);
        $this->assertMessageQueue();
        return [$introLink, $urlClink, $imageFullLink, $fieldLink];
    }


    #[Attributes\Depends('testLinkExtraction')]
    public function testLinkReplace(array $urls)
    {
        $this->assertLinkReplace($urls);
    }
}
