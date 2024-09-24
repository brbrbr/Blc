<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Button\TooltipButton;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as  HTTPCODES;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;

/**
 * Setup model.
 *
 * @since  1.0.0
 */
class SetupModel extends BaseDatabaseModel
{
    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_BLC';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.0
     */
    public $typeAlias = 'com_blc.link';

    /**
     * @var    null  Item data
     *
     * @since  1.0.0
     */
    protected $item = null;

    protected $plugins = [];
    private $results   = [];

    public function __construct($config = [])
    {
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        parent::__construct($config);
    }
    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @since   1.0.0
     */
    public function getTable($type = 'Synch', $prefix = 'Administrator', $config = [])
    {
        return parent::getTable($type, $prefix, $config);
    }
    public function getStatsHtml()
    {

        ob_start();
        $stats = self::getStats();

        if ($stats['Total']['links'] == 0 || $stats['Total']['items'] == 0) {
            echo '<strong>Please parse and check your links</strong>';
        } else {
            $headings = [
                'items',
                'links',
                'checked',
                'unchecked',
                'broken',
                'warning',
                'timeout',
                'internal',
                'changed',
                'external',
                'redirect',
                'hidden',
                'working',
                'ignored',
            ];
            $header = '<tr><th></th><th>' . join('</th><th>', array_keys($stats)) . '</th></tr>';

            echo   '<table class="table table-striped">';
            echo $header;
            $canDo = BlcHelper::getActions();
            if ($canDo->get('core.manage')) {
                $toolbar             = new Toolbar();
                $uri                 = (string) Uri::getInstance();
                //JED Cecker Warning: encode return URL like Joomla does
                $return = urlencode(base64_encode($uri));
                $button = new TooltipButton('link-replace', 'COM_BLC_SETUP_PURGE_BUTTON_LBL');
                $button->buttonClass('btn btn-danger')->icon('icon-trash')->listCheck(false)->tooltip(Text::_("COM_BLC_SETUP_PURGE_BUTTON_DESC"));

                print "<tr><td></td>";
                foreach (array_keys($stats) as $plugin) {
                    if ($plugin == 'Total') {
                        echo '<td>&nbsp;</td>';
                        continue;
                    }
                    // phpcs:disable Generic.Files.LineLength
                    $task = Route::_('index.php?option=com_blc&do=delete&what=synch&plugin=' . $plugin . '&task=link.trashit&return=' . $return);
                    // phpcs:enable Generic.Files.LineLength
                    $button->url($task);
                    $toolbar->appendButton($button);
                    echo '<td>' . $button->render() . '</td>';
                }
                print '</tr>';
            }
            $lang =  Factory::getApplication()->getLanguage();

            foreach ($headings as $heading) {
                $captionKey = "COM_BLC_SETUP_" . strtoupper($heading) . "_CAPTION";
                $headingKey = "COM_BLC_SETUP_" . strtoupper($heading) . "_HEADING";
                $header     = $lang->hasKey($headingKey) ? Text::_($headingKey) : ucfirst($heading);
                $caption    = $lang->hasKey($captionKey) ? Text::_($captionKey) : '';
                echo "<tr>
                            <th>
                              <span  aria-labelledby=\"label-$heading\" data-toggle=\"tooltip\" title=\"$caption\">
                                $header
                             </span>
                             ";
                if ($caption) {
                    echo "&nbsp<i class=\"fas fa-question-circle\"></i>
                        <div role=\"tooltip\" id=\"label-$heading\">$caption</div>";
                }
                echo "</th>";
                foreach ($stats as $plugin => $stat) {
                    $count  = $stat[$heading];
                    $plugin = $plugin == 'Total' ? '' : $plugin;
                    $query  =
                        [
                            'option'           => 'com_blc',
                            'task'             => 'links.filter',
                            "filter[plugin]"   => $plugin,
                            "filter[special]"  => 'all',
                            "filter[internal]" => '-1',
                            "filter[working]"  => '0',
                        ];
                    if ($count > 0) {
                        $btnClass = 'btn btn-success';
                        switch ($heading) {
                            case 'broken':
                                $btnClass                 = 'btn btn-error';
                                $query['filter[special]'] = 'broken';
                                break;
                            case 'warning':
                                $btnClass                 = 'btn btn-error';
                                $query['filter[special]'] = 'warning';
                                break;
                            case 'timeout':
                                $btnClass                 = 'btn btn-error';
                                $query['filter[special]'] = 'timeout';
                                break;
                            case 'redirect':
                                $query['filter[special]'] = 'redirect';
                                $btnClass                 = 'btn btn-warning';
                                break;
                            case 'unchecked':
                                $query['filter[response]'] = 0;

                                break;
                            case 'changed':
                                $query['filter[internal]'] = 1;
                                $query['filter[special]']  = 'internal';

                                break;
                            case 'internal':
                                $query['filter[internal]'] = 1;

                                break;
                            case 'external':
                                $query['filter[internal]'] = 0;

                                break;
                            case 'working':
                                $query['filter[working]'] = HTTPCODES::BLC_WORKING_WORKING;

                                break;
                            case 'ignored':
                                $query['filter[working]'] =  HTTPCODES::BLC_WORKING_IGNORE;

                                break;
                            case 'hidden':
                                $query['filter[working]'] =  HTTPCODES::BLC_WORKING_HIDDEN;

                                break;
                            default:
                        }


                        $url  = Uri::buildQuery($query);
                        $link = Route::link('administrator', 'index.php?' . $url);
                        print '<td class="text-center btns itemnumber">
                            <a  href="' . $link . '" class="' . $btnClass . '">' . $stat[$heading] . '</a>
                            </td>';
                    } else {
                        print '<td class="text-center btns itemnumber">
                              <span  class="disabled btn btn-secondary">' . $stat[$heading] . '</span>
                            </td>';
                    }
                }
                print '</tr>';
            }
            echo '</table>';
            echo '<p>' . Text::_('COM_BLC_TRASHIT_TEXT') . '</p>';
        }
        return ob_get_clean();
    }

    public function getStats()
    {
        //The total might be lower then the sum as same link might be on several pages
        $links            = self::getCountLinks();
        $synch            = self::getCountSynch();
        $links['items']   = array_sum(array_column($synch, 'items'));
        $synch['Total']   = $links;

        return $synch;
    }
    private function sumSelectQuery($query)
    {
        $active  = HTTPCODES::BLC_WORKING_ACTIVE;
        $working = HTTPCODES::BLC_WORKING_WORKING;
        $ignore  = HTTPCODES::BLC_WORKING_IGNORE;
        $hidden  = HTTPCODES::BLC_WORKING_HIDDEN;
        $db      = $this->getDatabase();

        $notActive = "({$db->quoteName('working')} != {$active})";
        // phpcs:disable Generic.Files.LineLength
        $query
            ->select("SUM(CASE WHEN {$db->quoteName('working')} = {$hidden} then 1 else 0 end) as  {$db->quoteName('hidden')}")
            ->select("SUM(CASE WHEN {$db->quoteName('working')} = {$ignore} then 1 else 0 end) as  {$db->quoteName('ignored')}")
            ->select("SUM(CASE WHEN {$db->quoteName('working')} = {$working} then 1 else 0 end) as   {$db->quoteName('working')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('broken')} != 1 then 0 else 1 end) as  {$db->quoteName('broken')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('broken')} != 2 then 0 else 1 end) as  {$db->quoteName('warning')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('broken')} != 3 then 0 else 1 end) as  {$db->quoteName('timeout')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('redirect_count')} = 0 then 0 else 1 end) as  {$db->quoteName('redirect')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('internal_url')} =  {$db->quote('')}  then 0 else 1 end) as  {$db->quoteName('internal')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('internal_url')} =  {$db->quote('')} OR {$db->quoteName('internal_url')} =  {$db->quoteName('url')}  then 0 else 1 end) as  {$db->quoteName('changed')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('internal_url')} != {$db->quote('')} then 0 else 1 end) as {$db->quoteName('external')}")
            ->select("SUM(CASE WHEN $notActive OR {$db->quoteName('http_code')} != 0  then 0 else 1 end) as {$db->quoteName('unchecked')}")
            ->select("SUM(CASE WHEN {$db->quoteName('working')}  = {$ignore} OR {$db->quoteName('http_code') } = 0  then 0 else 1 end) as {$db->quoteName('checked')}");
        // phpcs:enable Generic.Files.LineLength
    }
    public function getCountSynch()
    {

        if (!isset($this->results[__METHOD__])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $subQuery   = $db->getQuery(true);
            $subSelects = $db->quoteName([
                'link_id',
                'plugin_name',
            ]);

            $subQuery->select($subSelects)
                ->from($db->quoteName('#__blc_instances', 'i'))
                ->join('INNER', $db->quoteName('#__blc_synch', 's'), $db->quoteName('i.synch_id') . ' = ' . $db->quoteName('s.id'))
                ->group($subSelects);

            $query->from("($subQuery)  {$db->quoteName('sub')}")
                ->select($db->quoteName('plugin_name', 'plugin'))
                //->select('sum(`sub`.`ccount`) `items`')
                ->join('LEFT', $db->quoteName('#__blc_links', 'l'), "{$db->quoteName('sub.link_id')}  = {$db->quoteName('l.id')}")
                ->group($db->quoteName('plugin_name'));
            $this->sumSelectQuery($query);
            $db->setQuery($query);
            $checkedCount = $db->loadAssocList('plugin');

            $query = $db->getQuery(true);
            $query->from($db->quoteName('#__blc_links', 'l'))
                ->join('INNER', $db->quoteName('#__blc_instances', 'i'), "{$db->quoteName('i.link_id')}  =  {$db->quoteName('l.id')}")
                ->join('INNER', $db->quoteName('#__blc_synch', 's'), "{$db->quoteName('i.synch_id')}  =  {$db->quoteName('s.id')}")

                ->select($db->quoteName('plugin_name', 'plugin'))
                ->select("count(DISTINCT {$db->quoteName('l.id')}) as {$db->quoteName('links')}")
                ->select("count(DISTINCT {$db->quoteName('s.container_id')}) as {$db->quoteName('items')}")
                ->group($db->quoteName('plugin_name'));
            $db->setQuery($query);
            $itemCount = $db->loadAssocList('plugin');

            $this->results[__METHOD__] = array_merge_recursive($itemCount, $checkedCount);
        }

        return $this->results[__METHOD__];
    }

    public function getCountLinks()
    {
        if (!isset($this->results[__METHOD__])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select("count(*) as {$db->quoteName('links')}")
                ->from($db->quoteName('#__blc_links', 'l'))
                ->where("EXISTS (SELECT * FROM {$db->quoteName('#__blc_instances', 'i')} WHERE {$db->quoteName('i.link_id')} = {$db->quoteName('l.id')})");
            $this->sumSelectQuery($query);
            $db->setQuery($query);
            $this->results[__METHOD__] =  $db->loadAssoc();
        }
        return $this->results[__METHOD__];
    }


    public function getForm($data = [], $loadData = true)
    {
    }
    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem($pk = null)
    {

        if ($item = parent::getItem($pk)) {
            // Do any procesing on fields here if needed
        }

        return $item;
    }

    public static function lastAction($event = '')
    {
        $transientmanager = BlcTransientManager::getInstance();


        $transient = "Cron {$event}";
        $data      = $transientmanager->get($transient);

        $out = '';
        if ($data) {
            $out .= "{$event} by {$data->who}";
        }
        if (isset($data->last)) {
            $out .= " on " . HtmlHelper::date($data->last, Text::_('DATE_FORMAT_FILTER_DATETIME'));
        }

        if (isset($data->ip)) {
            $out .= " from {$data->ip}";
        }
        if ($out) {
            return $out;
        }
        return "Not yet for $event";
    }
    public static function cronEstimate(string $type, int $count, int $batch, int $interval, string $cmd)
    {

        if ($count == 0) {
            return;
        }
        print '<div class="list-group-item">';
        Text::printf("COM_BLC_SETUP_CURRENT_LINKS", $count, $type);
        print  " ";
        Text::printf("COM_BLC_SETUP_CURRENT_INTERVAL", $interval);
        print  " ";
        Text::printf("COM_BLC_SETUP_CURRENT_BATCH", $batch);
        if ($batch == 0) {
            return;
        }

        $cmd = htmlspecialchars($cmd);
        print  " ";
        $numberBatches = ceil($count / $batch);
        Text::printf("COM_BLC_SETUP_NEEDED_RUNS", $numberBatches, $type);
        $hours = $interval / $numberBatches;
        print  "<br>";
        if ($hours > 1) {
            $hours = floor($hours);
            Text::printf("COM_BLC_SETUP_NEEDED_INTERVAL_HOURS", $hours);
            print  "<br>";

            if ($hours >= 24) {
                print "<p>Example daily cron at 08:00::<br><code>0 8 * * * $cmd</code><br>";
                print "<a target=\"blank\" href=\"https://crontab.guru/#15_*/8_*_*_*\">Crontab.guru</a>";
                print "</p>";
            } elseif ($hours == 1) {
                print "<p>Example every hour : <code>15 * * * * $cmd</code><br>";
                print "<a target=\"blank\" href=\"https://crontab.guru/#15_*_*_*_*\"> Crontab.guru</a>";
                print "</p>";
            } else {
                foreach ([12, 8, 6, 4, 3, 2] as $h) {
                    if ($hours >= $h) {
                        print "<p>Example every $h hours:<br><code>15 */$h * * * $cmd</code><br>";
                        print "<a target=\"blank\" href=\"https://crontab.guru/#15_*/{$h}_*_*_*\">Crontab.guru</a>";
                        print "</p>";
                        break;
                    }
                }
            }
        } else {
            $minutes = $hours * 60;
            if ($minutes > 5) {
                $minutes = floor($minutes);
                Text::printf("COM_BLC_SETUP_NEEDED_INTERVAL_MINUTS", $minutes);
                print  "<br>";
                foreach ([30, 20, 10, 5] as $m) {
                    if ($minutes > $m) {
                        print "<p>Example every $m minutes:<br><code>*/$m * * * * $cmd</code><br>";
                        print "<a target=\"blank\" href=\"https://crontab.guru/#*/{$m}_*_*_*_*\">Crontab.guru</a>";
                        print "</p>";
                        break;
                    }
                }
            } else {
                print '<div class="list-group-item">';
                print "<strong>" . Text::_("You should increase the batch size or recheck interval.") . "</strong>";
            }
        }
        print  "</div>";
    }
}
