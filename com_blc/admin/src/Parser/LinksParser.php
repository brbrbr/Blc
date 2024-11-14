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

/* this parser is slighlty different from the tag-parsers
the import are 'ready' to use links.
Either an
- array of strings each string the URL
- or a array link ['url'=>$link,'anchor'=>$anchor]
                */

class LinksParser extends BlcParser implements BlcParserInterface
{


    protected string $parserName = 'links';

    public function extractfromSource(string $source): array
    {
        $source = filter_var($source, FILTER_SANITIZE_URL);

        // Validate url
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return [$source];
        }

        return [];
    }

    public function replaceInSource(string $source, string $oldUrl, string $newUrl): string
    {
        if ($source == $oldUrl) {
            $source = $newUrl;
        }
        return $source;
    }
}
