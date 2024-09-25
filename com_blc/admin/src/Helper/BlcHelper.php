<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\IpHelper;

/**
 * Blc helper.
 *
 * @since  1.0.0
 */
class BlcHelper extends BlcModule
{
    public static function jsonExtract(string $column, string $field, ?string $as = null, bool $text = true, string $cast = ''): string
    {
        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $driver = $db->getServerType();
        $column = $db->quoteName($column);
        if ($driver === 'mysql') {
            $query = "{$column}, {$db->quote('$.' .$field)}";

            $query = "JSON_EXTRACT($query)";

            if ($text) {
                $query = "JSON_UNQUOTE($query)";
            }

            switch ($cast) {
                //JSON_VALUE(json_doc, path RETURNING type) however this makes it easier to build the function step by step
                case ParameterType::INTEGER:
                    $query = "CAST($query as SIGNED)";
                    break;
                case ParameterType::STRING:
                    $query = "CAST($query as CHAR)";
                    break;
            }
        } else {
            $operand = $text ? '#>>' : '#>';
            $path    = '{' . join(',', explode('.', $field)) . '}';
            $query   = "{$column}::json {$operand} {$db->quote($path)}";
            switch ($cast) {
                case ParameterType::INTEGER:
                    $query = "({$query})::int";
                    break;
                case ParameterType::STRING:
                    $query = "({$query})::text";
                    break;
            }
        }
        $as ??= $field;
        if ($as) {
            $query .= " AS {$db->quoteName($as)}";
        }
        return $query;
    }

    public static function getFiles($pk, $table, $field)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query
            ->select($field)
            ->from($table)
            ->where('id = ' . (int) $pk);

        $db->setQuery($query);

        return explode(',', $db->loadResult());
    }

    public static function intervalTohours(int $freq, string $unit = 'hours')
    {
        switch (strtolower($unit)) {
            case 'second':
                $freq /= 60 * 60;
                break;
            case 'minute':
                $freq /= 60;
                break;

            case 'hour':
                //nothing
                break;

            case 'day':
                $freq *= 24;
                break;
            case 'week':
                $freq *= 24 * 7;
                break;
            case 'month':
                $freq *= 24 * 7 * 4.333; //estimate
                break;
            case 'year':
                $freq *= 24 * 7 * 365; //estimate
                break;
        }
        return $freq;
    }



    public static function getReplaceUrl($item)
    {
        // phpcs:disable Generic.Files.LineLength
        return $item->internal_url == '' ? ($item->final_url == '' ? $item->url : $item->final_url) : $item->internal_url;
        // phpcs:enable Generic.Files.LineLength
    }

    public static function footer($link = '')
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_blc');
        if ($link) {
            $ahref = '<a target="_blank" href="%s">' .  Text::_('BLC_READMORE_LINK_TEXT')  . '</a>';
            $text  = '<div class="list-group-item list-group-item-primary">' . sprintf($ahref, $link) . '</div>';
        } else {
            $text = '';
        }
        $helptranslated =  Text::_('BLC_READMORE_REVIEW_HELPTRANSLATE');
        $translated     =  Text::_('BLC_READMORE_REVIEW_TRANSLATED');
        if ($translated === 'NOCONTRIB' || $translated === 'BLC_READMORE_REVIEW_TRANSLATED') {
            $translated = "";
        } else {
            $translated = '<div class="list-group-item list-group-item-primary">'  . $translated . '</div>';
        }

        return '
		<div  class="list-group">
		  <h2 class="list-group-item m-0 mt-2 list-group-item-primary">' .  Text::_('BLC_READMORE_HEADER') . ' </h2>'
            . $text .
             $translated .
             '<div class="list-group-item list-group-item-primary">' .
               $helptranslated .
        '</div>' .
            '<div class="list-group-item list-group-item-warning">' .  Text::_('BLC_READMORE_DISCLAIMER') . '</div>' .
              '<div class="list-group-item list-group-item-success">' .  Text::_('BLC_READMORE_REVIEW') . '</div>
	    </div>';
    }

    public static function responseCode($http_code)
    {
        $langcode = "COM_BLC_HTTP_RESPONSE_" . $http_code;
        $r        = Text::_($langcode);
        if ($r == $langcode) {
            $r = 'Response code:' . $http_code;
        }
        return $r;
    }
    /**
     * @param string $path path appended to the site root. this not  the same as the path in Uri::root. the later replaced the base path to the site
     *
     * @return string
     *
     * @throws      \RuntimeException
     *
     */

    public static function root(?string $path = null): string
    {
        //if there is a host already rooted.
        if ($path) {
            $host = parse_url($path, PHP_URL_HOST);
            if ($host) {
                return $path;
            }
        }
        $app = Factory::getApplication();

        //for the web or with live_site set. Joomla picks the right url
        //Uri::root does not get correct url when runnning the CLI ( Joomla 4.4.0 and 5.0.0 at least)
        if ($app->isClient('cli') && '' === $app->get('live_site', '')) {
            //ConsoleApplication.php give joomla.invalid
            $input    = $app->getConsoleInput();
            if ($input->hasParameterOption(['--live-site', false])) {
                $liveSite = $input->getParameterOption(['--live-site'], '');
            }

            //try the components config
            $liveSite = $liveSite ?: ComponentHelper::getParams('com_blc')->get('live_site', '');

            if (!$liveSite) {
                throw new \RuntimeException(Text::_('COM_BLC_MISSING_LIVE_SITE'));
            }
            $app->set('live_site', $liveSite);
            Uri::reset();
        }

        $url = Uri::root(false);
        if (strpos($url, 'http') !== 0) {
            throw new \RuntimeException(Text::sprintf('COM_BLC_INVALID_LIVE_SITE', $url));
        }
        $url = rtrim($url, '/') . '/';

        if ($path) {
            $url .= ltrim($path, '/');
        }

        return $url;
    }

    /**
     * Gets a list of the actions that can be performed.
     *
     * @return  Registry
     *
     * @since   1.0.0
     */
    public static function getActions(): Registry
    {

        $result = new Registry();


        $user = Factory::getApplication()->getIdentity();
        if (!$user) {
            return $result;
        }

        $assetName = 'com_blc';
        $actions   = [
            'core.manage', 'core.options',
        ];

        foreach ($actions as $action) {
            $result->set($action, $user->authorise($action, $assetName));
        }

        return $result;
    }

    public static function setLastAction($who, $ajaxEvent)
    {

        $date = new Date();
        $unix = $date->toUnix();
        $data = [
            'who'  => $who,
            'ip'   => BlcHelper::getIP(),
            'last' => $unix,
        ];

        $transient = "Cron {$ajaxEvent}";
        BlcTransientManager::getInstance()->set($transient, $data, true);
    }

    public static function getIP()
    {
        $app = Factory::getApplication();
        if ($app->isClient('cli')) {
            return '127.0.0.1';
        }
        //Joomla takes care of HTTP_ headers via behind_loadbalancer
        $ip = IpHelper::getIp();
        if (empty($ip)) {
            $ip = '127.0.0.2';
        }

        return $ip;
    }
/*only used in dev */
    public static function printMemory()
    {
        static $previousUsage = 0;
        static $previousPeak  = 0;
        /* Currently used memory */
        $memUsage = memory_get_usage();
        /* Peak memory usage */
        $memPeak = memory_get_peak_usage();
        echo "<h3>Memort</h3>";
        echo 'Current: <strong>' . round($memUsage / 1024) . 'KB</strong> of memory.<br>';
        if ($previousUsage) {
            echo 'Difference: <strong>' . round(($memUsage - $previousUsage) / 1024) . 'KB</strong> of memory.<br>';
        }
        echo 'Peak: <strong>' . round($memPeak / 1024) . 'KB</strong> of memory.<br>';
        if ($previousPeak) {
            echo 'Peak difference: <strong>' . round(($memPeak - $previousPeak) / 1024) . 'KB</strong> of memory.<br>';
        }
        $previousUsage =  $memUsage;
        $previousPeak  =  $memPeak;
    }
}
