<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpBase;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerIgnoreRedirect;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Uri\Uri;
use Symfony\Component\Console\Style\SymfonyStyle;

class BlcCheckLink extends BlcModule implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */

    protected static ?BlcModule $instance = null;



    protected $checkers = [];
    protected $linkItem = null;
    protected $internalThrottle;
    protected $externalThrottle;
    protected $sleepThrottle = false;
    protected $transientManager;

    protected function init()
    {
        parent::init();
        $app                    = Factory::getApplication();
        try {
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        } catch (\Error $e) {
            $app->enqueueMessage(Text::_('COM_BLC_ERROR_IMPORTPLUGINS_BLC') . ':' . $e->getMessage(), 'error');
        }

        //TODO hoe de database netjes

        $this->transientManager =  BlcTransientManager::getInstance();
        $this->internalThrottle = $this->componentConfig->get('throttle_internal', 1);
        $this->externalThrottle = $this->componentConfig->get('throttle_external', 15);

        if ($app->isClient('cli')) {
            $this->sleepThrottle = (bool)$this->componentConfig->get('throttle_cli', false);
        }
        $this->requestCheckers();
    }
    /**
     * @since 24.44.6964
     *
     *s
     *
     */
    protected function requestCheckers()
    {
        $this->clearCheckers();
        $app                    = Factory::getApplication();
        $arguments              = [
            'item' => $this,
        ];
        $event = new BlcEvent('onBlcCheckerRequest', $arguments);
        $app->getDispatcher()->dispatch('onBlcCheckerRequest', $event);
    }

    protected function logCheckers()
    {
        $eventName = 'onBlcCheckerRequest';
        $list      = [];
        foreach ($this->checkers as $class => $checker) {
            $list[$class] = $checker->priority;
        }
        $this->transientManager->set('lastListeners:' . $eventName, $list, true);
    }

    protected function sortCheckers()
    {
        uasort($this->checkers, fn ($a, $b) => $a->priority <=> $b->priority);
    }
    /**
     * @since 25.44.7314
     */

    public function unRegisterChecker($class)
    {
        unset($this->checkers[$class]);
        $this->logCheckers();
    }

    /**
     * @since 25.44.7314
     */
    public function getCheckers(): array
    {
        return $this->checkers ?? [];
    }
    public function clearCheckers()
    {
        $this->checkers = [];
        $this->logCheckers();
    }

    public function registerChecker(BlcCheckerInterface $checker, $priority = 50)
    {

        $class                  = $checker::class;

        if (isset($this->checkers[$class])) {
            throw new \Exception(\sprintf('Checker with name %s already registered, unregister it first', $class));
        }

        $newChecker             = new \stdClass();
        $newChecker->instance   = $checker;
        $newChecker->priority   = $priority;
        $this->checkers[$class] = $newChecker;

        $this->sortCheckers();
        $this->logCheckers();
    }

    /**
     * @since 25.44.7314
     * the checker is wrapped in a container for the prioroty.
     */

    public function getChecker(string $class): ?\stdclass
    {
        return $this->checkers[$class] ?? null;
    }

    protected function getItem(int $id): LinkTable|bool
    {
        if (!$id) {
            return false;
        }
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $linkItem = new LinkTable($db);
        $linkItem->load($id);

        if (!$linkItem || $linkItem->id !== $id) {
            return false;
        }

        return $linkItem;
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        //simpler version is urlencodeFixParts needed?
        //maybe ->url would be enough?

        foreach ($this->checkers as $checker) {
            $cancheck = $checker->instance->canCheckLink($linkItem);
            if ($cancheck === self::BLC_CHECK_TRUE) {
                return  self::BLC_CHECK_TRUE;
            }
            // self::BLC_CHECKIGNORE will prevent the link from being indexed in BlcParser
            if ($cancheck === self::BLC_CHECK_IGNORE) {
                return  self::BLC_CHECK_IGNORE;
            }
        }
        return self::BLC_CHECK_FALSE;
    }

    public function manualLink(array $result): array
    {

        $url      = $result['url'];

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $linkItem = new LinkTable($db);
        $pk       = ['url' => $url];


        $linkItem->load($pk);
        $linkItem->bind($pk);



        $now      = Factory::getDate()->toSql();

        $linkItem->log                   = [];
        $linkItem->log['start']          = $now;
        $linkItem->log['manual request'] = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);




        $previousBroken               = $linkItem->broken ?? 0;
        $previousHttpCode             = $linkItem->http_code ?? 0;
        $httpCode                     = \intval($result['http_code']);
        $linkItem->broken             =  BlcCheckerHttpBase::getInstance()->isErrorCode($httpCode);
        $linkItem->http_code          = $httpCode;
        //reset the internal link
        $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKED;
        $linkItem->check_count++;

        $linkItem->redirect_count          = \intval($result['redirect_count']);
        $linkItem->final_url               = $result['final_url'] ?? $url;
        $linkItem->parked                  = self::BLC_PARKED_UNCHECKED;
        $linkItem->last_check_attempt      = $now;

        $linkItem->request_duration = \floatval($result['request_duration']);

        /**
         * @since 24.44.6882
         * Ignore redirect if the final url equals the orignal one. This happens with WAF redirects
         * this is done here so we can add a checker that removes unwanted query parameters after a CURL check.
         **/
        $linkItem->final_url ??= $linkItem->url;

        if (
            ($linkItem->final_url == $linkItem->url)

            && $linkItem->http_code >= 200
            && $linkItem->http_code < 300
        ) {
            $linkItem->redirect_count = 0;
        }
        $ignoreRedirectChecker = BlcCheckerIgnoreRedirect::getInstance();
        if (self::BLC_CHECK_TRUE == $ignoreRedirectChecker->canCheckLink($linkItem)) {
            $ignoreRedirectChecker->checkLink($linkItem);
        }

        $this->decideWarningState($linkItem, $previousBroken, $previousHttpCode);
        $this->statusChanged($linkItem);
        $linkItem->save();

        $linkItem->saveStorage();
        return get_object_vars($linkItem);
    }

    public function checkLink(LinkTable &$linkItem): void
    {


        $linkItem->log = [];


        //don't use getInstance since we messed with the original url in initInternal
        //we could use 'Uri:reset' also but that would reset all other links as well
        //or probable parse_url, but the Uri::toString is nice to have
        try {
        $parsedItem = new Uri($linkItem->toCheck);
    } catch (\RuntimeException) {
        $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKED;
        $linkItem->http_code     = self::BLC_INVALID_URL_HTTP_CODE;
        $linkItem->log['Broken'] = "Invalid URL";
        $linkItem->broken        = self::BLC_BROKEN_TRUE;
        return;
    }



        $host     = UrlHelper::hostToPunnycode($parsedItem->getHost()??'');
        $now      = Factory::getDate()->toSql();
        $throttle = $linkItem->isInternal() ? $this->internalThrottle : $this->externalThrottle;
        if ($host) {
            $parsedItem->setHost($host);
            if ($this->transientManager->get($host)) {
                if ($this->sleepThrottle) {
                    //we are running cli here
                    $style = new SymfonyStyle(Factory::getApplication()->getConsoleInput(), Factory::getApplication()->getConsoleOutput());
                    $style->note(Text::sprintf('COM_BLC_MESSAGE_SLEEPING_THROTTLE', $host));
                    sleep($throttle);
                } else {
                    Factory::getApplication()->enqueueMessage(Text::sprintf('COM_BLC_MESSAGE_SKIPPING_THROTTLE', $host), 'warning');
                    $linkItem->http_code = self::BLC_THROTTLE_HTTP_CODE;
                    $linkItem->save();
                    return;
                }
            }
        }

        $hasEncodeFix = UrlHelper::urlencodeFixParts($parsedItem);

        $linkItem->toCheck       = $parsedItem->toString();  //_ pseudo private property for Table/database
        $previousBroken          = $linkItem->broken ?? 0;
        $previousHttpCode        = $linkItem->http_code ?? 0;
        $linkItem->log['start']  = $now;
        $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKING;
        $linkItem->check_count++;
        $linkItem->http_code               = 0;
        $linkItem->redirect_count          = 0;
        $linkItem->parked                  = self::BLC_PARKED_UNCHECKED;
        $linkItem->last_check_attempt      = $now;
        $linkItem->save();

        $options = $this->componentConfig; //this allows checkers to change the options.
        foreach ($this->checkers as $checker) {
            try {
                $canCheck = $checker->instance->canCheckLink($linkItem);
                //this might happen when the settings are changed after extracting content
                if ($canCheck === self::BLC_CHECK_IGNORE) {
                    if ($linkItem->id !== null) {
                        $linkItem->delete();
                        return;
                    }
                }

                if ($canCheck !== self::BLC_CHECK_FALSE) {
                    $checker->instance->checkLink($linkItem, $options);
                }
            } catch (\Error $e) {
                $class = $checker->instance::class;
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_BLC_ERROR_CHECKLINK_BLC', $class, $e->getMessage()), 'error');
            }
        }

        if ($hasEncodeFix && $this->componentConfig->get('urlencodefix', 0) == 1) {
            if (
                $linkItem->final_url === ''
                && $linkItem->redirect_count == 0
                && $linkItem->http_code >= 200
                && $linkItem->http_code < 300
            ) {
                $linkItem->final_url      = $parsedItem->toString();
                $linkItem->redirect_count = 1;
            }
        }


        /**
         * @since 24.44.6882
         * Ignore redirect if the final url equals the orignal one. This happens with WAF redirects
         * this is done here so we can add a checker that removes unwanted query parameters after a CURL check.
         **/
        $linkItem->final_url ??= $linkItem->url;
        if (
            ($linkItem->final_url == $linkItem->url)
            && $linkItem->redirect_count > 0
            && $linkItem->http_code >= 200
            && $linkItem->http_code < 300
        ) {
            $linkItem->redirect_count = 0;
        }
        //todo fix this. pick results or log
        $linkItem->broken ??= self::BLC_BROKEN_TRUE;

        if ($linkItem->http_code === 0) {
            $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKED;
            $linkItem->http_code     = self::BLC_UNABLE_TOCHECK_HTTP_CODE;
            $linkItem->log['Broken'] = "Unable to find Checker";
            $linkItem->broken        = self::BLC_BROKEN_TRUE;
        }

        if ($linkItem->final_url === '') {
            //  if (strpos($linkItem->url, UrlHelper::punycodePrefix) !== false) {
            $fromPunnyCode = UrlHelper::urlToUTF8($linkItem->url);

            if ($fromPunnyCode !== $linkItem->url) {
                $linkItem->final_url      = $fromPunnyCode;
                $linkItem->redirect_count = 1;
            }
            //  }
        } else {
            if (str_contains($linkItem->final_url, UrlHelper::punycodePrefix)) {
                $linkItem->final_url = UrlHelper::urlToUTF8($linkItem->final_url);
            }
        }

        $this->decideWarningState($linkItem, $previousBroken, $previousHttpCode);


        $this->statusChanged($linkItem);

        $linkItem->save();
        $linkItem->saveStorage();
        if ($host) {
            switch ($linkItem->http_code) {
                case self::BLC_UNCHECKED_IGNORELINK: /*nothing mailto: etc.*/
                    break;
                case self::BLC_STATIC_FOUND_HTTP_CODE:
                    $this->transientManager->delete($host);
                    break;
                default:
                    $this->transientManager->set($host, [
                        'throttle' => $throttle,
                        'host'     => $host,
                        'saved'    => Factory::getDate("now $throttle SECONDS")->toSql(),
                    ], $throttle);
            }
        }
    }


    public function checkLinkId(int $id): LinkTable|bool
    {

        if (!$id) {
            return false;
        }

        $linkItem = $this->getItem($id);

        //not found or not valid
        if ($linkItem === false) {
            return false;
        }


        $this->checkLink($linkItem);

        return $linkItem;
    }

    private function statusChanged(LinkTable &$linkItem)
    {
        $db                      = Factory::getContainer()->get(DatabaseInterface::class);
        $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKED;
        $linkItem->last_check    = $linkItem->last_check_attempt;
        $nullDate                = $db->getNullDate();
        $lbl                     = Text::_('COM_BLC_FORM_LBL_LINK_STATE');
        if ($linkItem->broken == self::BLC_BROKEN_TRUE || $linkItem->broken == self::BLC_BROKEN_WARNING) {
            if ($linkItem->first_failure == 0 || $linkItem->first_failure == $nullDate) {
                $linkItem->first_failure = $linkItem->last_check;
            }
            $linkItem->log[$lbl] = Text::_('COM_BLC_MESSAGE_LINK_STATUS_BROKEN_WARMING');
        } elseif ($linkItem->broken === self::BLC_BROKEN_TIMEOUT) {
            $linkItem->log[$lbl] = Text::_('COM_BLC_BLC_BROKEN_TIMEOUT');
        } else {
            $linkItem->first_failure = $nullDate;
            $linkItem->last_success  = $linkItem->last_check;
            $linkItem->check_count   = 1;
            $linkItem->log[$lbl]     = Text::_('COM_BLC_BLC_WORKING');
        }
    }

    private function decideWarningState(LinkTable &$linkItem, int $previousBroken, int $previousHttpCode)
    {
        $http_code  = \intval($linkItem->http_code);
        $broken     = \intval($linkItem->broken);

        if (
            $linkItem->working == self::BLC_WORKING_HIDDEN || //hidden temporitly
            (
                $linkItem->working == self::BLC_WORKING_WORKING && (
                    $previousBroken != $broken
                    ||
                    $previousHttpCode != $http_code
                )
            )
        ) {
            $linkItem->working = self::BLC_WORKING_ACTIVE;
        }

        $failure_count         = $linkItem->check_count;

        //These could be configurable, but lets put that off until someone actually asks for it.

        $recheck_count     = $this->componentConfig->get('recheck_count', 3);
        $threshold_reached = ($failure_count >= $recheck_count);

        //we report/filter timeouts seperatly
        if ($http_code == self::BLC_TIMEOUT_HTTP_CODE) {
            $lbl = Text::_('COM_BLC_BLC_BROKEN_TIMEOUT');
            if ($threshold_reached) {
                $broken                         = self::BLC_BROKEN_TRUE;
                $linkItem->log[$lbl]            = Text::_('COM_BLC_MESSAGE_LINK_STATUS_TIMEOUT_FINAL');
            } else {
                $broken              = self::BLC_BROKEN_TIMEOUT;
                $linkItem->log[$lbl] = Text::_('COM_BLC_MESSAGE_LINK_STATUS_TIMEOUT_TEMPORARY');
            }
        }


        if (!$broken) {
            //Nothing to do, this is a working link.
            return;
        }

        if (!$this->componentConfig->get('warnings_enabled', true)) {
            //The user wants all failures to be reported as "broken", regardless of severity.
            return;
        }


        $warning_reason        = null;
        $maybe_temporary_error = false;

        if (\in_array($http_code, self::TEMPHTTPCODES)) {
            $maybe_temporary_error = true;
            $warning_reason        = Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_TEMPHTTPCODES', $http_code);
        }

        //----------------------------------------------------------------------

        //Attempt to detect false positives.
        $suspected_false_positive = false;

        //A "403 Forbidden" error on an internal link usually means something on the site is blocking automated
        //requests. Possible culprits include hotlink protection rules in .htaccess, badly configured IDS, and so on.
        $is_internal_link = $linkItem->isInternal();
        if ($is_internal_link && (403 === $http_code)) {
            $suspected_false_positive = true;
            $warning_reason           = Text::_('COM_BLC_MESSAGE_LINK_STATUS_FALSE_POSITIVE') . ' ' . Text::_('COM_BLC_MESSAGE_LINK_STATUS_403_INTERNAL');
        }

        if ($broken && ($linkItem->log['Last Headers']['server'] ?? '') == 'cloudflare') {
            if ($http_code == 403) {
                $suspected_false_positive = true;
                $warning_reason           = Text::_('COM_BLC_MESSAGE_LINK_STATUS_403_WAF');
                $http_code                = self::BLC_DNS_WAF_CODE;
            }
        } else {
            if (\in_array($http_code, self::CLOUDFLAREHTTPCODES)) {
                $maybe_temporary_error = true;
                $warning_reason        = Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_CLOUDFLAREHTTPCODES', $http_code);
            }
        }


        //Some hosting providers turn off loopback connections. This causes all internal links to be reported as broken.
        if ($is_internal_link && \in_array($http_code, self::INTERNALWARNINGHTTPCODES)) {
            $suspected_false_positive = true;
            $warning_reason           = Text::_('COM_BLC_MESSAGE_LINK_STATUS_FALSE_POSITIVE');
            if (self::BLC_DNS_HTTP_CODE === $http_code) {
                $warning_reason .= Text::_('COM_BLC_MESSAGE_LINK_STATUS_INTERNAL_BLC_DNS_HTTP_CODE');
            } else {
                $warning_reason .= Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_INTERNALWARNINGHTTPCODES', $http_code);
            }
        }



        //----------------------------------------------------------------------

        //Temporary problems and suspected false positives start out as warnings. False positives stay that way
        //indefinitely because they are usually caused by bugs and server configuration issues, not temporary downtime.
        if ($maybe_temporary_error || $suspected_false_positive) {
            //Upgrade temporary warnings to "broken" after X consecutive failures or Y hours, whichever comes first.
            if ($threshold_reached && !$suspected_false_positive) {
                $broken = self::BLC_BROKEN_TRUE;
            } else {
                $broken  = self::BLC_BROKEN_WARNING;
            }
        }

        if (!empty($warning_reason)) {
            $lbl                 = Text::_('COM_BLC_BLC_BROKEN_TIMEOUT');
            $formatted_reason    =  Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_WARNING_FORMATTED_REASON', trim($warning_reason));
            $linkItem->log[$lbl] = $formatted_reason;
        }
        $linkItem->http_code = $http_code;
        $linkItem->broken    = $broken;
    }
    public static function urlencodeFixParts(Uri &$parsedItem, $parts = ['path', 'fragment', 'query']): bool
    {
        @trigger_error(
            "Using 'BlcCheckLink::urlencodeFixParts' is deprecated use UrlHelper::urlencodeFixParts",
            E_USER_DEPRECATED
        );
        return UrlHelper::urlencodeFixParts($parsedItem, $parts);
    }
}
