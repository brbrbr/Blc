<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;


use PHPUnit\Framework\Attributes;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;

/**
 * Base Unit Test case for common behaviour across unit tests
 *
 * @since   4.0.0
 */



#[Attributes\CoversClass(CustomFieldsTrait::class)]
trait   CustomFieldsTraitTestsTrait
{

            /**
     * BlcExtractInterface
     * This will test all links. This is to cover the links in custom fields.
     * has overlap with testreplaceLink but since we can't see what links are from Fields we need toe loop them all.
     * the CustomFieldsTraitTest covers that all Fields are covered
     * 
     */
    #[Attributes\Depends('testonBlcExtract')]
    public function testreplaceAllLinks()
    {
        $this->setUser(action: 'core.edit.value', assetKey: 'com_content.field');
        $this->assertReplaceAllLinks();
    }
}
