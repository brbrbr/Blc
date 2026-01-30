<?php

declare(strict_types=1);

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

use Joomla\CMS\String\PunycodeHelper;
use Joomla\Uri\Uri;

/**
 * URL helper for handling Punycode conversions and URL encoding
 *
 * @since  1.0.0
 */
class UrlHelper extends PunycodeHelper
{
    public const PUNYCODE_PREFIX = 'xn--';

    private const VALID_URI_PARTS = ['path', 'fragment', 'query', 'queryarray'];

    /**
     * Convert hostname to Punycode format
     *
     * More efficient than PunycodeHelper::urlToPunycode since URI is already parsed
     *
     * @param   string  $host  The hostname to convert
     *
     * @return  string  The Punycode hostname
     *
     * @since   1.0.0
     */
    public static function hostToPunycode(string $host): string
    {
        if (empty($host)) {
            return $host;
        }

        $hostParts = explode('.', $host);
        $newHost   = [];

        foreach ($hostParts as $part) {
            $converted = self::toPunycode($part);

            // Converted strings should be lowercase
            // Non-punycode parts (ASCII) are lowercased
            if (!str_contains($converted, self::PUNYCODE_PREFIX)) {
                $converted = strtolower($converted);
            }

            $newHost[] = $converted;
        }

        return implode('.', $newHost);
    }

    /**
     * Convert hostname from Punycode to UTF-8 (all lowercase)
     *
     * Similar to PunycodeHelper::hostToUTF8 but ensures all output is lowercase
     *
     * @param   string  $host  The hostname to convert (caller should check for empty)
     *
     * @return  string  The UTF-8 lowercase hostname
     *
     * @since   1.0.0
     */
    protected static function hostToUTF8(string $host): string
    {
        if (empty($host)) {
            return $host;
        }

        $hostParts = explode('.', $host);
        $newHost   = [];

        foreach ($hostParts as $part) {
            // IDNA version 4 converts all ASCII to lowercase
            if (str_contains($part, self::PUNYCODE_PREFIX)) {
                $part = self::fromPunycode($part);
            }

            $newHost[] = mb_strtolower($part);
        }

        return implode('.', $newHost);
    }

    /**
     * Transform a Punycode URL to UTF-8 URL
     *
     * Output should be the same as PunycodeHelper::urlToUTF8
     * Uses Uri to parse and extract the hostname
     *
     * @param   mixed  $uri  The Punycode URL to transform
     *
     * @return  string  The UTF-8 URL
     *
     * @since   25.44.7314
     */
    public static function urlToUTF8($uri): string
    {
        // Can't change the $uri type as this overrides parent method
        if (empty($uri) || !\is_string($uri)) {
            return '';
        }

        $parsed = new Uri($uri);
        $host   = $parsed->getHost();

        if (empty($host)) {
            // No host means no conversion needed
            return $uri;
        }

        $newHost = self::hostToUTF8($host);

        if ($newHost === $host) {
            return $uri;
        }

        $parsed->setHost($newHost);

        return $parsed->toString();
    }

    /**
     * Fix URL encoding for specified URI parts
     *
     * Note: Uri::getQuery() always returns a URL-decoded query,
     * so encoding by default provides limited utility
     *
     * @param   Uri      $parsedItem  The parsed URI object (passed by reference)
     * @param   array    $parts       Parts to fix: 'path', 'fragment', 'query', 'queryarray'
     *
     * @return  bool  True if any fixes were applied
     *
     * @since   1.0.0
     */
    public static function urlencodeFixParts(Uri &$parsedItem, array $parts = ['path', 'fragment']): bool
    {
        $hasFix = false;

        // Validate parts
        $parts = array_intersect($parts, self::VALID_URI_PARTS);

        if (\in_array('path', $parts, true)) {
            $hasFix = self::fixUriPart($parsedItem, 'Path') || $hasFix;
        }

        if (\in_array('fragment', $parts, true)) {
            // Fragment fixes don't set $hasFix since version 24.44.6611
            self::fixUriPart($parsedItem, 'Fragment');
        }
        //no point in encoding the query since Uri will revert it.
        /* if (in_array('query', $parts, true)) {
        $hasFix = self::fixUriPart($parsedItem, 'Query') || $hasFix;
         }


        if (in_array('queryarray', $parts, true)) {
            $hasFix = self::fixQueryArray($parsedItem) || $hasFix;
        }
        */
        return $hasFix;
    }

    /**
     * Fix encoding for a specific URI part
     *
     * @param   Uri     $parsedItem  The parsed URI object
     * @param   string  $partName    The part name (capitalized, e.g., 'Path', 'Fragment')
     *
     * @return  bool  True if fix was applied
     */
    private static function fixUriPart(Uri &$parsedItem, string $partName): bool
    {
        $getter = 'get' . $partName;
        $setter = 'set' . $partName;

        $original = $parsedItem->$getter();

        if ($original === null || $original === '') {
            return false;
        }

        $fixed = self::urlencodeFix($original);



        if ($fixed !== $original) {
            $parsedItem->$setter($fixed);
            return true;
        }

        return false;
    }

    /**
     * Fix encoding for query array
     *
     * @param   Uri  $parsedItem  The parsed URI object
     *
     * @return  bool  True if fix was applied
     */
    /*
    private static function fixQueryArray(Uri &$parsedItem): bool
    {
        $original = $parsedItem->getQuery(true);

        if (empty($original)) {
            return false;
        }

        $fixed = self::urlencodeFix($original);

        if (array_diff_assoc($fixed, $original)) {
            $parsedItem->setQuery($fixed);
            return true;
        }

        return false;
    }
*/
    /**
     * Apply URL encoding fix to string or array
     *
     * Encodes characters that are not part of the safe URL character set
     *
     * @param   string|array  $part  The part to fix
     *
     * @return  string|array  The fixed part
     */
    private static function urlencodeFix(string|array $part): string|array
    {
        if (\is_array($part)) {
            return array_map(self::urlencodeFix(...), $part);
        }

        return preg_replace_callback(
            '|[^a-z0-9\+\-\/\\#:.,;=?!&%@()$\|*~_]|i',
            fn ($match) => rawurlencode($match[0]),
            $part
        );
    }
}
