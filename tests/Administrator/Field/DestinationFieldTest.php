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

use Blc\Component\Blc\Administrator\Field\DestinationField as Field;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\Form\Form;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/DestinationField

 *
 * @since       __DEPLOY_VERSION__
 */

#[Attributes\CoversClass(Field::class)]
class DestinationFieldTest extends UnitTestCase
{
    protected $field = ' <field name="destination" type="destination" default="-1" label="COM_BLC_OPTION_DESTINATION_FILTER" description="" onchange="this.form.submit();"/>';
    protected $default = -1;
    protected $expected = '<option value="-1" selected="selected">';
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testRenderFieldSelected()
    {
        $formMock     = $this->createStub(Form::class);
        $xml = new \SimpleXMLElement($this->field);
        $field = new Field($formMock);
        $field->setUp($xml, $this->default);
        $result = $field->renderField();
        $this->assertStringContainsString($this->expected, $result);
    }
}
