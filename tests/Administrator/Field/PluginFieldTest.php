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

use Blc\Component\Blc\Administrator\Field\PluginField;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/PluginField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(PluginField::class)]
class PluginFieldTest extends FilterFieldTest
{
    protected $fields    = [

        "value" => "s.plugin_name",

    ];

    protected string $class = PluginField::class;
}
