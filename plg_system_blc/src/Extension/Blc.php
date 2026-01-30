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

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Blc\BlcMessages;
use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Checker;
use Blc\Component\Blc\Administrator\Event as BLCEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Parser;
use Blc\Plugin\System\Blc\CliCommand;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Event as CMSEvent;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\User\LoginEvent;
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
use Joomla\Database;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Event;
use Joomla\Event\DispatcherAwareInterface;
use Joomla\Module\Quickicon\Administrator\Event\QuickIconsEvent;
use Joomla\Registry\Registry;

class Blc extends CMSPlugin implements Event\SubscriberInterface, DispatcherAwareInterface, Database\DatabaseAwareInterface
{
    use TaskPluginTrait;
    use Database\DatabaseAwareTrait;





    use Event\DispatcherAwareTrait;

    private Registry $componentConfig;
    protected $autoloadLanguage     = true; //the language strings of this plugin are used in others as wel.
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

    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            $dispatcher =  Factory::getApplication()->getDispatcher(); //@phpstan-ignore method.deprecatedInterface
            parent::__construct($dispatcher, $config);
        }
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
            'onAjaxBlcUpdate'    => 'onAjaxBlcUpdate',
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
            'onContentPrepareForm'             => 'onContentPrepareForm',
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

     * @param   Model\PrepareFormEvent|Form  $context  The onContentPrepareForm event or the Form object.
     * @param   mixed                        $data     The form data, required when $context is a {@see Form} instance.
     *
     * @return boolean  True if the form was successfully enhanced or the context was not relevant.
     *
     * @since  24.44.7004
     * @throws \Exception
     */
    public function onContentPrepareForm($context, $data = null): bool
    {

        if ($context instanceof Model\PrepareFormEvent) { //J5
            $data = $context->getData();
        } elseif ($context instanceof Event\EventInterface) { //J4 && J5
            [, $data] = array_values($context->getArguments());
        }
        //ther is also Form but then we use $data so no need to get the context

        if (\is_array($data)) {
            //   Array - rafter validation failed stupid joomla
            $folder  =  $data['folder'] ?? '';
            $element = $data['element'] ?? '';
        } else {
            //        CMS Object
            $folder  = $data->folder ?? '';
            $element = $data->element ?? '';
        }
        //this it to load the language voor als de blc plugins.
        //when validating the data is emty
        if ($folder == '' || $folder == 'blc' || $element == 'blc') {
            $this->loadLanguage('com_blc');
        }

        return $this->enhanceTaskItemForm($context, $data);
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
        if ($this->componentConfig->get('plgmessages', 1)) {
            //directly into the BLC Message queue.
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

        //everyone fires the same event split for plugins
        if (\in_array($component, ['com_plugins'])) {
            $model     = $this->getModel($component, $part);
            $table     = null;
            if (!$model) {
                return;
            }
            $table = $model->getTable();

            if (!$table) {
                return;
            }

            foreach ($pks as $pk) {
                if ($table->load($pk)) {
                    if ($table->folder !== 'blc') {
                        continue;
                    }

                    //if the plugin is unpublished the synch table should be purged
                    //in case of uninstall the installer script will purge
                    //if the pluguin is republished the synch table should already be empty for this plugin
                    //however it does not harm to run it again. Should be quick

                    if (isset($table->element)) {
                        //we could do a $model->trashit but we already have the quickPurge code for the uninstall
                        //so lets use it.
                        $this->quickPurgeSynch($table->element);
                    }
                }
            }
            return;
        }

        self::importBlcPlugins(); //no need to load the plugins everytime
        //content and custom modules
        //legacy components won't work with the getModel above.
        //simply fire the event and let the extractors figure it out.
        foreach ($pks as $pk) {
            $arguments =
                [
                    'context' => $context,
                    'id'      => $pk,
                    'event'   => 'ondelete', // treat as a delete. So we do not have to worry about the current state. The next extract will figure it out
                ];

            $event = new BLCEvent\BlcEvent('onBlcContainerChanged', $arguments);
            $this->getDispatcher()->dispatch('onBlcContainerChanged', $event); //@phpstan-ignore method.deprecated
        }
    }
    private function importBlcPlugins()
    {
        try {
            $dispatcher   = $this->getDispatcher();  //@phpstan-ignore method.deprecated
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc', dispatcher: $dispatcher); //no need to load the plugins everytime
        } catch (\Error $e) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_BLC_ERROR_IMPORTPLUGIN_BLC') . ':' . $e->getMessage(), 'error');
        }
        $this->loadLanguage('com_blc', JPATH_ADMINISTRATOR);
    }

    private function taskBlc(ExecuteTaskEvent $event): int
    {
        $this->logTask(Text::_("PLG_SYSTEM_BLC_LOG_TASK_START"), 'info');
        if (!$this->checkCronThrottle()) {
            $this->logTask(Text::_("PLG_SYSTEM_BLC_MSG_CRON_THROTTLE"), 'warning');
            return Status::WILL_RESUME;
        }
        self::importBlcPlugins(); //no need to load the plugins everytime
        $params      =  $event->getArgument('params');
        $extractTask = (bool) ($params->extracttask ?? false);
        $checkTask   = (bool) ($params->checktask ?? false);
        $reportTask  = (bool) ($params->reporttask ?? false);
        //componentConfig - deprecicated
        $resumeTask  = (bool) ($params->resumeTask ?? $this->componentConfig->get('resumeTask', 1));
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
                if ($resumeTask) {
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
                if ($resumeTask) {
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

        if (parse_url((string) $url, PHP_URL_HOST) == 'downloads.brokenlinkchecker.dev') {
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

    public function onBlcParserRequest(BLCEvent\BlcParserRequestEvent $event): void
    {
        $parser = $event->getItem();
        if ($this->componentConfig->get('href', 1)) {
            $parser->registerParser(Parser\HrefParser::getInstance());
        }
        if ($this->componentConfig->get('img', 1)) {
            $parser->registerParser(Parser\ImgParser::getInstance());
        }
        if ($this->componentConfig->get('embed', 0)) {
            if ($this->componentConfig->get('aimy', 0)) {
                $parser->registerParser(Parser\AimyvideoParser::getInstance());
            }
            if ($this->componentConfig->get('src', 0)) {
                $parser->registerParser(Parser\SrcplayerParser::getInstance());
            }

            if ($this->componentConfig->get('iframe', 0)) {
                $parser->registerParser(Parser\IframeParser::getInstance());
            }
            if ($this->componentConfig->get('video', 0)) {
                $parser->registerParser(Parser\VideoParser::getInstance());
            }
        }
    }

    public function onBlcCheckerRequest(BLCEvent\BlcEvent $event): void
    {
        $checker = $event->getItem();
        //checked during installation. However a user might change to a php version
        //without curl - bad hoster bad hoster
        if (\function_exists('curl_init')) {
            $checker->registerChecker(Checker\BlcCheckerHttpCurl::getInstance(), 50);
        } else {
            throw new \Exception(Text::_('PLG_SYSTEM_BLC_NOCURL'));
        }

        if ($this->componentConfig->get('field_checker', 0) == 1) {
            $fieldChecker = Checker\BlcCheckerField::getInstance();
            $fieldChecker->setDatabase($this->getDatabase());
            $checker->registerChecker($fieldChecker, 40);
        }

        if ($this->componentConfig->get('static_checker', 1) == 1) {
            $checker->registerChecker(Checker\BlcCheckerStatic::getInstance(), 45);
        }

        if ($this->componentConfig->get('unkownprotocols', 1) == 1) {
            $checker->registerChecker(Checker\BlcCheckerUnchecked::getInstance(), 100);
        }
        if (
            $this->componentConfig->get('ignore_hosts', '')
            || $this->componentConfig->get('ignore_paths', '')
        ) {
            $checker->registerChecker(Checker\BlcCheckerPre::getInstance(), 10);
        }

        if ($this->componentConfig->get('ignore_redirects', '')) {
            $checker->registerChecker(Checker\BlcCheckerIgnoreRedirect::getInstance(), 60); //after checker
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
                'item'    => $table,
                'event'   => 'onextension',
            ];

        $event = new BLCEvent\BlcEvent('onBlcExtensionAfterSave', $arguments);
        $this->getDispatcher()->dispatch('onBlcExtensionAfterSave', $event);  //@phpstan-ignore method.deprecated
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
            $event = new BLCEvent\BlcEvent('onBlcContainerChanged', $arguments);
            $this->getDispatcher()->dispatch('onBlcContainerChanged', $event);  //@phpstan-ignore method.deprecated
        }
    }

    public function onContentAfterSave(Event\Event $event): void
    {


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

            self::importBlcPlugins(); //no need to load the plugins everytime
            $event = new BLCEvent\BlcEvent('onBlcContainerChanged', $arguments);
            $this->getDispatcher()->dispatch('onBlcContainerChanged', $event);  //@phpstan-ignore method.deprecated
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
                . Text::sprintf('COM_BLC_SETUP_SECURITY_TOKEN', $url)
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

            .alert,
            .final {
                font-weight: bold;

            }

            .error {
                font-weight: bold;
                font-size: 2em;
                background-color: red;
                color: white;
                padding: 50px;
            }

            .broken {
                background-color: red;
                color: white
            }

            .warning {
                background-color: #ff0088;
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
        try {
            $this->loadLanguage('com_blc', JPATH_ADMINISTRATOR);
            $suppliedToken = $this->getApplication()->getInput()->getString('token', '');
            $this->checkMayCron($suppliedToken);
            $lock = BlcMutex::getInstance()->acquire();
            if (!$lock) {
                $this->maybeSendReport('check', 'HTTP');
                print Text::_('COM_BLC_LOCKED');
                return;
            }

            self::importBlcPlugins(); //no need to load the plugins everytime
            BlcHelper::setLastAction('HTTP', 'Check');
            $checkLimit = $this->componentConfig->get('check_http_limit', 10);
            $links      = $this->getModel(name: 'Links')->runBlcCheck($checkLimit, true);
            $count      = 0;

            foreach ($links as $link) {
                switch ($link->http_code) {
                    case HTTPCODES::BLC_THROTTLE_HTTP_CODE:
                        $short  = Text::_('COM_BLC_HTTP_RESPONSE_612_SHORT');
                        $long   =  Text::_('COM_BLC_HTTP_RESPONSE_612');
                        $status = 'throttle';
                        break;
                    case HTTPCODES::BLC_UNABLE_TOCHECK_HTTP_CODE:
                        $short  = Text::_('COM_BLC_HTTP_RESPONSE_609_SHORT');
                        $long   =  Text::_('COM_BLC_HTTP_RESPONSE_609');
                        $status = 'unable';
                        break;
                    default:
                        if ($link->broken) {
                            $short  = Text::_('COM_BLC_BLC_BROKEN_TRUE');
                            $status = 'broken';
                        } else {
                            if ($link->redirect_count && ($link->url != $link->final_url)) {
                                $short  = Text::_('COM_BLC_HTTP_RESPONSE_3_SHORT');
                                $status = 'redirect';
                            } else {
                                $short  = Text::_('COM_BLC_BLC_BROKEN_FALSE');
                                $status = 'success';
                            }
                        }
                        break;
                }
                $code     = \sprintf('[%3s]', $link->http_code);
                $duration = \sprintf(' [%1.4f]', $link->request_duration);
                $url      = $link->toString();
                $long     = substr((string) $link->url, 0, 200);
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
        } catch (\Exception $e) {
            BlcMessages::getInstance()->enqueueMessage($e->getMessage(), 'error');
        }

        $this->getMessageQueueAsHtml();
        $this->maybeSendReport('check', 'HTTP');
        $this->theStyle();
        $app = $this->getApplication();
        $app->setHeader('Expires', 'Wed, 1 Apr 2023 00:00:00 GMT', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', false);
        $app->sendHeaders();
        $app->close();
    }

    public function onBlcReport(BLCEvent\BlcReportEvent $event)
    {
        $client     = $event->getClient();
        $action     = $event->getAction();
        $format     = $event->getFormat();
        $result     = match ($format) {
            'email' => $this->maybeSendReport($action, $client),
            'json'  => $this->blcJsonReport(),
            default => throw new \Exception('Not supported'),
        };
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
        $this->getMessageQueueAsHtml();
        $this->theStyle();
        $app->setHeader('Expires', 'Wed, 1 Apr 2023 00:00:00 GMT', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', false);
        $app->sendHeaders();
        $app->close();
    }

    private function getMessageQueueAsHtml()
    {

        $messages = BlcMessages::getInstance()->getMessageQueue(true);
        foreach ($messages as $message) {
            print "<p class=\"{$message['type']}\">{$message['message']}</p>";
        }
    }

    private function runBlcExtract(int $limit): BLCEvent\BlcExtractEvent
    {
        BlcMessages::getInstance()->enqueueMessage(Text::_('PLG_SYSTEM_BLC_CRON_STARTING_EXTRACTORS'), 'alert');
        $event = $this->getModel(name: 'Links')->runBlcExtract($limit);
        BlcMessages::getInstance()->enqueueMessage(Text::_('PLG_SYSTEM_BLC_CRON_FINISHED_EXTRACTORS'), 'alert');
        $this->maybeSendReport('extract', 'HTTP');
        return $event;
    }

    public function onAjaxBlcUpdate($event)
    {
        //only joommla 5
        if (! $event instanceof CMSEvent\Plugin\AjaxEvent) {
            $app           = $this->getApplication();
            $app->logout();
            http_response_code(406);
            return;
        }




        $app          = $event->getApplication();
        $authenticate = Authentication::getInstance('api-authentication');
        $options      = ['silent' => true, 'action' => 'core.login.api'];
        $credentials  = ['username' => ''];

        $response     = $authenticate->authenticate($credentials, $options);

        if ($response->status !== Authentication::STATUS_SUCCESS) {
            $app->logout();
            http_response_code(403);
            header("HTTP/1.0 403 Forbidden");
            header("Status: 403 Forbidden");
            return;
        }
        $dispatcher   = $this->getDispatcher();

        $input         = $app->getInput();
        $linkData      = json_decode((string) $input->json->getRaw(), true); //getArray fucks up the &amp;

        // Import the user plugin group.
        PluginHelper::importPlugin('user', null, true, $dispatcher);

        $loginEvent = new LoginEvent('onUserLogin', ['subject' => (array) $response, 'options' => $options]);

        $dispatcher->dispatch('onUserLogin', $loginEvent);


        /*
         * If any of the user plugins did not successfully complete the login routine
         * then the whole method fails.
         *
         * Any errors raised should be done in the plugin as this provides the ability
         * to provide much more information about why the routine may have failed.
         */


        $user = $app->getIdentity();
        if (!$user->authorise('core.admin')) {
            $app->logout();
            header("HTTP/1.0 403 Forbidden");
            header("Status: 403 Forbidden");
        }

        $this->loadLanguage('com_blc');
        $result = BlcCheckLink::getInstance()->manualLink($linkData);


        $event->updateEventResult($result);

        $app->logout();
        return $result;
    }


    public function onAjaxBlcReport($event): string|array
    {

        self::importBlcPlugins(); //no need to load the plugins everytime

        $this->getModel(); //boot the component to load the html servce BLC
        if ($event instanceof CMSEvent\Plugin\AjaxEvent) {
            $app = $event->getApplication();
        } else {
            $app           = $this->getApplication();
        }
        $input         = $app->getInput();
        $suppliedToken =  $input->getString('token', '');
        $this->checkMayCron($suppliedToken, true);

        // Requested format passed via URL
        $format = strtolower($input->getWord('format', ''));

        $result = match ($format) {
            'json'  => $this->blcJsonReport(),
            'raw'   => $this->blcMailReport('HTTP'),
            'html'  => $this->blcHtmlReport(),
            default => '',
        };

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
        $instances  = $model->getSynch($linkID);

        if (\count($instances)) {
            print "<ul>";
            foreach ($instances as $instance) {
                $sourcePlugin = $instance->plugin;
                $activePlugin = $model->getPlugin($sourcePlugin);
                if (!$activePlugin) {
                    continue;
                }

                $instance->view  = $activePlugin->getViewLink($instance);
                $instance->edit  = $activePlugin->getEditLink($instance);
                $instance->title = $activePlugin->getTitle($instance);

                print "<li>";
                if (!empty($instance->view)) {
                    print "<a target=\"_view\" href=\"{$root}{$instance->view}\">";
                    print $instance->title ?? '';
                    print "</a>";
                }

                if (!empty($instance->anchor)) {
                    print '&nbsp;' . TEXT::_('PLG_SYSTEM_BLC_WITH_ANCHOR') . "&nbsp;" . htmlspecialchars((string) $instance->anchor);
                }
                if (!empty($instance->edit)) {
                    print "&nbsp;-&nbsp;<a target=\"_edit\" href=\"{$root}{$instance->edit}\">" . Text::_('JGLOBAL_EDIT') . "</a>";
                }
                print "</li>";
            }
            print "</ul>";
        }
    }
    /**
     *
     * @return string
     */
    private function blcHtmlReport(): string
    {

        $reportContent = $this->reportFromConfig(0);

        if (! $reportContent) {
            $reportContent[] = '<h2>' . Text::_("PLG_SYSTEM_BLC_REPORT_NOTHING") . '</h2>';
        }

        ob_start();
        echo implode("\n", $reportContent);
        $this->theStyle();
        return ob_get_clean();
    }
    /**
     * @return  mixed  The return value or null if the query failed.
     */


    private function blcJsonReport(): array
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


        $checked = $input->get('checked', 1, 'STRING');
        if ((int)$checked === 1) {
            $query->where("{$db->quoteName('http_code')} != 0");
        } elseif ($checked) {
            $codes = explode(',', (string) $checked);
            $query->whereIN($db->quoteName('http_code'), $codes, ParameterType::INTEGER);
        }


        $working = $input->get('working', 0, 'INT');
        if ($working != -1) {
            $query->where("{$db->quoteName('working')} = :working")->bind(':working', $working, ParameterType::INTEGER);
        }

        $tocheck = $input->get('tocheck', 0, 'INT');
        if ($tocheck == 1) {
            $model      = $this->getModel(name: 'Links');
            $model->setToCheck();
            $query->where("{$db->quoteName('being_checked')} = " . HTTPCODES::BLC_CHECKSTATE_TOCHECK);
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



        $report_limit    = $this->componentConfig->get('report_limit', 50);
        $report_limit    = $input->get('limit', $report_limit, 'INT');
        $query->setLimit($report_limit);
        $orderby = $input->get('orderby', 'http_code', 'CMD');
        $order   = $input->get('order', 'ASC', 'CMD');
        $order   = match (strtolower((string) $order)) {
            'asc'   => 'ASC',
            'desc'  => 'DESC',
            default => 'ASC'
        };
        $query->order($db->quoteName($orderby) . ' ' . $order);

        $db->setQuery($query);
        $list = $db->loadObjectList('url');


        return $list;
    }


    //todo change to private after implementing event
    private function blcMailReport(string $client): string
    {
        $reports = [];
        BlcHelper::setLastAction($client, 'Report');
        $this->getModel(); //boot the component to load the html servce BLC
        $transientmanager = BlcTransientManager::getInstance();
        $recipients       = $this->componentConfig->get('recipients', []);
        $report_freq      = $this->componentConfig->get('report_freq', 7);
        $report_delta     = $this->componentConfig->get('report_delta', 1);
        $date             = new Date();
        $unix             = $date->toUnix();
        $subject          = Text::sprintf('COM_BLC_EMAIL_REPORT_SUBJECT', $date->format(Text::_('COM_BLC_EMAIL_REPORT_SUBJECT_DATETIME')));   //if used from CLI there is no timezone info.
        $throttle         = $report_freq * 3600 * 24;

        $reportsSend = 0;
        //input option to override the configuration setting
        $report_delta   = $this->getApplication()->getInput()->getInt('all', $report_delta);
        foreach ($recipients as $recipient) {
            $id            = $recipient->recipient;
            $user          = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
            $transient     = "Report:$user->email";
            $transientData = $transientmanager->get($transient);
            //so reportSince is zero if report delta is zero (and a report should always include all links)
            //or a user never received a report.
            $reportSince = $report_delta * ($transientData->lastReport ?? 0);

            if (($reportSince + $throttle) > $unix) {
                continue;
            }

            //currenlt all users have the same setting so $reportSince should be identical
            //however a user might be added
            //and later we could add a per user configuration.
            if (!isset($reports[$reportSince])) {
                $reports[$reportSince] = $this->reportFromConfig($reportSince);
            }

            $reportContent = $reports[$reportSince];

            if (!$reportContent) {
                if (($reportSince == 0)) {
                    $reportContent[] = '<h2>' . Text::_("PLG_SYSTEM_BLC_REPORT_NOTHING") . '</h2>';
                } else {
                    continue;
                }
            }



            $reportString  = implode("\n", $reportContent);

            $hash = md5($reportString);
            if ($hash == ($transientData->hash ?? '')) {
                //do not send if report is identical to previous
                //effective if report all is enabled ( report_delta = 0 or reportAll  = 1)
                continue;
            }


            if ($reportString) {
                ob_start();
                echo "<h1>" . TEXT::_("PLG_SYSTEM_BLC_DELTA_" . $report_delta) . "</h1>\n";
                echo $reportString;
                $this->theStyle();
                $reportString = ob_get_clean();

                $reportsSend++;
                $mail   = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
                $mail->addRecipient($user->email); //joomla cleaner - PHPMailer::addAddress zou ook rechtstreeks kunnen

                $mail->setBody($reportString);
                $mail->setSubject($subject);
                $mail->SMTPDebug    = false;
                $breaks             = ["<br />", "<br>", "<br/>"];
                $AltBody            = str_ireplace($breaks, "\r\n", $reportString);
                $mail->AltBody      = strip_tags($AltBody);
                $mail->isHtml(true);
                try {
                    $mail->send();
                } catch (\Exception) {
                }
            }
            //   print "Nothing new\n";

            //reset transientData
            $transientData             = new \stdClass();
            $transientData->hash       = $hash;
            $transientData->lastReport = $unix;
            $transientmanager->set($transient, $transientData, true);
        }

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
    private function linkReport(QueryInterface $query, int $last, string $langPrefix, bool $showSources, int $report_limit = 50, $sort = 'added-DESC'): string
    {

        $db              = $this->getDatabase();
        [$sort, $order]  = explode('-', (string) $sort) + ['added', 'DESC'];
        $order           = match (strtolower($order)) {
            'desc'  => 'DESC',
            'asc'   => 'ASC',
            default => 'DESC'
        };
        $sort = match (strtolower($sort)) {
            'added'     => 'added',
            'url'       => 'url',
            'http_code' => 'http_code',
            default     => 'added'
        };

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
                ->order("{$db->quoteName($sort)} $order");
            $db->setQuery($query);
            $links       = $db->loadObjectList();
            $actualcount = \count($links);
            if ($actualcount != $linkCount) {
                print "<p><strong>" . Text::sprintf('PLG_SYSTEM_BLC_REPORT_ONLY_LAST', $actualcount) . "</strong></p>\n";
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
    /**
     * return array<string>
     */
    private function reportFromConfig(int $last): array
    {
        $report_broken   = (bool)$this->componentConfig->get('report_broken', 1);
        $report_warning  = (bool)$this->componentConfig->get('report_warning', 1);
        $report_redirect = (bool)$this->componentConfig->get('report_redirect', 1);
        $report_new      = (bool)$this->componentConfig->get('report_new', 1);
        $report_parked   = (bool)$this->componentConfig->get('report_parked', 1);
        $showSources     = (bool)$this->componentConfig->get('report_sources', 0);
        $report_limit    = $this->componentConfig->get('report_limit', 50);

        return $this->generateReport($last, $report_broken, $report_warning, $report_redirect, $report_new, $report_parked, $report_limit, $showSources);
    }




    /**
     *
     * @return array<string>
     */
    private function generateReport(
        int $last = 0,
        bool $report_broken = true,
        bool $report_warning = true,
        bool $report_redirect = true,
        bool $report_new = false,
        bool $report_parked = true,
        int $report_limit = 50,
        bool $report_source = false
    ): array {
        $app             = $this->getApplication();
        $input           = $app->getInput();
        $report_broken   = $input->get('broken', $report_broken, 'BOOL');
        $report_warning  = $input->get('warning', $report_warning, 'BOOL');
        $report_redirect = $input->get('redirect', $report_redirect, 'BOOL');
        $report_new      = $input->get('new', $report_new, 'BOOL');
        $report_parked   = $input->get('parked', $report_parked, 'BOOL');
        $report_limit    = $input->get('limit', $report_limit, 'INT');
        $report_source   = $input->get('source', $report_source, 'BOOL');
        $sort            = $input->get('sort', 'added-DESC', 'CMD');
        $allBroken       = $input->get('all', false, 'BOOL');
        ;
        $reportContent   = [];
        $db              = $this->getDatabase();
        $query           = $db->getQuery(true);
        if ($allBroken || $report_broken) {
            $query->where("{$db->quoteName('broken')} = " . HTTPCODES::BLC_BROKEN_TRUE);

            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_BROKEN', $report_source, $report_limit, $sort);
        }

        if ($allBroken || $report_warning) {
            $query->clear();
            $query->where("{$db->quoteName('broken')} = " . HTTPCODES::BLC_BROKEN_WARNING);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_WARNING', $report_source, $report_limit, $sort);
        }

        if ($allBroken || $report_redirect) {
            $query->clear();
            $query->where("{$db->quoteName('redirect_count')} > 0 ")
                ->where("{$db->quoteName('broken')} != " . HTTPCODES::BLC_BROKEN_TRUE); //otherwise this might give double results wit the previous.
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_REDIRECT', $report_source, $report_limit, $sort);
        }

        if ($allBroken || $report_parked) {
            $query->clear();
            $query->where("{$db->quoteName('parked')} = " . HTTPCODES::BLC_PARKED_PARKED);
            $reportContent[] = $this->linkReport($query, $last, 'PLG_SYSTEM_BLC_REPORT_PARKED', $report_source, $report_limit, $sort);
        }

        if ($report_new) {
            $query->clear();

            $query->where("{$db->quoteName('added')} > FROM_UNIXTIME(:lastStamp)")
                ->bind(':lastStamp', $last, ParameterType::STRING);
            $reportContent[] = $this->linkReport($query, 0, 'PLG_SYSTEM_BLC_REPORT_NEW', $report_source, $report_limit, $sort);
        }
        $reportContent = array_filter($reportContent);
        return $reportContent;
    }
}
