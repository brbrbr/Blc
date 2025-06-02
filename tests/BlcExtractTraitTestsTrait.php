<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;

use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\Language\Text;

/**
 * Base Unit Test case for common behaviour across unit tests
 *
 * @since   4.0.0
 */



#[Attributes\CoversClass(BlcExtractTrait::class)]
trait BlcExtractTraitTestsTrait
{
    public function testCanBoot()
    {

        $this->assertNotNull($this->class);
        $plugin = $this->bootPlugin(assert: true);
        $this->assertInstanceOf($this->class, $plugin);
        $this->assertMessageQueue();
        return $plugin;
    }

    public function testMagicGet()
    {
        $this->assertMagicGet();
    }

    /**
     * SubscriberInterface
     *
     */

    public function testgetSubscribedEvents()
    {
        $this->assertSubscribedEvents();
    }


    /**
     * BlcExtractInterface
     * This will test link extraction as well
     * this is to ensure we have a extract link for each field/parser
     * coverage of all custom fields is in the CustomFieldsTrait and CustomFieldsTraitTestsTrait
     *
     */
    #[Attributes\RunInSeparateProcess]
    #[Attributes\Depends('testonBlcExtract')]
    #[Attributes\DataProvider('fieldProvider')]
    public function testreplaceLink($field, $parser)
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $this->assertReplaceLink($field, $parser);
    }

    /**
     * code coverage for replaceLink when no container is set
     * this is a situation that should not happen in real life, but we need to ensure that the plugin can handle it gracefully.
     */
    public function testreplaceLinkNoContainer()
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $plugin = $this->bootPlugin();
        $this->app->bootComponent('com_blc')->getMVCFactory();

        $link   = $this->getSomeLinkId(parser: '', plugin: $this->element, fields: []);
        $this->assertNotNull($link, "No link found to test ({$this->element}): " . ' ' . json_encode($this->lastQueryInfo));
        $linkItem = $this->assertloadLinkItemID($link->link_id);
        $link->container_id = 0; //no container
        $newLink = $this->getRandomLink();
        $plugin->replaceLink($linkItem, $link, $newLink);
        $this->assertMessageQueue('warning', empty: Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR'), msg: [$link, $linkItem->url, $newLink]);
    }


    /**
     * BlcExtractInterface
     *
     */
    public function testonBlcExtract()
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $this->assertOnBlcExtract();
    }


    /**
     * BlcExtractInterface
     *
     */
    public function testgetTitle()
    {
        $this->assertGetTitle();
    }

    /**
     * BlcExtractInterface
     *
     */
    #[Attributes\RunInSeparateProcess]
    public function testonBlcContainerChanged()
    {
        $this->assertOnBlcContainerChanged();
    }
    public function testpluginCanReplaceLink()
    {
        $plugin = $this->bootPlugin(assert: false);
        $canReplace = $plugin->pluginCanReplaceLink();
        $this->assertTrue($canReplace, 'Plugin should be able to replace links');
    }

    /**
     * BlcExtractInterface
     *
     */

    public function testgetEditLink()
    {
        $this->assertgetEditLink();
    }
    /**
     * BlcExtractInterface
     *
     */
    public function testgetViewLink()
    {
        $this->assertgetViewLink();
    }
    /**
     * BlcExtractInterface
     *
     */
    #[Attributes\RunInSeparateProcess]
    public function testonBlcExtensionAfterSave()
    {
        $this->assertOnExtensionAfterSave();
    }

    /**
     * From joomla content events to onBlcContainerChanged
     *
     */
    #[Attributes\RunInSeparateProcess]
    public function testContentEvents()
    {
        $this->assertContentEvents();
    }

    /**
     * BlcHelpTrait
     *
     */


    public function testgetHelpLink()
    {
        $this->assertgetHelpLink();
    }

    /**
     * BlcHelpTrait
     *
     */

    public function testgetHelpHTML()
    {

        $this->assertgetHelpHtml();
    }

    public function testparseContainerInvalidId()
    {
        $this->clearMessageQueue();
        //code coverage for parseContainer when the id is invalid

        $id = 0;
        $plugin = $this->bootPlugin(assert: false);
        //reset the extracted data

        $protectedMethod = (function ($id) {
            /** @phpstan-ignore method.notFound **/
            $this->getItemSynch($id);
            $this->parseContainer($id);
        }
        );

        $protectedMethod->call($plugin, $id);
        $this->assertMessageQueue('warning', false);
    }
    protected function resetExtracted($id)
    {
        $plugin = $this->bootPlugin(assert: false);
        //reset the extracted data
        $protectedMethod = (function ($id) {
            /** @phpstan-ignore method.notFound **/
            $this->cleanupSynchId($id);
            $this->parseContainer($id);
        }
        );
        $protectedMethod->call($plugin, $id);
    }
}
