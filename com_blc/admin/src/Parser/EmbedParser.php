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

use Joomla\String\StringHelper;

class EmbedParser extends BlcTagParser
{
    protected string $parserName = 'embed';

    /**
     * Property instance.
     *
     * @var  EmbedParser
     */

    protected static $instance   = null;
    protected string $attribute  = 'src';
    protected string $element    = 'iframe';
    private const AIMVIDREGEX    = '#\{(YouTube|Vimeo)([^\}]*)\}\s*([^\{]+)\s*\{/\1\}#i';
    private const SRCPLAYERREGEX = '#{(?:youtube|avsplayer|vimeo)\s*([^}]+)}#i';
    // phpcs:disable Generic.Files.LineLength
    private const SHORTCOEEREGEX = '#([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)#';
    // phpcs:enable Generic.Files.LineLength
    protected function replaceLink(string $text, string $oldUrl, string $newUrl): string
    {
        if ($this->componentConfig->get('iframe', 0)) {
            $text =  parent::replaceLink($text, $oldUrl, $newUrl);
        }
        if ($this->componentConfig->get('aimy', 0)) {
            $text =  $this->aimyVidReplace($text, $oldUrl, $newUrl);
        }
        if ($this->componentConfig->get('src', 0)) {
            $text =  $this->srcPlayerReplace($text, $oldUrl, $newUrl);
        }


        return $text;
    }

    private function srcPlayerReplace(string $text, string $oldUrl, string $newUrl): string
    {
        $results = $this->srcPlayerExtract($text);

        foreach ($results as $result) {
            $url = $result['url'];
            if ($url != $oldUrl) {
                continue;
            }
            $match    = $result['match'];
            $newMatch = str_replace($oldUrl, $newUrl, $match);
            $text     = str_replace($match, $newMatch, $text);
        }
        return $text;
    }


    private function aimyVidReplace(string $text, string $oldUrl, string $newUrl): string
    {
        $results = $this->aimyVidExtract($text);

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

            $match = $result['match'];
            $newMatch = str_replace($vid, $newUrl, $match);
            $text     = str_replace($match, $newMatch, $text);
        }
        return $text;
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

    private function srcPlayerExtract($text): array
    {
        $parsed = [];
        preg_match_all(self::SRCPLAYERREGEX, $text, $allmatch, PREG_SET_ORDER);
        while ($match = array_pop($allmatch)) {
            $text = $match[1];
            $args = $this->shortcodeParseAtts($text);

            if (isset($args['src'])) {
                $parsed[] = [
                    'url'    => $args['src'],
                    'anchor' => $args['title'] ?? $args['src'],
                    'vid'    => $args['src'],
                    'match'  => $match[0],
                ];
            }
        };
        return $parsed;
    }

    /**
     *
     * @param   string  $text
     *
     * @return  array<array<string>>
     */


    private function aimyVidExtract($text): array
    {
        $parsed = [];
        preg_match_all(self::AIMVIDREGEX, $text, $allmatch, PREG_SET_ORDER);

        while ($match = array_pop($allmatch)) {
            $vid     = strip_tags(trim($match[3]));
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
     *
     * @param   string  $text
     *
     * @return  array<array<string>>
     */


    public function extractLinks(string $text): array
    {
        $parsed = [];
        if ($this->componentConfig->get('iframe', 0)) {
            $results = parent::extractLinks($text);
            $parsed  = array_merge($parsed, $results);
        }
        if ($this->componentConfig->get('aimy', 0)) {
            $results = $this->aimyVidExtract($text);
            $parsed  = array_merge($parsed, $results);
        }
        if ($this->componentConfig->get('src', 0)) {
            $results = $this->srcPlayerExtract($text);
            $parsed  = array_merge($parsed, $results);
        }
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
    /**
     *
     * from wordpress
     *
     * @param   string  $text
     *
     * @return array<string>
     */


    private function shortcodeParseAtts($text): array
    {
        $atts    = [];

        $text    = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text);
        if (preg_match_all(self::SHORTCOEEREGEX, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $atts[StringHelper::strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $atts[StringHelper::strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $atts[StringHelper::strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && \strlen($m[7])) {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8]) && \strlen($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                } elseif (isset($m[9])) {
                    $atts[] = stripcslashes($m[9]);
                }
            }

            // Reject any unclosed HTML elements.
            foreach ($atts as &$value) {
                if (false !== strpos($value, '<')) {
                    if (1 !== preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value)) {
                        $value = '';
                    }
                }
            }
        } else {
            $atts = ['param' => ltrim($text)]; //incorrect?
        }

        return $atts;
    }
}
