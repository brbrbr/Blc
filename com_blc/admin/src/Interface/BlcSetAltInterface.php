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

namespace Blc\Component\Blc\Administrator\Interface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Table\LinkTable;


interface BlcSetAltInterface
{

    public const BLC_REPLACE_ALT_YES              = 1;
    public const BLC_REPLACE_ALT_PARSER               = 2;
    public const BLC_REPLACE_ALT_NO             = 0;
    /**
     * @since __DEPLOY_VERSION__
     *
     */
    public function canSetAlt(object $instance): bool;
    /**
     * @since __DEPLOY_VERSION__
     *
     */
    public function getFieldsSetAlt(): array;

    /**
     * This wil set the alt attribute for a given link
     * that might be a 'alt' attribute in html or fields like image_alt
     *
     * @param LinkTable $link
     * @param object $instance  - join of instance and synch
     * @param string $newAlt
     *
     * @since __DEPLOY_VERSION__
     *
     */
    public function setAlt(LinkTable $link, object $instance, string $newAlt): void;
}
