<?php

declare(strict_types=1);

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
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\BlcMessageTrait;
use Blc\Component\Blc\Administrator\Traits\GetCheckerTrait;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;
    use BlcMessageTrait;
    use GetCheckerTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-external';

    // CSV delimiter candidates in order of preference
    private const CSV_DELIMITERS = [',', ';', '|', "\t"];

    // Common CSV column names for URLs
    private const URL_COLUMN_NAMES = ['url', 'link', 'u'];

    // Common CSV column names for titles/names
    private const NAME_COLUMN_NAMES = ['name', 'title', 'plaats', 'l'];

    // MIME types
    private const MIME_XML = ['application/xml', 'text/xml'];
    private const MIME_HTML = ['text/html', 'sitemap/html'];
    private const MIME_JSON = 'application/json';
    private const MIME_CSV = 'text/csv';

    private array $urls = [];
    private int $extractCount = 0;

    protected string $primary = 'url';
    protected string $context = 'com_blc.external';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->setRecheck();
    }

    #[\Override]
    public function onBlcContainerChanged(BlcEvent $event): void
    {
        // External links won't have a changed flag.
        // Interface requires this function
    }

    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {
        parent::onBlcExtensionAfterSave($event);

        $table = $event->getItem();

        if (!$this->isRelevantExtension($table)) {
            return;
        }

        // Parent has replaced the params with the new one
        $this->getUnsynchedCount();
        $this->validatePingUrls();
    }

    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {
        $this->getUnsynchedCount();

        $pingConfig = $this->findPingConfigForField($instance->field);

        if ($pingConfig) {
            $this->sendPingNotification($link, $newUrl, $pingConfig);
        } else {
            $this->messageWarning(
                Text::_('PLG_BLC_EXTERNAL_EXTRACT_NO_REPLACE'),
                false
            );
        }

        $this->updateSynchDate($instance->synch_id);
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

    public function onBlcExtract(BlcExtractEvent $event): void
    {
        $this->parseLimit = $event->getMax();
        $this->cleanupSynch();

        $event->setExtractor($this->_name);
        $todo = $this->getUnsynchedCount();
        $event->updateTodo($todo);

        foreach ($this->urls as $urlrow) {
            $name = $this->getUrlName($urlrow);
            $this->parseExernal($urlrow->url, $name, $urlrow->mime ?? '');

            $event->updateTodo(-1);
            $event->updateDidExtract($this->extractCount);

            if ($this->extractCount > $this->parseLimit) {
                break;
            }
        }

        if ($this->extractCount > 0) {
            $this->showExtractionSummary($event->getTodo());
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Check if the saved extension is this plugin
     */
    private function isRelevantExtension(object $table): bool
    {
        return $table->get('type') === 'plugin'
            && $table->get('folder') === $this->_type
            && $table->get('element') === $this->_name;
    }

    /**
     * Validate that ping URLs have unique names
     */
    private function validatePingUrls(): void
    {
        $seen = [];

        foreach ($this->urls as $urlrow) {
            if (empty($urlrow->ping)) {
                continue;
            }

            if (empty($urlrow->name)) {
                $this->messageWarning('To work correctly URL with a ping destination must have a name', false);
                continue;
            }

            if (\in_array($urlrow->name, $seen, true)) {
                $this->messageWarning('To work correctly URL with a ping destination must have a unique name', false);
            } else {
                $seen[] = $urlrow->name;
            }
        }
    }

    /**
     * Find ping configuration for a specific field
     */
    private function findPingConfigForField(string $field): ?object
    {
        foreach ($this->urls as $urlrow) {
            if ($urlrow->name === $field && !empty($urlrow->ping)) {
                return $urlrow;
            }
        }

        return null;
    }

    /**
     * Send ping notification when a link is replaced
     */
    private function sendPingNotification(LinkTable $link, string $newUrl, object $config): void
    {
        $data = [
            'oldurl' => $link->url,
            'newurl' => $newUrl,
            'name'   => $config->name,
        ];

        try {
            $response = HttpFactory::getHttp()->post($config->ping, $data);
            $body = $this->formatResponseBody($response);

            if ($response->code === 200) {
                $link->working = HTTPCODES::BLC_WORKING_HIDDEN;
                $link->save();
                $this->messageSuccess("External ping - link hidden.<br>{$body}", true);
            } else {
                $this->messageWarning("External ping - Failed.<br>{$body}", false);
            }
        } catch (\RuntimeException $e) {
            $this->messageError("External ping - Failed.<br>" . $e->getMessage(), false);
        }
    }

    /**
     * Format HTTP response for display
     */
    private function formatResponseBody(object $response): string
    {
        return "Response:<br>{$response->code}<br>"
            . nl2br(htmlspecialchars($response->body))
            . "<br>";
    }


    /**
     * Update synch date to prevent immediate re-parsing
     */
    private function updateSynchDate(int $synchId): void
    {
        $synchTable = new SynchTable($this->getDatabase());
        $synchTable->load(['id' => $synchId]);

        $date = clone $this->synchStillValidDate;
        $date->modify('+30 minutes');

        $synchTable->save(['last_synch' => $date->toSql()]);
    }

    /**
     * Get URL name from URL row object
     */
    private function getUrlName(object $urlrow): string
    {
        return ($urlrow->name ?? '') ?: substr((string) $urlrow->url, 0, 200);
    }

    /**
     * Show extraction summary message
     */
    private function showExtractionSummary(int $remaining): void
    {
        $this->messageAlert(
            Text::sprintf(
                'PLG_BLC_EXTERNAL_EXTRACT_FINISH_MESSAGE',
                $this->_name,
                $this->extractCount,
                $remaining
            )
        );
    }

    /**
     * Fetch and check external URL
     */
    private function getUrl(string $url): array
    {
        $this->extractCount++;

        $linkItem = $this->getLink($url);
        $checker = $this->getChecker();
        $linkItem->log = [];

        $parsedItem = new Uri((string)$linkItem);
        UrlHelper::urlencodeFixParts($parsedItem);
        $linkItem->toCheck = $parsedItem->toString();

        $config = $this->buildCheckerConfig();
        $checker->checkLink($linkItem, config: $config);

        return [
            'body'      => $linkItem->log['Response'] ?? '',
            'mime'      => $linkItem->mime ?? 'broken',
            'http_code' => $linkItem->http_code ?? 404,
            'broken'    => $linkItem->broken ?? HTTPCODES::BLC_BROKEN_TRUE,
        ];
    }

    /**
     * Build configuration for link checker
     */
    private function buildCheckerConfig(): object
    {
        $config = clone $this->componentConfig;
        $config->set('range', false);
        $config->set('head', false);
        $config->set('verbose', false);
        $config->set('follow', true);
        $config->set('log_response', HTTPCODES::CHECKER_LOG_RESPONSE_ALWAYS);
        $config->set('name', 'Get from External');

        return $config;
    }

    /**
     * Get or create link table entry
     */
    private function getLink(string $url): LinkTable
    {
        $pk = ['url' => $url];
        $linkItem = new LinkTable($this->getDatabase());
        $linkItem->load($pk);
        $linkItem->bind($pk);

        return $linkItem;
    }

    /**
     * Parse JSON content for URLs
     */
    private function parseJson(?string $content, string $name, int $synchId): void
    {
        if (empty($content)) {
            return;
        }

        $rows = json_decode($content);
        if (!$rows) {
            return;
        }

        $links = [];
        foreach ($rows as $key => $row) {
            $url = $row->url ?? $row->link ?? $row->u ?? $key;

            if ($this->isValidHttpUrl($url)) {
                $links[] = [
                    'url'    => $url,
                    'anchor' => $this->extractAnchorFromJson($row, $name, $key),
                ];
            }
        }

        $this->processLinks($links, $name, $synchId);
    }

    /**
     * Extract anchor text from JSON row
     */
    private function extractAnchorFromJson(string|object $row, string $name, string|int $key): string
    {
        return $row->name
            ?? $row->title
            ?? $row->plaats
            ?? $row->l
            ?? (\is_string($row) ? $row : "$name $key");
    }

    /**
     * Parse CSV content for URLs
     */
    private function parseCsv(string $content, string $name, int $synchId): void
    {
        if (empty($content)) {
            return;
        }

        $lines = explode("\n", $content);
        if (\count($lines) < 2) {
            return;
        }

        $header = array_shift($lines);
        if (\strlen($header) === 0) {
            return;
        }

        $delimiter = $this->detectCsvDelimiter($header);
        $header = $this->parseCsvLine($header, $delimiter);

        if (!$header) {
            return;
        }

        $header = array_map(mb_strtolower(...), $header);
        $linkCol = $this->findColumnIndex($header, self::URL_COLUMN_NAMES, 0);
        $nameCol = $this->findColumnIndex($header, self::NAME_COLUMN_NAMES, 1);

        $links = $this->extractLinksFromCsvLines($lines, $delimiter, $linkCol, $nameCol, $name);
        $this->processLinks($links, $name, $synchId);
    }

    /**
     * Detect CSV delimiter by counting occurrences
     */
    private function detectCsvDelimiter(string $header): string
    {
        $maxCount = 0;
        $delimiter = ',';

        foreach (self::CSV_DELIMITERS as $candidate) {
            $count = substr_count($header, $candidate);
            if ($count > $maxCount) {
                $delimiter = $candidate;
                $maxCount = $count;
            }
        }

        return $delimiter;
    }

    /**
     * Parse a single CSV line
     */
    private function parseCsvLine(string $line, string $delimiter): array|false
    {
        return str_getcsv($line, separator: $delimiter, enclosure: '"', escape: "\\");
    }

    /**
     * Find column index by matching against possible column names
     */
    private function findColumnIndex(array $header, array $candidates, int $default): int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $header, true);
            if ($index !== false) {
                return $index;
            }
        }

        return $default;
    }

    /**
     * Extract links from CSV lines
     */
    private function extractLinksFromCsvLines(
        array $lines,
        string $delimiter,
        int $linkCol,
        int $nameCol,
        string $name
    ): array {
        $links = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $row = $this->parseCsvLine($line, $delimiter);
            $url = trim($row[$linkCol] ?? '');

            if ($this->isValidHttpUrl($url)) {
                $links[] = [
                    'url'    => $url,
                    'anchor' => $row[$nameCol] ?? "CSV $name: $url",
                ];
            }
        }

        return $links;
    }

    /**
     * Parse sitemap HTML (delegates to text processing)
     */
    private function parseSiteMapHtml(string $map, string $name, int $synchId): void
    {
        $this->processText($map, $name, $synchId);
    }

    /**
     * Parse sitemap XML for URLs and images
     */
    private function parseSiteMapXml(string $map, string $name, int $synchId): void
    {
        $xml = simplexml_load_string($map);

        if (!$xml) {
            throw new \RuntimeException("Invalid xml $name");
        }

        // Process nested sitemaps
        foreach ($xml->sitemap as $sitemapEntry) {
            $this->parseExernal((string)$sitemapEntry->loc, $name);
        }

        // Extract URLs and images
        $links = $this->extractLinksFromSitemapXml($xml);
        $this->processLinks($links, $name, $synchId);
    }

    /**
     * Extract URLs and images from sitemap XML
     */
    private function extractLinksFromSitemapXml(\SimpleXMLElement $xml): array
    {
        $links = [];

        foreach ($xml->url as $urlEntry) {
            $url = (string)($urlEntry->loc ?? '');

            if (empty($url)) {
                continue;
            }

            $links[] = [
                'url'    => $url,
                'anchor' => 'Sitemap: ' . $url,
            ];

            // Extract images from the URL entry
            foreach ($urlEntry->children('image', true) as $child) {
                if ($child->getName() === 'image') {
                    $links[] = [
                        'url'    => (string)$child->loc,
                        'anchor' => 'Sitemap: ' . $url,
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Parse external URL and extract links based on content type
     * 
     * @return bool True if synch completed, false if skipped
     */
    private function parseExernal(string $url, string $name = '', ?string $mime = ''): bool
    {
        $id = crc32($this->_name . $url);
        $synchTable = $this->getItemSynch($id);
        $synchId = $synchTable->id;

        if (!$synchId) {
            return false;
        }

        if ($this->isSynchStillValid($synchTable)) {
            return false;
        }

        $this->loadLanguage();
        $this->messageInfo(Text::sprintf('PLG_BLC_EXTERNAL_EXTRACT_MESSAGE', $url));

        $this->extractCount++;
        $this->purgeInstances($synchId);
        $this->processLinks([$url], $name, $synchId);

        $response = $this->getOrFetchResponse($synchTable, $url);

        if (!$response || !isset($response['body'])) {
            $synchTable->setSynched(['data' => $response]);
            return true;
        }

        $mime = $mime ?: ($response['mime'] ?? 'broken');
        $this->parseContentByMimeType($response['body'], $mime, $name, $synchId);

        // Don't store large body content
        unset($response['body']);
        $synchTable->setSynched(['data' => $response]);

        return true;
    }

    /**
     * Check if synch is still valid (not expired)
     */
    private function isSynchStillValid(SynchTable $synchTable): bool
    {
        $dateLastSynch = new Date(
            $synchTable->last_synch ?? $this->getDatabase()->getNullDate()
        );

        return $dateLastSynch > $this->synchStillValidDate;
    }

    /**
     * Get cached response or fetch new one
     */
    private function getOrFetchResponse(SynchTable $synchTable, string $url): ?array
    {
        $response = json_decode($synchTable->data ?? '[]', true);

        if ($response && isset($response['body'])) {
            return $response;
        }

        $response = $this->getUrl($url);

        if ($response['broken']) {
            $this->messageError(
                Text::sprintf('COM_BLC_EXTERNAL_BROKEN_MESSAGE', $url, $response['http_code'])
            );
            return $response;
        }

        $synchTable->save(['data' => $response]);
        return $response;
    }

    /**
     * Parse content based on MIME type
     */
    private function parseContentByMimeType(
        string $body,
        string $mime,
        string $name,
        int $synchId
    ): void {
        if (\in_array($mime, self::MIME_XML, true)) {
            $this->parseSiteMapXml($body, $name, $synchId);
        } elseif (\in_array($mime, self::MIME_HTML, true)) {
            $this->parseSiteMapHtml($body, $name, $synchId);
        } elseif ($mime === self::MIME_JSON) {
            $this->parseJson($body, $name, $synchId);
        } elseif ($mime === self::MIME_CSV) {
            $this->parseCsv($body, $name, $synchId);
        }
    }

    /**
     * Clean up expired synch data
     */
    protected function cleanupSynch(): void
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->where($db->quoteName('last_synch') . ' < ' . $db->quote($this->synchStillValidDate->toSql()))
            ->bind(':containerPlugin', $this->_name, ParameterType::STRING);

        $db->setQuery($query)->execute();
    }

    /**
     * Get count of unsynced URLs
     */
    protected function getUnsynchedCount(): int
    {
        $this->urls = (array)$this->params->get('urls', []);
        return \count($this->urls);
    }

    /**
     * Check if string is a valid HTTP(S) URL
     */
    private function isValidHttpUrl(mixed $url): bool
    {
        return \is_string($url) && str_starts_with($url, 'http');
    }
}
