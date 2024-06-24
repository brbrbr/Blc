<?php

/**
 * @package         BLC Login
 * @version   24.44
 *
 * @author          Bram Brambring <info@brokenlinkchecker.dev>
 * @link            https://brokenlinkchecker.dev
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Form\FormField;

class InfoField extends FormField
{
    protected $tag;
    protected $text;

    protected function getLabel()
    {
        return "&nbsp;";
    }

    protected function getInput()
    {
        if ($this->class && method_exists($this->class, 'getHelpLink')) {
            $link = $this->class::getHelpLink();
        } else {
            $link = '';
        }

        return  BlcHelper::footer($link);
        ;
    }
}
