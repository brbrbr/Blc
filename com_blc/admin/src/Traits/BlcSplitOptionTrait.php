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

/**
 *
 * @since 25.44.7589
 */

trait BlcSplitOptionTrait
{
    /**
     *
     * @since 25.44.7589
     */

    private string $splitOptionPattern = "#(;|,|\r\n|\n|\r)#";
    /**
     *
     * @since 25.44.7589
     */

    protected function splitOption(string $optionsString): array
    {
        //unit tests would catch an invalid pattern
        $options = preg_split($this->splitOptionPattern, $optionsString);
        return array_filter($options);
    }
}
