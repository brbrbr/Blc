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
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;

class ImgParser extends BlcTagParser implements BlcParserInterface
{
    /**
     * Property instance.
     *
     * @var  Blc\Component\Blc\Administrator\Blc\BlcModule
     *
     */

    protected string $parserName = 'img';
    protected string $attribute  = 'src';
    protected string $element    = 'img';

    protected function getAnchor(array $result): string
    {
        return  ($result['attributes']['alt'] ?? HTTPCODES::BLC_EMPTY_LINK_TEXT_TXT) ?: HTTPCODES::BLC_EMPTY_LINK_TEXT_TXT;
    }
}
