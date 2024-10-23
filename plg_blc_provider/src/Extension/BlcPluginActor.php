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

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use BlcHelpTrait;

    protected $autoloadLanguage     = true;
    private const  HELPLINK         = 'https://brokenlinkchecker.dev/extensions/plg-blc-provider';
    private const YOUTUBE_API_HOST  = 'https://youtube.googleapis.com';
    //from wordpress. the 'true's seem to be unused but left for east copy/paste
    //phpcs:disable Generic.Files.LineLength
    protected $providers = [

        //  '#https?://((m|www)\.)?youtube\.com/watch.*#i'             => ['https://www.youtube.com/oembed', true],
        //   '#https?://((m|www)\.)?youtube\.com/playlist.*#i'          => ['https://www.youtube.com/oembed', true],
        //   '#https?://((m|www)\.)?youtube\.com/shorts/*#i'            => ['https://www.youtube.com/oembed', true],
        //   '#https?://((m|www)\.)?youtube\.com/live/*#i'              => ['https://www.youtube.com/oembed', true],
        //   '#https?://youtu\.be/.*#i'                                 => ['https://www.youtube.com/oembed', true],
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
        //  '#https?://([a-z0-9-]+\.)?amazon\.(com|com\.mx|com\.br|ca)/.*#i' => array('https://read.amazon.com/kp/api/oembed', true),
        //  '#https?://([a-z0-9-]+\.)?amazon\.(co\.uk|de|fr|it|es|in|nl|ru)/.*#i' => array('https://read.amazon.co.uk/kp/api/oembed', true),
        //  '#https?://([a-z0-9-]+\.)?amazon\.(co\.jp|com\.au)/.*#i' => array('https://read.amazon.com.au/kp/api/oembed', true),
        //  '#https?://([a-z0-9-]+\.)?amazon\.cn/.*#i'     => array('https://read.amazon.cn/kp/api/oembed', true),
        //  '#https?://(www\.)?a\.co/.*#i'                 => array('https://read.amazon.com/kp/api/oembed', true),
        //  '#https?://(www\.)?amzn\.to/.*#i'              => array('https://read.amazon.com/kp/api/oembed', true),
        //  '#https?://(www\.)?amzn\.eu/.*#i'              => array('https://read.amazon.co.uk/kp/api/oembed', true),
        //  '#https?://(www\.)?amzn\.in/.*#i'              => array('https://read.amazon.in/kp/api/oembed', true),
        //  '#https?://(www\.)?amzn\.asia/.*#i'            => array('https://read.amazon.com.au/kp/api/oembed', true),
        //  '#https?://(www\.)?z\.cn/.*#i'                 => array('https://read.amazon.cn/kp/api/oembed', true),
        '#https?://www\.someecards\.com/.+-cards/.+#i'              => ['https://www.someecards.com/v2/oembed/', true],
        '#https?://www\.someecards\.com/usercards/viewcard/.+#i'    => ['https://www.someecards.com/v2/oembed/', true],
        '#https?://some\.ly\/.+#i'                                  => ['https://www.someecards.com/v2/oembed/', true],
        '#https?://(www\.)?tiktok\.com/.*/video/.*#i'               => ['https://www.tiktok.com/oembed', true],
        '#https?://(www\.)?tiktok\.com/@.*#i'                       => ['https://www.tiktok.com/oembed', true],
        '#https?://([a-z]{2}|www)\.pinterest\.com(\.(au|mx))?/.*#i' => ['https://www.pinterest.com/oembed.json', true],
        '#https?://(www\.)?wolframcloud\.com/obj/.+#i'              => ['https://www.wolframcloud.com/oembed', true],
        '#https?://pca\.st/.+#i'                                    => ['https://pca.st/oembed.json', true],
        '#https?://((play|www)\.)?anghami\.com/.*#i'                => ['https://api.anghami.com/rest/v1/oembed.view', true],

        //override

        '#https?://((m|www)\.)?facebook\.com/.+#i'        => ['fetchFacebook', true],
        '#https?://((m|www)\.)?youtube\.com/watch.*#i'    => ['fetchYouTube', true],
        '#https?://((m|www)\.)?youtube\.com/playlist.*#i' => ['fetchYouTube', true],
        '#https?://((m|www)\.)?youtube\.com/shorts/*#i'   => ['fetchYouTube', true],
        '#https?://((m|www)\.)?youtube\.com/live/*#i'     => ['fetchYouTube', true],
        '#https?://youtu\.be/.*#i'                        => ['fetchYouTube', true],

    ];

    // phpcs:enable Generic.Files.LineLength
    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcCheckerRequest' => 'onBlcCheckerRequest',

        ];
    }

    protected function getProvider($url)
    {
        foreach ($this->providers as $matchmask => $data) {
            list($provider, $regex) = $data;
            if (preg_match($matchmask, $url)) {
                $host     = BlcHelper::root();
                $provider = str_replace('{format}', 'json', $provider); // JSON is easier to deal with than XML.
                $provider = str_replace('{host}', urlencode($host), $provider);
                return $provider;
            }
        }
        return false;
    }



    protected function fetchFacebook(LinkTable &$linkItem)
    {
        $result = $this->realCheckLink($linkItem);

        $final_url = $result['final_url'] ?? '';
        unset($result['final_url']);
        if ($final_url) {
            $facebookUrl = Uri::getInstance($final_url);
            $path        =  $facebookUrl->getPath();
            if (strpos($path, '/login') === 0) {
                $next = $facebookUrl->getVar('next');
            } else {
                $next = $final_url;
            }
        } else {
            $next = $linkItem->_toCheck;
        }

        $provider    =  'https://www.facebook.com/plugins/page.php';
        $providerUri = URI::getInstance($provider);

        //not sure which are really needed

        $providerUri->setVar('href', urlencode($next));
        $providerUri->setVar('tabs', 'timeline');
        $providerUri->setVar('width', 340);
        $providerUri->setVar('height', 331);
        $providerUri->setVar('small_header', 'false');
        $providerUri->setVar('adapt_container_width', 'true');
        $providerUri->setVar('hide_cover', 'false');
        $providerUri->setVar('show_facepile', 'true');

        $providerUri->setVar('href', urlencode($next));


        $appId = $this->params->get('appid');
        if ($appId) {
            $providerUri->setVar('appId', $appId);
        }
        if (isset($linkItem->log['Final Request header'])) {
            $linkItem->log['First Request header']  =     $linkItem->log['Final Request header'];
            unset($linkItem->log['Final Request header']);
        }
        $result   = $this->getFromProvider($providerUri, $linkItem);
        $response = $linkItem->log['Response'];

        //looks like none existing pages are missing this:
        $check = 'MANIFEST_LINK';
        //    $check= rtrim($next, '/');
        //   $checkEnc = urlencode($check);


        if (
            false === strpos($response, $check)
        ) {
            $result['broken']    = 1;
            $result['http_code'] = self::BLC_PROVIDER_NOT_FOUND_HTTP_CODE;
        }



        if ($next != $linkItem->_toCheck) {
            $result['final_url']      = $next;
            $result['redirect_count'] = 1;
        }
        return $result;
    }

    private function realCheckLink(LinkTable &$linkItem)
    {
        $checker = $this->getChecker();
        $config  = clone $this->componentConfig;
        $config->set('name', 'Check via Provider');
        $checker->setConfig($config);
        return $checker->checkLink($linkItem);
    }

    protected function fetch($provider, LinkTable &$linkItem)
    {
        if (\is_callable([$this, $provider])) {
            return   \call_user_func_array([$this, $provider], [&$linkItem]);
        }
        return  $this->fetchoEmbed($provider, $linkItem);
    }

    protected function fetchoEmbed($provider, LinkTable &$linkItem)
    {
        $providerUri = URI::getInstance($provider);
        $providerUri->setVar('maxwidth', 800);
        $providerUri->setVar('maxheight', 800);
        $providerUri->setVar('dnt', 1);
        $url = $linkItem->_toCheck;
        $providerUri->setVar('url', urlencode($url));

        //todo use format xml ??
        $providerUri->setVar('format', 'json');
        $results   = $this->getFromProvider($providerUri, $linkItem);
        $embedOnly = $this->params->get('embed', 0);
        if (!$embedOnly) {
            if (($results['http_code'] ?? 400) >= 400) {
                if (isset($linkItem->log['Final Request header'])) {
                    $linkItem->log['First Request header']  =     $linkItem->log['Final Request header'];
                    unset($linkItem->log['Final Request header']);
                    $results =    $this->realCheckLink($linkItem);
                }
            }
        }
        return $results;
    }

    protected function getFromProvider(URI $providerUri, LinkTable &$linkItem): array
    {
        $checker            = $this->getChecker();
        $href               = $linkItem->_toCheck;
        $linkItem->_toCheck = (string)$providerUri;
        $config             = clone $this->componentConfig;
        $config->set('range', false);
        $config->set('head', false);
        $config->set('response', self::CHECKER_LOG_RESPONSE_TEXT);
        $config->set('name', 'Get via Provider Checker');
        $checker->setConfig($config);
        $result             = $checker->checkLink($linkItem);
        $linkItem->_toCheck = (string)$href;
        return $result;
    }

    public function onBlcCheckerRequest($event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker($this, 40); //before the http checker (50)
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_FALSE;
        }

        if ($this->getProvider($linkItem->url)) {
            return self::BLC_CHECK_TRUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, $results = []): array
    {

        if ($linkItem->isInternal()) {
            //this should not happen, canCheckLink returns BLC_CHECK_FALSE on isInternal
            //however some other checker might have changed the linkItem;
            return   $this->realCheckLink($linkItem);
        }

        $linkItem->log['Checker Embed'] = 'Embed';
        $provider                       = $this->getProvider($linkItem->_toCheck);
        if (!$provider) {
            //this should not happen, canCheckLink returns BLC_CHECK_FALSE on !$provider
            //however some other checker might have changed the linkItem;
            return  $this->realCheckLink($linkItem);
        }
        $results = $this->fetch($provider, $linkItem);

        $forceResponse = $this->componentConfig->get('response', self::CHECKER_LOG_RESPONSE_NEVER);
        if (
            $forceResponse !== self::CHECKER_LOG_RESPONSE_ALWAYS
            && $forceResponse !== self::CHECKER_LOG_RESPONSE_TEXT
        ) {
            unset($linkItem->log['Response']);
        }

        return $results;
    }
    public function setConfig(Registry $config): void
    {
    }


    //TODO Youtube API





    protected function fetchYoutube(LinkTable &$linkItem)
    {
        $apiKey = $this->params->get('youapi');
        if (!$apiKey) {
            return  $this->fetchoEmbed('https://www.youtube.com/oembed', $linkItem);
        }

        $transientmanager = BlcTransientManager::getInstance();
        $transient        = "BLC Youtube API";
        $data             = $transientmanager->get($transient);
        if ($data) {
            return [
                'http_code' => self::BLC_THROTTLE_HTTP_CODE,
            ];
        }

        $transientmanager->set($transient, [$transient . $linkItem->_toCheck], 1); //BlcTransientManager / Joomla's mysql does not support microseconds. The data is just (debug) info


        $parsed = new Uri($linkItem->_toCheck);

        //Extract the video or playlist ID from the URL
        $videoId     = null;
        $playlist_id = null;
        $path        = $parsed->getPath();

        if (strtolower($parsed->getHost()) === 'youtu.be') {
            $videoId = trim($path, '/');
        } elseif ((strpos($path, 'watch') !== false) && $parsed->hasVar('v')) {
            $videoId =  $parsed->getVar('v', '');
        } elseif ('/playlist' == $path) {
            $playlist_id =  $parsed->getVar('list', '');
        } elseif ('/view_play_list' == $path) {
            $playlist_id =  $parsed->getVar('p', '');
        }

        if (empty($playlist_id) && empty($videoId)) {
            return [
                'http_code' => self::BLC_YOUTUBE_INVALID,
            ];
        }

        //Fetch video or playlist from the YouTube API
        if (!empty($videoId)) {
            $apiUrl = $this->buildVideoAPiCall($videoId);
        } else {
            $apiUrl = $this->buildPlaylistAPiCall($playlist_id);
        }

        $results = $this->getFromProvider($apiUrl, $linkItem);


        if (!empty($videoId)) {
            $results = $this->checkVideo($linkItem, $results);
        } else {
            $results = $this->checkPlaylist($linkItem, $results);
        }
        //single point for 'broken'
        $results['broken'] = $this->getChecker()->isErrorCode($results['http_code']);




        return $results;
    }


    protected function checkVideo(LinkTable &$linkItem, $results = [])
    {
        $logHeader  = "Youtube video";
        $api        = json_decode($linkItem->log['Response']);
        $videoFound = (200 == $results['http_code']) && isset($api->items, $api->items[0]);

        if (isset($api->error) && (404 !== $results['http_code'])) { //404's are handled later.
            $results['http_code']      = self::BLC_YOUTUBE_API_ERROR;
            $linkItem->log[$logHeader] = $this->formatApiErrors($api);
            return $results;
        } elseif ($videoFound) {
            $log  = Text::_("COM_BLC_YOUTUBE_API_VIDEO_FOUND");
            //Add the video title to the log, purely for information.
            $title          = $api->items[0]->snippet->title ?? '';
            if ($title) {
                $log .= "\n\nTitle : \"" . htmlentities($title) . '"';
            }
            $linkItem->log[$logHeader] = $log;
        } else {
            $linkItem->log[$logHeader] = Text::_("COM_BLC_YOUTUBE_API_VIDEO_NOT_FOUND");
            $results['http_code']      = self::BLC_YOUTUBE_NOT_FOUND;
        }

        return $results;
    }


    protected function checkPlaylist(LinkTable &$linkItem, $results = [])
    {
        $api        = json_decode($linkItem->log['Response']);
        $logHeader  = "Youtube playlist";

        if (404 === $results['http_code']) {
            $linkItem->log[$logHeader] = Text::_("COM_BLC_YOUTUBE_API_PLAYLIST_NOT_FOUND");
            $results['http_code']      = self::BLC_YOUTUBE_NOT_FOUND;
        } elseif (403 === $results['http_code']) {
            $results['http_code']      = self::BLC_YOUTUBE_API_ERROR;
            $linkItem->log[$logHeader] = $this->formatApiErrors($api);
        } elseif ((200 === $results['http_code']) && isset($api->items) && \is_array($api->items)) {
            $items = $api->items ?? [];
            //The playlist exists.
            if (empty($items)) {
                $log  =  Text::_("COM_BLC_YOUTUBE_API_PLAYLIST_EMPTY");

                $results['http_code']   = self::BLC_YOUTUBE_EMPTY;
            } else {
                $linkItem->log[$logHeader] = Text::_("COM_BLC_YOUTUBE_API_PLAYLIST_OK");
                //Treat the playlist as broken if at least one video is inaccessible.
                foreach ($items as $video) {
                    if (($video->status->privacyStatus ?? '') == 'private') {
                        $linkItem->log[$logHeader] = Text::_("COM_BLC_YOUTUBE_API_PLAYLIST_PRIVATE");
                        $results['http_code']      = self::BLC_YOUTUBE_PRIVATE;
                        break;
                    }
                }
            }
            $title          = $api->items[0]->snippet->title ?? '';

            if ($title) {
                $linkItem->log[$logHeader] .= "\n\nTitle : \"" . htmlentities($title) . '"';
            }
        } else {
            //Some other error.
            $results['http_code']      = self::BLC_YOUTUBE_API_ERROR;
            $linkItem->log[$logHeader] = $this->formatApiErrors($api);
        }

        return $results;
    }

    protected function buildVideoAPiCall($videoId): Uri
    {

        $query = [
            'part' => 'status,snippet',
            'id'   => $videoId,
        ];
        return $this->buildYoutubeAPICall('videos', $query);
    }

    protected function buildPlaylistAPiCall($playlist_id): Uri
    {
        $query = [
            'id'         => $playlist_id,
            'part'       => 'snippet,status',
            'maxResults' => 10, //Playlists can be big. Lets just check the first few videos.
        ];
        return $this->buildYoutubeAPICall('playlists', $query);
    }

    protected function buildYoutubeAPICall(string $endpoint, array $query)
    {
        $endpoint     = trim($endpoint, '/');
        $apiUri       = new Uri(self::YOUTUBE_API_HOST . '/youtube/v3/' . $endpoint);
        $query['key'] =    $this->params->get('youapi');
        $apiUri->setquery($query);
        return $apiUri;
    }

    protected function formatApiErrors($api)
    {

        $log     = Text::_("COM_BLC_YOUTUBE_API_ERROR");
        $errors  = $api->error->errors ?? [];
        $message = $api->error->message ?? '';
        if ($message) {
            $log .= "\n$message\n";
        }
        //Log error details.
        if (\is_array($errors)) {
            foreach ($errors as $error) {
                $log .= "\n---\n";

                if (\is_array($error)) {
                    foreach ($error as $key => $value) {
                        $log .= \sprintf(
                            "%s: %s\n",
                            htmlentities($key),
                            htmlentities($value)
                        );
                    }
                }
            }
        }

        return $log;
    }
}
