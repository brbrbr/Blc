<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * This is the controller between the plugins extracting fields from the container and the parsers gettign links from that field
 *
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Blc\Component\Blc\Administrator\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

/*
 * this is mostly a helper class to combine server parsers for one content pice
 */

class BlcExtractController extends BlcModule
{
    /**
     * Property instance.
     *
     * @var  Blc\Component\Blc\Administrator\Blc\BlcModule
     *
     */
    protected static $instance = null;

    private $parsers           = [];

    private $checkers;
    protected function init()
    {
        try {
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        } catch (\Error $e) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_ERROR_IMPORTPLUGINS_BLC') . ':' . $e->getMessage(), 'error');
        }
        //TODO hoe de database netjes
        parent::init();
        $arguments = [
            'item' => $this,
        ];
        $event = new BlcEvent('onBlcParserRequest', $arguments);
        Factory::getApplication()->getDispatcher()->dispatch('onBlcParserRequest', $event);
        $this->checkers = BlcCheckLink::getInstance();
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


    private function checkParsers()
    {
        if (empty($this->parsers)) {
            throw new \Exception("No parsers set");
        }
    }

    public function extractAndStoreLinks(array | string $data, array $meta, bool $store = true): array
    {

        $this->checkParsers();
        $links = [];

        $meta['field'] ?? 'generic';
        if (\is_string($data)) {
            $source               = $data;
            $data                 = [];
            $data[$meta['field']] = $source;
        }

        foreach ($data as $field => $source) {
            foreach ($this->parsers as $name => $parser) {
                $parserLinks = $parser->extractfromSource($source);
                if ($parserLinks) {
                    $meta['parser'] = $name;
                    $meta['field']  = $field;
                    if ($store) {
                        $this->storeLinks($parserLinks, $meta);
                    }

                    $links   = array_merge_recursive($links, [$field => $parserLinks]);
                }
            }
        }

        return $links;
    }

    //save  a bit of time
    public function replaceLinkInSourceByParser(
        string $parser,
        string | array $data,
        string $oldUrl,
        string $newUrl
    ): array | string {
        $this->checkParsers();
        if (isset($this->parsers[$parser])) {
            if (\is_string($data)) {
                return $this->parsers[$parser]->replaceInSource($data, $oldUrl, $newUrl);
            }

            if (\is_array($data)) {
                $replacedSources = [];
                foreach ($data as $field => $text) {
                    $replacedSources[$field] = $this->parsers[$parser]->replaceInSource($text, $oldUrl, $newUrl);
                }
                return $replacedSources;
            }

            return $data;
        }
        //sillent or not?

        return $data;
    }

    public function replaceLinkInSourceInAllParsers(string | array $data, string $oldUrl, string $newUrl): array | string
    {
        foreach ($this->parsers as $name => $parser) {
            $data = $this->replaceLinkInSourceByParser($name, $data, $oldUrl, $newUrl);
        }
        return $data;
    }

    public function unRegisterParser(string $name)
    {
        unset($this->parsers[$name]);
    }

    public function registerParsers(array $parsers)
    {
        foreach ($parsers as $name => $parser) {
            $this->registerParser($name, $parser);
        }
    }
    public function registerParser(?string $name, BlcParser $parser)
    {
        if ( ! $parser instanceof BlcParserInterface) {
            throw new \Exception('Parser must implement %s',BlcParserInterface::class);
        }
        $name ??= $parser->getName();
        if (isset($this->parsers[$name])) {
            throw new \Exception('Parser with name %s already registered, unregister it first');
        }
        $this->parsers[$name] = $parser;
    }
    /**
     *
     * This function does some sanity checks and then stores the link into the database
     * could/should be in LinkModel
     *
     *
     */



    final protected function storeLink(array|string $link): int
    {

        $url = trim($link['url'] ?? $link);

        $pk = [
            'url' => $url,
        ];

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $linkItem = new Table\LinkTable($db);
        $linkItem->load($pk);
        $linkItem->bind($pk);
        $linkItem->initInternal();

        $storeOrSkip = $this->parseUrl($linkItem);
     

        if ($storeOrSkip === false) {
            if ($linkItem->id) {
                $linkItem->delete();
            }
            return 0;
        }

        if (!$linkItem->id ) {
            $msg = Text::sprintf("COM_BLC_MSG_NEW_LINK", $url);

            try {
                //if there are multiple instances running their might be a collesion of
                //identical links insterted ad the same time
                //ignore these. Will be corrected at the next run.

                //->save does not Throw an
                $linkItem->save();
            } catch (\Exception) {
                return 0;
            }
        } else {
            $msg = Text::sprintf("COM_BLC_MSG_EXISTING_LINK", $url);
        }

        BlcMessages::getInstance()->enqueueMessage($msg, 'info');
        return  $linkItem->id;
    }


    /**
     *
     * A single list of links
     * a link is either a plain link or a [url,anchor] array
     *
     */
    public function storeLinks(array | string $links, array $meta): array
    {

        if (\is_string($links)) {
            $links = [$links];
        }
  

        foreach ($links as $link) {
            try {
                $linkItemId = $this->storeLink($link);
            
                if ($linkItemId) {
                    $anchor = $this->parseAnchor($link['anchor'] ?? $link['url'] ?? $link);
                   
                    $this->saveInstance($linkItemId, $anchor, $meta);
                }
            } catch (\Exception $e) {
                //ignore it. most likely this error occurs when there are multiple jobs running
                //will correct itself on a future run.
                echo 'Caught exception: ',  $e->getMessage(), "\n";
            }
        }
        return $links;
    }

    protected function saveInstance(int $linkId, string $linkText, array $meta): int
    {

        $synchId = $meta['synchId'] ?? null;
        if (empty($synchId)) {
            throw new \RuntimeException('saveInstance should be called with a synchId in the meta options');
        }

        $field = $meta['field'] ?? null;;
        if (empty($field)) {
            throw new \RuntimeException('saveInstance should be called with a field in the meta options');
        }

        $parserName = $meta['parser'] ?? null;;
        if (empty($parserName)) {
            throw new \RuntimeException('saveInstance should be called with a parser in the meta options');
        }


        $db            = Factory::getContainer()->get(DatabaseInterface::class);
        $instanceTable = new Table\InstanceTable($db);

        $pk = [
            'link_id'   => $linkId,
            'synch_id'  => $synchId,
            'field'     => $field,
            'parser'    => $parserName,
            'link_text' => $linkText,
        ];

        try {
            $instanceTable->save($pk);
        } catch (\RuntimeException $e) {
            //creation failed most likely due to concurrent jobs
            //ignore next job will retry
        }


        return $instanceTable->id ?? 0;
    }

    protected function parseAnchor($anchor)
    {
        return $anchor;
    }


    //clean up of url and  lookup joomla url
    /*
       @returns false if the link should be ignore. Or a string of it's an internal URL.
    */
    protected function parseUrl(Table\LinkTable $linkItem): bool
    {
        $url = $linkItem->url;

        if (!$url) {
            return false;
        }

        if (strpos($url, '#') === 0) {
            return false;
        }
        if ($url == '/') {
            //silently ignore links
            //do not ignore /index.php since that should probably be redirected.
            return false;
        }

        //this ensures we have a valid checker
        $canCheck = $this->checkers->canCheckLink($linkItem);
        if (HTTPCODES::BLC_CHECK_FALSE === $canCheck) {
            print Text::sprintf('COM_BLC_MSG_CHECK_FALSE', (string)$linkItem) . "\n";
            return false;
        }
        
        if (HTTPCODES::BLC_CHECK_IGNORE === $canCheck) {
            print Text::sprintf('COM_BLC_MSG_CHECK_IGNORE', (string)$linkItem) . "\n";
            return false;
        }
        return true;
    }
}
