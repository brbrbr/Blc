<?php

declare(strict_types=1);

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

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\GetCheckerTrait;
use Joomla\CMS\Uri\Uri;

class OEmbedChecker extends BlcModule implements BlcCheckerInterface
{
    use GetCheckerTrait;

    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;


    //from wordpress. the 'true's seem to be unused but left for east copy/paste
    //phpcs:disable Generic.Files.LineLength
    protected $providers = [

        '#https?://((m|www)\.)?youtube\.com/watch.*#i'             => ['https://www.youtube.com/oembed', true],
        '#https?://((m|www)\.)?youtube\.com/playlist.*#i'          => ['https://www.youtube.com/oembed', true],
        '#https?://((m|www)\.)?youtube\.com/shorts/*#i'            => ['https://www.youtube.com/oembed', true],
        '#https?://((m|www)\.)?youtube\.com/live/*#i'              => ['https://www.youtube.com/oembed', true],
        '#https?://youtu\.be/.*#i'                                 => ['https://www.youtube.com/oembed', true],
        '#https?://(.+\.)?vimeo\.com/.*#i'                         => ['https://vimeo.com/api/oembed.{format}', true],
        '#https?://(www\.)?dailymotion\.com/.*#i'                  => ['https://www.dailymotion.com/services/oembed', true],
        '#https?://dai\.ly/.*#i'                                   => ['https://www.dailymotion.com/services/oembed', true],
        '#https?://(www\.)?flickr\.com/.*#i'                       => ['https://www.flickr.com/services/oembed/', true],
        '#https?://flic\.kr/.*#i'                                  => ['https://www.flickr.com/services/oembed/', true],
        '#https?://(.+\.)?smugmug\.com/.*#i'                       => ['https://api.smugmug.com/services/oembed/', true],
        '#https?://(www\.)?scribd\.com/(doc|document)/.*#i'        => ['https://www.scribd.com/services/oembed', true],
        '#https?://wordpress\.tv/.*#i'                             => ['https://wordpress.tv/oembed/', true],
        '#https?://(.+\.)?crowdsignal\.net/.*#i'                   => ['https://api.crowdsignal.com/oembed', true],
        '#https?://(.+\.)?polldaddy\.com/.*#i'                     => ['https://api.crowdsignal.com/oembed', true],
        '#https?://poll\.fm/.*#i'                                  => ['https://api.crowdsignal.com/oembed', true],
        '#https?://(.+\.)?survey\.fm/.*#i'                         => ['https://api.crowdsignal.com/oembed', true],
        '#https?://(www\.)?twitter\.com/\w{1,15}/status(es)?/.*#i' => ['https://publish.twitter.com/oembed', true],
        '#https?://(www\.)?twitter\.com/\w{1,15}$#i'               => ['https://publish.twitter.com/oembed', true],
        '#https?://(www\.)?twitter\.com/\w{1,15}/likes$#i'         => ['https://publish.twitter.com/oembed', true],
        '#https?://(www\.)?twitter\.com/\w{1,15}/lists/.*#i'       => ['https://publish.twitter.com/oembed', true],
        '#https?://(www\.)?twitter\.com/\w{1,15}/timelines/.*#i'   => ['https://publish.twitter.com/oembed', true],
        '#https?://(www\.)?twitter\.com/i/moments/.*#i'            => ['https://publish.twitter.com/oembed', true],
        '#https?://(www\.)?soundcloud\.com/.*#i'                   => ['https://soundcloud.com/oembed', true],
        '#https?://(.+?\.)?slideshare\.net/.*#i'                   => ['https://www.slideshare.net/api/oembed/2', true],
        '#https?://(open|play)\.spotify\.com/.*#i'                 => ['https://embed.spotify.com/oembed/', true],
        '#https?://(.+\.)?imgur\.com/.*#i'                         => ['https://api.imgur.com/oembed', true],
        '#https?://(www\.)?issuu\.com/.+/docs/.+#i'                => ['https://issuu.com/oembed_wp', true],
        '#https?://(www\.)?mixcloud\.com/.*#i'                     => ['https://app.mixcloud.com/oembed/', true],
        '#https?://(www\.|embed\.)?ted\.com/talks/.*#i'            => ['https://www.ted.com/services/v1/oembed.{format}', true],
        '#https?://(www\.)?(animoto|video214)\.com/play/.*#i'      => ['https://animoto.com/oembeds/create', true],
        '#https?://(.+)\.tumblr\.com/.*#i'                         => ['https://www.tumblr.com/oembed/1.0', true],
        '#https?://(www\.)?kickstarter\.com/projects/.*#i'         => ['https://www.kickstarter.com/services/oembed', true],
        '#https?://kck\.st/.*#i'                                   => ['https://www.kickstarter.com/services/oembed', true],
        '#https?://cloudup\.com/.*#i'                              => ['https://cloudup.com/oembed', true],
        '#https?://(www\.)?reverbnation\.com/.*#i'                 => ['https://www.reverbnation.com/oembed', true],
        '#https?://videopress\.com/v/.*#'                          => ['https://public-api.wordpress.com/oembed/?for={$host}', true],
        '#https?://(www\.)?reddit\.com/r/[^/]+/comments/.*#i'      => ['https://www.reddit.com/oembed', true],
        '#https?://(www\.)?speakerdeck\.com/.*#i'                  => ['https://speakerdeck.com/oembed.{format}', true],
        '#https?://(www\.)?screencast\.com/.*#i'                   => ['https://api.screencast.com/external/oembed', true],
    /* only kindle
        '#https?://([a-z0-9-]+\.)?amazon\.(com|com\.mx|com\.br|ca)/.*#i' => array('https://read.amazon.com/kp/api/oembed', true),
        '#https?://([a-z0-9-]+\.)?amazon\.(co\.uk|de|fr|it|es|in|nl|ru)/.*#i' => array('https://read.amazon.co.uk/kp/api/oembed', true),
        '#https?://([a-z0-9-]+\.)?amazon\.(co\.jp|com\.au)/.*#i' => array('https://read.amazon.com.au/kp/api/oembed', true),
        '#https?://([a-z0-9-]+\.)?amazon\.cn/.*#i'     => array('https://read.amazon.cn/kp/api/oembed', true),
        '#https?://(www\.)?a\.co/.*#i'                 => array('https://read.amazon.com/kp/api/oembed', true),
        '#https?://(www\.)?amzn\.to/.*#i'              => array('https://read.amazon.com/kp/api/oembed', true),
        '#https?://(www\.)?amzn\.eu/.*#i'              => array('https://read.amazon.co.uk/kp/api/oembed', true),
        '#https?://(www\.)?amzn\.in/.*#i'              => array('https://read.amazon.in/kp/api/oembed', true),
        '#https?://(www\.)?amzn\.asia/.*#i'            => array('https://read.amazon.com.au/kp/api/oembed', true),
        '#https?://(www\.)?z\.cn/.*#i'                 => array('https://read.amazon.cn/kp/api/oembed', true),
      */
        '#https?://www\.someecards\.com/.+-cards/.+#i'              => ['https://www.someecards.com/v2/oembed/', true],
        '#https?://www\.someecards\.com/usercards/viewcard/.+#i'    => ['https://www.someecards.com/v2/oembed/', true],
        '#https?://some\.ly\/.+#i'                                  => ['https://www.someecards.com/v2/oembed/', true],
        '#https?://(www\.)?tiktok\.com/.*/video/.*#i'               => ['https://www.tiktok.com/oembed', true],
        '#https?://(www\.)?tiktok\.com/@.*#i'                       => ['https://www.tiktok.com/oembed', true],
        '#https?://([a-z]{2}|www)\.pinterest\.com(\.(au|mx))?/.*#i' => ['https://www.pinterest.com/oembed.json', true],
        '#https?://(www\.)?wolframcloud\.com/obj/.+#i'              => ['https://www.wolframcloud.com/oembed', true],
        '#https?://pca\.st/.+#i'                                    => ['https://pca.st/oembed.json', true],
        '#https?://((play|www)\.)?anghami\.com/.*#i'                => ['https://api.anghami.com/rest/v1/oembed.view', true],

    ];

    protected function getProvider($url)
    {
        foreach ($this->providers as $matchmask => $data) {
            [$provider] = $data;
            if (preg_match($matchmask, $url)) {
                $host     = BlcHelper::root();
                $provider = str_replace('{format}', 'json', $provider); // JSON is easier to deal with than XML.
                $provider = str_replace('{host}', urlencode($host), $provider);
                return $provider;
            }
        }
        return false;
    }


    private function fetchoEmbed($provider, LinkTable &$linkItem)
    {
        $url         = $linkItem->toCheck;
        $providerUri = URI::getInstance($provider);
        $providerUri->setVar('maxwidth', 800);
        $providerUri->setVar('maxheight', 800);
        $providerUri->setVar('dnt', 1);
        $providerUri->setVar('url', urlencode($url));
        //todo use format xml ??
        $providerUri->setVar('format', 'json');
        $linkItem->toCheck = (string)$providerUri;

        $this->getFromProvider($linkItem);
        $embedOnly = $this->params->get('embed', 0);
        if (!$embedOnly && $linkItem->broken) {
            $linkItem->http_code = self::BLC_CHECK_UNSET;
        }

        $linkItem->toCheck = $url;
    }

    protected function getFromProvider(LinkTable &$linkItem)
    {
        $checker            = $this->getChecker();
        $config             = clone $this->componentConfig;
        $config->set('range', false);
        $config->set('head', false);
        $config->set('response', self::CHECKER_LOG_RESPONSE_TEXT);
        $config->set('name', 'Get via Provider Checker');
        $checker->checkLink($linkItem, config: $config);
    }


    public function canCheckLink(LinkTable $linkItem): int
    {

        //do not recheck
        if ($linkItem->http_code !== self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }

        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }

        if ($this->getProvider($linkItem->url)) {
            return self::BLC_CHECK_TRUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;

        $provider  = $this->getProvider($linkItem->toCheck);
        if (!$provider) {
            return;
        }

        $this->fetchoEmbed($provider, $linkItem);

        $forceResponse = $this->componentConfig->get('response', self::CHECKER_LOG_RESPONSE_NEVER);
        if (
            $forceResponse !== self::CHECKER_LOG_RESPONSE_ALWAYS
            && $forceResponse !== self::CHECKER_LOG_RESPONSE_TEXT
        ) {
            unset($linkItem->log['Response']);
        }
    }
}
