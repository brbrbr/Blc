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
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Language\Text;
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
    public static function labelProvider(): array
    {
        return [
            [HTTPCODES::BLC_WORKING_ACTIVE,'COM_BLC_ACTION_NORMAL_LINK'],
            [HTTPCODES::BLC_WORKING_WORKING,'COM_BLC_ACTION_WORKING_LINK'],
            [HTTPCODES::BLC_WORKING_IGNORE,'COM_BLC_ACTION_HIDDEN_LINK'],
            [HTTPCODES::BLC_WORKING_HIDDEN,'COM_BLC_ACTION_HIDDEN_LINK'],
        ];
    }
    #[Attributes\DataProvider('labelProvider')]
    public function testHideButton($working, $expectedLabel)
    {
        $options = [
            'task_prefix' => 'links.',
            'disabled'    => false,
            'id'          => 'hide-1',
        ];
        //working / row
        $button = (new HideButton())->render($working, 2, $options, '', '');
        $this->assertStringContainsString(
            Text::_($expectedLabel),
            $button,
            'HideButton should have the correct label for working state.'
        );
    }
}
