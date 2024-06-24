<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Based on Wordpress Broken Link Checker by WPMU DEV https://wpmudev.com/
 *
 */

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/* this parser is slighlty different from the tag-parsers
the import are 'ready' to use links.
Either an
- array of strings each string the URL
- or a array link ['url'=>$link,'anchor'=>$anchor]
                */

class LinksParser extends BlcParser
{
    protected string $parserName = 'links';
    protected static $instance   = null;


    // not mutch extracting. just store it

    public function extractAndStoreLinks(array | string $links): array
    {
        return parent::storeLinks($links);
    }

    public function replaceLink(string $input, string $oldUrl, string $newUrl): string
    {
        if ($input == $oldUrl) {
            $input = $newUrl;
        }
        return $input;
    }

    public function replaceLinks(string|array $input, string $oldUrl, string $newUrl): array
    {
        if (\is_string($input)) {
            return $this->replaceLink($input, $oldUrl, $newUrl);
        }
        foreach ($input as &$link) {
            $link = $this->replaceLink($link, $oldUrl, $newUrl);
        }
        return  $input;
    }
}
