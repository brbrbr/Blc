<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Parser for All Video Share style links
 *
 *
 *
 */

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Traits\ShortCodeAttsTrait;

class SrcplayerParser extends BlcParser implements BlcParserInterface
{
    use ShortCodeAttsTrait;

    /**
     * Property instance.
     *
     * @var  Blc\Component\Blc\Administrator\Blc\BlcModule
     *
     */
    protected static $instance   = null;
    protected string $parserName = 'srcplayer';

    private const SRCPLAYERREGEX = '#{(?:youtube|avsplayer|vimeo)\s*([^}]+)}#i';
    // phpcs:disable Generic.Files.LineLength

    // phpcs:enable Generic.Files.LineLength


    public function replaceInSource(string $source, string $oldUrl, string $newUrl): string
    {
        $results = $this->extractfromSource($source);

        foreach ($results as $result) {
            $url = $result['url'];
            if ($url != $oldUrl) {
                continue;
            }
            $match      = $result['match'];
            $newMatch   = str_replace($oldUrl, $newUrl, $match);
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
        return $result['contents'] ?? "empty 'embed' tag";
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
}
