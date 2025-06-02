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

        "value"   => "i.field",

    ];
    protected string $class = FieldField::class;
}
