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

use Blc\Component\Blc\Administrator\Button\IgnoreButton as Button;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\Language\Text;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Button/IgnoreButton

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(Button::class)]
class IgnoreButtonTest extends UnitTestCase
{
    public function setUp(): void
    {
        $this->initApplication();
    }

    public function testcanBoot()
    {
        $button = new Button();
        $this->assertInstanceOf(Button::class, $button);
    }
    public static function stateProvider(): array
    {

        return [
            [HTTPCODES::BLC_WORKING_ACTIVE, 'COM_BLC_ACTION_NORMAL_LINK'],
            [HTTPCODES::BLC_WORKING_WORKING, 'COM_BLC_ACTION_WORKING_LINK'],
            [HTTPCODES::BLC_WORKING_IGNORE, 'COM_BLC_ACTION_IGNORED_LINK'],
            [HTTPCODES::BLC_WORKING_HIDDEN, 'COM_BLC_ACTION_HIDDEN_LINK'],

        ];
    }
    #[Attributes\DataProvider('stateProvider')]
    public function testRender($state, $result)
    {
        $button     = new Button();
        $n =   rand(1, 1000);
        $id = 'testing-' . $n;
        $options  = [
            'task_prefix' => 'links.',
            'disabled'    => false,
            'id'          => $id,
        ];
        $buttonHtml = $button->render($state, $n, $options);
        $this->assertStringContainsString(Text::_($result), $buttonHtml);
        $this->assertStringContainsString($id, $buttonHtml);
    }
}
