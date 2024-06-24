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
use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent; //using constants but not implementing
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
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

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }

    public function replaceLink(object $link, object $instance, string $newUrl): void
    {
        Factory::getApplication()->enqueueMessage("External link not replaced", 'warning');
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
        $config->set('follow', false);
        $config->set('response', HTTPCODES::CHECKER_LOG_RESPONSE_TEXT);
        $config->set('name', 'Get from External');
        $checker->initConfig($config);
        $result         = $checker->checkLink($linkItem);
        $result['body'] = $linkItem->log['Response'];

        return $result;
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

        foreach (['url', 'link'] as $urlHeader) {
            $maybe = array_search($urlHeader, $header);
            if ($maybe !== false) {
                $linkCol = $maybe;


                break;
            }
        }
        $nameCol = 1;
        foreach (['name', 'title', 'plaats'] as $urlHeader) {
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
    /* sitemap point to different sitemap or urls so no need to redo a parseContainer
    TODO images
    */
    protected function parseSiteMapXml($map, $name, $synchId)
    {
        $xml = simplexml_load_string($map);
        if ($xml) {
            foreach ($xml->sitemap as $url_list) {
                $url = $url_list->loc;
                $this->parseContainer($url, $name);
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
    protected function parseContainer(string $url, string $name = '', string|null $mime = ''): void
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

    protected function cleanupSynch(): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete('`#__blc_synch`')
            ->where('`plugin_name` = :containerPlugin')
            ->bind(':containerPlugin', $this->_name)
            ->where("`last_synch` < " . $db->quote($this->reCheckDate->toSql()));
        $db->setQuery($query)->execute();
    }
    protected function getUnsynchedCount(): int
    {
        $urls = (array) $this->params->get('urls', []);
        return \count($urls);
    }

    public function onBlcExtract(BlcExtractEvent $event): void
    {
        $event->setExtractor($this->_name);
        $this->parseLimit = $event->getMax();
        $this->cleanupSynch();
        $urls = (array) $this->params->get('urls', []);

        $event->updateTodo(\count($urls));
        foreach ($urls as $urlrow) {
            $event->updateTodo(-1);
            $this->parseContainer($urlrow->url, $urlrow->name, $urlrow->mime ?? '');
            $event->updateDidExtract($this->extractCount);
            if ($this->extractCount > $this->parseLimit) {
                return;
            }
        }
    }
}
