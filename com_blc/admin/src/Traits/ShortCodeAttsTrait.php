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

use Joomla\String\StringHelper;

trait ShortCodeAttsTrait
{
    /**
     *
     * from wordpress
     *
     * @param   string  $text
     *
     * @return array<string>
     *                                 1               2       n        3                4         n        5              6          n         7                 8                   9
     */
    private static $SHORTCOEEREGEX = '#([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)#';

    private function shortcodeParseAtts(string $text): array
    {
        $atts    = [];

        $text    = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text);
        if (preg_match_all(self::$SHORTCOEEREGEX, (string) $text, $match, PREG_SET_ORDER)) {
           
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $atts[StringHelper::strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $atts[StringHelper::strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $atts[StringHelper::strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && \strlen($m[7])) {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8]) && \strlen($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                } elseif (isset($m[9])) {
                    $atts[] = stripcslashes($m[9]);
                }
            }

            // Reject any unclosed HTML elements.
            foreach ($atts as &$value) {
                if (str_contains($value, '<')) {
                    if (1 !== preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value)) {
                        $value = '';
                    }
                }
            }
        } else {
            $atts = ['param' => ltrim((string) $text)]; //incorrect?
        }

        return $atts;
    }
}
