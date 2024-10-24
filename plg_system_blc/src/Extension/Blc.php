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
use Blc\Component\Blc\Administrator\Checker\BlcCheckerPost;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerPre;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerUnchecked;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Parser\EmbedParser;
use Blc\Component\Blc\Administrator\Parser\HrefParser;
use Blc\Component\Blc\Administrator\Parser\ImgParser;
use Blc\Plugin\System\Blc\CliCommand;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Event as CMSEvent;
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
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
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
        /*$this->loadLanguage('com_blc',JPATH_ADMINISTRATOR);*/
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
            'onContentChangeState'             => 'onContentChangeState',
            'onExtensionAfterUninstall'        => 'onExtensionAfterUninstall',
        ];
        //static function can't use $this->getApplication
        if (Factory::getApplication()->isClient('administrator')) {
            $events['onGetIcons'] = 'onGetIcons';
        }
        return $events;
    }

    /**

     * @param CMSEvent\Extension\AfterUninstallEvent|Event\Event $event
     * @since 24.44.6508

     */

    public function onExtensionAfterUninstall(Event\Event $event)
    {

        if ($event instanceof CMSEvent\Extension\AfterUninstallEvent) {
            $installer = $event->getInstaller();
        } else {
            $arguments         = array_values($event->getArguments());
            $installer         = $arguments[0] ?? false;
        }
        if (!$installer) {
            return;
        }

        $folder = $installer->extension->folder ?? 'no folder';

        if ($folder != 'blc') {
            return;
        }
        //the extension is delete so we can not use it to get it's name and purge
        $element = $installer->extension->element ?? 'no element';
        //neither can we call the model now
        $this->quickPurgeSynch($element);
    }

    private function quickPurgeSynch($plugin)
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_synch'))
            ->where("{$db->quoteName('plugin_name')} = :plugin")
            ->bind(':plugin', $plugin);
        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException) { //php 8
            //this might happen during uninstalling the main package.
            //the synch table is then removed before this script executes.
            //thus we can ignore it
            //the actual exception is mysqli_sql_exception
        }
        if ($this->getApplication()->get('debug')) {
            $this->getApplication()->enqueueMessage(
                Text::sprintf(
                    "PLG_SYSTEM_BLC_QUICK_PURGE",
                    $plugin,
                    $db->getAffectedRows()
                ),
                'info'
            );
        }
    }


    /**
     * this event is trigger when ever a item changes it's state from the list views
     * currenly it's implemented only half in Joomla
     * but it seems to fire fine in Joomla 5 However everything is firing a ContentChangeState  not fe PluginChangeState
     * @param CMSEvent\Model\AfterChangeStateEvent|Event\Event $event
     * @since 24.44.6508
     * does not fire for extensions in Joomla!4
     */
    public function onContentChangeState(Event\Event $event)
    {
        //ignore the value (what changed) and let's the plugins figure it out.
        if ($event instanceof CMSEvent\Model\AfterChangeStateEvent) {
            $context = $event->getContext();
            $pks     = $event->getPks();
        } else {
            $arguments   = array_values($event->getArguments());
            //['context', 'subject', 'value']
            $context         = $arguments[0] ?? '';
            $pks             = $arguments[1] ?? '';
        }



        $parts = explode('.', $context);

        $component = $parts[0];
        $part      = $parts[1] ?? '';
        $model     = $this->getModel($component, $part);

        if (!$model) {
            return;
        }

        $table = $model->getTable();
        if (!$table) {
            return;
        }


        foreach ($pks as $pk) {
            if ($table->load($pk)) {
                //in the future a extension should fire a different event
                if (\in_array($component, ['com_plugins'])) {
                    if ($table->folder !== 'blc') {
                        continue;
                    }
                    if (isset($table->element)) {
                        //we could do a $model->trashit but we already have the quickPurge code for the uninstall
                        //so lets use it.
                        $this->quickPurgeSynch($table->element);
                    }
                } else {
                    //content and custom modules
                    if (isset($table->id)) {
                        $arguments =
                            [
                                'context' => $context,
                                'id'      => $table->id,
                                'event'   => 'ondelete', // treat as a delete. So we do not have to worry about the current state. The next extract will figure it out
                            ];

                        $event = new BlcEvent('onBlcContainerChanged', $arguments);
                        $this->getApplication()->getDispatcher()->dispatch('onBlcContainerChanged', $event);
                    }
                }
            }
        }
    }
    private function importBlcPlugins()
    {
        try {
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc');
        } catch (\Error) {
            $this->getApplication()->enqueueMessage(Text::_("PLG_SYSTEM_BLC_ERROR_IMPORTPLUGIN_BLC"), 'error');
        }
    }

    private function taskBlc(ExecuteTaskEvent $event): int
    {
        $this->logTask(Text::_("PLG_SYSTEM_BLC_LOG_TASK_START"), 'warning');
        if (!$this->checkCronThrottle()) {
            $this->logTask(Text::_("PLG_SYSTEM_BLC_MSG_CRON_THROTTLE"), 'warning');
            return Status::WILL_RESUME;
        }
        self::importBlcPlugins(); //no need to load the plugins everytime
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
                $model      = $this->getModel(name: 'Links');
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
                $this->blcMailReport('Task');
                $this->logTask(Text::_("PLG_SYSTEM_BLC_LOG_TASK_REPORT"), 'info');
            } else {
                $status = Status::WILL_RESUME;
            }
        }
        BlcMutex::getInstance()->release();
        $this->logTask(Text::_("PLG_SYSTEM_BLC_LOG_TASK_END"), 'info');
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
            'text'  => Text::_('PLG_SYSTEM_BLC_QUICKICON_TXT') .
                ' <span class="badge bg-danger blc-menu-bubble"></span>' .
                '          
		<span class="d-none blcstatus Redirect">
			<span class="badge blcresponse count"></span>
		</span>
	',
            'link' => 'index.php?option=com_blc&view=links',
        ]];
        $event->setArgument('result', $result);
    }

    public function onInstallerBeforePackageDownload(Event\Event $event): bool
    {
        if ($event instanceof CMSEvent\Installer\BeforePackageDownloadEvent) {
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

            if ($event instanceof CMSEvent\Installer\BeforePackageDownloadEvent) {
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

    public function onExtensionAfterSave(Event\Event $event): void
    {

        self::importBlcPlugins(); //no need to load the plugins everytime
        if ($event instanceof CMSEvent\Model\AfterSaveEvent) {
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
    }

    public function onContentAfterDelete(Event\Event $event): void
    {
        self::importBlcPlugins(); //no need to load the plugins everytime
        if ($event instanceof CMSEvent\Model\AfterDeleteEvent) {
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
    }

    public function onContentAfterSave(Event\Event $event): void
    {

        self::importBlcPlugins(); //no need to load the plugins everytime
        if ($event instanceof CMSEvent\Model\AfterSaveEvent) {
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
    }
    public function registerCommands($event): void
    {
        $app  = $event->getApplication();
        $this->loadLanguage('com_blc', JPATH_ADMINISTRATOR);
        $app->addCommand(new CliCommand\CheckCommand());
        $app->addCommand(new CliCommand\ExtractCommand());
        $app->addCommand(new CliCommand\ReportCommand());
        $app->addCommand(new CliCommand\PurgeCommand());
    }

    private function getModel(string $component = 'com_blc', string $name = 'Link', string $prefix = 'Administrator', array $config = ['ignore_request' => true]): mixed
    {
        $mvcFactory = $this->getApplication()->bootComponent($component)->getMVCFactory();
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
            $this->loadLanguage('com_blc', JPATH_ADMINISTRATOR);
            $url = Route::link(
                'administrator',
                'index.php?option=com_config&view=component&component=com_blc'
            );
            print   '<p style="padding:50px;background-color:red">'
                . Text::sprintf("COM_BLC_SETUP_SECURITY_TOKEN", $url)
                . '</p>';
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
            print   '<p style="padding:50px;background-color:red">' . Text::_("PLG_SYSTEM_BLC_MSG_CRON_THROTTLE") . '</p>';
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
        //can't reuse the style from the module since the var's are not defined here
        ?>
        <style>
            p {
                padding: 5px;
            }

            .final {
                font-weight: bold;
                font-size: 2em;
            }

            .broken {
                background-color: red;
                color: white
            }

            .warning {
                background-color: #ff000088;
                color: white
            }

            .success {
                background-color: green;
                color: white
            }

            .redirect {
                background-color: orange;
                color: black
            }

            .timeout {
                background-color: gray;
                color: white;
            }

            .unable {
                background-color: gray;
                color: white;
            }

            .throttle {
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
        $this->loadLanguage('com_blc', JPATH_ADMINISTRATOR);
        $suppliedToken = $this->getApplication()->getInput()->getString('token', '');
        $this->checkMayCron($suppliedToken);
        $lock = BlcMutex::getInstance()->acquire();
        if (!$lock) {
            $this->maybeSendReport('check', 'HTTP');
            print Text::_("COM_BLC_LOCKED");
        }

        self::importBlcPlugins(); //no need to load the plugins everytime
        BlcHelper::setLastAction('HTTP', 'Check');
        $checkLimit = $this->componentConfig->get('check_http_limit', 10);
        $links      = $this->getModel(name: 'Links')->runBlcCheck($checkLimit, true);
        $count      = 0;
        $this->theStyle();
        foreach ($links as $link) {
            switch ($link->http_code) {
                case HTTPCODES::BLC_THROTTLE_HTTP_CODE:
                    $short  = Text::_("COM_BLC_HTTP_RESPONSE_612_SHORT");
                    $long   =  Text::_("COM_BLC_HTTP_RESPONSE_612");
                    $status = 'throttle';
                    break;
                case HTTPCODES::BLC_UNABLE_TOCHECK_HTTP_CODE:
                    $short  = Text::_("COM_BLC_HTTP_RESPONSE_609_SHORT");
                    $long   =  Text::_("COM_BLC_HTTP_RESPONSE_609");
                    $status = 'unable';
                    break;
                default:
                    if ($link->broken) {
                        $short  = Text::_("COM_BLC_BLC_BROKEN_TRUE");
                        $status = 'broken';
                    } else {
                        if ($link->redirect_count && ($link->url != $link->final_url)) {
                            $short  = Text::_("COM_BLC_HTTP_RESPONSE_3_SHORT");
                            $status = 'redirect';
                        } else {
                            $short  = Text::_("COM_BLC_BLC_BROKEN_FALSE");
                            $status = 'success';
                        }
                    }
                    break;
            }
            $code     = \sprintf('[%3s]', $link->http_code);
            $duration = \sprintf(' [%1.4f]', $link->request_duration);
            $url      = $link->toString();
            $long     = substr($link->url, 0, 200);
            print "<p class=\"$status\">$short: $code $duration - 
                         <a href=\"{$url}\" target=\"checked\">
                           $long
                         </a>
                       </p>";
        }
        $model      = $this->getModel(name: 'Links');
        $count      = $model->getToCheck(true);

        if ($count) {
            print '<p id="unchecked" class="final redirect">' . Text::sprintf("PLG_SYSTEM_BLC_CHECK_UNCHECKED", $count) . '</p>';
        } else {
            print '<p class="final success">' . Text::_("PLG_SYSTEM_BLC_CHECK_COMPLETED") . '</p>';
        }


        $this->maybeSendReport('check', 'HTTP');
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
            case 'json':
                $result = $this->blcJsonReport();
                break;
            default:
                throw new \Exception('Not supported');
        }
        $event->setReport($result);
    }


    private function maybeSendReport(string $event, string $client): string
    {
        $key = "report_{$event}";
        if ($this->componentConfig->exists($key) && !$this->componentConfig->get($key, 0)) {
            return 'Not Enabled After: ' . ucfirst($event);
        }
        return $this->blcMailReport($client);
    }

    public function onAjaxBlcExtract(): void
    {
        $app           = $this->getApplication();
        $suppliedToken = $app->getInput()->getString('token', '');
        $this->checkMayCron($suppliedToken);
        self::importBlcPlugins(); //no need to load the plugins everytime
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
        $event = $this->getModel(name: 'Links')->runBlcExtract($limit);
        $this->maybeSendReport('extract', 'HTTP');
        print "Finished - Last\n";
        return $event;
    }


    public function onAjaxBlcReport($event): string
    {
        self::importBlcPlugins(); //no need to load the plugins everytime
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
                $result = $this->blcMailReport('HTTP');
                break;
            case 'html':
                $result = $this->blcHtmlReport();
                break;
            default:
                $result = '';
                break;
        }

        if ($event instanceof CMSEvent\Plugin\AjaxEvent) {
            $event->updateEventResult($result);
        } else {
            $event->setArgument('result', $result);
        }
        return $result;
    }
    private function printInstances(int $linkID)
    {
        $model      = $this->getModel(name: 'Link');
        $root       = Uri::base();
        $instances  = $model->getInstances($linkID);

        if (\count($instances)) {
            print "<ul>";
            foreach ($instances as $instance) {
                print "<li>";
                if (!empty($instance->view)) {
                    print "<a target=\"_view\" href=\"{$root}{$instance->view}\">";
                    print $instance->title ?? '';
                    print "</a>";
                }

                if (!empty($instance->anchor)) {
                    print '&nbsp;' . TEXT::_('PLG_SYSTEM_BLC_WITH_ANCHOR') . "&nbsp;" . htmlspecialchars($instance->anchor);
                }
                if (!empty($instance->edit)) {
                    print "&nbsp;-&nbsp;<a target=\"_edit\" href=\"{$root}{$instance->edit}\">" . Text::_('JGLOBAL_EDIT') . "</a>";
                }
                print "</li>";
            }
            print "</ul>";
        }
    }
    private function blcHtmlReport($showSources = false)
    {
        return $this->report(report_source: $showSources);
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
        $query->from($db->quoteName('#__blc_links', 'l'))
            ->select($db->quoteName(['http_code', 'id', 'url', 'final_url', 'broken', 'redirect_count']))
            ->where("EXISTS (SELECT * FROM {$db->quoteName('#__blc_instances', 'i')} WHERE {$db->quoteName('i.link_id')} = {$db->quoteName('l.id')})");

        $internal = $input->get('internal', 0, 'INT');
        if ($internal == 1) {
            $query->where("{$db->quoteName('internal_url')} != {$db->quote('')}");
        }

        $external = $input->get('external', 0, 'INT');
        if ($external == 1) {
            $query->where("{$db->quoteName('internal_url')} =  {$db->quote('')}");
        }

        $checked = $input->get('checked', 1, 'INT');
        if ($checked == 1) {
            $query->where("{$db->quoteName('http_code')} != 0");
        }

        $working = $input->get('working', 0, 'INT');
        if ($working != -1) {
            $query->where("{$db->quoteName('working')} = :working")->bind(':working', $working, ParameterType::INTEGER);
        }

        $all = $input->get('all', false, 'BOOL');
        if (!$all) {
            $ors    = [];
            $broken = $input->get('broken', 1, 'INT');
            if ($broken == 1) {
                $ors[] = "{$db->quoteName('broken')} = " . HTTPCODES::BLC_BROKEN_TRUE;
            }
            $parked = $input->get('parked', 1, 'INT');
            if ($parked == 1) {
                $ors[] = "{$db->quoteName('parked')} = " . HTTPCODES::BLC_PARKED_PARKED;
            }
            $redirect = $input->get('redirect', 1, 'INT');
            if ($redirect == 1) {
                $ors[] = "{$db->quoteName('redirect_count')} > 0";
            }
            $warning = $input->get('warning', 1, 'INT');
            if ($warning == 1) {
                $ors[] = "{$db->quoteName('broken')} = " . HTTPCODES::BLC_BROKEN_WARNING;
            }


            if ($ors) {
                $query->extendWhere('AND', $ors, 'OR');
            }
        }
        $report_limit = $input->get('limit', 50, 'INT');
        $query->setLimit($report_limit);
        $query->order($db->quoteName('http_code'));

        $db->setQuery($query);
        return $db->loadObjectList('url');
    }


    //todo change to private after implementing event
    private function blcMailReport(string $client): string
    {
        BlcHelper::setLastAction($client, 'Report');
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

        $report      = '';
        $reportsSend = 0;
        $reportAll   = $this->getApplication()->getInput()->getInt('all', 0);
        foreach ($recipients as $recipient) {
            $id            = $recipient->recipient;
            $user          = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
            $transient     = "Report:$user->email";
            if ($reportAll) {
                $lastReport = 0;
            } else {
                $transientData = $transientmanager->get($transient);
                $lastReport    = $transientData->lastReport ?? 0;
            }

            if ($lastReport && (($lastReport + $throttle) > $unix)) {
                continue;
            }

            $report = $this->reportFromConfig($report_delta * $lastReport);

            if (\strlen($report)) {
                $reportsSend++;
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
                } catch (\Exception $e) {
                }
            }
            //   print "Nothing new\n";


            $data['email'] = $user->email;
            $transientmanager->set($transient, $data, true);
        }
        //users might get different reports. This is the last one.
        //not that important. Is not used for email reports
        return  Text::plural('PLG_SYSTEM_REPORTS_SEND', $reportsSend);
    }

    private function makeLink(\stdClass $item): string
    {
        $link = '';


        if (isset($item->redirect_count) && $item->redirect_count > 0) {
            $text  = 'Redirect';
            $class = "redirect";
        } else {
            $text = match ($item->broken ?? 0) {
                HTTPCODES::BLC_BROKEN_TRUE    => 'Broken',
                HTTPCODES::BLC_BROKEN_WARNING => 'Warning',
                HTTPCODES::BLC_BROKEN_TIMEOUT => 'Timeout',
                default                       => ''
            };
            $class = match ($item->broken ?? 0) {
                HTTPCODES::BLC_BROKEN_TRUE    => 'broken',
                HTTPCODES::BLC_BROKEN_WARNING => 'warning',
                HTTPCODES::BLC_BROKEN_TIMEOUT => 'timeout',
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
            $link .= '<span class="' . $class . '">'
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
    private function linkReport(QueryInterface $query, int $last, string $langPrefix, bool $showSources, int $report_limit = 10): string
    {

        $db              = $this->getDatabase();
        $query
            ->from($db->quoteName('#__blc_links', 'l'))
            ->where("EXISTS(SELECT * FROM {$db->quoteName('#__blc_instances', 'i')} WHERE {$db->quoteName('i.link_id')} = {$db->quoteName('l.id')})")
            ->select('count(*)')
            ->where("{$db->quoteName('working')} = 0");
        if ($last) {
            $query->where("{$db->quoteName('first_failure')} > FROM_UNIXTIME(:lastStamp)")
                ->bind(':lastStamp', $last, ParameterType::STRING);
        }
        $db->setQuery($query);
        $linkCount = $db->loadResult();
        if ($linkCount) {
            ob_start();
            print "<h2>" . Text::plural($langPrefix, $linkCount) . "</h2>\n";
            $query->clear('select');
            $query
                ->select($db->quoteName(['url', 'broken', 'id', 'internal_url', 'redirect_count']))
                ->setLimit($report_limit)
                ->order("{$db->quoteName('added')} DESC");
            $db->setQuery($query);
            $links       = $db->loadObjectList();
            $actualcount = \count($links);
            if ($actualcount != $linkCount) {
                print "<p><strong>" . Text::sprintf('PLG_SYSTEM_BLC_REPORT_ONLY_LAST', $actualcount) . "</p>\n";
            }
            print "<ul>\n";
            foreach ($links as $link) {
                print "<li>" . $this->makeLink($link);
                if ($showSources) {
                    $this->printInstances($link->id);
                }
                print "</li>\n";
            }

            print "</ul>\n";

            return ob_get_clean();
        }
        return '';
    }
    private function reportFromConfig(int $last): string
    {


        $report_broken   = (bool)$this->componentConfig->get('report_broken', 1);
        $report_warning  = (bool)$this->componentConfig->get('report_warning', 1);
        $report_redirect = (bool)$this->componentConfig->get('report_redirect', 1);
        $report_new      = (bool)$this->componentConfig->get('report_new', 1);
        $report_parked   = (bool)$this->componentConfig->get('report_parked', 1);
        $showSources     = (bool)$this->componentConfig->get('report_sources', 0);
        $report_limit    = $this->componentConfig->get('report_limit', 20);
        return $this->report($last, $report_broken, $report_warning, $report_redirect, $report_new, $report_parked, $report_limit, $showSources);
    }


    private function report(int $last = 0, bool $report_broken = true, bool $report_warning = true, bool $report_redirect = true, bool $report_new = false, bool $report_parked = true, int $report_limit = 50, bool $report_source = false): string
    {
        $app             = $this->getApplication();
        $input           = $app->getInput();
        $report_broken   = $input->get('broken', $report_broken, 'BOOL');
        $report_warning  = $input->get('warning', $report_warning, 'BOOL');
        $report_redirect = $input->get('redirect', $report_redirect, 'BOOL');
        $report_new      = $input->get('new', $report_new, 'BOOL');
        $report_parked   = $input->get('parked', $report_parked, 'BOOL');
        $report_limit    = $input->get('limit', $report_limit, 'INT');
        $report_source   = $input->get('source', $report_source, 'BOOL');
        $allBroken       = $input->get('all', false, 'BOOL');


        $reportContent   = [];
        $db              = $this->getDatabase();
        $query           = $db->getQuery(true);
        if ($allBroken || $report_broken) {
            $query->where("{$db->quoteName('broken')} = " . HTTPCODES::BLC_BROKEN_TRUE);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_BROKEN', $report_source, $report_limit);
        }

        if ($allBroken || $report_warning) {
            $query->clear();
            $query->where("{$db->quoteName('broken')} = " . HTTPCODES::BLC_BROKEN_WARNING);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_WARNING', $report_source, $report_limit);
        }

        if ($allBroken || $report_redirect) {
            $query->clear();
            $query->where("{$db->quoteName('redirect_count')} > 0 ")
                ->where("{$db->quoteName('broken')} != " . HTTPCODES::BLC_BROKEN_TRUE); //otherwise this might give double results wit the previous.
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_REDIRECT', $report_source, $report_limit);
        }

        if ($allBroken || $report_parked) {
            $query->clear();
            $query->where("{$db->quoteName('parked')} = " . HTTPCODES::BLC_PARKED_PARKED);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_PARKED', $report_source, $report_limit);
        }

        if ($report_new) {
            $query->clear();
            $query->where("{$db->quoteName('added')} > FROM_UNIXTIME(:lastStamp)")
                ->bind(':lastStamp', $last, ParameterType::STRING);
            $reportContent[] = $this->linkReport($query, 0, 'PLG_SYSTEM_BLC_REPORT_NEW', $report_source, $report_limit);
        }
        $reportContent = array_filter($reportContent);
        if (! $reportContent) {
            $reportContent[] = '<h2>' . Text::_("PLG_SYSTEM_BLC_REPORT_NOTHING") . '</h2>';
        }



        ob_start();
        echo join("\n", $reportContent);
        $this->theStyle();
        return ob_get_clean();
    }
}
