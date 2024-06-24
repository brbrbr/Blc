<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Based on Wordpress Broken Link Checker by WPMU DEV https://wpmudev.com/
 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Joomla\CMS\Factory;
use  Joomla\CMS\Plugin\PluginHelper;

/*
 * this is mostly a helper class to combine server parsers for one content pice
 */

class BlcParsers extends BlcModule
{
    private $parsers           = [];
    protected static $instance = null;

    protected function init()
    {
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        //TODO hoe de database netjes
        parent::init();
        $arguments = [
            'item' => $this,
        ];
        $event = new BlcEvent('onBlcParserRequest', $arguments);
        Factory::getApplication()->getDispatcher()->dispatch('onBlcParserRequest', $event);
        $this->logParsers();
    }

    protected function logParsers()
    {
        $eventName = 'onBlcParserRequest';
        $list      = [];
        foreach ($this->parsers as $class => $parsers) {
            $list[$class] = 0;
        }
        BlcTransientManager::getInstance()->set('lastListeners:' . $eventName, $list, true);
    }

    public function setMeta(array|object $meta = []): BlcParsers
    {
        $this->checkParsers();
        foreach ($this->parsers as $parser) {
            $parser->setMeta($meta);
        }
        return $this;
    }

    private function checkParsers()
    {
        if (empty($this->parsers)) {
            throw new \Exception("No parsers set");
        }
    }

    public function extractAndStoreLinks(array | string $data): array
    {
        $this->checkParsers();
        $links = [];
        foreach ($this->parsers as $parser) {
            $parserLinks = $parser->extractAndStoreLinks($data);
            $links       = array_merge_recursive($links, $parserLinks);
        }
        return $links;
    }

    //save  a bit of time
    public function replaceLinksParser(
        string $parser,
        string | array $data,
        string $oldUrl,
        string $newUrl
    ): array | string {
        $this->checkParsers();
        if (isset($this->parsers[$parser])) {
            $data = $this->parsers[$parser]->replaceLinks($data, $oldUrl, $newUrl);
        }
        //sillent or not?

        return $data;
    }

    public function replaceLinksAll(string | array $data, string $oldUrl, string $newUrl): array | string
    {
        $this->checkParsers();
        foreach ($this->parsers as $parser) {
            $data = $parser->replaceLinks($data, $oldUrl, $newUrl);
        }
        return $data;
    }

    public function removeParser(string $name)
    {
        unset($this->parsers[$name]);
    }


    public function registerParsers(array $parsers)
    {
        foreach ($parsers as $name => $parser) {
            $this->registerParser($name, $parser);
        }
    }
    public function registerParser(string $name, BlcParser $parser)
    {
        unset($this->parsers[$name]);

        $this->parsers[$name] = $parser;
    }
}
