<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
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
    private ?BlcCheckerHttpCurl $checker = null;

    protected function getChecker(bool $clone = false): BlcCheckerHttpCurl
    {
        if ($clone) {

            return clone BlcCheckerHttpCurl::getInstance();
        }

        return $this->checker ??= BlcCheckerHttpCurl::getInstance();

    }
}
