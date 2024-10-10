<?php

/**
 * Joomla! Content Management System
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Button;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Joomla\CMS\Button\ActionButton;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The FeaturedButton class.
 *
 * @since  4.0.0
 */
class HideButton extends ActionButton
{
    protected function preprocess()
    {
        $this->addState(
            HTTPCODES::BLC_WORKING_ACTIVE,
            'hide',
            'icon- fa-eye-slash text-gray',
            Text::_('COM_BLC_ACTION_TO_HIDE_LINK'),
            ['tip_title' => Text::_('COM_BLC_ACTION_NORMAL_LINK')]
        );
        $this->addState(
            HTTPCODES::BLC_WORKING_WORKING,
            'hide',
            'icon- fa-eye-slash text-gray',
            Text::_('COM_BLC_ACTION_TO_HIDE_LINK'),
            ['tip_title' => Text::_('COM_BLC_ACTION_WORKING_LINK')]
        );
        $this->addState( //hidden button
            HTTPCODES::BLC_WORKING_IGNORE,
            'hide',
            'icon- fa-eye-slash text-gray',
            Text::_('COM_BLC_ACTION_TO_HIDE_LINK'),
            ['tip_title' => Text::_('COM_BLC_ACTION_HIDDEN_LINK')]
        );

        $this->addState(
            HTTPCODES::BLC_WORKING_HIDDEN,
            'active',
            'icon- fa-eye-slash text-success',
            Text::_('COM_BLC_UNSET'),
            ['tip_title' => Text::_('COM_BLC_ACTION_HIDDEN_LINK')]
        );
    }
}
