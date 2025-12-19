<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Checker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES; //using constants but not implementing
use Blc\Component\Blc\Administrator\Table\LinkTable;

use Composer\CaBundle\CaBundle;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Filesystem\File;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

class BlcCheckerHttpBase extends BlcModule
{


    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;

    protected $userAgent              = "";
    protected $headers                = [];
    protected $cookies                = [];
    protected $timeOut                = 10;
    protected $referer                = '';
    protected $maxRedirs              = 5;
    protected $validSsl               = 2;
    protected $useRange               = true;
    protected $logResponse            = HTTPCODES::CHECKER_LOG_RESPONSE_AUTO;
    protected $useFollowRedirects     = true;
    protected $useHead                = true;
    protected bool $cookieJar         = false;
    protected $HSTSJar                = '';
    protected $cacheDir               = '';
    protected $acceptLanguage         = 'en-US,en;q=0.5';
    protected $hostChecked            = '{HOST}';
    /**
     * @var string|int
     */
    protected string|int $sslVersion             = 'CURL_SSLVERSION_DEFAULT';
    protected $token                  = '';
    protected $isCli                  = false;
    protected $caFile                 = false;
    protected $verboseLog             = false;
    protected $dynamicSecFetch        = true;
    protected $checkerName            = 'Main Checker';
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
        $this->token   = $this->__set('token', $this->referer);
        $this->isCli   = $app->isClient('cli');
        $this->HSTSJar = $this->cacheDir . '/' . $this->token . '.hsts';
    }

    public function setConfig(?Registry $config = null): self
    {

        parent::setConfig($config);



        $this->__set('cookies', $this->componentConfig->get('cookies', 1));

        $this->__set(
            'timeout',
            $this->componentConfig->get($this->isCli ? 'timeout_cli' : 'timeout_http', $this->timeOut)
        );


        $this->acceptLanguage = $this->componentConfig->get('accept-language', $this->acceptLanguage);


        $signature = $this->setSignature($this->componentConfig->get('signature', 'firefox'));
        if (!isset($signature['Accept-Language'])) {
            $this->setLanguage(
                $this->componentConfig->get('language', 0),
                $this->componentConfig->get('accept-language', $this->acceptLanguage)
            );
        }

        $this->validSsl = $this->componentConfig->get('valid_ssl', $this->validSsl);

        if ($this->isOpenBasedir()) {
            $this->useFollowRedirects = false;
        } else {
            $this->useFollowRedirects = (bool)$this->componentConfig->get('follow', $this->useFollowRedirects);
            $this->maxRedirs          = (int)$this->componentConfig->get('maxredirs', $this->maxRedirs);
        }

        $this->dynamicSecFetch = (bool)$this->componentConfig->get('dynamicSecFetch', $this->dynamicSecFetch);


        $this->__set('sslversion', $this->componentConfig->get('sslversion', $this->sslVersion));


        $this->__set('name', $this->componentConfig->get('name', $this->checkerName));
        $this->__set('verboseLog', $this->componentConfig->get('verbose', $this->verboseLog));
        $this->__set('head', $this->componentConfig->get('head', $this->useHead));
        $this->__set('range', $this->componentConfig->get('range', $this->useRange));
        //log_repsonse sets head and range so after those
        $this->__set('log_response', $this->componentConfig->get('log_response', $this->logResponse));

        $this->setcaFile(
            $this->componentConfig->get('cafilesource', ''),
            $this->componentConfig->get('cafile', '')
        );
        return $this;
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
                $short                     = explode('-', (string) $langCode);
                $languageAccept[$short[0]] = $short[0];
                $languageAccept[$langCode] = $langCode;
            }

            $q = 1.0;
            array_walk(
                $languageAccept,
                function (&$item) use (&$q): void {
                    if ($q < 1) {
                        $item .= ";q=$q";
                    }

                    $q = max(0.3, $q - 0.1);
                }
            );
            $languageAccept['en-US'] ??= "en-US;q=0.2";
            $languageAccept['en']    ??= "en;q=0.1";
            $languageAcceptString = implode(',', $languageAccept);
        } else {
            $languageAcceptString = $languageString;
        }
        $this->acceptLanguage = trim($languageAcceptString, ' -');;
    }

    protected function getSignature(): array
    {
        return
            [

                "userAgent" => $this->userAgent,
                "Accept-Language" =>  $this->acceptLanguage,
                "headers"  => $this->__get('headers'),
            ];
    }

    protected function setSignature(array|object|string $signature): array
    {
        if (\is_string($signature)) {
            $signature = $this->loadSignature($signature);
        } elseif (\is_object($signature)) {
            $signature = (array) $signature;
        }
        if (isset($signature['Accept-Language'])) {
            $this->acceptLanguage = $signature['Accept-Language'];
        }
        $this->userAgent = $signature['userAgent'] ?? 'Joomla fetcher';
        $this->__set('headers', $signature['headers'] ?? []); //takes care of spliting
        return $signature;
    }

    public function loadSignature($browser): array
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
        $this->replaceHeader("Sec-Fetch-Site", $secFetchSite);
    }

    /**
     * add and remove header to send with the http request.
     * headers might have the save 'key' multiple times.
     * However that is not the case in this application
     *
     */
    public function removeHeader(string $header)
    {
        // works for both 'key: value' as naked 'key'
        $key           = strtolower(strtok($header, ':'));
        unset($this->headers[$key]);
    }

    public function replaceHeader(string $header, ?string $value = null)
    {
        $this->removeHeader($header);
        $this->addHeader($header, $value);
    }

    public function addHeader(string $header, ?string $value = null)
    {
        // works for both 'key: value' as naked 'key'
        $key           = strtolower(strtok($header, ':'));
        if ($value) {
            //create the header if a value is given
            $header = "$key: $value";
        }

        $this->headers[$key] = $header;
       
    }

    public function clearHeaders()
    {
      
        $this->headers = [];
    }
    public function getHeaders()
    {
        return array_filter(array_values($this->headers));
    }

    public function addCookie(array|object|string $cookie)
    {
        $newCookies = [];
        switch (true) {
            case \is_array($cookie):
                $newCookies = $cookie;
                break;
            case \is_object($cookie):
                $newCookies = (array)$cookie;
                break;
            case \is_string($cookie):
                $newCookies = explode("\n", $cookie); // can't use the splitOption trait as cookies might contain ;
                break;
            case (bool)$cookie:  //value is an integer from the configuration
                $this->cookieJar = true;
                $this->clearCookies();
                break;
        }
        $newCookies    = array_map($this->buildCookie(...), $newCookies);
        $this->cookies = array_filter(array_unique(array_merge($this->cookies, $newCookies)));
        if (!empty($this->cookies)) {
            $this->cookieJar = true;
        }
    }
    public function clearCookies()
    {
        $this->cookies = [];
    }
    /**
     * delete the cookiejar for the given url/host
     * mostly for testing
     * @since 25.44.7989
     */

    public function clearCookieJar(): void
    {
        $this->clearCookies();
        $path  = $this->getCookieJarPath();

        if ($path == false) {
            return;
        }

        if (file_exists($path)) {
            File::delete($path);
        }
    }

    protected function getCookieJarPath(): string|bool
    {

        if ($this->cookieJar) {
            return $this->cacheDir . '/' . $this->token . '.cookies';
        }
        return false;
    }

    public function __get($name)
    {

        $name = match (strtolower((string) $name)) {
            'language'     => 'acceptLanguage',
            'range'        => 'useRange',
            'follow'       => 'useFollowRedirects',
            'head'         => 'useHead',
            'verboselog'   => 'verboseLog',
            'cafile'       => 'caFile',
            'sslversion'   => 'sslVersion',
            'timeout'      => 'timeOut',
            'validssl'     => 'validSsl',
            'maxredirs'    => 'maxRedirs',
            'useragent'    => 'userAgent',
            'name'         => 'checkerName',
            'log_response' => 'logResponse',
            'cookies'      => 'cookies',
            default        => $name
        };
        if ($name == 'cookieJar') {
            return $this->getCookieJarPath();
        }

        if ($name == 'signature') {
            return $this->getSignature();
        }


        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }
    /**
     * 
     * @since 25.44.7989     
     * 
     *
     */

    protected function setSSLVersion(int|string $value): void
    {

        $this->sslVersion = $value;
    }

    public function __set($name, $value)
    {
        $name = strtolower((string) $name);

        switch ($name) {
            case 'token':
                $this->token   = md5(Factory::getApplication()->get('secret') . $value) . '-' . OutputFilter::stringURLSafe($value);
                break;
            case 'acceptlanguage':
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
                if (\is_int($value) || $value === '1' || $value === '0' || \is_bool($value)) {
                    $this->cookieJar = (bool)$value;
                    $this->clearCookies();
                    break;
                }

                $this->addCookie($value);


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
                       
                        $this->headers = explode("\n", $value); //can't use splitoption trait as header might contain some of the characters.
                        break;
                }
                break;
            case 'userange':
            case 'range':
                $this->useRange = (bool)$value;
                break;
            case 'usefollowredirects':
            case 'follow':
                $this->useFollowRedirects = (bool)$value;
                break;
            case 'usehead':
            case 'head':
                $this->useHead = (bool)$value;
                break;
            case 'verboselog':
                $this->verboseLog = (bool)$value;
                break;
            case 'cafile':
                $this->caFile = false;

                if ($value = $this->validateCaFile($value)) {
                    $this->caFile = $value;
                }
                break;

            case 'sslversion':
                $this->setSSLVersion($value);
                //these constants should be defined in supported php and curl
                break;

            case 'timeout':
                //restrict to acceptable range

                $this->timeOut = max(1, min(60, $value));
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
                if (\is_array($value)) {
                    $this->setSignature($value);
                }
                break;

            case 'useragent':
                if (\is_string($value)) {
                    $this->userAgent = $value;
                }
                break;
            case 'checkername':
            case 'name':
                if (\is_string($value)) {
                    $this->checkerName = $value;
                }
                break;


            case 'response': //depricated
            case 'log_response':
                if (
                    \in_array($value, [
                        HTTPCODES::CHECKER_LOG_RESPONSE_ALWAYS,
                        HTTPCODES::CHECKER_LOG_RESPONSE_AUTO,
                        HTTPCODES::CHECKER_LOG_RESPONSE_NEVER,
                        HTTPCODES::CHECKER_LOG_RESPONSE_TEXT,
                    ])
                ) {
                    $this->logResponse = $value;
                }


                if (
                    $this->logResponse === HTTPCODES::CHECKER_LOG_RESPONSE_ALWAYS
                    || $this->logResponse === HTTPCODES::CHECKER_LOG_RESPONSE_TEXT
                ) {
                    $this->__set('range', false);
                    $this->__set('head', false);
                }

                break;
            default:
        }
    }


    //used elswhere todo make static
    public function isErrorCode($http_code)
    {
        if ($http_code == HTTPCODES::BLC_TIMEOUT_HTTP_CODE) {
            return HTTPCODES::BLC_BROKEN_TIMEOUT;
        }
        /*"Good" response codes are anything in the 2XX range (e.g "200 OK") and redirects  - the 3XX range.
        and some custom codes         */
        $good_code = (($http_code >= 200) && ($http_code < 400)) || \in_array($http_code, HTTPCODES::GOODHTTPCODES);
        return $good_code ? HTTPCODES::BLC_BROKEN_FALSE : HTTPCODES::BLC_BROKEN_TRUE;
    }

    /**
     *
     * @since 24.44.6964
     *
     * @param LinkTable if something odd is detected the http_code is set accordingly
     *
     * @return bool wether or not it is a valid http(s) link and continue checking
     */
    protected function validateUrl(LinkTable &$linkItem): bool
    {
        $url = $linkItem->toCheck;


        if (
            (! str_starts_with((string) $url, 'https://')) &&
            (! str_starts_with((string) $url, 'http://'))
        ) {
            return false; // let other checkers take care
        }
        //parse_url does not throw exceptions
        $host = parse_url((string) $url, PHP_URL_HOST);
        $this->hostChecked = $host;
        $this->__set('token', $host);

        //this should never happen. Better save then sorry
        if (! $host) {
            if ($host === false) {
                //invalid url/host.
                $linkItem->http_code = HTTPCODES::BLC_INVALID_URL_HTTP_CODE;
                $linkItem->broken    = HTTPCODES::BLC_BROKEN_TRUE;
                $linkItem->log[]     = Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_BLC_DNS_HTTP_CODE', $host);
            }

            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        //php dns_get_record will resolve a non-existing host as a subdomain of the servers domainname
        //with an ip pointing to the localhost
        //therefor the .
        $host .= '.';
        @$ipv4Records = dns_get_record($host, DNS_A); //returns array or false
        if ($ipv4Records && \count($ipv4Records)) {
            return true;
        }
        @$ipv6Records = dns_get_record($host, DNS_AAAA);  //returns array or false
        if ($ipv6Records && \count($ipv6Records)) {
            return true;
        }
        $linkItem->http_code = HTTPCODES::BLC_DNS_HTTP_CODE;
        $linkItem->broken    = HTTPCODES::BLC_BROKEN_TRUE;
        $linkItem->log[]     = Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_BLC_DNS_HTTP_CODE', $host);
        return false;
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        //do not check checked links
        if ($linkItem->http_code !== HTTPCODES::BLC_CHECK_UNSET) {
            return  HTTPCODES::BLC_CHECK_FALSE;
        }

        $scheme = parse_url($linkItem->url, PHP_URL_SCHEME);
        //for internal URL the scheme might be empty (for example when called from BlcParseController)
        //same for the host. Can't check here.
        return \in_array($scheme, ['', 'http', 'https']) ? HTTPCODES::BLC_CHECK_TRUE : HTTPCODES::BLC_CHECK_FALSE;
    }
    /**
     * @param LinkTable
     * @since 24.44.6964
     *
     * basicly a dummy, usefull for testing
     */
    public function checkLink(LinkTable &$linkItem): void
    {
        if (!$this->validateUrl($linkItem)) {
            return;
        }

        $linkItem->http_code = HTTPCODES::BLC_WRONG_CLASS_HTTP_CODE;
        $linkItem->broken    = HTTPCODES::BLC_BROKEN_FALSE;
        $linkItem->log[]     = Text::sprintf('COM_BLC_MESSAGE_LINK_STATUS_BLC_WRONG_CLASS_HTTP_CODE');
    }
    protected function isSSL($url)
    {
        return 'https' === strtolower(parse_url((string) $url, PHP_URL_SCHEME) ?? '');
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

    /**
     *
     * Slit header of the type 'key: value' into an array.
     * Ignoring  duplicats
     * currenlty used to get the 'server' header to find cloudflare servers.
     *
     * @param array<string> $respHeaders
     * @return array<string>
     *
     * @since 24.44.6717
     *
     */

    protected function splitHeaders(array $respHeaders): array
    {
        $headers = [];
        foreach ($respHeaders as $header) {
            $s = explode(':', $header, 2);
            //the http_code has no ':' seperator
            //skip it here. we log it later.
            if (\count($s) == 2) {
                $headers[strtolower(trim($s[0]))] = trim($s[1]);
            } else {
                $headers[] = trim($s[0]);
            }
        }
        return $headers;
    }

    /**
     * this prepares the cookies in the correct format for CURLOPT_COOKIELIST
     * @since 25.44.7989
     * 
     */

    protected function buildCookie($cookie_line): string
    {

        if (str_contains($cookie_line, "\t")) {   //tabs already in netsape cookie format
            return trim($cookie_line);
        }
        if (str_starts_with($cookie_line, "Set-Cookie")) {   //already in cookie format
            return trim($cookie_line);
        }
        $parts = explode('=', $cookie_line);

        switch (count($parts)) {
            case 1:
                $name = $parts[0];
                $value = uniqid();
                break;
            case 2:
                [$name, $value] = $parts;
                break;
            default:
                return trim($cookie_line);
        }
        /**
         * if executed via checklink the host is set. 
         */
        $cookie = [
            'domain'     => $this->hostChecked, //here the hostChecked might be unset this allows to change the cookie per link
            'flag'       => 'FALSE', ///F value indicating if all machines within a given domain can access the variable. This value is set automatically by the browser, depending on the value you set for domain.
            'path'       => '/', // The path within the domain that the variable is valid for.
            'secure'     => 'FALSE', //- A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.
            'expiration' => time() + 3600, // The UNIX time that the variable will expire on.
            'name'       => trim($name), //- The name of the variable.
            'value'      => trim($value), // - The value of the variable.
        ];
        return join("\t", array_values($cookie));
    }
}
