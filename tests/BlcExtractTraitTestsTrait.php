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
    protected function resetExtracted($id) {
        $plugin = $this->bootPlugin(assert: false);
         //reset the extracted data
        $this->deleteSynch($id, $this->element);
        $protectedMethod = (fn($id) =>
        /** @phpstan-ignore method.notFound **/
        $this->parseContainer($id)
        );
        $protectedMethod->call($plugin, $id);
    }
}
