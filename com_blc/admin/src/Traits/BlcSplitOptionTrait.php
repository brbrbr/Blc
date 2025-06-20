<?php

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
 * @since __DEPLOY_VERSION__
 */

trait BlcSplitOptionTrait
{
    /**
     * 
     * @since __DEPLOY_VERSION__
     */

    private string $splitOptionPattern = "#(;|,|\r\n|\n|\r)#";
    /**
     * 
     * @since __DEPLOY_VERSION__
     */

    protected function splitOption(string $optionsString): array
    {
        //unit tests would catch an invalid pattern
        $options = preg_split($this->splitOptionPattern, $optionsString);
        return array_filter($options);
    }
}
