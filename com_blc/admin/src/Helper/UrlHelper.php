<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\String\PunycodeHelper as PunycodeHelper;
use Joomla\Uri\Uri;

/**
 * Blc helper.
 *
 * @since  1.0.0
 */
class UrlHelper extends PunycodeHelper
{
    public const punycodePrefix = 'xn--';

    public static function hostToPunnycode(string $host) : string
    {
        //this is a bit shorter then PunycodeHelper::urlToPunycode since we already parsed the uri
        if (!$host) {
            return $host;
        }
        $hostExploded = explode('.', $host);
        $newHost      =     [];

        foreach ($hostExploded as $part) {
            $part = self::toPunycode($part);
            //converted strings should be lower case. Algo26\IdnaConvert\ version 4 does this for asciii as wel
            if (!str_contains($part, self::punycodePrefix)) {
                //should be ascii here
                $part = strtolower($part);
            }

            $newHost[] = $part;
        }

        return implode('.', $newHost);
    }

    /**
     * output like PunnnycodeHelper::hostToUTF8 just all to lowercase
     * @param string $host - caller should check empty host
     * @return string
     *
     */

    protected static function hostToUTF8(string $host): string
    {
    
        $hostExploded = explode('.', $host);
        $newHost      =     [];

        foreach ($hostExploded as $part) {
            //idna version 4 will convert all ASCII to lowercase
            if (str_contains($part, self::punycodePrefix)) {
                $part =  self::fromPunycode($part);
            }

            $newHost[] = mb_strtolower($part);
        }
        return implode('.', $newHost);
    }

    /**
     * Transforms a Punycode URL to a UTF-8 URL
     *    * @since __DEPLOY_VERSION__
     *
     * output should be the same as PunycodeHelper::hostToUTF8
     * use Uri to parse and extract the
     *
     * @param   string  $uri  The Punycode URL to transform
     *
     * @return  string  The UTF-8 URL
     *
     * @since   3.1.2
     */
    public static function urlToUTF8( $uri) : string
    {
        //can't change the $uri type as it's an override function
        if (empty($uri) || !\is_string($uri)) {
            return '';
        }

        $parsed = new Uri($uri);
        $host   = $parsed->getHost();

        if (empty($host)) {
            // If there is no host we do not need to convert it.
            return $uri;
        }

        $newHost         = self::hostToUTF8($host);

        if ($newHost == $host) {
            return $uri;
        }
        $parsed->setHost($newHost);


        return $parsed->toString();
    }
    /**
     * 
     * Uri::getQuery always returns a urldecode query. So little usefull to encode is by default.
     */

    public static function urlencodeFixParts(Uri &$parsedItem, $parts = ['path', 'fragment']): bool
    {

        $hasFix   = false;
        if (\in_array('path', $parts)) {
            $origPart = $parsedItem->getPath();
            if ($origPart !== null) {
                $fixPart = self::urlencodeFix($origPart);
                if ($fixPart !== $origPart) {
                    $hasFix = true;
                    $parsedItem->setPath($fixPart);
                }
            }
        }
        if (\in_array('fragment', $parts)) {
            $origPart = $parsedItem->getFragment();
            if ($origPart !== null) {
                $fixPart = self::urlencodeFix($origPart);
                if ($fixPart !== $origPart) {
                    // $hasFix = true; since 24.44.6611
                    $parsedItem->setFragment($fixPart);
                }
            }
        }
        if (\in_array('query', $parts)) {
            $origPart = $parsedItem->getQuery();
            if ($origPart !== null) {
                $fixPart = self::urlencodeFix($origPart);
                if ($fixPart !== $origPart) {
                    $hasFix = true;
                    $parsedItem->setQuery($fixPart);
                }
            }
        }
        /**
         * this preserves the urlencode values. 
         */
        if (\in_array('queryarray', $parts)) {
            $origPart = $parsedItem->getQuery(true);
            if ($origPart !== null) {
                $fixPart = self::urlencodeFix($origPart);
                if (array_diff($fixPart, $origPart)) {
                    $hasFix = true;
                    $parsedItem->setQuery($fixPart);
                }
            }
        }

        return $hasFix;
    }

    private static function urlencodeFix(string|array $part): string|array
    {
        if (\is_array($part)) {
            // @phpstan-ignore-next-line
            return array_map([self::class,'urlencodeFix'], $part);
        }
        return preg_replace_callback(
            '|[^a-z0-9\+\-\/\\#:.,;=?!&%@()$\|*~_]|i',
            fn ($str) => rawurlencode($str[0]),
            $part
        );
    }
}
