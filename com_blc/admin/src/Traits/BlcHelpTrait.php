<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Traits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;

trait BlcHelpTrait
{
    public static function getHelpLink(): string
    {
        return  \defined('self::HELPLINK') ? self::HELPLINK : '';
    }
    public static function getHelpHTML(string $anchor = ''): string
    {
        $helpLink = self::getHelpLink();
        if (! $helpLink) {
            return $anchor;
        }

        if (! $anchor) {
            $anchor =   $helpLink;
        }

        return  HTMLHelper::_('blc.linkme', self::HELPLINK, $anchor, 'blc-help');
    }
}
