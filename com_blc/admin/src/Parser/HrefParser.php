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
use Joomla\CMS\Language\Text;

class HrefParser extends BlcTagParser implements BlcParserInterface
{
    protected string $parserName = 'href';
    protected string $attribute  = 'href';
    protected string $element    = 'a';



    protected function getAnchor(array $result): string
    {
        return $result['contents'] ?? Text::_("COM_BLC_EMPTY_ANCHOR_A_TAG");
    }
}
