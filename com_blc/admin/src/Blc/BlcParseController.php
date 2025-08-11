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


use Blc\Component\Blc\Administrator\Event\BlcParserRequestEvent;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Blc\Component\Blc\Administrator\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

/*
 * this class:
 * - parser sources to find emebed links
 * - stores links into the database
 */

class BlcParseController extends BlcModule
{
    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;


    private DatabaseInterface $db;
    private $parsers           = [];
    private $eventName         = 'onBlcParserRequest';
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


        //init has a reset function as well.
        $this->clearParsers();
    }
    public function clearParsers()
    {
        $this->parsers = [];
    }



    protected function logParsers()
    {
        $list = array_fill_keys(array_keys($this->parsers), 0);
        BlcTransientManager::getInstance()->set('lastListeners:' . $this->eventName, $list, true);
    }


    private function checkDb()
    {
        if (empty($this->db)) {
            $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        }
    }

    private function checkCheckers()
    {
        if (empty($this->checkers)) {
            $this->checkers = BlcCheckLink::getInstance();
        }
    }

    private function checkParsers()
    {
        if (empty($this->parsers)) {
            $arguments = [
                'subject' => $this,
            ];
            $event = new BlcParserRequestEvent($this->eventName, $arguments);
            Factory::getApplication()->getDispatcher()->dispatch($this->eventName, $event);  //@phpstan-ignore method.deprecatedInterface

            $this->logParsers();
        }

        if (empty($this->parsers)) {
            throw new \Exception("No parsers set");
        }
    }

    public function extractAndStoreLinks(array | string $data, array $meta, bool $store = true): array
    {

        $this->checkParsers();
        $links = [];

        $meta['field'] ??= 'generic';
        if (\is_string($data)) {
            $source               = $data;
            $data                 = [];
            $data[$meta['field']] = $source;
        }

        foreach ($data as $field => $source) {
            if (empty($source)) {
                continue;
            }
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
        BlcParser|string $parser,
        string | array $data,
        string $oldUrl,
        string $newUrl
    ): array | string {
        $this->checkParsers();
        if (\is_string($parser)) {
            $parserString = $parser;
            $parser       = $this->getParser($parser);
            if (!$parser) {
                throw new \RuntimeException(__FUNCTION__ . " should be called with a BLcParser instance or valid Parser name.({$parserString})");
            }
        }

        if (\is_string($data)) {
            return $parser->replaceInSource($data, $oldUrl, $newUrl);
        }

        if (\is_array($data)) {
            $replacedSources = [];
            foreach ($data as $field => $text) {
                $replacedSources[$field] = $parser->replaceInSource($text, $oldUrl, $newUrl);
            }
            return $replacedSources;
        }

        return $data;

        //sillent or not?

        return $data;
    }



    //save  a bit of time
    public function setAltInSourceByParser(
        BlcParser|string $parser,
        string | array $data,
        string $currentUrl,
        string $newAlt,
    ): array | string {
        $this->checkParsers();
        if (\is_string($parser)) {
            $parserString = $parser;
            $parser       = $this->getParser($parser);
            if (!$parser) {
                throw new \RuntimeException(__FUNCTION__ . " should be called with a BLcParser instance or valid Parser name.({$parserString})");
            }
        }


        if (!$parser->getCanSetAlt()) {
            //if the parser does not support replacing alt, return the data as is
            return $data;
        }



        //if the parser does support replacing alt, replace it
        if (\is_string($data)) {
            return $parser->setAltInSource($data, $currentUrl, $newAlt);
        }

        if (\is_array($data)) {
            $replacedSources = [];
            foreach ($data as $field => $text) {
                $replacedSources[$field] = $parser->setAltInSource($text, $currentUrl, $newAlt);
            }
            return $replacedSources;
        }


        //sillent or not?

        return $data;
    }

    public function getParser(string $name): ?BlcParserInterface
    {
        $this->checkParsers();
        $name = strtolower($name);
        return $this->parsers[$name] ?? null;
    }

    public function getParsers(): array
    {
        $this->checkParsers();
        return $this->parsers ?? [];
    }

    public function replaceLinkInSourceInAllParsers(string | array $data, string $oldUrl, string $newUrl): array | string
    {
        $this->checkParsers();
        foreach ($this->parsers as $parser) {
            $data = $this->replaceLinkInSourceByParser($parser, $data, $oldUrl, $newUrl);
        }
        return $data;
    }

    public function unRegisterParser(string $name)
    {
        $name = strtolower($name);
        unset($this->parsers[$name]);
    }

    public function registerParsers(array $parsers)
    {
        foreach ($parsers as $parser) {
            $this->registerParser($parser);
        }
    }

    public function registerParser(BlcParserInterface $parser)
    {

        $name = $parser->getName();
        if (isset($this->parsers[$name])) {
            throw new \Exception(\sprintf('Parser with name %s already registered, unregister it first', $name));
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
        //do not store empty links
        if (empty($url)) {
            return 0;
        }

        $pk = [
            'url' => $url,
        ];


        $linkItem = new Table\LinkTable($this->db);
        $linkItem->load($pk);
        $linkItem->bind($pk);


        $storeOrSkip = $this->parseLink($linkItem);


        if ($storeOrSkip === false) {
            if ($linkItem->id) {
                $linkItem->delete();
            }
            return 0;
        }

        if (!$linkItem->id) {
            $msg = Text::sprintf('COM_BLC_MSG_NEW_LINK', $url);

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
            $msg = Text::sprintf('COM_BLC_MSG_EXISTING_LINK', $url);
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
        $this->checkDb();
        $this->checkCheckers();


        if (\is_string($links)) {
            $links = [$links];
        }

        foreach ($links as $link) {
            $linkItemId = $this->storeLink($link);
            if ($linkItemId) {
                $linkMeta = $meta;
                $linkMeta['field'] .= isset($link['suffix']) ? '.' . $link['suffix'] : '';


                $anchor = $this->parseAnchor($link['anchor'] ?? $link['url'] ?? $link);
                $this->saveInstance($linkItemId, $anchor, $linkMeta);
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

        $field = $meta['field'] ?? null;
        if (empty($field)) {
            throw new \RuntimeException('saveInstance should be called with a field in the meta options');
        }

        $parserName = $meta['parser'] ?? null;
        if (empty($parserName)) {
            throw new \RuntimeException('saveInstance should be called with a parser in the meta options');
        }



        $instanceTable = new Table\InstanceTable($this->db);

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
            throw new \RuntimeException('Unable to save instance:'  . $e->getMessage());
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
    protected function parseLink(Table\LinkTable $linkItem): bool
    {
        $url = $linkItem->url;

        if (!$url) {
            return false;
        }

        if (str_starts_with($url, '#')) {
            return false;
        }
        if ($url == '/') {
            //silently ignore links
            //do not ignore /index.php since that should probably be redirected.
            return false;
        }
        //pseudo recheck
        $keep_code           = $linkItem->http_code;
        $linkItem->http_code = HTTPCODES::BLC_CHECK_UNSET;
        //this ensures we have a valid checker
        $canCheck            = $this->checkers->canCheckLink($linkItem);
        $linkItem->http_code = $keep_code;
        if (HTTPCODES::BLC_CHECK_FALSE === $canCheck) {
            BlcMessages::getInstance()->enqueueMessage(Text::sprintf('COM_BLC_MSG_CHECK_FALSE', (string)$linkItem), 'info');
            return false;
        }

        if (HTTPCODES::BLC_CHECK_IGNORE === $canCheck) {
            BlcMessages::getInstance()->enqueueMessage(Text::sprintf('COM_BLC_MSG_CHECK_IGNORE', (string)$linkItem), 'info');
            return false;
        }
        return true;
    }
}
