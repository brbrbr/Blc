<?php

/**
 * @version   __DEPLOY_VERSION
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

class VideoParser extends BlcTagParser implements BlcParserInterface
{
    protected string $parserName = 'video';

    /**
     * Property instance.
     *
     * @var  EmbedParser
     */

    protected string $attribute  = 'src';
    protected string $element    = 'video';
  
    /**
     *
     * @param   array<string>  $result
     *
     * @return  string
     */

    protected function getAnchor(array $result): string
    {
        return 'Video tag';
    }

}
