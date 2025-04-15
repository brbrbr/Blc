<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Button;

use Blc\Component\Blc\Administrator\Button\HideButton;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Button/HideButton

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(HideButton::class)]
class HideButtonTest extends UnitTestCase
{


    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testDummy() {
            $this->markTestIncomplete(
             'This test has not been implemented yet.'
           );
           }

}
