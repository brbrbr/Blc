<?php

/**
 * @package     Blc.Plugin
 * @subpackage  Blc.Provider
 * @version   24.44
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Provider\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Uri\Uri;

final class FacebookChecker extends OEmbedChecker implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var   Blc\Component\Blc\Administrator\Blc\BlcModule;
     *
     */
    protected static ?\Blc\Component\Blc\Administrator\Blc\BlcModule $instance = null;

    public function canCheckLink(LinkTable $linkItem): int
    {

        //only recheck
        if ($linkItem->http_code === self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }

        //not on internal
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }

        $finalUrl = $linkItem->final_url ?? '';
        if (str_contains($finalUrl, 'facebook.com/login')) {
            return self::BLC_CHECK_TRUE;
        }

        return self::BLC_CHECK_FALSE;
    }




    protected function fetchFacebook(LinkTable &$linkItem)
    {

        $url         = $linkItem->toCheck;
        $provider    =  'https://www.facebook.com/plugins/page.php';
        $providerUri = URI::getInstance($provider);

        //not sure which are really needed

        $providerUri->setVar('href', urlencode($url));
        $providerUri->setVar('tabs', 'timeline');
        $providerUri->setVar('width', 340);
        $providerUri->setVar('height', 331);
        $providerUri->setVar('small_header', 'false');
        $providerUri->setVar('adapt_container_width', 'true');
        $providerUri->setVar('hide_cover', 'false');
        $providerUri->setVar('show_facepile', 'true');

        $appId = $this->params->get('appid');
        if ($appId) {
            $providerUri->setVar('appId', $appId);
        }
        if (isset($linkItem->log['Final Request header'])) {
            $linkItem->log['First Request header']  =     $linkItem->log['Final Request header'];
            unset($linkItem->log['Final Request header']);
        }


        $linkItem->toCheck = (string)$providerUri;
        $this->getFromProvider($linkItem);
        $linkItem->toCheck = $url;

        $response = $linkItem->log['Response'];

        $forceResponse = $this->componentConfig->get('response', self::CHECKER_LOG_RESPONSE_NEVER);
        if (
            $forceResponse !== self::CHECKER_LOG_RESPONSE_ALWAYS
            && $forceResponse !== self::CHECKER_LOG_RESPONSE_TEXT
        ) {
            //   unset($linkItem->log['Response']);
        }


        //looks like none existing pages are missing this:
        $check = 'MANIFEST_LINK';
        //    $check= rtrim($next, '/');
        //   $checkEnc = urlencode($check);


        if (
            !str_contains($response, $check)
        ) {
            $linkItem->broken    = self::BLC_BROKEN_TRUE;
            $linkItem->http_code = self::BLC_FACEBOOK_PAGE_NOT_FOUND_HTTP_CODE;
            $linkItem->final_url = $linkItem->toCheck;
        } else {
            $linkItem->broken    = self::BLC_BROKEN_FALSE;
            $linkItem->http_code = self::BLC_FACEBOOK_PAGE_FOUND_HTTP_CODE;
        }
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;
        $this->fetchFacebook($linkItem);
    }
}
