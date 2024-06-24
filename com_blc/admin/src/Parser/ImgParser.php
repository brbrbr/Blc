<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Based on Wordpress Broken Link Checker by WPMU DEV https://wpmudev.com/
 *
 */

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class ImgParser extends BlcTagParser
{
    protected string $parserName = 'img';
    protected string $attribute  = 'src';
    protected string $element    = 'img';
    protected static $instance   = null;


    protected function getAnchor(array $result): string
    {
        return  $result['attributes']['alt'] ?? $result['attributes']['title'] ?? '\'img\' without alt or title';
    }
}
