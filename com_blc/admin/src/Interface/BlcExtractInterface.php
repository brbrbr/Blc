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

use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Table\LinkTable;

interface BlcExtractInterface
{
    public function getTitle($data): string;
    public function getViewLink($data): string;
    public function getEditLink($data): string;
    public function getLinks($data): object;
    public function onBlcExtract(BlcExtractEvent $event): void;
    public function onBlcContainerChanged(BlcEvent $event): void;
    public function onBlcExtensionAfterSave(BlcEvent $event): void;
    /**
     * @param LinkTable $link
     * @param object $instance  - join of instance and synch
     * @param string $newUrl
     *
     */
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void;
}
