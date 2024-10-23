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

namespace Blc\Component\Blc\Administrator\Checker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\Filesystem\Path;

//final for now, consider the private variables when extening
final class BlcCheckerHttpCurl extends BlcCheckerHttpBase implements BlcCheckerInterface
{
    private $ch;
    private $redirectCount;
    private $requestLog        = [];
    private $responseLog        = [];
    private $lastHeaders       = [];
    private $requestHeaders    = [];
    protected static $instance = null;
    private $verboseWrapper;


    private function initCurl(LinkTable &$linkItem)
    {
        $this->lastHeaders = [];
        $this->requestLog  = [];
        $this->responseLog  = [];
        $this->ch          = curl_init();
        // reset these values as they are per request.
        $this->redirectCount = 0;

        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); //always otherwise curl prints the results

        //Close the connection after the request (disables keep-alive). The plugin rate-limits requests,
        //so it's likely we'd overrun the keep-alive timeout anyway.
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, $this->referer);

        //Redirects don't work when safe mode or open_basedir is enabled.

        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, $this->useFollowRedirects);

        //Set maximum redirects
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, $this->maxRedirs);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeOut);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);

        if ($this->HSTSJar && \defined('CURLOPT_HSTS')) {
            curl_setopt($this->ch, CURLOPT_HSTS, $this->HSTSJar);
            curl_setopt($this->ch, CURLOPT_HSTS_CTRL, CURLHSTS_ENABLE);
        }
        if ($this->verboseLog) {
            //this limit for in-memory for //temp is 2MB, that will hardly be reached.
            //thus no need to do a lot of checks. If the fopen fails it fails
            $this->verboseWrapper = fopen('php://temp', 'r+');
            if ($this->verboseWrapper) {
                curl_setopt($this->ch, CURLOPT_VERBOSE, true);
                curl_setopt($this->ch, CURLOPT_STDERR, $this->verboseWrapper);
            } else {
                $this->verboseLog = false; //this disabled the wrap up in checkLink
            }
        }

        if ($this->cookieJar) {
            //does skip cookies added with CURLOPT_COOKIELIST
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieJar);

            //save automaticly
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        }

        if ($this->cookies) {
            foreach ($this->cookies as $cookie_line) {
                curl_setopt($this->ch, CURLOPT_COOKIELIST, $cookie_line);
            }
        }
        // curl_setopt($this->ch, CURLOPT_VERBOSE, true);
        // $streamVerboseHandle = fopen('/tmp/curl.log', 'w+');
        // curl_setopt($this->ch, CURLOPT_STDERR, $streamVerboseHandle);

        //Make CURL return a valid result even if it gets a 404 or other error.
        curl_setopt($this->ch, CURLOPT_FAILONERROR, false);

        //add the header to the response.
        //so we can use CURLOPT_VERBOSE ( can't use VERBOSE with HEADERFUNCTION)
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        //signatures should contain Accept-encoding
        curl_setopt($this->ch, CURLOPT_ENCODING, "");
        if ($this->caFile !== false) {
            curl_setopt($this->ch, CURLOPT_CAINFO, $this->caFile);
            //if you use  a system file outside of Joomla it's not me to blame
            $linkItem->log['CAINFO'] =   Path::removeRoot($this->caFile);
            if ($caPath = curl_getinfo($this->ch, CURLOPT_CAPATH)) {
                $linkItem->log['CAPATH'] =   Path::removeRoot($caPath);
            }
        }


        $this->requestHeaders   = [];
        $this->requestHeaders[] = 'Connection: close';
        $this->requestHeaders[] = 'Accept-Language: ' . $this->acceptLanguage;

        // Override the Expect header to prevent cURL from confusing itself in its own stupidity.
        // Link: http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
        $this->requestHeaders[] = 'Expect:';
        $this->requestHeaders   = array_merge($this->requestHeaders, $this->headers);
    }

    private function setSSL($url)
    {
        if ($this->isSSL($url)) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, (bool)($this->validSsl == 2));
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->validSsl);
            $this->setSSLVersion();
        }
    }

    private function setSSLVersion()
    {
        switch ($this->sslVersion ?? 0) {
            case CURL_SSLVERSION_TLSv1_2:
                curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_2 | CURL_SSLVERSION_TLSv1_2);
                break;
            case CURL_SSLVERSION_TLSv1_3:
                curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_3 | CURL_SSLVERSION_TLSv1_3);
                break;
            default:
                curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT | CURL_SSLVERSION_MAX_DEFAULT);
        }
    }

    public function checkLink(LinkTable &$linkItem, $results = [], object|array $options = []): array
    {

        $this->dynamicSecFetch($linkItem);
        $this->initCurl($linkItem);
        $linkItem->log['Checker'] = "Curl: {$this->checkerName}";
        $this->requestLog[]       = ">Start: {$linkItem->_toCheck}";
        $results                  =  array_merge($results, $this->executeCurl($linkItem));
        curl_close($this->ch);

        if ($this->verboseLog) {
            rewind($this->verboseWrapper);
            $linkItem->log['Verbose Log'] = '';
            while (!feof($this->verboseWrapper)) {
                $linkItem->log['Verbose Log'] .= fread($this->verboseWrapper, 8192);
            }
            fclose($this->verboseWrapper);
        }
        return $results;
    }

    private function executeCurl(LinkTable &$linkItem)
    {
        $result = [
            'broken'    => self::BLC_BROKEN_FALSE,
            'final_url' => '',
        ];
       
        $this->setSSL($linkItem->_toCheck);
        curl_setopt($this->ch, CURLOPT_URL, $linkItem->_toCheck);
        //curl_setopt($this->ch, CURLOPT_CERTINFO, true);

        //reset range
        curl_setopt($this->ch, CURLOPT_RANGE, null);
        unset($this->requestHeaders['range']);

        if ($this->useHead) {
            curl_setopt($this->ch, CURLOPT_NOBODY, 1); //turn on head
        } else {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($this->ch, CURLOPT_NOBODY, 0);

            if ($this->useRange) {
                //If we must use GET at least limit the amount of downloaded data.
                $this->requestHeaders['range'] = 'Range: bytes=0-2048'; //2 KB
                curl_setopt($this->ch, CURLOPT_RANGE, "0,2048");
            }
        }
        $this->requestHeaders = array_filter($this->requestHeaders);
        //Set request headers.
        if (!empty($this->requestHeaders)) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->requestHeaders);
        }
        //Execute the request
        $start_time                 = microtime(true);
        $response                   = curl_exec($this->ch);
        $measured_request_duration  = microtime(true) - $start_time;

        //manualy extract the header and body. Can't use HEADERFUNCTION as this conflicts with VERBOSE
        $headerSize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $this->logHeaders(substr($response, 0, $headerSize));
        $content = substr($response, $headerSize);

        $info                      = curl_getinfo($this->ch);

        //Store the results
        $result['http_code']        = \intval($info['http_code']);
        $result['request_duration'] = $info['total_time'];
        $redirectCount              =  abs((int)$info['redirect_count']);

        //CURL doesn't return a request duration when a timeout happens, so we measure it ourselves.
        //It is useful to see how long the plugin waited for the server to respond before assuming it timed out.
        if (empty($result['request_duration'])) {
            $result['request_duration'] = $measured_request_duration;
        }

        if (isset($info['request_header'])) {    //hier stond ??=
            $this->requestLog[] = "Request headers";
            $this->requestLog[] = array_filter(explode("\r\n", $info['request_header']));
        }

        //Determine if the link counts as "broken"
        if (0 === abs((int) $result['http_code'])) {
            $error_code                       = curl_errno($this->ch);
            $linkItem->log['Curl Error Code'] = \sprintf("%s [Error #%d]\n", curl_error($this->ch), $error_code);

            //We only handle a couple of CURL error codes; most are highly esoteric.
            //libcurl "CURLE_" constants can't be used here because some of them have
            //different names or values in PHP.
            switch ($error_code) {
                case 6: //CURLE_COULDNT_RESOLVE_HOST
                    $result['http_code'] = self::BLC_DNS_HTTP_CODE;
                    break;
                case 28: //CURLE_OPERATION_TIMEDOUT
                    $result['http_code'] =
                        $result['http_code'] == 0 ? self::BLC_TIMEOUT_HTTP_CODE : $result['http_code'];
                    break;
                case 7: //CURLE_COULDNT_CONNECT
                    //More often than not, this error code indicates that the connection attempt
                    //timed out. This heuristic tries to distinguish between connections that fail
                    //due to timeouts and those that fail due to other causes.
                    if ($result['request_duration'] >= 0.9 * $this->timeOut) {
                        $result['http_code'] =
                            $result['http_code'] == 0 ? self::BLC_TIMEOUT_HTTP_CODE : $result['http_code'];
                    } else {
                        $result['http_code'] = self::BLC_DNS_WAF_CODE;
                    }
                    break;
                case 35:
                    if ($this->sslVersion) {
                        $this->sslVersion   = 0;
                        $this->requestLog[] = ">Redo without SSL Version Contrain: {$linkItem->_toCheck}";
                        return $this->executeCurl($linkItem);
                    }
                    $result['http_code'] = self::BLC_FAILED_SSL_VERSION_CODE;
                    break;

                case 58:
                case 59:
                case 60:   //SSL Errors
                    $result['http_code'] = self::BLC_FAILED_SSL_CODE;
                    break;
                default:
                    $result['http_code'] = self::BLC_UNKNOWN_ERROR_HTTP_CODE;
            }
        }

        if ($result['http_code']) {
            $linkItem->log['HTTP code'] = \sprintf('HTTP code : %d', $result['http_code']);
        } else {
            $linkItem->log['HTTP code'] = '(No response)';
        }
        $this->requestLog[] =  $linkItem->log['HTTP code'];
        $this->requestLog[] = "Response headers";
        $this->requestLog[] = $this->responseLog;
        $this->responseLog = [];

        $result['broken'] = $this->isErrorCode($result['http_code']);
        //retry some
        if (
            $result['broken']
            || $redirectCount == 1
            || $result['http_code'] == self::BLC_TIMEOUT_HTTP_CODE
        ) {
            if ($this->useHead) {
                //The site in question might be expecting GET instead of HEAD, so lets retry the request
                $this->useHead      = false;
                $this->requestLog[] = ">Redo with GET: {$linkItem->_toCheck}";
                return $this->executeCurl($linkItem);
            } elseif ($this->useRange) {
                //do not use range with HEAD
                //The site in question might have problems with the range
                $this->useRange     = false;
                $this->requestLog[] = ">Redo with full Response: {$linkItem->_toCheck}";
                return $this->executeCurl($linkItem);
            }
        }

        //HSTS Redirect && Failure
        if (
            $redirectCount == 0
            && !$this->isSSL($linkItem->_toCheck)
            && $this->isSSL($info['url'])
        ) {
            $redirectCount = 1;
        }

        $this->redirectCount += $redirectCount;

        if ($this->redirectCount > 0) {
            $result['final_url']  = $info['url'];
        }

        //When safe_mode or open_basedir is enabled CURL will be forbidden from following redirects,
        //so redirect_count will be 0 for all URLs. As a workaround, we restart the checker with the found locaiotn
        if ((0 === $redirectCount) && (\in_array($result['http_code'], [301, 302, 303, 307]))) {
            $this->redirectCount += 1;
            if ($this->useFollowRedirects === true && $this->redirectCount < $this->maxRedirs) {
                $next = $this->lastHeaders['location'];
                if ($next && $next != $info['url']) {
                    $linkItem->_toCheck = $next;
                    $this->requestLog[] = ">Pseudo Redirect: {$next}";
                    return $this->executeCurl($linkItem);
                }
            } else {
                //if we do not follow.
                $result['final_url'] =  $this->lastHeaders['location'];
            }
        }

        if ($this->redirectCount >= $this->maxRedirs) {
            $result['broken']    = 1;
            $result['http_code'] = self::BLC_FAILED_TOO_MANY_REDIRECTS;
        }

        $linkItem->log['Request Log'] = $this->requestLog;
        $linkItem->log['lastHeaders'] = $this->lastHeaders;

        $contentType               = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
        $linkItem->log['Response'] = '';
        if ($contentType) {
            $e                             = explode(';', $contentType);
            $result['mime']                = trim($e[0]);
            $linkItem->log['Content Type'] = $contentType;

            if ($content && $this->forceResponse !== self::CHECKER_LOG_RESPONSE_NEVER) {
                if (strpos($contentType, 'text') !== false) {
                    $this->isValidText($content);
                    $linkItem->log['Response'] =  $content;
                } elseif (strpos($contentType, 'json') !== false) {
                    $body                      = json_decode($content) ?? ['failed'];
                    $linkItem->log['Response'] = json_encode(
                        $body,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } elseif ($this->forceResponse === self::CHECKER_LOG_RESPONSE_ALWAYS) {
                    $this->isValidText($content);
                    $linkItem->log['Response'] =  $content;
                }
            }
        }


        $result['redirect_count']   = $this->redirectCount;
        return $result;
    }

    private function isValidText(&$content)
    {
        if (!mb_detect_encoding($content, strict: true)) {
            $content = 'Invalid MultiByte Encoding';
        }
    }

    /**
     *
     * This function add the response headers to the Log of the link
     * both as an array of the last headers as a full text log.
     *
     * replaces HEADERFUNCTION
     *
     * @param string $respHeaders
     *
     * @since 24.44.6426
     *
     */

    private function logHeaders(string $respHeaders): void
    {

        $headerText = trim($respHeaders, "\r\n");
        foreach (explode("\r\n", $headerText) as $header) {
            $s = explode(':', $header, 2);
            //the http_code has no ':' seperator
            //skip it here. we log it later.
            if (\count($s) == 2) {
                $this->lastHeaders[strtolower(trim($s[0]))] = trim($s[1]);
            }

            $this->responseLog[] = trim($header);
        }
    }
    /**
     *
     */
    public function __clone()/*: void*/
    {
        //  return clone $this;
    }
}
