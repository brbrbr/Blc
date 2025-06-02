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


use Blc\Component\Blc\Administrator\Field\SpecialField;

use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Field/SpecialField

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(SpecialField::class)]
class SpecialFieldTest extends FilterFieldTest
{
protected string $class = SpecialField::class;
    protected $fields    = [

        "broken"   => "",
        "warning"  => "",
        "redirect" => "",
        "internal" => "",
        "timeout"  => "",
        "tocheck"  => "",
        "parked"   => "",
        "empty-alt"    => "",

        //   "all"   => "COM_BLC_OPTION_WITH_ALL",
    ];

  
   
}
