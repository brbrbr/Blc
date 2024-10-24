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
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class BlcCheckerPost extends BlcModule implements BlcCheckerInterface
{
    protected static $instance = null;
    protected $ignoreHosts;
    protected $ignorePaths;
    public $always = true;

    public function init()
    {
        parent::init();
        //  Factory::getApplication()->getDispatcher()->addSubscriber($this);
        $ignoreHosts = preg_split($this->splitOption, $this->componentConfig->get('ignore_redirects', ''));
        if ($ignoreHosts === false) {
            Factory::getApplication()->enqueueMessage(
                "COM_BLC_IGNOREHOSTS_LIST_INVALID",
                'warning'
            );
            $ignoreHosts = [];
        }
        $this->ignoreHosts = array_map('strtolower', array_filter($ignoreHosts));
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

    public function canCheckLink(LinkTable $linkItem): int
    {
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }
        $parsed = Uri::getInstance($linkItem->url);
        $host   = $parsed->getHost() ?? '';


        return $this->isIgnoredHost($host) ? self::BLC_CHECK_TRUE : self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, $results = []): array
    {
        $code = $results['http_code'] ?? 0 ;
        //if the final response is a 301 it's wrong as wel.
        if ($code < 400) {
            $results['final_url']      = '';
            $results['redirect_count'] = 0;
            $results['http_code']      = BlcCheckerInterface::BLC_IGNORED_REDIRECT_PROTOCOL_HTTP_CODE;
            $linkItem->log['Checker']  = 'Ignore redirect';
        }
        return $results;
    }

  
}
