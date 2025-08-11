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
use Joomla\CMS\Uri\Uri;

class BlcCheckerDns extends BlcModule implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;


    public function canCheckLink(LinkTable $linkItem): int
    {
        //do not check checked links
        if ($linkItem->http_code !== self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }

        return  self::BLC_CHECK_TRUE;
    }
    private function hasDNS(string $host): bool
    {

        foreach ([DNS_A, DNS_A, DNS_CNAME] as $resource) {
            if ($records = dns_get_record($host, $resource)) {
                //dns gt record should resolve cnames
                if ($resource === DNS_CNAME) {
                    return $this->hasDNS($records[0]['target']);
                }
                return true;
            }
        }
        return false;
    }


    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;
        $parsed          = Uri::getInstance($linkItem->toCheck);
        $host            = $parsed->getHost() ?? '';
        if (! $host) {
            //ignore links without hosts ( like mailto:)
            //the unchecked checker takes care if these.
            return;
        }

        $hasDns = $this->hasDNS($host);
        if (! $hasDns) {
            $linkItem->http_code      = self::BLC_DNS_HTTP_CODE;
            $linkItem->broken         = self::BLC_BROKEN_TRUE;
        }
    }
}
