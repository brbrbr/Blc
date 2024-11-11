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
@trigger_error(
    \sprintf(
        'This class (%s) is deprecated use the specific parser directly',
        'Blc\Component\Blc\Administrator\Parser\EmbedParser'
    ),
    E_USER_DEPRECATED
);


// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;


class EmbedParser extends BlcParser implements BlcParserInterface
{
    protected string $parserName = 'embed';


    // phpcs:enable Generic.Files.LineLength
    public function replaceInSource(string $source, string $oldUrl, string $newUrl): string
    {

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








    public function extractfromSource(string $text): array
    {
        $parsed = [];

        return $parsed;
    }
}
