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

        if ($stats['Total']->links == 0 || $stats['Total']->items == 0) {
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
                $button = new TooltipButton('link-replace', 'Thash Al');
                $button->buttonClass('btn btn-danger')->icon('icon-trash')->listCheck(false);

                print "<tr><td></td>";
                foreach (array_keys($stats) as $plugin) {
                    if ($plugin == 'Total') {
                        echo '<td>&nbsp;</td>';
                        continue;
                    }
                    // phpcs:disable Generic.Files.LineLength
                    $task = Route::_('index.php?option=com_blc&do=delete&what=synch&plugin=' . $plugin . '&task=link.trashit&return=' . $return);
                    // phpcs:enable Generic.Files.LineLength
                    $button->url($task)
                        ->tooltip("This will remove all parsed links for this plugin")
                        ->text("Purge");
                    $toolbar->appendButton($button);
                    echo '<td>' . $button->render() . '</td>';
                }
                print '</tr>';
            }
            $lang =  Factory::getApplication()->getLanguage();

            foreach ($headings as $heading) {
                $captionKey = "COM_BLC_SETUP_" . strtoupper($heading) . "_CAPTION";
                $header     = ucfirst($heading);
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
                    $count  = $stat->$heading;
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
                            <a  href="' . $link . '" class="' . $btnClass . '">' . $stat->$heading . '</a>
                            </td>';
                    } else {
                        print '<td class="text-center btns itemnumber">
                              <span  class="disabled btn btn-secondary">' . $stat->$heading . '</span>
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
        $links          = self::getCountLinks();
        $synch          = self::getCountSynch();
        $links->items   = array_sum(array_column($synch, 'items'));
        $synch['Total'] = $links;
        return $synch;
    }
    private function sumSelectQuery(&$query)
    {
        $active  = HTTPCODES::BLC_WORKING_ACTIVE;
        $working = HTTPCODES::BLC_WORKING_WORKING;
        $ignore  = HTTPCODES::BLC_WORKING_IGNORE;
        $hidden  = HTTPCODES::BLC_WORKING_HIDDEN;

        $notActive = "(`working` != $active )";
        // phpcs:disable Generic.Files.LineLength
        $query
            ->select("SUM(CASE WHEN `working` = $hidden then 1 else 0 end) as `hidden`")
            ->select("SUM(CASE WHEN `working` = $ignore then 1 else 0 end) as `ignored`")
            ->select("SUM(CASE WHEN `working` = $working then 1 else 0 end) as `working`")
            ->select("SUM(CASE WHEN $notActive OR `broken` != 1 then 0 else 1 end) as `broken`")
            ->select("SUM(CASE WHEN $notActive OR `broken` != 2 then 0 else 1 end) as `warning`")
            ->select("SUM(CASE WHEN $notActive OR `broken` != 3 then 0 else 1 end) as `timeout`")
            ->select("SUM(CASE WHEN $notActive OR `redirect_count` = 0 then 0 else 1 end) as `redirect`")
            ->select("SUM(CASE WHEN $notActive OR `internal_url` = '' then 0 else 1 end) as `internal`")
            ->select("SUM(CASE WHEN $notActive OR `internal_url` = '' OR `internal_url` =  `url` then 0 else 1 end) as `changed`")
            ->select("SUM(CASE WHEN $notActive OR `internal_url` != '' then 0 else 1 end) as `external`")
            ->select("SUM(CASE WHEN $notActive OR `http_code` != 0  then 0 else 1 end) as `unchecked`")
            ->select("SUM(CASE WHEN `working` = $ignore OR `http_code` = 0  then 0 else 1 end) as `checked`");
        // phpcs:enable Generic.Files.LineLength
    }
    public function getCountSynch()
    {

        if (!isset($this->results[__METHOD__])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            //  $subQuery = "SELECT `link_id`,`plugin_name`,count(DISTINCT `container_id`) `ccount`
            $subQuery = "SELECT `link_id`,`plugin_name`,`container_id`
		FROM `#__blc_instances` `i`
		JOIN `#__blc_synch` `s` ON ( `synch_id` = `s`.`id`)
		GROUP BY `plugin_name`,`link_id`";
            $query->select('`plugin_name` `plugin`')
                //->select('sum(`sub`.`ccount`) `items`')
                ->select('count(DISTINCT `l`.`id`) `links`')
                ->select('count(DISTINCT `sub`.`container_id`) `items`')
                ->from("($subQuery) `sub`")
                ->innerJoin('`#__blc_links` `l` ON `sub`.`link_id` = `l`.`id`')
                ->group('`plugin_name`');
            $this->sumSelectQuery($query);
            $db->setQuery($query);
            $this->results[__METHOD__] =  $db->loadObjectList('plugin');
        }
        return $this->results[__METHOD__];
    }

    public function getCountLinks()
    {
        if (!isset($this->results[__METHOD__])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select('count(*) `links`')
                ->from('`#__blc_links` `l`')
                ->where('EXISTS (SELECT * FROM `#__blc_instances` `i` WHERE `i`.`link_id` = `l`.`id`)');
            $this->sumSelectQuery($query);
            $db->setQuery($query);
            $this->results[__METHOD__] =  $db->loadObject();
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
        print  Text::sprintf("Currently there are %d %s.", $count, $type);
        print  " ";
        print  Text::sprintf("The interval is set to %s hours.", $interval);
        print  " ";
        print  Text::sprintf("The batch size %s.", $batch);
        if ($batch == 0) {
            return;
        }

        $cmd = htmlspecialchars($cmd);
        print  " ";
        $numberBatches = ceil($count / $batch);
        print  Text::sprintf("You will need %s run(s) to recheck al %s.", $numberBatches, $type);
        $hours = $interval / $numberBatches;
        print  "<br>";
        if ($hours > 1) {
            $hours = floor($hours);
            print  Text::sprintf("That is every %d hours.", $hours);
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
                print  Text::sprintf("That is every %d minutes.", $minutes);
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
