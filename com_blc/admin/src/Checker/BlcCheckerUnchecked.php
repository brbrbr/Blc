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
// phpcs:enable PSR1.Files.SideEffects
use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\Registry\Registry;

class BlcCheckerUnchecked extends BlcModule implements BlcCheckerInterface
{
    protected static $instance = null;
    public function init()
    {
        parent::init();
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        return $this->componentConfig->get('unkownprotocols') ? self::BLC_CHECK_TRUE : self::BLC_CHECK_IGNORE;
    }

    public function checkLink(LinkTable &$linkItem, $results = [], object|array $options = []): array
    {
        $results['http_code']     = self::BLC_UNCHECKED_PROTOCOL_HTTP_CODE;
        $results['broken']        = false;
        $linkItem->log['Checker'] = 'Unchecked Protocols';
        return $results;
    }
    public function initConfig(Registry $config): void
    {
    }
}
