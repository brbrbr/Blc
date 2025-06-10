<?php

/**
 * @version   24.44
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Helper;

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Helper/BlcHelper

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(BlcHelper::class)]
class BlcHelperTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function intervalTohoursProvider(): array
    {
        return [
            [1, 'second', 1 / 3600],
            [60, 'second', 60/3600],
            [1, 'minute', 1 / 60],
            [60, 'minute', 1],
            [1, 'hour', 1],
            [24, 'hour', 24],
            [1, 'day', 24],
            [7, 'day', 168],
            [1, 'week', 168],
            [4, 'week', 672],
            [1, 'month', 24 * 7 * 4.333],
            [12, 'month', 12 * 24 * 7 * 4.333],
            [1, 'year', 24 * 7 * 365],
            [2, 'year', 2 * 24 * 7 * 365],
            [100, 'invalid', 100], // Should default to hours
        ];
    }

    #[Attributes\DataProvider('intervalTohoursProvider')]
    public function testIntervalTohours(int $freq, string $unit, float $expected): void
    {
        $expected=round($expected,3);
        $result =round( BlcHelper::intervalTohours($freq, $unit),3);
        $this->assertEquals($expected, $result, sprintf('Failed for frequency %d and unit %s', $freq, $unit));
    }

  
}
