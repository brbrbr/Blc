<?php

/**
 * Joomla! Content Management System
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Button;

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
class BrokenButton extends ActionButton
{
    protected function preprocess()
    {
        $this->addState(
            1,
            'recheck',
            'icon- fa-chain-broken text-danger',
            Text::_('COM_BLC_ACTION_RECHECK_TOGGLE'),
            ['tip_title' => Text::_('COM_BLC_ACTION_BROKEN_RECHECK_LINKS')]
        );
        $this->addState(
            0,
            'recheck',
            'icon- fa-chain text-success ',
            Text::_('COM_BLC_ACTION_RECHECK_TOGGLE'),
            ['tip_title' => Text::_('COM_BLC_ACTION_WORKING_RECHECK_LINKS')]
        );
        $this->addState(
            2,
            'recheck',
            'icon- fa-arrow-right text-warning ',
            Text::_('COM_BLC_ACTION_RECHECK_TOGGLE'),
            ['tip_title' => Text::_('COM_BLC_ACTION_REDIRECT_RECHECK_LINKS')]
        );
        $this->addState(
            3,
            'recheck',
            'icon- icon-warning-circle text-warning ',
            Text::_('COM_BLC_ACTION_RECHECK_TOGGLE'),
            ['tip_title' => Text::_('COM_BLC_ACTION_INTERNAL_MISMATCH_RECHECK_LINKS')]
        );

        $this->addState(
            4,
            'recheck',
            'icon- fa-solid fa-hourglass-end text-error',
            Text::_('COM_BLC_ACTION_RECHECK_TOGGLE'),
            ['tip_title' => Text::_('COM_BLC_ACTION_TIMEOUT_RECHECK_LINKS')]
        );
        $this->addState(
            5,
            'recheck',
            'icon-refresh  text-gray',
            Text::_('COM_BLC_ACTION_CHECK_TOGGLE'),
            ['tip_title' => Text::_('COM_BLC_ACTION_UNCHECKED_RECHECK_LINKS')]
        );
    }
}
