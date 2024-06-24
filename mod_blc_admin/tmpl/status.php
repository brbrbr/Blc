<?php

/**
 * @version   24.44
 * @package    BLC Packge
 * @module    mod_blc_admin
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

\defined('_JEXEC') or die('Restricted access');
if ($checkCompleet === false) {
    ?>
    <div class="header-item blcclose ">
        <div class="header-item-content no-link blcstatus Redirect">
            <div class="header-item-icon">
                <span class="blcicon fa-rotate-by icon-refresh" aria-hidden="true"></span>
            </div>
            <div class="header-item-text ">
                <span aria-hidden="true" class="blcresponse short"><span class="Redirect">Waiting</span></span>
            </div>
        </div>
    </div>
    <?php
}
