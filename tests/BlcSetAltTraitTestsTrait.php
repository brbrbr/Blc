<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;

use Blc\Component\Blc\Administrator\Traits\BlcSetAltTrait;
use PHPUnit\Framework\Attributes;

/**
 *
 * @since  25.44.7545
 */



#[Attributes\CoversClass(BlcSetAltTrait::class)]
trait BlcSetAltTraitTestsTrait
{
    #[Attributes\DataProvider('canSetAltProvider')]
    public function testCanSetAlt($field, $parser, $expected)
    {

        $instance = new \Stdclass();
        if ($field) {
            $instance->field = $field;
        }
        if ($parser) {
            $instance->parser = $parser;
        }
        $plugin         =  $this->bootPlugin();
        $result         = $plugin->canSetAlt($instance);
        ($result ? 'true' : 'false');
        $field        = $field ?: 'null';
        $parser       = $parser ?: 'null';
        $resultString = $result ? 'true' : 'false';
        $this->assertSame($expected, $result, "canSetAlt should return {$resultString} for instance with field: {$field} and parser: {$parser}");
    }


    #[Attributes\DataProvider('setAltProvider')]
    public function testSetAlt($field, $parser, $expected)
    {
        $plugin         =  $this->bootPlugin();
        //default to content just what we need
        $linkObject = $this->getSomeLinkId($parser, fields: [$field]);
        $this->assertNotNull($linkObject, 'Link object should not be null');
        $newAlt    = $this->getDummyAlt();
        $linkItem  = $this->assertloadLinkItemID($linkObject->link_id);
        $plugin->setAlt($linkItem, $linkObject, $newAlt);
        $this->assertAltString($newAlt, $linkObject->link_id, $expected);
    }
}
