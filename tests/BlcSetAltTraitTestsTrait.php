<?php

/**
 * @package    Joomla.UnitTest
 *
 * @copyright  (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://www.phpunit.de/manual/current/en/installation.html
 */

namespace Blc\Tests;

use Joomla\CMS\Language\Text;
use PHPUnit\Framework\Attributes;

/**
 *
 * @since  25.44.7545
 */




trait BlcSetAltTraitTestsTrait
{
    #[Attributes\DataProvider('canSetAltProvider')]
    #[Attributes\Group('setAlt')]
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
        $field          = $field ?: 'null';
        $parser         = $parser ?: 'null';
        $expectedString = $expected ? 'true' : 'false';
        $this->assertSame($expected, $result, "canSetAlt should return {$expectedString} for instance with field: {$field} and parser: {$parser}");
    }

    #[Attributes\Group('setAlt')]
    #[Attributes\DataProvider('setAltProvider')]
    public function testSetAlt($field, $parser, $expected)
    {
        $plugin         =  $this->bootPlugin();
        $this->app->bootComponent('com_blc')->getMVCFactory();
        //default to content just what we need
        $linkObject         = $this->getSomeLinkId($parser, plugin: $this->element, fields: [$field]);
        $linkObject->parser = $parser;

        $this->assertNotNull($linkObject, 'Link object should not be null');
        $newAlt    = $this->getRandomAlt();
        $linkItem  = $this->assertloadLinkItemID($linkObject->link_id);


        $plugin->setAlt($linkItem, $linkObject, $newAlt);

        $this->assertAltString($newAlt, $linkObject->link_id, $expected);
    }
    #[Attributes\Group('setAlt')]
    public function testSetAltParserNotSet()
    {
        $plugin         =  $this->bootPlugin();
        $this->app->bootComponent('com_blc')->getMVCFactory();
        //default to content just what we need
        $linkObject         = $this->getSomeLinkId(plugin: $this->element, fields: []);
        $linkObject->parser = '';

        $this->assertNotNull($linkObject, 'Link object should not be null');
        $newAlt    = $this->getRandomAlt();
        $linkItem  = $this->assertloadLinkItemID($linkObject->link_id);


        $plugin->setAlt($linkItem, $linkObject, $newAlt);
        $this->assertMessageQueue('warning', empty: Text::_('PLG_BLC_ANY_REPLACE_PARSER_NOT_SET'));

        $this->assertAltString($newAlt, $linkObject->link_id, false);
    }
}
