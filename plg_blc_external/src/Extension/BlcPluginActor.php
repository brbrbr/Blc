<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\External\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES; //using constants but not implementing
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;

    private const HELPLINK  = 'https://brokenlinkchecker.dev/extensions/plg-blc-external';
    protected $primary      =  'url';
    protected $context      = 'com_blc.external';
    protected $extractCount = 0;
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->setRecheck();
    }

    #[\Override]
    public function onBlcContainerChanged(BlcEvent $event): void
    {
        //external links won't have a changed flag.
        //Interface requires this function
    }

    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {
        parent::onBlcExtensionAfterSave($event);
        $table = $event->getItem();
        $type  = $table->get('type');
        if ($type != 'plugin') {
            return;
        }

        $folder = $table->get('folder');
        if ($folder != $this->_type) {
            return;
        }

        $element = $table->get('element');
        if ($element != $this->_name) {
            return;
        }

        $params = new Registry($table->get('params')); // the new config is already saved
        $urls   = (array) $params->get('urls', []);

        $seen = [];
        foreach ($urls as $urlrow) {
            if (!empty($urlrow->ping)) {
                if (empty($urlrow->name)) {
                    Factory::getApplication()->enqueueMessage("To work correctly URL with a ping destination must have an name", 'warning');
                } else {
                    if (\in_array($urlrow->name, $seen)) {
                        Factory::getApplication()->enqueueMessage("To work correctly URL with a ping destination must have an unique name", 'warning');
                    } else {
                        $seen[] = $urlrow->name;
                    }
                }
            }
        }
    }

    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {
        $urls = (array) $this->params->get('urls', []);
        $ping = false;
        $name = false;
        foreach ($urls as $urlrow) {
            if ($urlrow->name == $instance->field) {
                $ping = $urlrow->ping;
                $name = $urlrow->name;
                break;
            }
        }

        if ($ping) {
            $data = [
                'oldurl' => $link->url,
                'newurl' => $newUrl,
                'name'   => $name,

            ];

            try {
                $response = HttpFactory::getHttp()->post($ping, $data);
            } catch (\RuntimeException $exception) {
                Factory::getApplication()->enqueueMessage("BLC External Plugin Ping Failed", 'error');
                return;
            }

            $body = "Response:<br>{$response->code}<br>" . nl2br(htmlspecialchars($response->body)) . "<br>";
            if ($response->code == 200) {
                $link->working = HTTPCODES::BLC_WORKING_HIDDEN;
                $link->save();
                Factory::getApplication()->enqueueMessage("External ping - link hidden.<br>{$body}", 'success');
            } else {
                Factory::getApplication()->enqueueMessage("External ping - Failed.<br>{$body}", 'error');
            }
        } else {
            Factory::getApplication()->enqueueMessage("External link can not be replaced directy. However your can ping a remote site", 'warning');
        }
    }

    public function getTitle($data): string
    {
        return $data->field;
    }

    public function getEditLink($data): string
    {
        return '';
    }

    public function getViewLink($data): string
    {
        return '';
    }

    protected function getUrl(string $url): bool|array
    {
        $this->extractCount++;  // extra penalty for fetch
        //just used to send the correct data type to the checker.
        //we don't use the probably old data
        //the external checker has it's own expired data
        $linkItem      = $this->getLink($url);
        $checker       = $this->getChecker();
        $linkItem->log = [];
        $parsedItem    = new Uri($url);
        BlcCheckLink::urlencodeFixParts($parsedItem);
        $linkItem->_toCheck = $parsedItem->toString();
        $config             = clone $this->componentConfig;
        $config->set('range', false);
        $config->set('head', false);
        $config->set('follow', true);
        $config->set('response', HTTPCODES::CHECKER_LOG_RESPONSE_TEXT);
        $config->set('name', 'Get from External');
        $result         = $checker->checkLink($linkItem,config:$config);
        $result['body'] = $linkItem->log['Response'];

        return $result;
    }


    final protected function getLink(string $url): LinkTable
    {
        $pk    = [
            'url' => $url,
        ];

        $linkItem =  new LinkTable($this->getDatabase());
        $linkItem->load($pk);
        $linkItem->bind($pk);
        $linkItem->initInternal();
        return $linkItem;
    }

    protected function parseJson($content, $name, $synchId)
    {

        //str_getcsv does not work wel with multiline
        if (!$content) {
            return;
        }

        $links = [];
        $rows  = json_decode($content);
        if (!$rows) {
            return;
        }
        $links = [];
        foreach ($rows as $key => $row) {
            $url = $row->url ?? $row->link ?? $row->u ?? $key;
            if ($url && strpos($url, 'http') === 0) {
                $link = [
                    'url'    => $url,
                    'anchor' => $row->name ?? $row->title ?? $row->plaats ?? $row->l ?? $key,
                ];
                $links[] = $link;
            }
        }
        $this->processLinks($links, $name, $synchId);
    }

    protected function parseCsv($content, $name, $synchId)
    {

        //str_getcsv does not work wel with multiline
        if (!$content) {
            return;
        }

        $cache    = Factory::getApplication()->get('cache_path', JPATH_CACHE);
        $fileName = uniqid(true);
        $file     = $cache . '/' . $fileName;
        File::write($file, $content);
        $handle = fopen($file, 'r');

        $header = fgets($handle);

        if (!$header) {
            return;
        }
        $count     = 0;
        $delimiter = ',';
        foreach ([',', ';', '|', "\t"] as $v) {
            $c = substr_count($header, $v);
            if ($c > $count) {
                $delimiter = $v;
                $count     = $c;
            }
        }

        fseek($handle, 0);
        //Joomla has a polyfill for mb_strtolower
        $header = fgetcsv($handle, separator: $delimiter);

        if (!$header) {
            return;
        }
        $header  = array_map('mb_strtolower', $header);
        $linkCol = 0;

        foreach (['url', 'link','u'] as $urlHeader) { //todo make this an option
            $maybe = array_search($urlHeader, $header);
            if ($maybe !== false) {
                $linkCol = $maybe;
                break;
            }
        }
        $nameCol = 1;
        foreach (['name', 'title', 'plaats','l'] as $urlHeader) {  //todo make this an option
            $maybe = array_search($urlHeader, $header);
            if ($maybe !== false) {
                $nameCol = $maybe;
                break;
            }
        }
        $links = [];
        while ($row =   fgetcsv($handle, separator: $delimiter)) {
            $url = trim($row[$linkCol] ?? '');
            if ($url && strpos($url, 'http') === 0) {
                $link = [
                    'url'    => $url,
                    'anchor' => $row[$nameCol] ?? "CSV $name:  $url",
                ];
                $links[] = $link;
            }
        }
        $this->processLinks($links, $name, $synchId);
        fclose($handle);
        File::delete($file);
    }
    protected function parseSiteMapHtml($map, $name, $synchId)
    {
        $this->processText($map, $name, $synchId);
    }
    /* sitemap point to different sitemap or urls so no need to redo a parseExernal
    TODO images
    */
    protected function parseSiteMapXml($map, $name, $synchId)
    {
        $xml = simplexml_load_string($map);
        if ($xml) {
            foreach ($xml->sitemap as $url_list) {
                $url = $url_list->loc;
                $this->parseExernal($url, $name);
            }
            $links = [];
            foreach ($xml->url as $url_list) {
                $url = $url_list->loc ?? '';
                if ($url) {
                    $link = [
                        'url'    => $url,
                        'anchor' => 'Sitemap: ' . $url,
                    ];
                    $links[] = $link;
                    foreach ($url_list->children('image', true) as $child) {
                        if ($child->getName() != 'image') {
                            continue;
                        }
                        $link = [
                            'url'    => $child->loc,
                            'anchor' => 'Sitemap: ' . $url,
                        ];
                        $links[] = $link;
                    }
                }
            }

            $this->processLinks($links, $name, $synchId);
        }
    }
    //true == continue
    //false == stop
    protected function parseExernal(string $url, string $name = '', string|null $mime = ''): void
    {
        $id            = crc32($this->_name . $url);
        $synchTable    = $this->getItemSynch($id);
        $dateLastSynch = new Date($synchTable->last_synch ?? '1970-01-01 00:00:00');

        if ($dateLastSynch > $this->reCheckDate) {
            return;
        }
        print "Starting Extraction: $id  {$this->_name} - {$url}\n";
        $this->extractCount++;
        $this->purgeInstances($synchTable->id);
        $this->processLinks([$url], $name, $synchTable->id);
        $result = json_decode($synchTable->data ?? '[]', true);

        if (!$result || !isset($result['body'])) {
            $result = $this->getUrl($url);
            $synchTable->save([
                'data' => $result,
            ]);
        }

        if (!$result || !isset($result['body'])) {
            //some kind of error, set synched
            //so it shows up in the link checker
            $synchTable->setSynched([
                'data' => $result,
            ]);
            return;
        }
        if ($mime === '' || $mime === null) {
            $mime = $result['mime'] ?? 'broken';
        }

        switch ($mime) {
            case 'text/xml': //sitemap
                $this->parseSiteMapXml($result['body'], $name, $synchTable->id);
                break;
            case 'sitemap/html': //sitemap
                $this->parseSiteMapHtml($result['body'], $name, $synchTable->id);
                break;
            case 'application/json': //sitemap
                $this->parseJson($result['body'], $name, $synchTable->id);
                break;
            case 'text/csv': //csv
                $this->parseCsv($result['body'], $name, $synchTable->id);
                break;
            case 'text/html':
                break;
            default:
                //done link checker takes over
                break;
        }
        //content is reload on each synch, so not usefull to keep the possible large data in storage
        unset($result['body']);

        $synchTable->setSynched([
            'data' => $result,
        ]);
    }


    /**
     * this will clean up all synch data for deleted and expired content
     * @param bool $onlyOrhpans delete only orphans (true) or purge all (false)
     *
     */

    protected function cleanupSynch(bool $onlyOrhpans = true): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->bind(':containerPlugin', $this->_name, ParameterType::STRING)
            ->where($db->quoteName('last_synch') . ' < ' . $db->quote($this->reCheckDate->toSql()));

        if ($onlyOrhpans) {
            // there are no parent containers
        }

        $db->setQuery($query)->execute();
    }


    protected function getUnsynchedCount(): int
    {
        $urls = (array) $this->params->get('urls', []);
        return \count($urls);
    }

    public function onBlcExtract(BlcExtractEvent $event): void
    {

        $this->parseLimit = $event->getMax();
        $this->cleanupSynch();
        $urls = (array) $this->params->get('urls', []);
        $event->updateTodo(\count($urls));
        $event->setExtractor($this->_name);

        foreach ($urls as $urlrow) {
            $event->updateTodo(-1);
            $name = ($urlrow->name ?? '') ?: substr($urlrow->url, 0, 200);
            $this->parseExernal($urlrow->url, $name, $urlrow->mime ?? '');
            $event->updateDidExtract($this->extractCount);
            if ($this->extractCount > $this->parseLimit) {
                return;
            }
        }
    }
}
