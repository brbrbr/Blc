<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Tests\Administrator\Field;

use Blc\Component\Blc\Administrator\Field\ResponseField;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/ResponseField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(ResponseField::class)]
class ResponseFieldTest extends FilterFieldTest
{
    protected $fields    = [

        "value" => "a.http_code",

    ];

    protected string $class = ResponseField::class;
}
