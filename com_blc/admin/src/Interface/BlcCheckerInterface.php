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

namespace Blc\Component\Blc\Administrator\Interface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Table\LinkTable;

interface BlcCheckerInterface
{
    public const CHECKER_LOG_RESPONSE_NEVER  = -1; //disables adding the reponse ( text types) to the log
    public const CHECKER_LOG_RESPONSE_AUTO   = 0; //log's the reponse  (text type) if not a head request
    public const CHECKER_LOG_RESPONSE_ALWAYS = 1; //always add response to logs disables head and range
    public const CHECKER_LOG_RESPONSE_TEXT   = 2; //always log (text types) disabled head and range


    /*
        'BLC_CHECK_FALSE' = This checker can not handle the provided URI. proceede to the next checker
        'BLC_CHECK_TRUE' = This checker can  handle the provided URI and therefor be imported
        'BLC_CHECK_IGNORE'= This URI should not be imported. Used when parsing.
        'BLC_CHECK_CONTINUE'= I maybr did some stuff , please go on.
    */
    public const BLC_CHECK_FALSE              = 0;
    public const BLC_CHECK_TRUE               = 1;
    public const BLC_CHECK_IGNORE             = -1;
    public const BLC_CHECK_CONTINUE           = 2;
    public const BLC_CHECK_CONTINUE_ON_BROKEN = 3;
    public const BLC_CHECK_ALWAYS             = 3;

    public const BLC_WORKING_UNSET   = -1;
    public const BLC_WORKING_ACTIVE  = 0;
    public const BLC_WORKING_WORKING = 1;
    public const BLC_WORKING_IGNORE  = 2;
    public const BLC_WORKING_HIDDEN  = 3;

    public const BLC_BROKEN_FALSE   = 0;
    public const BLC_BROKEN_TRUE    = 1;
    public const BLC_BROKEN_WARNING = 2;
    public const BLC_BROKEN_TIMEOUT = 3;

    //psuedo http codes
    public const BLC_DNS_WAF_CODE                        =  601;
    public const BLC_DNS_HTTP_CODE                       =  602;
    public const BLC_UNCHECKED_IGNORELINK                =  603;
    public const BLC_UNCHECKED_PROTOCOL_HTTP_CODE        =  604;
    public const BLC_FAILED_TOO_MANY_REDIRECTS           =  605;
    public const BLC_FAILED_SSL_CODE                     =  606;
    public const BLC_FAILED_SSL_VERSION_CODE             =  607;
    public const BLC_IGNORED_REDIRECT_PROTOCOL_HTTP_CODE =  608;
    public const BLC_UNABLE_TOCHECK_HTTP_CODE            =  609;
    public const BLC_PROVIDER_NOT_FOUND_HTTP_CODE        =  610;
    public const BLC_UNKNOWN_ERROR_HTTP_CODE             =  611;
    public const BLC_THROTTLE_HTTP_CODE                  =  612;
    public const BLC_TIMEOUT_HTTP_CODE                   =  613;
    public const BLC_LOCK_HTTP_CODE                      =  614;

    public const BLC_YOUTUBE_INVALID   =  620;
    public const BLC_YOUTUBE_API_ERROR =  621;
    public const BLC_YOUTUBE_NOT_FOUND =  622;
    public const BLC_YOUTUBE_EMPTY     =  623;
    public const BLC_YOUTUBE_PRIVATE   =  624;

    public const BLC_CHECKSTATE_CHECKED  = 0;
    public const BLC_CHECKSTATE_TOCHECK  = 1;
    public const BLC_CHECKSTATE_CHECKING = 2;

    public const BLC_PARKED_UNCHECKED = 0;
    public const BLC_PARKED_PARKED    = 1;
    public const BLC_PARKED_CHECKED   = 2;

    public const DOMAINPARKINGSQL = [
        'sedo.com'      => "`url` like '%sedo.com%' OR `final_url` like '%sedo.com%'",
        'buy-domain'    => "`final_url` like '%buy-domain%'",
        '(sedo)parking' => "`url` REGEXP( 'https?://www?[0-9]') OR `final_url` REGEXP( 'https?://www?[0-9]')",
        'dan.com'       => "`log` like '%.dan.com%'",
    ];


    public const TEMPHTTPCODES = [
        408, //Request timeout. Probably a plugin bug, but could just be an overloaded client server.
        420, //Custom Twitter code returned when the client gets rate-limited.
        429, //Client has sent too many requests in a given amount of time.
        502, //Bad Gateway. Often a sign of a temporarily overloaded or misconfigured server.
        503, //Service Unavailable.
        504, //Gateway Timeout.
        509, //Bandwidth Limit Exceeded.
        self::BLC_TIMEOUT_HTTP_CODE, //time out -- catched elsewhere
    ];

    public const INTERNALWARNINGHTTPCODES = [
        408,
        self::BLC_DNS_HTTP_CODE,
        self::BLC_TIMEOUT_HTTP_CODE, //time out -- catched elsewhere
    ];

    public const CLOUDFLAREHTTPCODES = [
        self::BLC_DNS_WAF_CODE,
        520, //CloudFlare - web server returns an unknown error
        521, //CloudFlare - web server is down
        522, //CloudFlare - connection timed out
        523, //CloudFlare - origin is unreachable
        524, //cloudflare - a timeout occurred
        525, //CloudFlare - SSL handshake failed
        526, //CloudFlare - invalid SSL certificate
        530, //CloudFlare - other
    ];

    public const UNCHECKEDHTTPCODES = [
        0,
        self::BLC_THROTTLE_HTTP_CODE,
    ];
    public const GOODHTTPCODES = [
        self::BLC_TIMEOUT_HTTP_CODE,
        self::BLC_THROTTLE_HTTP_CODE,
        self::BLC_IGNORED_REDIRECT_PROTOCOL_HTTP_CODE,
        self::BLC_UNCHECKED_PROTOCOL_HTTP_CODE,
        self::BLC_UNCHECKED_IGNORELINK,
    ];

    public function canCheckLink(LinkTable $linkItem): int;
    /**
     * linkItem holds the old values
     *
     * @var LinkTable $linkItem
     * @var array $results   current check results
     * @var array|object $config
     * 
     * @return array updated results
     */
    public function checkLink(LinkTable &$linkItem, array $results = []): array;


}
