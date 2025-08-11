<?php

declare(strict_types=1);

/**
 * @version   24.44.6882
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 *
 */

namespace Blc\Component\Blc\Administrator\Interface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

interface BlcParserInterface
{
    public const BLC_PARSE_FALSE                 = 0; //I didn't parse this link
    public const BLC_PARSE_CONTINUE              = 1; //parsed and there might be other links
    public const BLC_PARSE_COMPLEET              = 2; //parsed and there won't be any other links.

    public const BLC_EMPTY_ALT                         =  'Empty-Alternative-Text';
    public const BLC_EMPTY_ANCHOR                      =  'Empty-Anchor-Text';
    public const BLC_EMPTY_ANY                         =  'Empty-Any-Text';
    public const ALT_TYPE                              = 'img-alt';
    public const ALT_TYPE_EDIT                         = 'img-alt-edit';
    public const ALT_TYPE_FILTER                       = 'img-alt-filter';
    /**
     *
     * This function replaces the oldUrl with newUrl
     *
     */
    public function replaceInSource(string $source, string $oldUrl, string $newUrl): string;

    /**
     * @param string $source (html) source
     * @return array of string
     *
     */
    public function extractfromSource(string $source): array;

    public function getName(): string;

    /**
     * @since 25.44.7545
     *
     */
    public function getCanSetAlt(): bool;
}
