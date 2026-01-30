<?php

declare(strict_types=1);

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

class BlcCheckerUnchecked extends BlcModule implements BlcCheckerInterface
{
    protected function init(): void
    {
        parent::init();
    }

    public function canCheckLink(LinkTable $linkItem): int
    {

        //do not check checked links
        if ($linkItem->http_code !== self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }

        return $this->componentConfig->get('unkownprotocols') ? self::BLC_CHECK_TRUE : self::BLC_CHECK_IGNORE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[]          = self::class;
        $linkItem->http_code      = self::BLC_UNCHECKED_PROTOCOL_HTTP_CODE;
        $linkItem->broken         = self::BLC_BROKEN_FALSE;
    }
}
