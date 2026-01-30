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
    protected function isIp($host)
    {
        return preg_match('#^(([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)$#', (string) $host);
    }

    /**
     * 'gethostbyname' for ipv6. Returns $host on failure like gethostbymanem
     *
     *
     * @since 25.44.7985
     *
     *
     */

    private function gethostbyname6(string $host): string
    {
        foreach ([DNS_A, DNS_AAAA, DNS_CNAME] as $resource) {
            if ($records = dns_get_record($host, $resource)) {
                //dns get record should resolve cnames to final ip - so this should
                if ($resource === DNS_CNAME) {
                    return gethostbyname6($records[0]['target']);
                }
                $record = $records[0];
                return $resource === DNS_A ? $record['ip'] : $record['ipv6'];
            }
        }
        return $host;
    }


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
        $ip = $this->gethostbyname6($host);
        return $ip !== $host;
    }


    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;
        try {
            $parsed          = Uri::getInstance($linkItem->toCheck);
        } catch (\RuntimeException) {
            //some kind of invalid host
            //we should never get here
            return;
        }
        $host            = $parsed->getHost() ?? '';
        if (! $host) {
            //ignore links without hosts ( like mailto:)
            //the unchecked checker takes care if these.
            return;
        }
        if ($this->isIp($host)) {
            return;
        }

        $hasDns = $this->hasDNS($host);
        if (! $hasDns) {
            $linkItem->http_code      = self::BLC_DNS_HTTP_CODE;
            $linkItem->broken         = self::BLC_BROKEN_TRUE;
        }
    }
}
