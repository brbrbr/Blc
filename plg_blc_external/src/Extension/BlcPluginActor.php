<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\External\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcMessages;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES; //using constants but not implementing
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\GetCheckerTrait;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;
    use GetCheckerTrait;

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
                    $this->getApplication()->enqueueMessage("To work correctly URL with a ping destination must have an name", 'warning');
                } else {
                    if (\in_array($urlrow->name, $seen)) {
                        $this->getApplication()->enqueueMessage("To work correctly URL with a ping destination must have an unique name", 'warning');
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
            } catch (\RuntimeException) {
                $this->getApplication()->enqueueMessage("BLC External Plugin Ping Failed", 'error');
                return;
            }

            $body = "Response:<br>{$response->code}<br>" . nl2br(htmlspecialchars($response->body)) . "<br>";
            if ($response->code == 200) {
                $link->working = HTTPCODES::BLC_WORKING_HIDDEN;
                $link->save();
                $this->getApplication()->enqueueMessage("External ping - link hidden.<br>{$body}", 'success');
            } else {
                $this->getApplication()->enqueueMessage("External ping - Failed.<br>{$body}", 'error');
            }
        } else {
            $this->getApplication()->enqueueMessage("External link can not be replaced directy. However your can ping a remote site", 'warning');
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
        $parsedItem    = new Uri((string)$linkItem);
        UrlHelper::urlencodeFixParts($parsedItem);
        $linkItem->toCheck = $parsedItem->toString();

        $config             = clone $this->componentConfig;
        $config->set('range', false);
        $config->set('head', false);
        $config->set('follow', true);
        $config->set('response', HTTPCODES::CHECKER_LOG_RESPONSE_ALWAYS);
        $config->set('name', 'Get from External');
        $checker->checkLink($linkItem, config: $config);
        $response = [
            'body'      => $linkItem->log['Response'] ?? '',
            'mime'      => $linkItem->mime ?? 'broken',
            'http_code' => $linkItem->http_code ?? 404,
            'broken'    => $linkItem->broken ?? HTTPCODES::BLC_BROKEN_TRUE,
        ];

        return $response;
    }


    final protected function getLink(string $url): LinkTable
    {
        $pk    = [
            'url' => $url,
        ];

        $linkItem =  new LinkTable($this->getDatabase());
        $linkItem->load($pk);
        $linkItem->bind($pk);
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
            if ($url && str_starts_with($url, 'http')) {
                $link = [
                    'url'    => $url,
                    'anchor' => $row->name ?? $row->title ?? $row->plaats ?? $row->l ?? (string)$row,
                ];
                $links[] = $link;
            }
        }
        $this->processLinks($links, $name, $synchId);
    }
    /**
     * reads CVS content using str_getcsv
     * so parsed in memory. This might result in memory issues.
     * will see when someone get's a CSV that large.
     * @return  void
     *
     * @since   3.5
     *  
     */

    protected function parseCsv(string $content, string $name, int $synchId)
    {

        //str_getcsv does not work wel with multiline
        if (!$content) {
            return;
        }
        $lines = explode("\n", $content);
        if (count($lines)< 2) {
            return;
        }
        unset($content);
        $header =array_shift($lines);

        if (strlen($header) == 0) {
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

        $header = str_getcsv($header, separator: $delimiter, escape: "");

        if (!$header) {
            return;
        }

        $header  = array_map('mb_strtolower', $header);
        $linkCol = 0;

        foreach (['url', 'link', 'u'] as $urlHeader) { //todo make this an option
            $maybe = array_search($urlHeader, $header);
            if ($maybe !== false) {
                $linkCol = $maybe;
                break;
            }
        }
        $nameCol = 1;
        foreach (['name', 'title', 'plaats', 'l'] as $urlHeader) {  //todo make this an option
            $maybe = array_search($urlHeader, $header);
            if ($maybe !== false) {
                $nameCol = $maybe;
                break;
            }
        }
        $links = [];
        foreach ($lines as  $line) {
            if (empty($line)) {
                continue; // Skip empty lines
            }
            $row = str_getcsv($line, separator: $delimiter, escape: "");
         
            $url = trim($row[$linkCol] ?? '');
            if ($url && str_starts_with($url, 'http')) {
                $link = [
                    'url'    => $url,
                    'anchor' => $row[$nameCol] ?? "CSV $name:  $url",
                ];
                $links[] = $link;
            }
        }
        $this->processLinks($links, $name, $synchId);
      
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
        } else {
            throw new \RuntimeException("Invalid xml $name");
        }
    }
    //true == continue
    //false == stop
    protected function parseExernal(string $url, string $name = '', string|null $mime = ''): void
    {
        $id            = crc32($this->_name . $url);
        $synchTable    = $this->getItemSynch($id);

        $synchId = $synchTable->id;
        if (!$synchId) {
            return;
        }
        $dateLastSynch = new Date($synchTable->last_synch ?? '1970-01-01 00:00:00');

        if ($dateLastSynch > $this->reCheckDate) {
            return;
        }

        $this->loadLanguage();
        BlcMessages::getInstance()->enqueueMessage(Text::sprintf('PLG_BLC_EXTERNAL_EXTRACT_MESSAGE', $url), 'info');

        $this->extractCount++;
        $this->purgeInstances($synchId);
        $this->processLinks([$url], $name, $synchId);
        $response = json_decode($synchTable->data ?? '[]', true);

        if (!$response || !isset($response['body'])) {
            $response = $this->getUrl($url);
            if ($response['broken']) {
                BlcMessages::getInstance()->enqueueMessage(Text::sprintf('COM_BLC_EXTERNAL_BROKEN_MESSAGE', $url, $response['http_code']), 'error');
                return;
            }
            $synchTable->save([
                'data' => $response,
            ]);
        }



        if (!$response || !isset($response['body'])) {
            //some kind of error, set synched
            //so it shows up in the link checker
            $synchTable->setSynched([
                'data' => $response,
            ]);
            return;
        }
        if ($mime === '' || $mime === null) {
            $mime = $response['mime'] ?? 'broken';
        }

        switch ($mime) {
            case 'application/xml': //sitemap
            case 'text/xml': //sitemap
                $this->parseSiteMapXml($response['body'], $name, $synchId);
                break;
            case 'text/html': //just html
            case 'sitemap/html': //sitemap
                $this->parseSiteMapHtml($response['body'], $name, $synchId);
                break;
            case 'application/json': //sitemap
                $this->parseJson($response['body'], $name, $synchId);
                break;
            case 'text/csv': //csv
                $this->parseCsv($response['body'], $name, $synchId);
                break;
            case 'text/html':
                break;
            default:
                //done link checker takes over
                break;
        }
        //content is reload on each synch, so not usefull to keep the possible large data in storage
        unset($response['body']);

        $synchTable->setSynched([
            'data' => $response,
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

        $todo = \count($urls);
        $event->updateTodo($todo);
        $event->setExtractor($this->_name);
        BlcMessages::getInstance()->enqueueMessage(Text::sprintf('COM_BLC_EXTRACT_MESSAGE', $this->_name, $todo), 'alert');
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
