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
use Joomla\Registry\Registry;

//final for now, consider the private variables when extening
final class BlcCheckerHttpCurl extends BlcCheckerHttpBase implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var   Blc\Component\Blc\Administrator\Blc\BlcModule;
     *
     */
    protected static ?\Blc\Component\Blc\Administrator\Blc\BlcModule $instance = null;

    private $ch;
    private $redirectCount;
    private $requestLog        = [];

    private $responseHeaders       = [];

    private $verboseWrapper;


    private function initCurl(LinkTable &$linkItem)
    {
        $this->responseHeaders = [];
        $this->requestLog      = [];
        $this->ch              = curl_init();

        // reset these values as they are per request.
        $this->redirectCount = 0;

        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); //always otherwise curl prints the results

        //Close the connection after the request (disables keep-alive). The plugin rate-limits requests,
        //so it's likely we'd overrun the keep-alive timeout anyway.
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, false);

        curl_setopt($this->ch, CURLOPT_REFERER, $this->referer);


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

        if ($this->verboseLog == false) {
            //CURLINFO_HEADER_OUT conflicts with VERBOSE
            curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
        }


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


        if ($this->cookieJar) {
            //does skip cookies added with CURLOPT_COOKIELIST
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieJar);

            //save automatically
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
        $this->addHeader('Connection: close');
        $this->addHeader('Accept-Language: ' . $this->acceptLanguage);
        // Override the Expect header to prevent cURL from confusing itself in its own stupidity.
        // Link: http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
        $this->addHeader('Expect:');
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
                curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                break;
            case CURL_SSLVERSION_TLSv1_3:
                curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_3);
                break;
            default:
                curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_MAX_DEFAULT);
        }
    }

    public function checkLink(LinkTable &$linkItem, ?Registry $config = null): void
    {
        if (! $this->validateUrl($linkItem)) {
            return;
        }
        //reset to global configuration if nothing set.
        $this->setConfig($config);

        $this->dynamicSecFetch($linkItem);
        $this->initCurl($linkItem);
        $linkItem->log['Checker'] = "Curl: {$this->checkerName}";
        $this->requestLog[]       = ">Start: {$linkItem->toCheck}";
        $this->executeCurl($linkItem);
        curl_close($this->ch);
        $linkItem->log['Request Log']  = $this->requestLog;
        if ($this->verboseLog) {
            rewind($this->verboseWrapper);
            $linkItem->log['Verbose Log'] = stream_get_contents($this->verboseWrapper);

            fclose($this->verboseWrapper);
        }
    }

    private function executeCurl(LinkTable &$linkItem)
    {

        $linkItem->final_url = '';
        //Might change after redirect
        $this->setSSL($linkItem->toCheck);
        curl_setopt($this->ch, CURLOPT_URL, $linkItem->toCheck);
        //curl_setopt($this->ch, CURLOPT_CERTINFO, true);

        //reset range - this adds the range: header. No need to add it to ->headers
        curl_setopt($this->ch, CURLOPT_RANGE, null);

        if ($this->useHead) {
            curl_setopt($this->ch, CURLOPT_NOBODY, 1); //turn on head
        } else {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($this->ch, CURLOPT_NOBODY, 0);

            if ($this->useRange) {
                //If we must use GET at least limit the amount of downloaded data.
                curl_setopt($this->ch, CURLOPT_RANGE, "0,2048");
            }
        }
        //Set request headers.
        if (!empty($this->headers)) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        }
        $this->responseHeaders = [];
        //Execute the request
        $start_time                 = hrtime(true);

        $response                   = curl_exec($this->ch);

        //CURL doesn't return a request duration when a timeout happens, so we measure it ourselves.
        //It is useful to see how long the plugin waited for the server to respond before assuming it timed out.
        $measured_request_duration  = (hrtime(true) - $start_time) / 1e+9; //nanoseconds to seconds

        //manualy extract the header and body. Can't use HEADERFUNCTION as this conflicts with VERBOSE (if enable)
        //when VERBOSE is disabled we could use HEADERFUNCTION this works fine in both situation
        //The information is needed for the server header ( cloudflare) and the location header when  safe_mode or open_basedir is enabled
        $headerSize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $this->logHeaders(substr($response, 0, $headerSize));
        $content = substr($response, $headerSize);

        $info                      = curl_getinfo($this->ch);

        //Store the results
        $http_code                  = \intval($info['http_code']);
        $linkItem->request_duration = $info['total_time'] ?? $measured_request_duration;
        $redirectCount              =  abs((int)$info['redirect_count']);


        if (isset($info['request_header'])) {
            $this->requestLog[] = "Request headers";
            $this->requestLog[] = array_filter(explode("\r\n", $info['request_header']));
        }

        //Determine if the link counts as "broken"
        if (0 === $http_code) {
            $error_code                       = curl_errno($this->ch);
            $linkItem->log['Curl Error Code'] = \sprintf("%s [Error #%d]\n", curl_error($this->ch), $error_code);

            //We only handle a couple of CURL error codes; most are highly esoteric.
            //libcurl "CURLE_" constants can't be used here because some of them have
            //different names or values in PHP.
            switch ($error_code) {
                case 6: //CURLE_COULDNT_RESOLVE_HOST
                    $http_code = self::BLC_DNS_HTTP_CODE;
                    break;
                case 28: //CURLE_OPERATION_TIMEDOUT
                    $http_code = self::BLC_TIMEOUT_HTTP_CODE;
                    break;
                case 7: //CURLE_COULDNT_CONNECT
                    //More often than not, this error code indicates that the connection attempt
                    //timed out. This heuristic tries to distinguish between connections that fail
                    //due to timeouts and those that fail due to other causes.
                    if ($linkItem->request_duration >= 0.9 * $this->timeOut) {
                        $http_code = self::BLC_TIMEOUT_HTTP_CODE;
                    } else {
                        $http_code = self::BLC_DNS_WAF_CODE;
                    }
                    break;
                case 35:
                    if ($this->sslVersion) {
                        $this->sslVersion   = 0;
                        $this->requestLog[] = ">Redo without SSL Version Contrain: {$linkItem->toCheck}";
                        return $this->executeCurl($linkItem);
                    }
                    $http_code = self::BLC_FAILED_SSL_VERSION_CODE;
                    break;

                case 58:
                case 59:
                case 60:   //SSL Errors
                    $http_code = self::BLC_FAILED_SSL_CODE;
                    break;
                default:
                    $http_code = self::BLC_UNKNOWN_ERROR_HTTP_CODE;
            }
        }

        if ($http_code) {
            $linkItem->log['HTTP code'] = \sprintf('HTTP code : %d', $http_code);
        } else {
            $linkItem->log['HTTP code'] = '(No response)';
        }
        if ($this->verboseLog == false) {
            //no need to log this when there is a verbose log. There is already all the information
            $this->requestLog[] =  $linkItem->log['HTTP code'];
            $this->requestLog[] = "Response headers";
            $this->requestLog[] = $this->responseHeaders;
        }


        $broken = $this->isErrorCode($http_code);
        //retry some
        if (
            $broken
            || $redirectCount == 1
            || $http_code == self::BLC_TIMEOUT_HTTP_CODE
        ) {
            if ($this->useHead) {
                //The site in question might be expecting GET instead of HEAD, so lets retry the request
                $this->useHead      = false;
                $this->requestLog[] = ">Redo with GET: {$linkItem->toCheck}";
                $this->executeCurl($linkItem);
                return;
            } elseif ($this->useRange) {
                //do not use range with HEAD
                //The site in question might have problems with the range
                $this->useRange     = false;
                $this->requestLog[] = ">Redo with full Response: {$linkItem->toCheck}";
                $this->executeCurl($linkItem);
                return;
            }
        }

        //HSTS Redirect && Failure
        if (
            $redirectCount == 0
            && !$this->isSSL($linkItem->toCheck)
            && $this->isSSL($info['url'])
        ) {
            $redirectCount = 1;
        }

        $this->redirectCount += $redirectCount;

        if ($this->redirectCount > 0) {
            $linkItem->final_url  = $info['url'];
        }

        $currentHeaders                = $this->splitHeaders($this->responseHeaders);
        $linkItem->log['Last Headers'] = $currentHeaders;


        //When safe_mode or open_basedir is enabled CURL will be forbidden from following redirects,
        //so redirect_count will be 0 for all URLs. As a workaround, we restart the checker with the found locaiotn
        if ((0 === $redirectCount) && (\in_array($http_code, [301, 302, 303, 307]))) {
            $next=$info['redirect_url']??$currentHeaders['location']??'';
          
            if ($next) {
                $host = parse_url($next, PHP_URL_HOST);
                //if there is no host something bad happend.
                //let the user figure this out
                if ($host) {
                    $this->redirectCount += 1;
                    if ($this->useFollowRedirects === true && $this->redirectCount < $this->maxRedirs) {
                        if ($next != $info['url']) {
                            $linkItem->toCheck  = $next;
                            $this->requestLog[] = ">Pseudo Redirect: {$next}";
                            $this->executeCurl($linkItem);
                            return;
                        }
                    } else {
                        //pretend to follow
                        $linkItem->final_url = $next;
                    }
                }
            }
        }

        if ($this->redirectCount >= $this->maxRedirs) {
            $broken    = self::BLC_BROKEN_TRUE;
            $http_code = self::BLC_FAILED_TOO_MANY_REDIRECTS;
        }

        $contentType               = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
        $linkItem->log['Response'] = '';
        if ($contentType) {
            $e                             = explode(';', $contentType);
            $linkItem->mime                = trim($e[0]);
            $linkItem->log['Content Type'] = $contentType;

            if ($content && $this->forceResponse !== self::CHECKER_LOG_RESPONSE_NEVER) {
                if (str_contains($contentType, 'text')) {
                    $this->isValidText($content);
                    $linkItem->log['Response'] =  $content;
                } elseif (str_contains($contentType, 'json')) {
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
        } else {
            $linkItem->mime  = 'unknown';
        }
        $linkItem->broken          =   $broken;
        $linkItem->http_code       = $http_code;
        $linkItem->redirect_count  = $this->redirectCount;
        return;
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
            $this->responseHeaders[] = trim($header);
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
