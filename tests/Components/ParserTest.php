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

use Blc\Tests\UnitTestCase;
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

#[Attributes\TestDox('Test Embed Parser')]
class ParserTest extends UnitTestCase
{
    protected $wrappedClass;
    protected string $fieldContext = 'com_content.article';
    #[Attributes\TestDox('boot the plugin')]
    public function setUp(): void
    {
        $this->initApplication();
    }

    protected function assertTestTag(string $html = '')
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $model = $this->getModel('com_content', 'Article');
        $links = [];
        $anchors = [];

        $testTitle =  JTEST_TITLE . ' Test';

        $itemTemplate = $model->getItem(['title' => $testTitle]); //object

        $this->assertNotEmpty($itemTemplate, 'A item with title: ' . $testTitle . ' is needed');

        if ((bool)$itemTemplate->checked_out === true) {

            $this->markTestSkipped(
                "Warning test item is checkedout",
            );
            return [];
        }





        $itemString =  $html . '<hr id="system-readmore">' . $html;

        $itemString = preg_replace_callback(
            '#phpunit.(text|jpg|png)#',
            function ($m) {
                return uniqid() . '.' . $m[1];
            },
            $itemString
        );

        $itemString = preg_replace_callback(
            '#phpunit.invalid#',
            function ($m) {
                return uniqid() . '-gen.invalid';
            },
            $itemString
        );


        $itemString = preg_replace_callback(
            '#phpunit.anchor#',
            function ($m) use (&$anchors) {
                $anchor = uniqid() . ' Generated Anchor';
                $anchors[] = $anchor;
                return $anchor;
            },
            $itemString
        );
        preg_match_all('#(https://(.*?)\.(com|dev|invalid)[a-z0-9\-/./]+)#u', $itemString, $m);

        $links = $m[1];

        $itemTemplate->articletext = $itemString;
        unset($itemTemplate->fulltext);
        unset($itemTemplate->introtext);
    
        $itemTemplate = ArrayHelper::fromObject($itemTemplate);

        $input                                     = $this->getApplication()->getInput();
        $input->post->set('jform', $itemTemplate);
        $model->save($itemTemplate);
   
        $itemTemplate = $model->getItem(['title' => $testTitle]); //object
 
       // return;
        foreach ($links as $link) {
            $this->assertLinkExists($link);
        }
        foreach ($anchors as $anchor) {
            $this->assertAnchorExists($anchor);
        }

        return $links;
    }


    public function estCanIframe()
    {


        $this->assertTestTag('<iframe src="https://phpunit.invalid/iframe-link" poster=""></iframe>');
    }

    public function estCanVideo()
    {

        $this->assertTestTag('<video src="https://phpunit.invalid/video-link" poster=""></video>');
    }

    public function testCanA()
    {

        $this->assertTestTag('<a href="https://phpunit.invalid/a-href-link">phpunit.anchor</a>');
    }
    public function testCanImg()
    {

        $this->assertTestTag('<img src="https://phpunit.invalid/image.jpg" alt="phpunit.anchor"/>');
    }
}
