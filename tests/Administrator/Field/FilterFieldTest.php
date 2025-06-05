<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Field;

use Blc\Component\Blc\Administrator\Field\FilterField;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/FilterField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(FilterField::class)]
class FilterFieldTest extends UnitTestCase
{
    protected string $class = FilterField::class;
    protected $fields       = [

        "value" => "broken",

    ];

    public function setUp(): void
    {
        $this->initApplication();
    }



    public function testQuery()
    {
        $field           = new $this->class();
        $protectedMethod = (
            fn () => /** @phpstan-ignore method.notFound **/
        $this->processQuery()
        );
        $query       =  $protectedMethod->call($field);
        $queryString = $query->__toString();
        $db          = $this->getDatabase();
        foreach ($this->fields as $key => $value) {
            $matchString = '';
            if ($value) {
                $valueQuoted = $db->quoteName($value);
                $matchString = $valueQuoted;
            }
            $keyQuoted = $db->quoteName($key);
            $matchString .= ' AS ' . $keyQuoted;
            $this->assertStringContainsString($matchString, $queryString, "Query ($query) should contain $matchString");
        }
    }



    public function testGetAttribute()
    {
        //there is no xml loaded so the attrbiutes will be empty
        $default = 'default';
        $field   = new $this->class();
        $result  = $field->getAttribute('non-existing-attribute', $default);
        $this->assertEquals($default, $result, "getAttribute should return the default value when attribute does not exist");
        $element      =  '<field name="destination" test-attribute="test-value" default="-1" label="COM_BLC_OPTION_DESTINATION_FILTER" description="" onchange="this.form.submit();"/>';
        $xml          = new \SimpleXMLElement($element);
        $field->setUp($xml, 'test-default-value');
        $result = $field->getAttribute('test-attribute', $default);
        $this->assertEquals('test-value', $result, "getAttribute should return the default value when attribute does not exist");

        //mostly for code coverage
        //the actual correct values and count for the filters should be checked in the administrator.
        if ($this->class == FilterField::class) {
            $this->assertStringContainsString('value="test-default-value"', $field->input, "input shouldcontain selected value");
        } else {
            $this->assertStringContainsString('value="-1"', $field->input, "input should contain selected value");
        }
    }
}
