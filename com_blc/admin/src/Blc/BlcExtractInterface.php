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

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;

interface BlcExtractInterface
{
    public function getTitle($data): string;
    public function getViewLink($data): string;
    public function getEditLink($data): string;
    public function getLinks($data): object;
    public function onBlcExtract(BlcExtractEvent $event): void;
    public function onBlcPurge();
    public function onBlcContainerChanged(BlcEvent $event): void;
    public function onBlcExtensionAfterSave(BlcEvent $event): void;
    public function replaceLink(object $link, object $instance, string $newUrl): void;
}
