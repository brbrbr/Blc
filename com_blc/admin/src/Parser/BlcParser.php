<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * base class for parsers

 *
 */

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Joomla\CMS\Language\Text;

abstract class BlcParser implements BlcParserInterface
{
    protected bool $canSetAlt=false;
    ## Pseudo abstract variables
    protected string $parserName = ''; //this should become the classname

    final private function __construct()
    {
    }
    //parsers might have a memory, so no singletons.
    //they ain't that big
    final public static function getInstance()
    {
        return new static();
    }

    public function getName(): string
    {
        return $this->parserName;
    }

     public function getcanSetAlt(): bool
    {
        return $this->canSetAlt;
    }

    /**
     * @since 24.44.6882
     *
     */
    public function extractfromSources(array $input): array
    {
        $links = [];
        foreach ($input as $field => $text) {
            $links[$field] = $this->extractfromSource($text);
        }
        return array_filter($links);
    }

    protected function init()
    {
        if (empty($this->parserName)) {
            throw new \Exception(Text::sprintf("COM_BLC_ERROR_NOT_MISSING_VALUE", __CLASS__, 'parserName'));
        }
    }
}
