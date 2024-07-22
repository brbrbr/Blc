<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Based on Wordpress Broken Link Checker by WPMU DEV https://wpmudev.com/
 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;

class BlcCheckLink extends BlcModule implements BlcCheckerInterface
{
    protected $checkers = [];
    protected $linkItem = null;
    protected $internalThrottle;
    protected $externalThrottle;
    protected $sleepThrottle = false;
    protected $transientManager;
    protected static $instance = null;




    protected function init()
    {
        parent::init();
        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        //TODO hoe de database netjes

        $this->transientManager =  BlcTransientManager::getInstance();
        $this->internalThrottle = $this->componentConfig->get('throttle_internal', 1);
        $this->externalThrottle = $this->componentConfig->get('throttle_external', 15);
        $app                    = Factory::getApplication();
        if ($app->isClient('cli')) {
            $this->sleepThrottle = (bool)$this->componentConfig->get('throttle_cli', false);
        }


        $arguments = [
            'item' => $this,
        ];
        $event = new BlcEvent('onBlcCheckerRequest', $arguments);
        $app->getDispatcher()->dispatch('onBlcCheckerRequest', $event);
        $this->sortCheckers();
        $this->logCheckers();
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
        uasort($this->checkers, function ($a, $b) {
            return $a->priority <=> $b->priority;
        });
    }


    public function registerChecker($checker, $priority = 50, $always = false)
    {

        if ($checker instanceof BlcCheckerInterface) {
            $class                  = \get_class($checker);
            $newChecker             = new \stdClass();
            $newChecker->instance   = $checker;
            $newChecker->priority   = $priority;
            $newChecker->always     = $always;
            $this->checkers[$class] = $newChecker;
        }
    }

    protected function hostToPunnycode($host)
    {
        //this is a bit shorter then PunycodeHelper::urlToPunycode since we already parsed the uri
        if (!$host) {
            return;
        }
        $hostExploded = explode('.', $host);
        $newHost      =     [];

        foreach ($hostExploded as $part) {
            $newHost[] =  PunycodeHelper::toPunycode($part);
        }
        return join('.', $newHost);
    }

    protected function hostToUTF8($host)
    {
        if (!$host) {
            return;
        }
        $hostExploded = explode('.', $host);
        $newHost      =     [];

        foreach ($hostExploded as $part) {
            $newHost[] =  PunycodeHelper::fromPunycode($part);
        }
        return join('.', $newHost);
    }

    protected function getItem($id): LinkTable|bool
    {
        if (!$id) {
            return false;
        }
        $linkItem = $this->getTable();
        $linkItem->load($id);

        if (!$linkItem || $linkItem->id !== $id) {
            return false;
        }

        return $linkItem;
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
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

    public function checkLink(LinkTable &$linkItem, $results = [], object|array $options = []): array
    {



        //reset the internal link
        $linkItem->initInternal();
        $linkItem->log = [];
        $results       = [];


        //use the orginal url ( for internal, not the unsef or corrected)
        $toCheck = $linkItem->toString(
            orig: true,
            sef: true,
            xhtml: false,
            absolute: true
        );

        //don't use getInstance since we messed with the original url in initInternal
        //we could use 'Uri:reset' also but that would reset al other links as well
        //or probable parse_url, but the Uri::toString is nice to have
        $parsedItem = new Uri($toCheck);

        $host     = $this->hostToPunnycode($parsedItem->getHost());
        $now      = Factory::getDate()->toSql();
        $throttle = $linkItem->isInternal() ? $this->internalThrottle : $this->externalThrottle;

        if ($host) {
            $parsedItem->setHost($host);

            if ($this->transientManager->get($host)) {
                if ($this->sleepThrottle) {
                    print "Sleeping for throttle $host\n";
                    sleep($throttle);
                } else {
                    Factory::getApplication()->enqueueMessage("Domain Throttle", 'warning');
                    $linkItem->http_code = self::BLC_THROTTLE_HTTP_CODE;
                    $linkItem->save();
                    return $results;
                }
            }
        }

        $hasEncodeFix = self::urlencodeFixParts($parsedItem);


        $linkItem->_toCheck = $parsedItem->toString();

        $linkItem->log['start']  = $now;
        $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKING;
        $linkItem->check_count++;
        $linkItem->http_code          = 0;
        $linkItem->parked             = self::BLC_PARKED_UNCHECKED;
        $linkItem->last_check_attempt = $now;
        $linkItem->save();
        $results  = [];
        $didCheck = false;


        foreach ($this->checkers as $checker) {
            if ($didCheck === false || $checker->always === true) {
                $canCheck = $checker->instance->canCheckLink($linkItem);
                if ($canCheck !== self::BLC_CHECK_FALSE) { // self::BLC_CHECK_IGNORE will check the link here.
                    if (method_exists($checker->instance, 'initConfig')) {
                        $this->componentConfig->set('name', 'Main Checker');
                        $checker->instance->initConfig($this->componentConfig);
                    }

                    $results = $checker->instance->checkLink($linkItem, $results);
                    if (
                        $canCheck !== self::BLC_CHECK_CONTINUE
                    ) {
                        $didCheck = true;
                    }
                }
            }
        }

        if ($hasEncodeFix && $this->componentConfig->get('urlencodefix', 1) == 1) {
            $results['redirect_count'] ??= 0;
            $results['http_code'] ??= 0;
            if (
                $results['redirect_count'] == 0
                && $results['http_code'] >= 200
                && $results['http_code'] < 300
            ) {
                $results['final_url']      = $parsedItem->toString();
                $results['redirect_count'] = 1;
            }
        }


        if (($results['http_code'] ?? 0) === 0) {
            $linkItem->being_checked = self::BLC_CHECKSTATE_CHECKED;
            $linkItem->http_code     = self::BLC_UNABLE_TOCHECK_HTTP_CODE;
            $linkItem->log['Broken'] = "Unable to find Checker";
            $linkItem->broken        = self::BLC_BROKEN_TRUE;
            $this->statusChanged($linkItem);
            $linkItem->save();
            return $results;
        }

        if (isset($results['final_url']) && $results['final_url'] != $linkItem->url) {
            //does the 'if' save a lot? Probably not
            $results['final_url'] = PunycodeHelper::urlToUTF8($results['final_url']);
        }

        //todo fix this. pick results or log

        $results = $this->decideWarningState($linkItem, $results);

        $linkItem->broken    = $results['broken'] ?? $linkItem->broken ?? self::BLC_BROKEN_TRUE;
        $linkItem->http_code = $results['http_code']; //this will have a value here

        $this->statusChanged($linkItem);
        $linkItem->save($results);
        $linkItem->saveStorage();
        if ($host) {
            if ($linkItem->http_code !== self::BLC_UNCHECKED_IGNORELINK) {
                $this->transientManager->set($host, [
                    'throttle'=>$throttle, 
                    'host'=>$host,
                    'microtime'=>microtime(true),
                ], $throttle);
            }
        }
        //mailto: etc.


        return $results;
    }

    public function initConfig(Registry $config): void
    {
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

        if ($linkItem->broken == self::BLC_BROKEN_TRUE || $linkItem->broken == self::BLC_BROKEN_WARNING) {
            if ($linkItem->first_failure == 0 || $linkItem->first_failure == $nullDate) {
                $linkItem->first_failure = $linkItem->last_check;
            }

            $linkItem->log['Broken'] = "Link is broken.";
        } elseif ($linkItem->broken === self::BLC_BROKEN_TIMEOUT) {
            $linkItem->log['Timout'] = "Timeout";
        } else {
            $linkItem->first_failure = $nullDate;
            $linkItem->last_success  = $linkItem->last_check;
            $linkItem->check_count   = 1;
            $linkItem->log['Valid']  = "Link is valid.";
        }
    }

    private function decideWarningState(LinkTable &$linkItem, $results)
    {

        if (
            $linkItem->working == self::BLC_WORKING_HIDDEN || //hidden temporitly
            (
                $linkItem->working == self::BLC_WORKING_WORKING && (
                    $results['broken'] != $linkItem->broken
                    ||
                    $results['http_code'] != $linkItem->http_code
                )
            )
        ) {
            $linkItem->working = self::BLC_WORKING_ACTIVE;
        }

        $http_code             = \intval($results['http_code']);
        $failure_count         = $linkItem->check_count;

        //These could be configurable, but lets put that off until someone actually asks for it.

        $recheck_count     = $this->componentConfig->get('recheck_count', 3);
        $threshold_reached = ($failure_count >= $recheck_count);

        //we report/filter timeouts seperatly
        if ($http_code == self::BLC_TIMEOUT_HTTP_CODE) {
            if ($threshold_reached) {
                $results['broken']              = self::BLC_BROKEN_TRUE;
                $linkItem->log['Timeout']       = 'Timeouts during multiple checks';
            } else {
                $results['broken']  = self::BLC_BROKEN_TIMEOUT;
                // phpcs:disable Generic.Files.LineLength
                $linkItem->log['Timeout']       = 'Timeouts are sometimes caused by high server load or other temporary issues.';
                // phpcs:enable Generic.Files.LineLength
            }
            return $results;
        }

        if (!($results['broken'] ?? false)) {
            //Nothing to do, this is a working link.
            return $results;
        }

        if (!$this->componentConfig->get('warnings_enabled', true)) {
            //The user wants all failures to be reported as "broken", regardless of severity.
            return $results;
        }


        $warning_reason        = null;
        $maybe_temporary_error = false;

        if (\in_array($http_code, self::TEMPHTTPCODES)) {
            $maybe_temporary_error = true;
            $warning_reason        = sprintf(
                'HTTP error %d usually means that the site is down due to high server load or a configuration problem. '
                    . 'This error is often temporary and will go away after while.',
                $http_code
            );
        }

        //----------------------------------------------------------------------

        //Attempt to detect false positives.
        $suspected_false_positive = false;

        //A "403 Forbidden" error on an internal link usually means something on the site is blocking automated
        //requests. Possible culprits include hotlink protection rules in .htaccess, badly configured IDS, and so on.
        $is_internal_link = $linkItem->isInternal();
        if ($is_internal_link && (403 === $http_code)) {
            $suspected_false_positive = true;
            $warning_reason           = 'This might be a false positive. Make sure the link is not password-protected, '
                . 'and that your server is not set up to block automated requests or loopbacks.';
        }

        if ($results['broken'] && ($linkItem->log['lastHeaders']['server'] ?? '') == 'cloudflare') {
            if ($http_code == 403) {
                $suspected_false_positive = true;
                $warning_reason           = 'Cloudflare firewall';
                $http_code                = self::BLC_DNS_WAF_CODE;
                $results['http_code']     = $http_code;
            }
        } else {
            if (\in_array($http_code, self::CLOUDFLAREHTTPCODES)) {
                $maybe_temporary_error = true;
                // phpcs:disable Generic.Files.LineLength
                $warning_reason = sprintf(
                    'HTTP error %d is a specific Cloudflare error. It usually means that the site is down due to high server load or a configuration problem. '
                        . 'This error is often temporary and will go away after while.',
                    $http_code
                );
                // phpcs:enable Generic.Files.LineLength
            }
        }


        //Some hosting providers turn off loopback connections. This causes all internal links to be reported as broken.
        if ($is_internal_link && \in_array($http_code, self::INTERNALWARNINGHTTPCODES)) {
            $suspected_false_positive = true;
            $warning_reason           = 'This is probably a false positive. ';
            if (self::BLC_DNS_HTTP_CODE === $http_code) {
                $warning_reason .= 'The plugin could not connect to your site because DNS resolution failed. '
                    . 'This could mean DNS is configured incorrectly on your server.';
            } else {
                $warning_reason .= 'The plugin could not connect to your site. That usually means that your '
                    . 'hosting provider has disabled loopback connections.';
            }
        }



        //----------------------------------------------------------------------

        //Temporary problems and suspected false positives start out as warnings. False positives stay that way
        //indefinitely because they are usually caused by bugs and server configuration issues, not temporary downtime.
        if ($maybe_temporary_error || $suspected_false_positive) {
            //Upgrade temporary warnings to "broken" after X consecutive failures or Y hours, whichever comes first.
            if ($threshold_reached && !$suspected_false_positive) {
                $results['broken']  = self::BLC_BROKEN_TRUE;
            } else {
                $results['broken']  = self::BLC_BROKEN_WARNING;
            }
        }

        if (!empty($warning_reason)) {
            $formatted_reason = "\n==========\n"
                . 'Warning' . "\n"
                . 'Reason: ' . trim($warning_reason)
                . "\n==========\n";

            $linkItem->log['Warning'] = $formatted_reason;
        }

        return $results;
    }
    public static function urlencodeFixParts(Uri &$parsedItem): bool
    {
        $hasFix   = false;
        $origPart = $parsedItem->getPath();
        if ($origPart !== null) {
            $fixPart = self::urlencodeFix($origPart);
            if ($fixPart !== $origPart) {
                $hasFix = true;
                $parsedItem->setPath($fixPart);
            }
        }

        $origPart = $parsedItem->getFragment();
        if ($origPart !== null) {
            $fixPart = self::urlencodeFix($origPart);
            if ($fixPart !== $origPart) {
                $hasFix = true;
                $parsedItem->setFragment($fixPart);
            }
        }
        $origPart = $parsedItem->getQuery();
        if ($origPart !== null) {
            $fixPart = self::urlencodeFix($origPart);
            if ($fixPart !== $origPart) {
                $hasFix = true;
                $parsedItem->setQuery($fixPart);
            }
        }

        return $hasFix;
    }

    protected static function urlencodeFix(string|array $part): string|array
    {
        if (\is_array($part)) {
            return array_map([self, 'urlencodeFix'], $part);
        }
        return preg_replace_callback(
            '|[^a-z0-9\+\-\/\\#:.,;=?!&%@()$\|*~_]|i',
            function ($str) {
                return rawurlencode($str[0]);
            },
            $part
        );
    }
}
