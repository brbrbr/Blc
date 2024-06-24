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

namespace Blc\Component\Blc\Administrator\Checker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Helper\BlcHelper; //using constants but not implementing
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Composer\CaBundle\CaBundle;
use  Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

class BlcCheckerHttpBase extends BlcModule
{
    protected $userAgent          = "";
    protected $headers            = [];
    protected $cookies            = [];
    protected $timeOut            = 10;
    protected $referer            = '';
    protected $maxRedirs          = 5;
    protected $validSsl           = 2;
    protected $useRange           = true;
    protected $forceResponse      = HTTPCODES::CHECKER_LOG_RESPONSE_AUTO;
    protected $useFollowRedirects = true;
    protected $useHead            = true;
    protected static $instance    = null;
    protected $cookieJar          = '';
    protected $HSTSJar            = '';
    protected $cacheDir           = '';
    protected $acceptLanguage     = 'en-US,en;q=0.5';
    protected $sslVersion         = 0;
    protected $token              = '';
    protected $isCli              = false;
    protected $caFile             = false;
    protected $dynamicSecFetch    = true;
    protected $checkerName        = 'Main Checker';
    /**
     * Here for save keeping. This array as patters to find dommain sellers like dan.com and sedo
     * @var array
     */



    protected function init()
    {
        parent::init();
        $app        = Factory::getApplication();
        $admin_info = ApplicationHelper::getClientInfo('administrator', true);
        //the cache dir might be cleaned too often
        $this->cacheDir =  Path::clean($admin_info->path)  . '/logs/com_blc';
        if (!is_dir($this->cacheDir)) {
            Folder::create($this->cacheDir);
        }
        $this->referer = BlcHelper::root(); #uri:root is buggy on CLI
        $this->token   = md5($app->get('secret') . $this->referer);
        $this->isCli   = $app->isClient('cli');

        $this->HSTSJar = $this->cacheDir . '/' . $this->token . '.hsts';
        $this->initConfig($this->componentConfig);
    }
    public function initConfig(Registry $config): void
    {

        if ($config->get('cookies', 1)) {
            $this->cookieJar = $this->cacheDir . '/' . $this->token . '.cookies';
        } else {
            $this->cookieJar = false;
        }

        $this->timeOut = max(
            1,
            min(
                30,
                $config->get($this->isCli ? 'timeout_cli' : 'timeout_http', $this->timeOut)
            )
        );

        $this->acceptLanguage = $config->get('accept-language', $this->acceptLanguage);

        $signature = $this->setSignature($config->get('signature', 'firefox'));
        if (!isset($signature['Accept-Language'])) {
            $this->setLanguage(
                $config->get('language', 0),
                $config->get('accept-language', $this->acceptLanguage)
            );
        }

        $this->validSsl = $config->get('valid_ssl', $this->validSsl);

        if ($this->isOpenBasedir()) {
            $this->useFollowRedirects = false;
        } else {
            $this->useFollowRedirects = (bool)$config->get('follow', $this->useFollowRedirects);
            $this->maxRedirs          = (int)$config->get('maxredirs', $this->maxRedirs);
        }

        $this->dynamicSecFetch = (bool)$config->get('dynamicSecFetch', $this->dynamicSecFetch);
        $this->__set('sslversion', $config->get('sslversion', 0));
        $this->__set('response', $config->get('response', $this->forceResponse));
        $this->__set('name', $config->get('name', $this->checkerName));



        $this->setcaFile(
            $config->get('cafilesource', ''),
            $config->get('cafile', '')
        );
    }

    protected function setcaFile($ca, $caFile)
    {
        $this->caFile = false;
        if ($ca === '') {
            return;
        }

        switch ($ca) {
            case 'Bundled':
                $this->caFile = CaBundle::getBundledCaBundlePath(); //trusted
                break;
            case 'System':
                $this->caFile = CaBundle::getSystemCaRootBundlePath(); //trusted
                break;
            case 'Custom':
                $this->__set('cafile', $caFile); // not trusted
                break;
        }
    }

    private static function validateCaFile($certFile)
    {

        if (!$certFile) {
            return false;
        }
        $certFile = Path::clean($certFile);

        if (!is_file($certFile)) {
            $certFile = Path::clean(JPATH_ROOT . '/' . $certFile);

            if (!is_file($certFile)) {
                return false;
            }
        }
        if (
            is_readable($certFile)
            && CaBundle::validateCaFile($certFile)
        ) {
            return $certFile;
        }
        return false;
    }

    protected function setLanguage($language, $languageString)
    {
        if ($language == 0) {
            $languages      = LanguageHelper::getLanguages();
            $languageAccept = [];
            foreach ($languages as $lang) {
                $langCode                  = $lang->lang_code;
                $short                     = explode('-', $langCode);
                $languageAccept[$short[0]] = $short[0];
                $languageAccept[$langCode] = $langCode;
            }

            $q = 1.0;
            array_walk(
                $languageAccept,
                function (&$item) use (&$q) {
                    if ($q < 1) {
                        $item .= ";q=$q";
                    }

                    $q = max(0.3, $q - 0.1);
                }
            );
            $languageAccept['en-US'] ??= "en-US;q=0.2";
            $languageAccept['en'] ??= "en;q=0.1";
            $languageAcceptString = join(',', $languageAccept);
        } else {
            $languageAcceptString = $languageString;
        }
        $this->acceptLanguage = $languageAcceptString;
    }

    protected function setSignature($signature)
    {

        $signature = $this->loadSignature($signature);
        if (isset($signature['Accept-Language'])) {
            $this->acceptLanguage = $signature[' Accept-Language'];
        }
        $this->userAgent = $signature['userAgent'] ?? 'Joomla fetcher';
        $this->__set('headers', $signature['headers'] ?? []); //takes care of spliting
        return $signature;
    }

    public function loadSignature($browser)
    {
        $admin_info = ApplicationHelper::getClientInfo('administrator', true);
        $file       = Path::clean($admin_info->path  . "/components/com_blc/forms/signatures/{$browser}.json");
        $signature  = [];
        if (is_file($file)) {
            $signature = json_decode(file_get_contents($file), true);
        }
        if (!$signature) {
            $signature = $this->loadSignature('firefox');
        }

        return $signature;
    }


    protected function dynamicSecFetch(LinkTable &$linkItem)
    {
        if (!$this->dynamicSecFetch) {
            return;
        }
        $isInteral    = $linkItem->isInternal();
        $secFetchSite = $isInteral ? 'same-site' : 'cross-site';
        $this->replaceHeader("Sec-Fetch-Site: $secFetchSite");
    }

    public function removeHeader(string $header)
    {
        $key           = strtok($header, ':');
        $partialString = "$key:";
        $this->headers =  array_filter($this->headers, function ($item) use ($partialString) {
            return stripos($item, $partialString) !== 0;
        });
    }

    public function replaceHeader(string $header)
    {
        $this->removeHeader($header);
        $this->addHeader($header);
    }
    public function addHeader(string $header)
    {
        $this->headers[] = $header;
    }
    public function clearHeaders()
    {
        $this->headers = [];
    }

    public function addCookie(string $cookie)
    {
        $this->cookies[] = $cookie;
    }
    public function clearCookies()
    {
        $this->cookies = [];
    }

    public function __get($name)
    {
        $name = strtolower($name);
        switch ($name) {
            case 'useragent':
                return  $this->userAgent;
                break;
            default:
                return null;
        }
    }

    public function __set($name, $value)
    {
        $name = strtolower($name);

        switch ($name) {
            case 'language':
                if (\is_string($value)) {
                    $this->acceptLanguage = $value;
                }
                break;
            case 'referer':
                if (\is_string($value)) {
                    $this->referer = $value;
                }
                break;
            case 'cookies':
                switch (true) {
                    case \is_array($value):
                        $this->cookies = $value;
                        break;
                    case \is_object($value):
                        $this->cookies = (array)$value;
                        break;
                    case \is_string($value):
                        $this->cookies = preg_split($this->splitOption, $value);
                        break;
                }
                break;
            case 'headers':
                switch (true) {
                    case \is_array($value):
                        $this->headers = $value;
                        break;
                    case \is_object($value):
                        $this->headers = (array)$value;
                        break;
                    case \is_string($value):
                        $this->headers = preg_split($this->splitOption, $value);
                        break;
                }
                break;
            case 'range':
                $this->useRange = (bool)$value;
                break;
            case 'follow':
                $this->useFollowRedirects = (bool)$value;
                break;
            case 'head':
                $this->useHead = (bool)$value;
                break;

            case 'cafile':
                $this->caFile = false;

                if ($value = $this->validateCaFile($value)) {
                    $this->caFile = $value;
                }
                break;
            case 'sslversion':
                if (!is_numeric($value)) {
                    $value = \constant($value);
                }
                $this->sslVersion = (int)$value;
                break;
            case 'timeout':
                $this->timeOut = (int)$value;
                break;
            case 'validssl':
                $this->validSsl = (int)$value;
                break;
            case 'maxredirs':
                $this->maxRedirs = (int)$value;
                break;
            case 'signature':
                if (\is_string($value)) {
                    $this->setSignature($value);
                }
                break;

            case 'useragent':
                if (\is_string($value)) {
                    $this->userAgent = $value;
                }
                break;

            case 'name':
                if (\is_string($value)) {
                    $this->checkerName = $value;
                }
                break;

            case 'response':
                if (
                    \in_array($value, [
                        HTTPCODES::CHECKER_LOG_RESPONSE_ALWAYS,
                        HTTPCODES::CHECKER_LOG_RESPONSE_AUTO,
                        HTTPCODES::CHECKER_LOG_RESPONSE_NEVER,
                        HTTPCODES::CHECKER_LOG_RESPONSE_TEXT,
                    ])
                ) {
                    $this->forceResponse = $value;
                }


                if (
                    $this->forceResponse === HTTPCODES::CHECKER_LOG_RESPONSE_ALWAYS
                    || $this->forceResponse === HTTPCODES::CHECKER_LOG_RESPONSE_TEXT
                ) {
                    $this->__set('range', false);
                    $this->__set('head', false);
                }

                break;
            default:
        }
    }

    public function isErrorCode($http_code)
    {
        /*"Good" response codes are anything in the 2XX range (e.g "200 OK") and redirects  - the 3XX range.
        and some custom codes         */
        $good_code = (($http_code >= 200) && ($http_code < 400)) || \in_array($http_code, HTTPCODES::GOODHTTPCODES);
        return $good_code ? HTTPCODES::BLC_BROKEN_FALSE : HTTPCODES::BLC_BROKEN_TRUE;
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        $scheme = parse_url($linkItem->url, PHP_URL_SCHEME);
        //for internal URL the scheme might be empty (for example when called from BlcParsers)
        return \in_array($scheme, ['', 'http', 'https']) ? HTTPCODES::BLC_CHECK_TRUE : HTTPCODES::BLC_CHECK_FALSE;
    }
    protected function isSSL($url)
    {
        return 'https' === strtolower(parse_url($url, PHP_URL_SCHEME));
    }
    /**
     * Checks if open_basedir is enabled
     *
     * @return bool
     */
    protected function isOpenBasedir()
    {
        $open_basedir = \ini_get('open_basedir');
        return $open_basedir && (strtolower($open_basedir) != 'none');
    }
}