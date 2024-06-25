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

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;

use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Utilities\ArrayHelper;

/**
 * Methods supporting a list of Links records.
 *
 * @since  1.0.0
 */
class LinksModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'special',
                'response',
                'destination',
                'mime',
            ];
        }
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        parent::__construct($config);
    }


    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null)
    {
        // List state information.
        parent::populateState('id', 'ASC');

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');


        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);
            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.special');
        $id .= ':' . $this->getState('filter.working');
        $id .= ':' . $this->getState('filter.destination');
        $id .= ':' . $this->getState('filter.plugin');
        $id .= ':' . $this->getState('filter.response');
        $id .= ':' . $this->getState('filter.mime');
        return parent::getStoreId($id);
    }

    public function getEmptyInfo()
    {
        ob_start();
        $redirect = Route::_('index.php?option=com_blc&view=setup');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->from('`#__blc_links` AS a')->select('count(*) `c`');
        $linkCount = $db->setQuery($query)->loadResult();

        $unCheckCount = $this->getToCheck(true);

        $query = $db->getQuery(true);
        //check op _transient wellicht later inbouwen. Lijkt niet nodig aangezien alles leeg gaat bij een reset
        $query->from('`#__blc_synch` AS a')
            ->select('count(DISTINCT `container_id`,`plugin_name`) `c`')
            ->group('`container_id`')
            ->group('`plugin_name`');
        $synchCount = $db->setQuery($query)->loadResult();
        if ($synchCount == 0 || $linkCount == 0) {
            print Text::sprintf('COM_BLC_MESSAGE_NO_PARSED_DATA', $redirect);
        }
        if ($unCheckCount != 0) {
            print Text::sprintf('COM_BLC_MESSAGE_UNCHECKED_LINKS', $unCheckCount, $redirect);
        }

        return ob_get_clean();
    }


    /**
     * add a query part for the  working filter
     * @param QueryInterface $query
     * @param array<string> $exolude
     * @return void
     * @since __DEPLOY_VERSION__
     */

    public function addToquery(QueryInterface $query, $exclude = [])
    {
        if (!in_array('instance', $exclude)) {
            $addPlugin=!in_array('plugin', $exclude);
            $addSearch=!in_array('search', $exclude);
            $this->addInstanceToQuery($query,$addPlugin,$addSearch);
        }
        
        if (!in_array('working', $exclude)) {
            $this->addWorkingToQuery($query);
        }
        if (!in_array('mime', $exclude)) {
            $this->addMimeToQuery($query);
        }
        if (!in_array('response', $exclude)) {
            $this->addReponseToQuery($query);
        }
        if (!in_array('special', $exclude)) {
            $this->addSpecialToQuery($query);
        }
        if (!in_array('destination', $exclude)) {
            $this->addDestinationToQuery($query);
        }

        if (!in_array('search', $exclude)) {
            $this->addSearchToQuery($query);
        }
    }


    /**
     * add a query part for the  special filter
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */


    protected function addSpecialToQuery(QueryInterface $query): void
    {
        $special      = $this->getState('filter.special', 'broken');
        $specialQuery =  match ($special) {
            'timeout'  => '`broken` = ' . HTTPCODES::BLC_BROKEN_TIMEOUT, //COM_BLC_OPTION_WITH_TIMEOUT
            'broken'   => '`broken` = ' . HTTPCODES::BLC_BROKEN_TRUE, //COM_BLC_OPTION_WITH_BROKEN
            'redirect' => '`redirect_count` > 0',  //COM_BLC_OPTION_WITH_REDIRECT
            'warning'  => '`broken` = ' . HTTPCODES::BLC_BROKEN_WARNING, //COM_BLC_OPTION_WITH_WARNING
            'internal' => '`internal_url` != \'\' AND  `internal_url` != `url`', //COM_BLC_OPTION_WITH_INTERNAL_MISMATCH
            'pending'  => join(' OR ', $this->getRecheck()),
            'parked'   => join(' OR ', HTTPCODES::DOMAINPARKINGSQL),
            default    => ''
        };

        if ($specialQuery) {
            $query->where('(' . $specialQuery . ')');
            if ($special == 'parked') {
                $query->leftJoin($query->quoteName('#__blc_links_storage', 'ls'), '`ls`.`link_id` = `a`.`id`');
            }
        }
    }



    /**
     * add a query part for the  working filter
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */

    protected function addWorkingToQuery(QueryInterface $query): void
    {
        $isWorking = $this->getState('filter.working', 0);
        if ($isWorking != -1) {
            $query->where('(`working` = :isWorking)')->bind(':isWorking', $isWorking);
        }
    }
 /**
     * add a query part for the  search filter
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */

    protected function addSearchToQuery(QueryInterface $query): void
    {
    $search = $this->getState('filter.search', '');
    if ($search && stripos($search, 'anchor:') !== 0) {
        $search = '%' . str_replace(' ', '%', trim($search)) . '%';
        $query->extendWhere(
            'AND',
            [
                $query->quoteName('a.url') . ' LIKE :url',
                $query->quoteName('a.internal_url') . ' LIKE :internalurl',
                $query->quoteName('a.final_url') . ' LIKE :finalurl',
            ],
            'OR'
        );
        $query->bind([':url', ':internalurl', ':finalurl'], $search);
    }
}

    /**
     * add a query part for the  destination filter
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */

    protected function addDestinationToQuery(QueryInterface $query): void
    {

        $destination = (int)$this->getState('filter.destination', -1);

        switch ($destination) {
            case 'external':
            case 0:
                $query->where("( `internal_url` = '' )");
                break;
                case 'internal':
            case 1:
                $query->where("( `internal_url` != '' )");
                break;
            default:
                break;
        }
    }


    /**
     * add a query part for the  mime filter
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */

    protected function addMimeToQuery(QueryInterface $query): void
    {

        $mimeFilter = $this->getState('filter.mime', '');
        if ($mimeFilter) {
            $query->where("( `mime` = :mime)")
                ->bind(':mime', $mimeFilter);
        }
    }

    /**
     * add a query part for the  response filter
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */

    protected function addReponseToQuery(QueryInterface $query): void
    {

        $response = (int)$this->getState('filter.response', -1); //mysql save

        if ($response > -1) {
            switch ($response) {
                case 0:
                    //unchecked and throttled\
                    $responseQuery = '(`http_code` IN  (' . join(',', HTTPCODES::UNCHECKEDHTTPCODES) . ') )';
                    break;
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                    $start         = $query->quote($response * 100);
                    $end           = $query->quote($response * 100 + 99);
                    $responseQuery = "((`http_code` BETWEEN $start AND $end))";
                    break;


                default:
                    $responseQuery = '(`http_code`= ' . $query->quote($response) . ')';
                    break;
            }
            $query->where($responseQuery);
        }
    }

      /**
     * add a query part for the instances ( for existing links) and plugin filter to the query
     * @param QueryInterface $query
     * @return void
     * @since __DEPLOY_VERSION__
     */


    protected function addInstanceToQuery(QueryInterface $query,bool $addPlugin=true,bool $addSearch=true): void
    {

     // Create a new query object.
     $db    = $this->getDatabase();

     $instanceQuery = $db->getQuery(true);
     // Select the required fields from the table.

     $instanceQuery->select('*')
         ->from('`#__blc_instances` `i`')
         ->where('`a`.`id` = `i`.`link_id`');
if ( $addPlugin) {
     $plugin = $this->getState('filter.plugin', '');
     if ($plugin) {
         $instanceQuery->Join(
             'INNER',
             '`#__blc_synch` `s`',
             '(`s`.`id` = `i`.`synch_id` AND `s`.`plugin_name` = ' . $db->quote($plugin) . ' )'
         );
     }
    }
    if ( $addSearch) {
     $search = $this->getState('filter.search', '');
     if ($search && stripos($search, 'anchor:') === 0) {
         $search = '%' . substr($search, 7) . '%';
         $instanceQuery->where('(' . $db->quoteName('i.link_text') . ' LIKE ' . $db->quote($search) . ' )');
       
     }
    }
     $query->where('EXISTS (' . $instanceQuery->__toString() . ')');
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   1.0.0
     */

    protected function getListQuery(): QueryInterface
    {
        // Create a new query object.
        $db    = $this->getDatabase();

        $instanceQuery = $db->getQuery(true);
        // Select the required fields from the table.

        $instanceQuery->select('*')
            ->from('`#__blc_instances` `i`')
            ->where('`a`.`id` = `i`.`link_id`');

        $plugin = $this->getState('filter.plugin', '');
        if ($plugin) {
            $instanceQuery->Join(
                'INNER',
                '`#__blc_synch` `s`',
                '(`s`.`id` = `i`.`synch_id` AND `s`.`plugin_name` = ' . $db->quote($plugin) . ' )'
            );
        }

        $search = $this->getState('filter.search', '');
        if ($search && stripos($search, 'anchor:') === 0) {
            $search = '%' . substr($search, 7) . '%';
            $instanceQuery->where('(' . $db->quoteName('i.link_text') . ' LIKE ' . $db->quote($search) . ' )');
            $search = '';
        }


        $query = $db->getQuery(true);
        //only get what's need. Espeicaly ommit the larg e log and data blobs
        $query->select(
            [
                '`a`.`id`',
                '`url`',
                '`final_url`',
                '`internal_url`',
                '`http_code`',
                '`broken`',
                '`working`',
                '`mime`',
                '`redirect_count`',
            ]
        );

        $query->from($db->quoteName('#__blc_links', 'a'));

        $this->addInstanceToQuery($query);
        $this->addReponseToQuery($query);
        $this->addReponseToQuery($query);
        $this->addSpecialToQuery($query);
        $this->addWorkingToQuery($query);
        $this->addMimeToQuery($query);
        $this->addDestinationToQuery($query);
        $this->addSearchToQuery($query);

      

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    protected function logListeners($event)
    {
        $list           = [];
        $eventName      = $event->getName();
        $dispatcher     = Factory::getApplication()->getDispatcher();
        $lastExtractors = $dispatcher->getListeners($eventName);
        foreach ($lastExtractors as $listener) {
            if (\is_array($listener)) {
                $class        = \get_class($listener[0]);
                $list[$class] = $dispatcher->getListenerPriority($eventName, $listener);
            }
        }
        BlcTransientManager::getInstance()->set('lastListeners:' . $eventName, $list, true);
    }

    public function runBlcExtract($maxExtract): BlcExtractEvent
    {
        //assume blc plugin group is loaded
        $arguments =
            [
                'maxExtract' => $maxExtract,
            ];
        BlcHelper::setCronState(false);
        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $this->logListeners($event);
        Factory::getApplication()->getDispatcher()->dispatch('onBlcExtract', $event);
        return $event;
    }

    public function runBlcCheck(int $checkLimit, bool $moreOnThrottle = false)
    {
        $links      = [];
        $seen       = [];
        $checkLink  = BlcCheckLink::getInstance();
        while ($checkLimit) {
            $rows        = $this->getToCheck(checkLimit: $checkLimit, ignoreIds: $seen);
            $checkLimit  = 0;
            if ($rows) {
                foreach ($rows as $linkId) {
                    $link = $checkLink->checkLinkId($linkId);
                    if ($moreOnThrottle && ($link->http_code == HTTPCODES::BLC_THROTTLE_HTTP_CODE)) {
                        //this avoids an enless loop when the database only contains links to domains that are throttled
                        $seen[] = $link->id;
                        $checkLimit++;
                    }
                    $links[] = $link;
                }
            }
        }
        return $links;
    }

    /*
    This is the cron for the admin pseudo module
    it runs an extract and when those are done it checks a single link
    */

    public function cron()
    {

        $lock = BlcMutex::getInstance()->acquire(minLevel: BlcMutex::LOCK_SERVER);
        if (!$lock) {
            $response = [
                'msgshort' => "614 - Running",
                'msglong'  => "Another instance of the broken link checker is running",
                'status'   => 'Unable',
                'count'    => 1,
                'log'      => '',
            ];
            return $response;
        }

        PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        $maxExtract = 5;
        ob_start();
        $event  = $this->runBlcExtract($maxExtract);
        $log    = ob_get_clean();
        $parsed = $event->getdidExtract();
        if ($parsed > 0) {
            $lastExtractor = $event->getExtractor();
            $todoExtract   = $event->getTodo();
            //only report if anything happened
            BlcHelper::setLastAction('Admin', 'Extract');
            $timezone = Factory::getApplication()->getIdentity()->getTimezone();
            $date     = new Date();
            $date->setTimezone($timezone);

            $response = [
                'msgshort' => "[$todoExtract] $lastExtractor",
                'msglong'  => "[$todoExtract] - Extracted $parsed in $lastExtractor",
                'status'   => 'Good',
                'count'    => $todoExtract,
                'log'      => $log,
            ];
            return $response;
        }


        //nothing to extract so check a link.

        //in general the admin pseudo cron will be slow enough so we don't hit any host-throttles. So leave the moreOnThrottle to false
        $links = $this->runBlcCheck(checkLimit: 1);
        $count = $this->getToCheck(true);
        $lock  = BlcMutex::getInstance()->acquire();
        if (!$lock) {
            $response = [
                'msgshort' => "614 - Running",
                'msglong'  => "Another instance of the broken link checker is running",
                'status'   => 'Unable',
                'count'    => 1,
                'log'      => '',
            ];
            return $response;
        }

        if ($links) {
            //only report if anything happened
            BlcHelper::setLastAction('Admin', 'Check');
            $link = $links[0];
            if ($link) {
                switch ($link->http_code) {
                    case HTTPCODES::BLC_THROTTLE_HTTP_CODE:
                        $text  = "Throttle";
                        $short = "Domain Throttle";
                        break;
                    case HTTPCODES::BLC_UNABLE_TOCHECK_HTTP_CODE:
                        $text  = 'Unable';
                        $short = "Unable to check";
                        break;
                    default:
                        if ($link->broken) {
                            $text = 'Broken';
                        } else {
                            if ($link->redirect_count && ($link->url != $link->final_url)) {
                                $text = 'Redirect';
                            } else {
                                $text = 'Good';
                            }
                        }
                        $short = substr($link->url, 0, 200);
                        break;
                }

                $code     = sprintf('%3s', $link->http_code);
                $duration = sprintf('[%1.2f]', $link->request_duration);
                $url      = $link->toString();
                // phpcs:disable Generic.Files.LineLength
                $base     = sprintf("[%d] %s:%3s", $count, $text, $code);
                $response = [
                    'msgshort' => "<a title=\"{$short}\" href=\"{$link}\" target=\"checked\">$base</a>",
                    'msglong'  => "$base $duration - <a href=\"{$url}\" target=\"checked\">$short</a>",
                    'status'   => $text,
                    'count'    => $count,
                ];
                return $response;
            }
        }
        //no links found so the  $count = $this->getToCheck(true); should be zero
        if ($count == 0) {
            BlcHelper::setCronState(true);
            $response = [
                'msgshort' => '<span class="Final Good">Done</span>',
                'msglong'  => '<span class="Final Good">Done</span>',
                'status'   => 'Good',
                'count'    => $count,
            ];
            return $response;
        }

        //this is mostyl wrong however there could be an extract between the start of runBlcCheck and the call to  $count = $this->getToCheck(true);
        $response = [
            'msgshort' => '<span class="Unable">Working</span>',
            'msglong'  => '<span class="Unable">Working</span>',
            'status'   => 'Unable',
            'count'    => $count,
        ];
        return $response;


        return $response;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems()
    {
        if ($this->getState('filter.special', '') == '') {
            $this->setState('filter.special', 'broken');
            $items = parent::getItems();
            if (\count($items) == 0) {
                Factory::getApplication()->setUserState($this->context . '.filter.special', 'all');
                Factory::getApplication()->redirect(Uri::getInstance());
            }
            Factory::getApplication()->setUserState($this->context . '.filter.special', 'broken');
        } else {
            $items = parent::getItems();
        }

        return $items;
    }
    protected function getRecheck()
    {
        $checkThreshold = BlcHelper::intervalTohours(
            (int)$this->componentConfig->get('check_threshold', 168),
            $this->componentConfig->get('check_thresholdUnit', 'hours')
        );
        $brokenThreshold = BlcHelper::intervalTohours(
            (int)$this->componentConfig->get('broken_threshold', 24),
            $this->componentConfig->get('broken_thresholdUnit', 'hours')
        );

        $recheckCount = (int)$this->componentConfig->get('recheck_count', 3);
        // phpcs:disable Generic.Files.LineLength
        return [
            'never'   => '`http_code` IN (' . join(',', HTTPCODES::UNCHECKEDHTTPCODES) . ')',
            'working' => "(`working` != 2 AND `broken` = " . HTTPCODES::BLC_BROKEN_FALSE . " AND `last_check` <  UTC_TIMESTAMP() - INTERVAL $checkThreshold HOUR)",
            'broken'  => "(`working` != 2 AND `broken` != " . HTTPCODES::BLC_BROKEN_FALSE . "  AND `check_count` < $recheckCount AND `last_check` <  UTC_TIMESTAMP() - INTERVAL $brokenThreshold HOUR)",
        ];
        // phpcs:enable Generic.Files.LineLength
    }

    public function getToCheck($count = false, $checkLimit = 10, array $ignoreIds = [])
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $now = Factory::getDate('now - 1 minute')->toSql();
        $query->update('`#__blc_links`')
            ->set('`being_checked` = 0 ')
            ->where('`being_checked` != 0 ')
            ->where('`last_check_attempt` < ' . $db->quote($now));
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true);
        $query->from('`#__blc_links` AS `l`')
            ->where('`l`.`being_checked` = 0');
        if ($count) {
            $query->select('count(*) `c`');
        } else {
            //just get the id, linkcheck will fetch the whole object.
            $query->select('`l`.`id` as `id`')
                ->order('`http_code` ASC') //unchecked first
                ->order('`last_check_attempt` ASC')
                ->setLimit($checkLimit);
        }

        $query->where("EXISTS (SELECT * FROM `#__blc_instances` `i` WHERE `i`.`link_id` = `l`.`id`)")
            ->extendWhere(
                'AND',
                $this->getRecheck(),
                'OR'
            );

        if (\count($ignoreIds)) {
            $query->whereNotIn($db->quoteName('id'), $ignoreIds, ParameterType::INTEGER);
        }

        $db->setQuery($query);
        if ($count) {
            return $db->loadResult();
        }
        return $db->loadColumn();
    }


    public function hideLinks(array $pks)
    {
        if (!\is_array($pks)) {
            $pks = [$pks];
        }

        ArrayHelper::toInteger($pks);
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete('`#__blc_links`')

            ->whereIn('id', $pks);

        $db->setQuery($query)->execute();
    }

    public function changeState(array $pks, string $column, int|string $value)
    {
        if (!\is_array($pks)) {
            $pks = [$pks];
        }

        ArrayHelper::toInteger($pks);
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->update('`#__blc_links`')
            ->set($db->quoteName($column) . ' = ' . $db->quote($value))
            ->whereIn('id', $pks);

        $db->setQuery($query)->execute();
    }
}
