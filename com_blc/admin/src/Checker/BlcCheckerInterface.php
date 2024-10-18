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

namespace Blc\Component\Blc\Administrator\Checker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
@trigger_error(
    \sprintf(
        'This interface (%s) is deprecated use %s',
        'Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface',
        'Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface'
    ),
    E_USER_DEPRECATED
);

// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as OldInterface;

interface BlcCheckerInterface extends OldInterface
{
}
