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

use Blc\Component\Blc\Administrator\Field\FieldField;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/FieldField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(FieldField::class)]
class FieldFieldTest extends FilterFieldTest
{
    protected $fields    = [

        "value" => "i.field",

    ];
    protected string $class = FieldField::class;

    /**
     *
     * this is acutally implemented in FilterField. Testing it here so we can keep FilterFieldTest as parent class even if the children do not use the column in the database
     * and it's only needed to be tested once
     */

    public function testSetColumn()
    {

        $field = new $this->class();

        $element      =  '<field name="destination" column="test-column" test-attribute="test-value" default="-1" label="COM_BLC_OPTION_DESTINATION_FILTER" description="" onchange="this.form.submit();"/>';
        $xml          = new \SimpleXMLElement($element);
        $field->setUp($xml, 'test-default-value');
        $setColumn = $field->getAttribute('column');
        $this->assertEquals('test-column', $setColumn, "Column should be set to 'test-column' but is '$setColumn'");
        $protectedMethod = (
            fn () => /** @phpstan-ignore method.notFound **/
        $this->processQuery()
        );
        $query       =  $protectedMethod->call($field);
        $queryString = $query->__toString();
        $db          = $this->getDatabase();
        $key         = 'value';
        $value       = 'i.test-column';

        $valueQuoted = $db->quoteName($value);
        $matchString = $valueQuoted;

        $keyQuoted = $db->quoteName($key);
        $matchString .= ' AS ' . $keyQuoted;
        $this->assertStringContainsString($matchString, $queryString, "Query ($query) should contain $matchString");
    }
}
