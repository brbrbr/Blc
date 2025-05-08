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

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;

class AimyvideoParser extends BlcParser implements BlcParserInterface
{
    protected string $parserName = 'aimyvideo';

    private const AIMYVIDREGEX    = '#\{(YouTube|Vimeo)([^\}]*)\}\s*([^\{]+)\s*\{/\1\}#i';

    public function replaceInSource(string $source, string $oldUrl, string $newUrl): string
    {
        $results = $this->extractfromSource($source);

        foreach ($results as $result) {
            $url = $result['url']; //url is the complete url with https://..../

            if ($url != $oldUrl) {
                continue;
            }

            $vid     = $result['vid']; //this is either a complete url or just the video id
            $service = $result['service'];

            if (!preg_match('#^(?:https?:)?//#i', $vid)) {
                //convert the url to a vid
                $newUrl = $this->createVidFromUrl($service, $newUrl);
            }

            $match      = $result['match'];
            $newMatch   = str_replace($vid, $newUrl, $match);
            $source     = str_replace($match, $newMatch, $source);
        }
        return $source;
    }

    /**
     *
     * @param   array<string>  $result
     *
     * @return  string
     */

    protected function getAnchor(array $result): string
    {
        return $result['contents'] ?? 'empty \'embed\' tag';
    }


    /**
     *
     * @param   string  $text
     *
     * @return  array<array<string>>
     */


    public function extractfromSource($text): array
    {
        $parsed = [];
        preg_match_all(self::AIMYVIDREGEX, $text, $allmatch, PREG_SET_ORDER);
        while ($match = array_pop($allmatch)) {
            $vid     = strip_tags(trim($match[3]));
            $vid     = strtok($vid, '|');//allvideo parameters
            $service = strtolower(trim($match[1]));

            if (!preg_match('#^(?:https?:)?//#i', $vid)) {
                $url = $this->createUrlfromVid($service, $vid);
            } else {
                $url = $vid;
            }
            $parsed[] = [
                'url'     => $url,
                'anchor'  => $vid,
                'vid'     => $vid,
                'service' => $service,
                'match'   => $match[0],
            ];
        };
        return $parsed;
    }




    /**
     * Copyright (c) 2017-2023 Aimy Extensions, Netzum Sorglos Software GmbH
     * Copyright (c) 2014-2017 Aimy Extensions, Lingua-Systems Software GmbH
     *
     * https://www.aimy-extensions.com/
     *
     * License: GNU GPLv2, see LICENSE.txt within distribution and/or
     *            https://www.aimy-extensions.com/software-license.html
     *
     * @param   string  $srv
     * @param   string  $vid
     *
     *
     * @return  string
     */

    private function createUrlfromVid(string $srv, string $vid): string
    {
        if ($srv == 'youtube') {
            return  'https://www.youtube.com/watch?v=' . $vid;
        }
        if ($srv == 'vimeo') {
            return  'https://vimeo.com/' . $vid;
        }

        return $vid;
    }
    /**
     * @param   string  $srv
     * @param   string  $vid
     *
     *
     * @return  string
     */

    private function createVidFromUrl(string $srv, string $vid): string
    {
        if (empty($srv) or !\is_string($srv)) {
            return $vid;
        }
        $res = ['#/([a-z0-9_-]+)\?#i', '#/([a-z0-9_-]+)$#i'];
        if ($srv == 'youtube') {
            array_unshift($res, '#(?:&|&amp;|\?)vi?=([a-z0-9_-]+)#i');
        }
        foreach ($res as $re) {
            if (preg_match($re, $vid, $ms)) {
                return $ms[1];
            }
        }
        return $vid;
    }
}
