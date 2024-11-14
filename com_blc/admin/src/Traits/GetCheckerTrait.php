<?php

/**
 * @version   __DEPLOY_VERSION
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


use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;

trait GetCheckerTrait
{
    //TODO rework to get the first 'real' checker
    protected function getChecker()
    {
        //TODO function like getUrl and getProvider change the settings so use a clone
        return  BlcCheckerHttpCurl::getInstance();
    }
}
