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
use Blc\Component\Blc\Administrator\Traits\BlcSplitOptionTrait;
use Joomla\CMS\Uri\Uri;

class BlcCheckerPre extends BlcModule implements BlcCheckerInterface
{
    use BlcSplitOptionTrait;

    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;

    protected $ignoreHosts;
    protected $ignorePaths;

    protected function init()
    {
        parent::init();
        //  Factory::getApplication()->getDispatcher()->addSubscriber($this);
        $ignoreHosts = $this->splitOption($this->componentConfig->get('ignore_hosts', ''));

        $this->ignoreHosts = array_map('strtolower', $ignoreHosts);

        $ignorePaths  =      $this->splitOption($this->componentConfig->get('ignore_paths', ''));


        if ($ignorePaths) {
            $ignorePaths = array_map(
                fn ($item) => strtr($item, ['#' => '\\#']),
                $ignorePaths
            );
            $this->ignorePaths = '(' . implode('|', $ignorePaths) . ')';
        }
    }

    protected function isIgnoredHost(string $host): bool
    {
        $host = trim(strtolower($host));
        if ($host) {
            $host = preg_replace('#^(www|m)\.#', '', $host);

            if ($this->ignoreHosts && \in_array($host, $this->ignoreHosts)) {
                return true;
            }
        }
        return false;
    }
    protected function isIgnoredPath($path)
    {
        $path = trim(strtolower($path));
        if ($path && $this->ignorePaths) {
            if (preg_match('#' . $this->ignorePaths . '#', $path)) {
                return true;
            }
        }
        return false;
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        //do not check checked links
        if ($linkItem->http_code !== self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }
        try {
            $parsed = Uri::getInstance($linkItem->url);
        } catch (\RuntimeException) {
            //this is an invalid link. Ignore it here
            return  self::BLC_CHECK_FALSE;
        }
        $host   = $parsed->getHost() ?? '';
        if ($this->isIgnoredHost($host)) {
            // phpcs:disable Generic.Files.LineLength
            return $this->componentConfig->get('ignore_hosts_action') == 1 ? self::BLC_CHECK_TRUE : self::BLC_CHECK_IGNORE;
            // phpcs:enable Generic.Files.LineLength
        }
        $path = $parsed->getPath() ?? '';
        if ($this->isIgnoredPath($path)) {
            // phpcs:disable Generic.Files.LineLength
            return $this->componentConfig->get('ignore_paths_action') == 1 ? self::BLC_CHECK_TRUE : self::BLC_CHECK_IGNORE;
            // phpcs:enable Generic.Files.LineLength
        }
        return  self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[]          = self::class;
        $linkItem->http_code      = self::BLC_UNCHECKED_IGNORELINK;
        $linkItem->broken         = self::BLC_BROKEN_FALSE;
    }
}
