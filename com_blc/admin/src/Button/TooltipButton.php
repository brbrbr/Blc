<?php

/**
 * Joomla! Content Management System
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Button;

use Joomla\CMS\Toolbar\Button\StandardButton;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Renders a standard button
 *
 * @since  3.0
 */
class TooltipButton extends StandardButton
{
    /**
     * Property layout.
     *
     * @var  string
     *
     * @since  4.0.0
     */



    /**
     * Fetch the HTML for the button
     *
     * @param   string   $type    Unused string.
     * @param   string   $name    The name of the button icon class.
     * @param   string   $text    Button text.
     * @param   string   $task    Task associated with the button.
     * @param   boolean  $list    True to allow lists
     * @param   string   $formId  The id of action form.
     *
     * @return  string  HTML string for the button
     *
     * @since   3.0
     *

     */

    protected $layout = 'joomla.toolbar.basic';

    protected function prepareOptions(array &$options)
    {
        parent::prepareOptions($options);

        $options['attributes']['title']           = $options['tooltip'] ?? '';
        $options['attributes']['confirm-message'] = $options['message'] ?? '';
        if ($options['disabled'] ?? false) {
            $options['attributes']['disabled'] = 'disabled';
        }
    }

    public function url(string $url)
    {
        $this->layout = 'joomla.toolbar.link';
        return parent::url($url);
    }

    protected static function getAccessors(): array
    {
        $list   = parent::getAccessors();
        $list[] = 'tooltip';
        $list[] = 'url';
        $list[] = 'message';
        return $list;
    }
}
