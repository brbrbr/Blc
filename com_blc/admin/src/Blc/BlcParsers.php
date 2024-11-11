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

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

/*
 * this is mostly a helper class to combine server parsers for one content pice
 */

class BlcParsers extends BlcModule
{
    private $parsers           = [];
    protected static $instance = null;

    protected function init()
    {
        parent::init();
        Factory::getApplication()->enqueueMessage('This code is outdated, please update all extensions', 'error');
    }

    protected function logParsers() {}

    public function setMeta(array|object $meta = []): BlcParsers
    {

        return $this;
    }

    public function extractAndStoreLinks(array | string $data): array
    {
        return [];
    }

    //save  a bit of time
    public function replaceLinkInSourceByParser(
        string $parser,
        string | array $data,
        string $oldUrl,
        string $newUrl
    ): array | string {
        return [];
    }

    public function replaceLinkInSourceInAllParsers(string | array $data, string $oldUrl, string $newUrl): array | string
    {
        return [];
    }

    public function removeParser(string $name) {}

    public function registerParsers(array $parsers) {}
    public function registerParser(string $name, BlcParser $parser) {}
}
