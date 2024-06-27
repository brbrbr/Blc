<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\System\Blc\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerPost;


use Blc\Component\Blc\Administrator\Checker\BlcCheckerPre;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerUnchecked;
use Blc\Component\Blc\Administrator\Event\BlcEvent;

use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Parser\EmbedParser;
use Blc\Component\Blc\Administrator\Parser\HrefParser;
use Blc\Component\Blc\Administrator\Parser\ImgParser;
use Blc\Plugin\System\Blc\CliCommand\CheckCommand;
use Blc\Plugin\System\Blc\CliCommand\ExtractCommand;
use Blc\Plugin\System\Blc\CliCommand\PurgeCommand;
use Blc\Plugin\System\Blc\CliCommand\ReportCommand;
use Joomla\CMS\Component\ComponentHelper;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Module\Quickicon\Administrator\Event\QuickIconsEvent;
use Joomla\Registry\Registry;

class Blc extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;
    use DatabaseAwareTrait;

    private Registry $componentConfig;
    protected $autoloadLanguage     = true;
    protected $allowLegacyListeners = false;

    private const TASKS_MAP = [
        'blc.tasks' => [
            'langConstPrefix' => 'PLG_SYSTEM_BLC_TASKS',
            'method'          => 'taskBlc',
            'form'            => 'taskForm',
        ],
    ];
    /**
     * @param array<mixed> $config
     */

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->componentConfig = ComponentHelper::getParams('com_blc');
    }



    public static function getSubscribedEvents(): array
    {
        //this should prevent server faults when the component is deinstalled.
        //and it's pointless to run this plugin without component.
        //as a side-effect none of the 'blc' group plugins will run
        //thus we don't need a check there.
        if (!ComponentHelper::isEnabled('com_blc')) {
            return [];
        }

        $events = [
            \Joomla\Application\ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
            //using the ajax compoent for this
            //could be done in an onAfterRoute but then that code is executed
            //every time a page is loaded.
            'onAjaxBlcReport'    => 'onAjaxBlcReport',
            'onAjaxBlcCheck'     => 'onAjaxBlcCheck',
            'onAjaxBlcExtract'   => 'onAjaxBlcExtract',
            'onContentAfterSave' => [
                'onContentAfterSave',
                Event\Priority::MIN,
            ], //should run after the Field handlers
            'onContentAfterDelete'             => 'onContentAfterDelete',
            'onExtensionAfterSave'             => 'onExtensionAfterSave',
            'onBlcCheckerRequest'              => 'onBlcCheckerRequest',
            'onBlcParserRequest'               => 'onBlcParserRequest',
            'onInstallerBeforePackageDownload' => 'onInstallerBeforePackageDownload',
            'onTaskOptionsList'                => 'advertiseRoutines',
            'onExecuteTask'                    => 'standardRoutineHandler',
            'onContentPrepareForm'             => 'enhanceTaskItemForm',
            'onBlcReport'                      => 'onBlcReport',
        ];
        //static function can't use $this->getApplication
        if (Factory::getApplication()->isClient('administrator')) {
            $events['onGetIcons'] = 'onGetIcons';
        }
        return $events;
    }

    private function taskBlc(ExecuteTaskEvent $event): int
    {

        $this->logTask('BLC Task Start', 'info');
        if (!$this->checkCronThrottle()) {
            $this->logTask(Text::_("Cron throttle"), 'warning');
            return Status::OK; //or resume?? TODO
        }
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        $params      =  $event->getArgument('params');
        $extractTask = (bool) ($params->extracttask ?? false);
        $checkTask   = (bool) ($params->checktask ?? false);
        $reportTask  = (bool) ($params->reporttask ?? false);
        $status      =  Status::OK;
        if ($extractTask) {
            $lock = BlcMutex::getInstance()->acquire(minLevel: BlcMutex::LOCK_SITE);
            if ($lock) {
                ob_start();
                BlcHelper::setLastAction('Task', 'Extract');
                $event  = $this->runBlcExtract($this->componentConfig->get('extract_http_limit', 10));
                $parsed = $event->getdidExtract();
                ob_get_clean();
                $this->logTask(Text::plural('PLG_SYSTEM_BLC_TASKS_LINKS_EXTRACTED', $parsed), 'info');
                if ($this->componentConfig->get('resumeTask', 1)) {
                    $todo = $event->getTodo();
                    if ($todo) {
                        $status = Status::WILL_RESUME;
                    }
                }
            } else {
                $status = Status::WILL_RESUME;
            }
        }

        if ($checkTask) {
            $lock = BlcMutex::getInstance()->acquire();
            if ($lock) {
                BlcHelper::setLastAction('Task', 'Check');
                $checkLimit = $this->componentConfig->get('check_http_limit', 10);
                $model      = $this->getModel('Links');
                $links      =  $model->runBlcCheck($checkLimit, true);
                $this->logTask(Text::plural('PLG_SYSTEM_BLC_TASKS_LINKS_CHECKED', \count($links)), 'info');
                if ($this->componentConfig->get('resumeTask', 1)) {
                    $todo = $model->getToCheck(true);
                    if ($todo) {
                        $status = Status::WILL_RESUME;
                    }
                }
            } else {
                $status = Status::WILL_RESUME;
            }
        }
        //here we can lock, the RESUME will re-run the task.
        if ($reportTask) {
            $lock = BlcMutex::getInstance()->acquire(minLevel: BlcMutex::LOCK_SITE);
            if ($lock) {
                BlcHelper::setLastAction('Task', 'Report');
                $this->blcReport();
                $this->logTask('BLC Task Report', 'info');
            } else {
                $status = Status::WILL_RESUME;
            }
        }
        BlcMutex::getInstance()->release();
        $this->logTask('BLC Task End', 'info');
        return $status;
    }

    public function onGetIcons(QuickIconsEvent $event): void
    {
        $context   = $event->getContext();
        $quickicon = $this->componentConfig->get('quickicon', 'system_quickicon');

        if ($quickicon == 1) { //old
            $quickicon = 'system_quickicon';
        }

        if ($context !== $quickicon) {
            return;
        }
        $result = $event->getArgument('result', []);

        $result[] = [[
            'image' => 'star fas icon- icon-small fa-chain-broken',
            'text'  => Text::_('PLG_SYSTEM_BLC_QUICKICON_TXT') . '          
		<span class="d-none blcstatus Redirect">
			<span class="badge blcresponse count"></span>
		</span>
	',
            'link' => 'index.php?option=com_blc&view=links',
        ]];
        $event->setArgument('result', $result);
    }

    public function onInstallerBeforePackageDownload(mixed $event): bool
    {

        if (version_compare(JVERSION, '5.0', 'ge')) {
            $url     = $event->getUrl();
            $headers = $event->getHeaders();
        } else {
            $arguments   = array_values($event->getArguments());
            $url         = &$arguments[0] ?? '';
            $headers     = &$arguments[1] ?? [];
        }

        if (parse_url($url, PHP_URL_HOST) == 'downloads.brokenlinkchecker.dev') {
            $key = $this->params->get('blckey', '');
            $uri = clone Uri::getInstance($url);

            if (!$key) {
                $key = $uri->getVar('dlid', '');
            }

            if (!$key) {
                $host       = Uri::getInstance()->getHost();
                $md5List    = str_split(strtoupper(md5($host)), 4);
                $md5List[0] = 'AUTO';
                $key        = implode('-', $md5List);
            }

            $uri->setVar('dlid', $key);
            $url                  = $uri->toString();
            $headers['X-BLC-KEY'] =  $key;

            if (version_compare(JVERSION, '5.0', 'ge')) {
                $event->updateUrl($url);
                $event->updateHeaders($headers);
            }
        }

        return true;
    }

    public function onBlcParserRequest(BlcEvent $event): void
    {
        $parser = $event->getItem();
        if ($this->componentConfig->get('href', 1)) {
            $parser->registerParser('href', HrefParser::getInstance());
        }
        if ($this->componentConfig->get('href', 1)) {
            $parser->registerParser('img', ImgParser::getInstance());
        }
        if ($this->componentConfig->get('embed', 0)) {
            $parser->registerParser('embed', EmbedParser::getInstance());
        }
    }

    public function onBlcCheckerRequest(BlcEvent $event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker(BlcCheckerHttpCurl::getInstance(), 50);
        if ($this->componentConfig->get('unkownprotocols', 1) == 1) {
            $checker->registerChecker(BlcCheckerUnchecked::getInstance(), 100);
        }
        if (
            $this->componentConfig->get('ignore_hosts', '')
            || $this->componentConfig->get('ignore_paths', '')
        ) {
            $checker->registerChecker(BlcCheckerPre::getInstance(), 10);
        }

        if ($this->componentConfig->get('ignore_redirects', '')) {
            $checker->registerChecker(BlcCheckerPost::getInstance(), 60, always: true); //after checker
        }
    }

    public function onExtensionAfterSave($event): void
    {
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        if (version_compare(JVERSION, '5.0', 'ge')) {
            $context   = $event->getContext();
            $table     = $event->getItem();
        } else {
            $arguments = array_values($event->getArguments());
            $context   = $arguments[0] ?? '';
            $table     = $arguments[1] ?? null;
        }

        $arguments =
            [
                'context' => $context,
                'item'    => &$table,
            ];

        $event = new BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->getApplication()->getDispatcher()->dispatch('onBlcExtensionAfterSave', $event);
        BlcHelper::setCronState(false);
    }

    public function onContentAfterDelete($event): void
    {
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        if (version_compare(JVERSION, '5.0', 'ge')) {
            $context   = $event->getContext();
            $table     = $event->getItem();
        } else {
            $arguments = array_values($event->getArguments());
            $context   = $arguments[0] ?? '';
            $table     = $arguments[1] ?? null;
        }
        if (isset($table->id)) {
            $arguments =
                [
                    'context' => $context,
                    'id'      => $table->id,
                    'event'   => 'ondelete',
                ];
            $event = new BlcEvent('onBlcContainerChanged', $arguments);
            $this->getApplication()->getDispatcher()->dispatch('onBlcContainerChanged', $event);
        }
        BlcHelper::setCronState(false);
    }

    public function onContentAfterSave($event): void
    {
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        if (version_compare(JVERSION, '5.0', 'ge')) {
            $context   = $event->getContext();
            $table     = $event->getItem();
        } else {
            $arguments = array_values($event->getArguments());
            $context   = $arguments[0] ?? '';
            $table     = $arguments[1] ?? null;
        }

        if (isset($table->id)) {
            $arguments =
                [
                    'context' => $context,
                    'id'      => $table->id,
                    'event'   => 'onsave',
                ];


            $event = new BlcEvent('onBlcContainerChanged', $arguments);
            $this->getApplication()->getDispatcher()->dispatch('onBlcContainerChanged', $event);
        }
        BlcHelper::setCronState(false);
    }
    public function registerCommands($event): void
    {
        $app  = $event->getApplication();
        $lang = $app->getLanguage();
        $lang->load('com_blc', JPATH_ADMINISTRATOR);
        $app->addCommand(new CheckCommand());
        $app->addCommand(new ExtractCommand());
        $app->addCommand(new ReportCommand());
        $app->addCommand(new PurgeCommand());
    }
    private function getModel(string $name = 'Link', string $prefix = 'Administrator', array $config = ['ignore_request' => true]): mixed
    {

        $mvcFactory = $this->getApplication()->bootComponent('com_blc')->getMVCFactory();
        return $mvcFactory->createModel($name, $prefix, $config);
    }


    protected function checkMayCron(string $suppliedToken, bool $skipThrottle = false): bool
    {
        //can't use joomla's settoken since the cron runs anonymously.
        $app = $this->getApplication();

        if ($app->isClient('cli')) {
            return true;
        }

        if (session_id() != '') {
            session_write_close();
        }

        $app->allowCache(false);
        $mustToken = $this->componentConfig->get('token', null);
        if ($mustToken == '') {
            $url = Route::link(
                'administrator',
                'index.php?option=com_config&view=component&component=com_blc'
            );
            print   "<p style=\"padding:50px;background-color:red\">"
                . Text::sprintf("Please set a security token in the <a href=\"%s\">configuration</a>", $url)
                . "</p>";
            $app->close();
            return false;
        }

        if ($suppliedToken == '' || $mustToken != $suppliedToken) {
            $app->close();
            return false;
        }
        if ($skipThrottle) {
            return true;
        }
        if (!$this->checkCronThrottle()) {
            print   "<p style=\"padding:50px;background-color:red\">" . Text::_("Cron throttle") . "</p>";
            $app->close();
            return false;
        }
        return true;
    }
    private function checkCronThrottle(): bool
    {
        $transientmanager = BlcTransientManager::getInstance();
        $date             = new Date();
        $unix             = $date->toUnix();
        $throttle         = $this->componentConfig->get('throttle', 60);
        $transient        = 'onAjaxSite';
        $lastCron         = $transientmanager->get($transient);
        //the 'throttle might change
        if ($lastCron && (($lastCron + $throttle) > $date->toUnix())) {
            return false;
        }
        $transientmanager->set($transient, $unix, true);
        return true;
    }

    private function theStyle(): void
    {
        // phpcs:disable
        ?>
        <style>
            p {
                padding: 5px;
            }

            .Final {
                font-weight: bold;
                font-size: 2em;
            }

            .Broken {
                background-color: red;
                color: white
            }

            .Warning {
                background-color: #ff000088;
                color: white
            }

            .Good {
                background-color: green;
                color: black
            }

            .Redirect {
                background-color: orange;
                color: black
            }

            .Timeout {
                background-color: gray;
                color: white;
            }

            .Unable {
                background-color: gray;
                color: white;
            }

            .Throttle {
                background-color: gray;
                color: white;
            }
        </style>

<?php
                // phpcs:enable
    }

    /**
     * AjaxEvent|Event\Event $event
     */

    public function onAjaxBlcCheck(): void
    {
        $suppliedToken = $this->getApplication()->getInput()->getString('token', '');
        $this->checkMayCron($suppliedToken);
        $lock = BlcMutex::getInstance()->acquire();
        if (!$lock) {
            $this->maybeSendReport('check_report', 'HTTP');
            print "Another instance of the broken link checker is running";
        }

        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        BlcHelper::setLastAction('HTTP', 'Check');
        $checkLimit = $this->componentConfig->get('check_http_limit', 10);
        $links      = $this->getModel('Links')->runBlcCheck($checkLimit, true);
        $count      = 0;
        $this->theStyle();
        foreach ($links as $link) {
            switch ($link->http_code) {
                case HTTPCODES::BLC_THROTTLE_HTTP_CODE:
                    $text  = "Throttle";
                    $short = "Domain Throttle";
                    break;
                case HTTPCODES::BLC_UNABLE_TOCHECK_HTTP_CODE:
                    $text  = 'Unable';
                    $short = "Unable to check";
                    break;
                default:
                    if ($link->broken) {
                        $text = 'Broken';
                    } else {
                        if ($link->redirect_count && ($link->url != $link->final_url)) {
                            $text = 'Redirect';
                        } else {
                            $text = 'Good';
                        }
                    }
                    $short = substr($link->url, 0, 200);
                    break;
            }
            $code     = sprintf('[%3s]', $link->http_code);
            $duration = sprintf(' [%1.4f]', $link->request_duration);
            $url      = $link->toString();
            $short    = substr($link->url, 0, 200);
            print "<p class=\"$text\">$text: $code $duration - 
                         <a href=\"{$url}\" target=\"checked\">
                           $short
                         </a>
                       </p>";
        }

        $count = $this->getModel('Links')->getToCheck(true);


        if ($count) {
            print "<p id=\"unchecked\" class=\"Final Redirect\">Still $count unchecked Links</p>";
        } else {
            print "<p class=\"Final Good\">Link Checker Completed</p>";
        }


        $this->maybeSendReport('check_report', 'HTTP');
        $app = $this->getApplication();
        $app->setHeader('Expires', 'Wed, 1 Apr 2023 00:00:00 GMT', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', false);
        $app->sendHeaders();
        $app->close();
    }

    public function onBlcReport(BlcEvent $event)
    {
        $client = $event->getContext();
        $action = $event->getEvent();
        $id     = $event->getId();
        switch ($id) {
            case 'email':
                $result = $this->maybeSendReport($action, $client);
                break;
            case 'print':
                $result = $this->blcJsonReport();
                break;
            default:
                throw new \Exception('Not supported');
        }
        $event->updateEventResult($result);
    }


    private function maybeSendReport(string $event, string $client): string
    {
        $key = "{$event}_check";

        if ($this->componentConfig->exists($key) && !$this->componentConfig->get($key, 0)) {
            return 'Not Enabled After ' . ucfirst($event);
        }
        BlcHelper::setLastAction($client, 'Report');
        return $this->blcReport();
    }

    public function onAjaxBlcExtract(): void
    {
        $app           = $this->getApplication();
        $suppliedToken = $app->getInput()->getString('token', '');
        $this->checkMayCron($suppliedToken);
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        BlcHelper::setLastAction('HTTP', 'Extract');
        ob_start();
        $this->runBlcExtract($this->componentConfig->get('extract_http_limit', 10));

        $result = ob_get_clean();
        echo nl2br($result);

        $app->setHeader('Expires', 'Wed, 1 Apr 2023 00:00:00 GMT', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', false);
        $app->sendHeaders();
        $app->close();
    }

    private function runBlcExtract(int $limit): BlcExtractEvent
    {
        print "Starting Extractors\n";
        $event = $this->getModel('Links')->runBlcExtract($limit);
        $this->maybeSendReport('report_extract', 'HTTP');
        print "Finished - Last\n";
        return $event;
    }


    public function onAjaxBlcReport($event)
    {
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        $this->getModel(); //boot the component to load the html servce BLC
        $app           = $this->getApplication();
        $input         = $app->getInput();
        $suppliedToken =  $input->getString('token', '');
        $this->checkMayCron($suppliedToken, true);

        // Requested format passed via URL
        $format = strtolower($input->getWord('format', ''));
        switch ($format) {
            case 'json':
                $result = $this->blcJsonReport();
                break;
            case 'raw':
                $result = $this->blcMailReport();
                break;
            case 'html':
                $result = $this->blcHtmlReport();
                break;
            default:
                break;
        }
        $event->updateEventResult($result);
    }
    private function blcHtmlReport()
    {
        $brokenLinks = $this->blcJsonReport();
        ob_start();

        print "<ul>\n";
        foreach ($brokenLinks as $brokenLink) {
            //     https://brokenlinkchecker.dev/administrator/index.php?option=com_blc&task=link.view&id=2
            print  "<li>" . $this->makeLink($brokenLink) . "</li>\n";
        }

        print  "</ul>\n";
        $this->theStyle();
        return ob_get_clean();
    }
    /**
     * @return  mixed  The return value or null if the query failed.
     */


    private function blcJsonReport()
    {
        $app   = $this->getApplication();
        $input = $app->getInput();
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('`#__blc_links` `l`')
            ->select(['`http_code`,`id`', '`url`', '`final_url`', '`broken`', '`redirect_count`'])
            ->where('EXISTS (SELECT * FROM `#__blc_instances` `i` WHERE `i`.`link_id` = `l`.`id`)');

        $internal = $input->get('internal', -1, 'INT');
        if ($internal == 1) {
            $query->where('`internal_url` != \'\'');
        }

        $external = $input->get('external', -1, 'INT');
        if ($external == 1) {
            $query->where('`internal_url` = \'\'');
        }

        $checked = $input->get('checked', 1, 'INT');
        if ($checked == 1) {
            $query->where('`http_code` != 0');
        }

        $working = $input->get('working', 0, 'INT');
        if ($working != 0) {
            $query->where('`working` = :working')->bind(':working', $working);
        }


        $ors    = [];
        $broken = $input->get('broken', 1, 'INT');
        if ($broken == 1) {
            $ors[] = '`broken` = ' . HTTPCODES::BLC_BROKEN_TRUE;
        }
        $parked = $input->get('parked', 1, 'INT');
        if ($parked == 1) {
            $ors[] = '`parked` = ' . HTTPCODES::BLC_PARKED_PARKED;
        }
        $redirect = $input->get('redirect', 1, 'INT');
        if ($redirect == 1) {
            $ors[] = '`redirect_count` > 0';
        }

        $warning = $input->get('warning', 1, 'INT');
        if ($warning == 1) {
            $ors[] = '`broken` = ' . HTTPCODES::BLC_BROKEN_WARNING;
        }


        if ($ors) {
            $query->extendWhere('AND', $ors, 'OR');
        }
        $query->order('`http_code`');
        $db->setQuery($query);
        return $db->loadObjectList('url');
    }

    private function blcMailReport(): string
    {
        BlcHelper::setLastAction('HTTP', 'Report');
        return   $this->blcReport();
    }
    //todo change to private after implementing event
    private function blcReport(): string
    {
        $this->getModel(); //boot the component to load the html servce BLC
        $transientmanager = BlcTransientManager::getInstance();
        $recipients       = $this->componentConfig->get('recipients', []);
        $report_freq      = $this->componentConfig->get('report_freq', 7);
        $report_delta     = $this->componentConfig->get('report_delta', 1);
        $date             = new Date();
        $unix             = $date->toUnix();
        $subject          = "Brokenlink checker report: " . date("Y-m-d H:i:s");   //if used from CLI there is no timezone info.
        $throttle         = $report_freq * 3600 * 24;
        $data             = [
            'email'      => '',
            'lastReport' => $unix,
        ];
        $report = '';

        foreach ($recipients as $recipient) {
            $id            = $recipient->recipient;
            $user          = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
            $transient     = "Report:$user->email";
            $transientData = $transientmanager->get($transient);
            $lastReport    = $transientData->lastReport ?? 0;


            if ($lastReport && (($lastReport + $throttle) > $unix)) {
                continue;
            }

            $report = $this->report($report_delta * $lastReport);
            if (\strlen($report)) {
                $report = "<h1>" . TEXT::_("PLG_SYSTEM_BLC_DELTA_" . $report_delta) . "</h1>\n" . $report;
                $mail   = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
                $mail->addRecipient($user->email); //joomla cleaner - PHPMailer::addAddress zou ook rechtstreeks kunnen
                //  $mail->setSender(self::getSender());
                $mail->setBody($report);
                $mail->setSubject($subject);
                $mail->SMTPDebug = false;
                $breaks          = ["<br />", "<br>", "<br/>"];
                $body            = str_ireplace($breaks, "\r\n", $report);
                $mail->AltBody   = strip_tags($body);
                $mail->isHtml(true);
                try {
                    $mail->send();
                } catch (Exception $e) {
                }
            }
            //   print "Nothing new\n";


            $data['email'] = $user->email;
            $transientmanager->set($transient, $data, true);
        }
        return $report;
    }

    private function makeLink(\stdClass $item): string
    {
        $link = '';


        if (isset($item->redirect_count) && $item->redirect_count > 0) {
            $text = 'Redirect';
        } else {
            $text = match ($item->broken ?? 0) {
                HTTPCODES::BLC_BROKEN_TRUE    => 'Broken',
                HTTPCODES::BLC_BROKEN_WARNING => 'Warning',
                HTTPCODES::BLC_BROKEN_TIMEOUT => 'Timeout',
                default                       => ''
            };
        }


        if ($text) {
            $url = Route::link(
                'administrator',
                'index.php?option=com_blc&task=link.view&id=' . $item->id,
                absolute: true
            );
            /* this sucks, depricated triggerEvent
            $uri      = new Uri($url);
            $this->getApplication()->triggerEvent('onBuildAdministratorLoginURL', [&$uri]);
            $url=$uri->toString();
            */
            $link .= '<span class="' . $text . '">'
                . HTMLHelper::_('blc.linkme', $url, '[' . $text . ']', $text)
                . '</span> - ';
        }

        $isInternal = !empty($item->internal_url);
        $url        =  $isInternal ? BlcHelper::root(path: $item->url) : $item->url;
        $link .= HTMLHelper::_('blc.linkme', $url, $url, '_blank');
        return $link;
    }
    /**
     * @since 24.44.6385
     */
    private function linkReport($query, $last, $langPrefix)
    {
        $db              = $this->getDatabase();
        $report_limit    = $this->componentConfig->get('report_limit', 20);
        ob_start();
        $query
            ->from('`#__blc_links` `l`')
            ->where('EXISTS(SELECT * FROM `#__blc_instances`  `i` WHERE `i`.`link_id` = `l`.`id`)')
            ->select('count(*)')
            ->where('`working` = 0');
        if ($last) {
            $query->where('`first_failure` > FROM_UNIXTIME(:lastStamp)')
                ->bind(':lastStamp', $last);
        }
        $db->setQuery($query);
        $linkCount = $db->loadResult();
        if ($linkCount) {
            print "<h2>" . Text::plural($langPrefix . '_N', $linkCount) . "</h2>\n";

            $query->clear('select');
            $query
                ->select('`url`')
                ->select('`broken`')
                ->select('`id`')
                ->select('`internal_url`')
                ->setLimit($report_limit)
                ->order('`added` DESC');
            $db->setQuery($query);
            $links       = $db->loadObjectList();
            $actualcount = \count($links);
            if ($actualcount != $linkCount) {
                print "<p><strong>" . Text::sprintf('PLG_SYSTEM_BLC_REPORT_ONLY_LAST', $actualcount) . "</p>\n";
            }
            print "<ul>\n";
            foreach ($links as $link) {
                print "<li>" . $this->makeLink($link) . "</li>\n";
            }

            print "</ul>\n";
        }
        return ob_get_clean();
    }

    private function report(int $last): string
    {

        $report_broken   = $this->componentConfig->get('report_broken', 1);
        $report_warning  = $this->componentConfig->get('report_warning', 1);
        $report_redirect = $this->componentConfig->get('report_redirect', 1);
        $report_new      = $this->componentConfig->get('report_new', 1);
        $report_parked   = $this->componentConfig->get('report_parked', 1);
        $reportContent   = [];

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        if ($report_broken) {
            $query->where('`broken` = ' . HTTPCODES::BLC_BROKEN_TRUE);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_BROKEN');
        }

        if ($report_warning) {
            $query->clear();
            $query->where('`broken` = ' . HTTPCODES::BLC_BROKEN_WARNING);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_WARNING');
        }

        if ($report_redirect) {
            $query->clear();
            $query->where('`redirect_count` > 0 ')
                ->where('`broken` != ' . HTTPCODES::BLC_BROKEN_TRUE); //otherwise this might give double results wit the previous.
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_REDIRECT');
        }

        if ($report_parked) {
            $query->clear();
            $query->where('`parked` = ' . HTTPCODES::BLC_PARKED_PARKED);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_PARKED');
        }

        if ($report_new) {
            $query->clear();
            $query->where('`added` > FROM_UNIXTIME(:lastStamp)')
                ->bind(':lastStamp', $last);
            $reportContent[] = $this->linkReport($query, 0, 'PLG_SYSTEM_BLC_REPORT_NEW');
        }


        ob_start();
        echo join("\n", $reportContent);
        if ($reportContent) {
            $this->theStyle();
        }
        return ob_get_clean();
    }
}
