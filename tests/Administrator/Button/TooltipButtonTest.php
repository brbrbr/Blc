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

use Blc\Component\Blc\Administrator\Button\TooltipButton as Button;

use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;
use Joomla\CMS\Language\Text;
use  Joomla\CMS\Toolbar\Toolbar;

/**
 * Test class for SiteStatus plugin
 *
 * @package     BLC.UnitTest
 * @subpackage  Button/TooltipButton

 *
 * @since       25.44.7398
 */

#[Attributes\CoversClass(Button::class)]
class TooltipButtonTest extends UnitTestCase
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


    public function testRender()
    {
        $button = new Button('purge-links', 'COM_BLC_TOOLBAR_PURGE_LINKS_LBL');
        $task   = 'https://example.com/' . uniqid();
        $button->buttonClass('btn btn-danger')
            ->listCheck(false)
            ->url($task)
            ->icon('icon-purge')
            ->tooltip(Text::_('COM_BLC_TOOLBAR_PURGE_LINKS_DESC'))
            ->message(Text::_('COM_BLC_TOOLBAR_SURE'));
        $toolbar = new Toolbar();
        $button->setParent($toolbar);

        $buttonHtml = $button->render();
        $this->assertStringContainsString(Text::_('COM_BLC_TOOLBAR_PURGE_LINKS_DESC'), $buttonHtml);
        $this->assertStringContainsString(Text::_('COM_BLC_TOOLBAR_SURE'), $buttonHtml);
        $this->assertStringContainsString($task, $buttonHtml);
        $this->assertStringNotContainsString('disabled', $buttonHtml);
    }

    public function testDisabled()
    {
        $button = new Button(
            'purge-links',
            'COM_BLC_TOOLBAR_PURGE_LINKS_LBL',
            ['disabled' => true]
        );
      
        $toolbar = new Toolbar();
        $button->setParent($toolbar);
        $buttonHtml = $button->render();
        $this->assertStringContainsString('disabled', $buttonHtml);
    }
}
