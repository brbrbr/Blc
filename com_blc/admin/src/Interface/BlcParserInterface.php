<?php

/**
 * @version   __DEPLOY_VERSION__
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 *
 */

namespace Blc\Component\Blc\Administrator\Interface;

use Blc\Component\Blc\Administrator\Parser\BlcParser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

interface BlcParserInterface
{
    public const BLC_PARSE_FALSE              = 0; // I didn't parse this link
    public const BLC_PARSE_CONTINUE              = 1; //parsed and there might be other links
    public const BLC_PARSE_COMPLEET             = 2; //parsed and there won't be any other links.
    public function setMeta(array|object $meta = []): BlcParser;
    public function extractAndStoreLinks(array|string $input): array;
    public function replaceLinks(array|string $input, string $oldUrl, string $newUrl): array | string;
}
