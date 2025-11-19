<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * this checker resets redirecting links
 * For example for affiliate links
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

class BlcCheckerIgnoreRedirect extends BlcModule implements BlcCheckerInterface
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
        $ignoreHosts       = $this->splitOption($this->componentConfig->get('ignore_redirects', ''));
        $this->ignoreHosts = array_map(strtolower(...), $ignoreHosts);
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

    public function canCheckLink(LinkTable $linkItem): int
    {
        //no point to do anything with unchecked files
        if ($linkItem->http_code === self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }

        //if the final response is a 301 it's wrong as wel.
        if ($linkItem->http_code > 300) {
            return  self::BLC_CHECK_FALSE;
        }

        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }
        return self::BLC_CHECK_TRUE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        //as we get here the response code is just checked in canCheckLink
        $linkItem->log[] = self::class;
        $parsed          = Uri::getInstance($linkItem->url);
        $host            = $parsed->getHost() ?? '';
        //redirect_count might be set yet.
        //let's simpy reset it
        if ($this->isIgnoredHost($host)) {
            $linkItem->final_url       = '';
            $linkItem->redirect_count  = 0;
            $linkItem->http_code       = BlcCheckerInterface::BLC_IGNORED_REDIRECT_PROTOCOL_HTTP_CODE;
        }
    }
}
