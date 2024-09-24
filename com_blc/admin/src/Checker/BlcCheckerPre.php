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
use  Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class BlcCheckerPre extends BlcModule implements BlcCheckerInterface
{
    protected static $instance = null;
    protected $ignoreHosts;
    protected $ignorePaths;

    public function init()
    {
        parent::init();
        //  Factory::getApplication()->getDispatcher()->addSubscriber($this);
        $ignoreHosts = preg_split($this->splitOption, $this->componentConfig->get('ignore_hosts', ''));
        if ($ignoreHosts === false) {
            Factory::getApplication()->enqueueMessage(
                "COM_BLC_IGNOREHOSTS_LIST_INVALID",
                'warning'
            );
            $ignoreHosts = [];
        }
        $this->ignoreHosts = array_map('strtolower', array_filter($ignoreHosts));
        $ignorePathsString = trim($this->componentConfig->get('ignore_paths', ''));
        $ignorePaths       = preg_split($this->splitOption, $ignorePathsString);
        if ($ignorePaths === false) {
            Factory::getApplication()->enqueueMessage(
                "COM_BLC_IGNOREPATHS_LIST_INVALID",
                'warning'
            );
            $ignorePaths = [];
        } else {
            $ignorePaths = array_filter($ignorePaths);
        }
        if ($ignorePaths) {
            $ignorePaths = array_map(
                function ($item) {
                    return strtr($item, ['#' => '\\#']);
                },
                $ignorePaths
            );
            $this->ignorePaths = '(' . join('|', $ignorePaths) . ')';
        }
    }

    protected function isIgnoredHost($host)
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
        $parsed = Uri::getInstance($linkItem->url);
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

    public function checkLink(LinkTable &$linkItem, $results = [], object|array $options = []): array
    {
        $results['http_code']     = self::BLC_UNCHECKED_IGNORELINK;
        $results['broken']        = false;
        $linkItem->log['Checker'] = 'Ignore domain or path';
        return $results;
    }

    public function initConfig(Registry $config): void
    {
    }
}
