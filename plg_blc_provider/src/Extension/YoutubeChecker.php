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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

final class YoutubeChecker extends OEmbedChecker implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var  Blc\Component\Blc\Administrator\Blc\BlcModule
     *
     */
    protected static $instance = null;

    private const YOUTUBE_API_HOST  = 'https://youtube.googleapis.com';

    protected function getProvider($url)
    {
        //this checker shouldn't be enabled if no key present
        $apiKey = $this->params->get('youapi');
        if (!$apiKey) {
            return false;
        }
        $provider = parent::getProvider($url);
        if ($provider && $provider != 'https://www.youtube.com/oembed') {
            $provider = false;
        }
        return $provider;
    }

    public function checkLink(LinkTable &$linkItem): void
    {

        $linkItem->log['Checker Embed'] = 'YoutubeChecker';
        $provider                       = $this->getProvider($linkItem->_toCheck);
        if (!$provider) {
            return;
        }
        $this->fetchYouTube($linkItem);
        if ($linkItem->broken) {
            $linkItem->http_code = self::BLC_CHECK_UNSET;
        }

        $forceResponse = $this->componentConfig->get('response', self::CHECKER_LOG_RESPONSE_NEVER);
        if (
            $forceResponse !== self::CHECKER_LOG_RESPONSE_ALWAYS
            && $forceResponse !== self::CHECKER_LOG_RESPONSE_TEXT
        ) {
            unset($linkItem->log['Response']);
        }
    }

    protected function fetchYoutube(LinkTable &$linkItem)
    {


        $parsed = new Uri($linkItem->_toCheck);

        //Extract the video or playlist ID from the URL
        $videoId     = null;
        $playlist_id = null;
        $path        = $parsed->getPath();

        if (strtolower($parsed->getHost()) === 'youtu.be') {
            $videoId = trim($path, '/');
        } elseif ((str_contains($path, 'watch')) && $parsed->hasVar('v')) {
            $videoId =  $parsed->getVar('v', '');
        } elseif ('/playlist' == $path) {
            $playlist_id =  $parsed->getVar('list', '');
        } elseif ('/view_play_list' == $path) {
            $playlist_id =  $parsed->getVar('p', '');
        }

        if (empty($playlist_id) && empty($videoId)) {
            $linkItem->http_code = self::BLC_YOUTUBE_INVALID;
            return;
        }

        //Fetch video or playlist from the YouTube API
        if (!empty($videoId)) {
            $apiUrl = $this->buildVideoAPiCall($videoId);
        } else {
            $apiUrl = $this->buildPlaylistAPiCall($playlist_id);
        }

        $url                = $linkItem->url;
        $linkItem->_toCheck = (string)$apiUrl;
        $this->getFromProvider($linkItem);
        $url = $linkItem->url = $url;


        if (!empty($videoId)) {
            $this->checkVideo($linkItem);
        } else {
            $this->checkPlaylist($linkItem);
        }
    }


    protected function checkVideo(LinkTable &$linkItem)
    {
        $logHeader  = "Youtube video";
        $api        = json_decode($linkItem->log['Response']);
        $videoFound = (200 == $linkItem->http_code) && isset($api->items, $api->items[0]);

        if (isset($api->error) && (404 !== $linkItem->http_code)) { //404's are handled later.
            $linkItem->http_code       = self::BLC_YOUTUBE_API_ERROR;
            $linkItem->log[$logHeader] = $this->formatApiErrors($api);
            return;
        } elseif ($videoFound) {
            $log  = Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_VIDEO_FOUND");
            //Add the video title to the log, purely for information.
            $title          = $api->items[0]->snippet->title ?? '';
            if ($title) {
                $log .= "\n\nTitle : \"" . htmlentities($title) . '"';
            }
            $linkItem->log[$logHeader] = $log;
        } else {
            $linkItem->log[$logHeader] = Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_VIDEO_NOT_FOUND");
            $linkItem->http_code       = self::BLC_YOUTUBE_NOT_FOUND;
        }
    }


    protected function checkPlaylist(LinkTable &$linkItem)
    {
        $api        = json_decode($linkItem->log['Response']);
        $logHeader  = "Youtube playlist";

        if (404 === $linkItem->http_code) {
            $linkItem->log[$logHeader] = Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_PLAYLIST_NOT_FOUND");
            $linkItem->http_code       = self::BLC_YOUTUBE_NOT_FOUND;
        } elseif (403 === $linkItem->http_code) {
            $linkItem->http_code       = self::BLC_YOUTUBE_API_ERROR;
            $linkItem->log[$logHeader] = $this->formatApiErrors($api);
        } elseif ((200 === $linkItem->http_code) && isset($api->items) && \is_array($api->items)) {
            $items = $api->items ?? [];
            //The playlist exists.
            if (empty($items)) {
                $linkItem->log[$logHeader] =  Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_PLAYLIST_EMPTY");

                $linkItem->http_code  = self::BLC_YOUTUBE_EMPTY;
            } else {
                $linkItem->log[$logHeader] = Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_PLAYLIST_OK");
                //Treat the playlist as broken if at least one video is inaccessible.
                foreach ($items as $video) {
                    if (($video->status->privacyStatus ?? '') == 'private') {
                        $linkItem->log[$logHeader] = Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_PLAYLIST_PRIVATE");
                        $linkItem->http_code       = self::BLC_YOUTUBE_PRIVATE;
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
            $linkItem->http_code       = self::BLC_YOUTUBE_API_ERROR;
            $linkItem->log[$logHeader] = $this->formatApiErrors($api);
        }
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

        $log     = Text::_("PLG_BLC_PROVIDER_YOUTUBE_API_ERROR");
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
