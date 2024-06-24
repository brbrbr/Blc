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
?>
<?php if ($checkCompleet === false) { ?>
<nav class="main-nav-container  item">
    <ul class="nav flex-column main-nav metismenu ">
            <li class="menu-quicktask item item-level-1 blcclose blcstatus">
                <span>
                    <span class="icon-fas icon- blcicon fa-rotate-by icon-refresh icon-fw" aria-hidden="true"></span>
                    <span class="sidebar-item-title blcresponse long">Waiting for first BLC Response</span>
                </span>
                </span>
            </li>
    </ul>
</nav>
<?php } ?>