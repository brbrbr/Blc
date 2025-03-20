<?php


declare(strict_types=1);

namespace Blc\Tests\Plugin;

use Blc\Component\Blc\Administrator\Button\BrokenButton as Button;
use PHPUnit\Framework\Attributes;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Language\Text;

#[Attributes\CoversClass(Button::class)]
class BrokenButtonTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the button')]
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
            [0, 'COM_BLC_ACTION_WORKING_RECHECK_LINKS'],
            [1, 'COM_BLC_ACTION_BROKEN_RECHECK_LINKS'],
            [2, 'COM_BLC_ACTION_REDIRECT_RECHECK_LINKS'],
            [3, 'COM_BLC_ACTION_INTERNAL_MISMATCH_RECHECK_LINKS'],
            [4, 'COM_BLC_ACTION_TIMEOUT_RECHECK_LINKS'],
            [5,'COM_BLC_ACTION_UNCHECKED_RECHECK_LINKS']
        ];
    }
    #[Attributes\DataProvider('stateProvider')]
    public function testRender($state, $result)
    {
        $button = new Button();
        $buttonHtml= $button->render($state);
        $this->assertStringContainsString(Text::_($result),$buttonHtml);
    }
}
