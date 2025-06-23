<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;

use Blc\Component\Blc\Administrator\Table\LinkTable;
use PHPUnit\Framework\Attributes;

/**
 * Base Unit Test case for common behaviour across unit tests
 *
 * @since   4.0.0
 */




trait CustomFieldsTraitTestsTrait
{
    /**
     * BlcExtractInterface
     * This will test all links. This is to cover the links in custom fields.
     * has overlap with testreplaceLink but since we can't see what links are from Fields we need toe loop them all.
     * the CustomFieldsTraitTest covers that all Fields are covered
     *
     */


    #[Attributes\Depends('testonBlcExtract')]
    #[Attributes\RunInSeparateProcess]
    public function testreplaceAllLinks()
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $plugin = $this->bootPlugin();
        $this->app->bootComponent('com_blc')->getMVCFactory();

        $links = $this->getAllLinkIds(plugin: $this->element, fields: ['Fields']);
        //it not a problem if we don't test all types. This is done in the test of the trait
        $this->assertNotEmpty($links, 'No links found to test');
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());


        foreach ($links as $link) {
            $this->clearMessageQueue();
            $linkItem->reset();
            $linkItem->load([
                'id' => $link->link_id,

            ]);

            if (str_starts_with($linkItem->url, 'sqlfield')) {
                //can not be replaced
                continue;
            }

            $this->assertNotNull($linkItem, 'No linkItem found to test:' . json_encode(\func_get_args()) . json_encode($link));
            $newLink = $this->getRandomLink(ext: $link->parser);

            $plugin->replaceLink($linkItem, $link, $newLink);

            $this->assertMessageQueue('success', empty: false, msg: [$link, $linkItem->url, $newLink]);
            $newLinkItem = $this->assertGetSomeLink(parser: $link->parser, plugin: $this->element, fields: [$link->field], linkPattern: $newLink);
            $this->assertEquals($newLinkItem->url, $newLink);
        }
    }
}
