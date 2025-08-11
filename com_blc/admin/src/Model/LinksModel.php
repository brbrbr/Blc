<?php

declare(strict_types=1);

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
use Blc\Component\Blc\Administrator\Blc\BlcMessages;
use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;
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

    private readonly Registry $componentConfig; //A reference to the plugin's global configuration object.

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
     * Get the filter form
     *
     * @param   array    $data      data
     * @param   boolean  $loadData  load current data
     *
     * @return  \Joomla\CMS\Form\Form|null  The Form object or null if the form can't be found
     *
     * @since   3.2
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        $form = ListModel::getFilterForm($data, $loadData);
        if ($this->componentConfig->get('show_plugin_filter', 0) != '1') {
            $form->removeField('plugin', 'filter');
        }
        if ($this->componentConfig->get('show_mime_filter', 0) != '1') {
            $form->removeField('mime', 'filter');
        }
        if ($this->componentConfig->get('show_field_filter', 0) != '1') {
            $form->removeField('field', 'filter');
        }
        if ($this->componentConfig->get('show_parser_filter', 0) != '1') {
            $form->removeField('parser', 'filter');
        }

        return $form;
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
        $id .= ':' . $this->getState('filter.field');
        $id .= ':' . $this->getState('filter.parser');
        return parent::getStoreId($id);
    }

    public function getEmptyInfo()
    {
        ob_start();
        $redirect = Route::_('index.php?option=com_blc&view=setup');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->from($db->quoteName('#__blc_links', 'a'))
            ->select('count(*) ' . $db->quoteName('c'));
        $linkCount = $db->setQuery($query)->loadResult();

        $unCheckCount = $this->getToCheck(true);

        $query = $db->getQuery(true);
        //check op _transient wellicht later inbouwen. Lijkt niet nodig aangezien alles leeg gaat bij een reset
        $query->from($db->quoteName('#__blc_synch', 'a'))
            //   ->select('count(DISTINCT ' . $db->quoteName('container_id') . ',' . $db->quoteName('plugin_name') . ') as ' . $db->quoteName('c'))
            ->select('count(*) as ' . $db->quoteName('c'))
            ->group($db->quoteName('container_id'))
            ->group($db->quoteName('plugin_name'));
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
     * @since 24.44.6378
     */

    public function addToquery(QueryInterface $query, $exclude = [])
    {
        if (!\in_array('instance', $exclude)) {
            $this->addInstanceToQuery($query);
        }
        if (!\in_array('plugin', $exclude)) {
            $this->addPluginToQuery($query);
        }
        //add fields from the instance table to the query
        foreach (['field', 'parser'] as $field) {
            if (!\in_array($field, $exclude)) {
                $this->addInstanceColumnToQuery($query, $field);
            }
        }


        if (!\in_array('working', $exclude)) {
            $this->addWorkingToQuery($query);
        }
        if (!\in_array('mime', $exclude)) {
            $this->addMimeToQuery($query);
        }

        if (!\in_array('response', $exclude)) {
            $this->addReponseToQuery($query);
        }
        if (!\in_array('special', $exclude)) {
            $this->addSpecialToQuery($query);
        }
        if (!\in_array('destination', $exclude)) {
            $this->addDestinationToQuery($query);
        }

        if (!\in_array('search', $exclude)) {
            $this->addSearchToQuery($query);
        }
    }
    public function updateParked(bool $reset = false, int $id = 0)
    {

        $db    = $this->getDatabase();
        if ($db->getServerType() !== 'mysql') {
            //todo for postgesql
            return;
        }
        $parked    = implode(' OR ', HTTPCODES::DOMAINPARKINGSQL);
        $crc32     = crc32($parked); //no need to fill the database with a (large) real value.
        $transient = 'updateParked';
        $manager   = BlcTransientManager::getInstance();
        $oldCrc32  = (int)$manager->get($transient); // false to 0
        if ($crc32 != $oldCrc32) {
            $reset = true; //force a reset
            $manager->set($transient, $crc32, true); //true is ten years
        }

        /**
         * let's resett them in a seperate query. This one is pretty fast.
         * so if the second fails the 'reset' request is not lost
         * as it would when removing the ->where('`parked` = ' . HTTPCODES::BLC_PARKED_UNCHECKED) in the second query
         */
        if ($reset) {
            $query = $db->getQuery(true);
            $this->addInstanceToQuery($query);
            $query->update($db->quoteName('#__blc_links', 'a'))
                ->set($db->quoteName('parked') . ' = ' . HTTPCODES::BLC_PARKED_UNCHECKED);
            $db->setQuery($query)->execute();
        }

        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__blc_links', 'a'));
        $this->addInstanceToQuery($query);
        $query->leftJoin($db->quoteName('#__blc_links_storage', 'ls'), $db->quoteName('ls.link_id') . ' = ' . $db->quoteName('a.id'))
            ->where($db->quoteName('being_checked') . ' = ' . HTTPCODES::BLC_CHECKSTATE_CHECKED) //no point in checking pending links
            ->where($db->quoteName('broken') . ' = ' . HTTPCODES::BLC_BROKEN_FALSE) //no point in checking broken links
            ->where($db->quoteName('internal_url') . ' = ' . $db->quote('')) //no point in checking internal links
            ->set($db->quoteName('parked') . " = IF ($parked," . HTTPCODES::BLC_PARKED_PARKED . "," . HTTPCODES::BLC_PARKED_CHECKED . ")");

        if ($id) {
            $query->where($db->quoteName('a.id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('parked') . ' = ' . HTTPCODES::BLC_PARKED_UNCHECKED);
        }
        $db->setQuery($query)->execute();
    }

    /**
     * add a query part for the  special filter
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */

    protected function addSpecialToQuery(QueryInterface $query): void
    {
        $db           = $this->getDatabase();
        $special      = $this->getState('filter.special', 'broken');
        $specialQuery =  match ($special) {
            'timeout'   => $db->quoteName('broken') . ' = ' . HTTPCODES::BLC_BROKEN_TIMEOUT, //COM_BLC_OPTION_WITH_TIMEOUT
            'broken'    => $db->quoteName('broken') . ' = ' . HTTPCODES::BLC_BROKEN_TRUE, //COM_BLC_OPTION_WITH_BROKEN
            'redirect'  => $db->quoteName('redirect_count') . ' > 0',  //COM_BLC_OPTION_WITH_REDIRECT
            'warning'   => $db->quoteName('broken') . ' = ' . HTTPCODES::BLC_BROKEN_WARNING, //COM_BLC_OPTION_WITH_WARNING
            'internal'  => $db->quoteName('internal_url') . ' != ' . $db->quote('') . ' AND  ' .  $db->quoteName('internal_url') . ' != ' . $db->quoteName('url'), //COM_BLC_OPTION_WITH_INTERNAL_MISMATCH
            'tocheck'   => $db->quoteName('being_checked') . ' = ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK, //COM_BLC_OPTION_WITH_TIMEOUT
            'parked'    => $db->quoteName('parked') . ' = ' . HTTPCODES::BLC_PARKED_PARKED, //COM_BLC_OPTION_WITH_TIMEOUT
            'empty-alt' => \call_user_func(fn () => 'EXISTS (' . $db->getQuery(true)->select('*')
                ->from($db->quoteName('#__blc_instances', 'x'))
                ->where($db->quoteName('a.id') . ' = ' . $db->quoteName('x.link_id'))
                ->where($db->quoteName('x.link_text') . ' = ' . $db->quote(PARSE_STRINGS::BLC_EMPTY_ALT))->__toString() . ')'),




            default => ''
        };

        if ($specialQuery) {
            $query->where('(' . $specialQuery . ')');
        }
    }



    /**
     * add a query part for the  working filter
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */

    protected function addWorkingToQuery(QueryInterface $query): void
    {
        $isWorking = $this->getState('filter.working', HTTPCODES::BLC_WORKING_ACTIVE);
        if ($isWorking != HTTPCODES::BLC_WORKING_ALL) {
            $db    = $this->getDatabase();
            $query->where('(' . $db->quoteName('working') . ' = :isWorking)')->bind(':isWorking', $isWorking, ParameterType::INTEGER);
        }
    }

    /**
     * add a query part for the  search filter
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */

    protected function addSearchToQuery(QueryInterface $query): void
    {
        $search = $this->getState('filter.search', '');
        if (! $search) {
            return; //no search
        }

        if (stripos($search, 'anchor:') === 0) {
            $db    = $this->getDatabase();

            $instanceQuery = $db->getQuery(true);
            // Select the required fields from the table.

            $instanceQuery->select('*')
                ->from($db->quoteName('#__blc_instances', 'x'))
                ->where($db->quoteName('a.id') . ' = ' . $db->quoteName('x.link_id'));
            $search = '%' . substr($search, 7) . '%';
            $instanceQuery->where('(' . $db->quoteName('x.link_text') . ' LIKE ' . $db->quote($search) . ' )');
            $query->where('EXISTS (' . $instanceQuery->__toString() . ')');
        } else {
            $search = '%' . str_replace(' ', '%', trim($search)) . '%';

            // $query->where('1');
            //   $query->extendWhere(
            //      'AND',
            $query->where('(' .   join(' OR ', [
                $query->quoteName('a.url') . ' LIKE :url',
                $query->quoteName('a.internal_url') . ' LIKE :internalurl',
                $query->quoteName('a.final_url') . ' LIKE :finalurl',
            ]) . ')');
            //    'OR'
            //);
            $query->bind([':url', ':internalurl', ':finalurl'], $search, ParameterType::STRING);
        }
    }

    /**
     * add a query part for the  destination filter
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */

    protected function addDestinationToQuery(QueryInterface $query): void
    {

        $destination = $this->getState('filter.destination', -1);
        if ($destination && $destination != '-1') {
            $db    = $this->getDatabase();
            switch ($destination) {
                case 'external':
                case 0: //to be removed
                    $query->where('(' . $db->quoteName('internal_url') . ' = ' . $db->quote('') . ')');
                    break;
                case 'internal':
                case 1: //to be removed
                    $query->where('(' . $db->quoteName('internal_url') . '!= ' . $db->quote('') . ')');

                    break;
                default:
                    break;
            }
        }
    }



    /**
     * add a query part for the  mime filter
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */

    protected function addMimeToQuery(QueryInterface $query): void
    {
        $mimeFilter = $this->getState('filter.mime', '-1');
        if ($mimeFilter && $mimeFilter != '-1') {
            $db    = $this->getDatabase();
            $query->where('(' . $db->quoteName('mime') . ' = :mime)')
                ->bind(':mime', $mimeFilter, ParameterType::STRING);
        }
    }

    /**
     * add a query part for the  response filter
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */

    protected function addReponseToQuery(QueryInterface $query): void
    {

        $response = (int)$this->getState('filter.response', -1); //mysql save

        if ($response > -1) {
            $db    = $this->getDatabase();

            switch ($response) {
                case 0:
                    //unchecked and throttled\
                    $query->whereIn('http_code', HTTPCODES::UNCHECKEDHTTPCODES, ParameterType::INTEGER);
                    break;
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                    $start         = $query->quote($response * 100);
                    $end           = $query->quote($response * 100 + 99);
                    $query->where('(' . $db->quoteName('http_code') . " BETWEEN $start AND $end)");
                    break;
                default:
                    $query->where('(' . $db->quoteName('http_code') .  ' = ' . $query->quote($response) . ')');
                    break;
            }
        }
    }

    /**
     * A plugin filter to the query
     * this needs a join up to the synch table as there is no direct foreign key between the links and the syncs
     * @param QueryInterface $query
     * @return void
     * @since 25.44.7548
     */


    protected function addPluginToQuery(QueryInterface $query): void
    {
        $plugin = $this->getState('filter.plugin', '-1');
        if (!$plugin || $plugin == '-1') {
            return; //no field filter
        }
        // Create a new query object.
        $db    = $this->getDatabase();

        $instanceQuery = $db->getQuery(true);
        // Select the required fields from the table.

        $instanceQuery->select('*')
            ->from($db->quoteName('#__blc_instances', 'x'))
            ->where($db->quoteName('a.id') . ' = ' . $db->quoteName('x.link_id'));

        $instanceQuery->Join(
            'INNER',
            $db->quoteName('#__blc_synch', 's'),
            '(' . $db->quoteName('s.id') . ' = ' . $db->quoteName('x.synch_id') . ' AND ' . $db->quoteName('s.plugin_name') . ' = ' . $db->quote($plugin) . ' )'
        );

        $query->where('EXISTS (' . $instanceQuery->__toString() . ')');
    }

    /**
     * A parser filter to the query
     * @param QueryInterface $query
     * @return void
     * @since 25.44.7548
     */


    protected function addInstanceColumnToQuery(QueryInterface $query, string $field): void
    {
        $value = $this->getState('filter.' . $field, '-1');
        if (!$value || $value == '-1') {
            return; //no field filter
        }
        // Create a new query object.
        $db    = $this->getDatabase();

        $instanceQuery = $db->getQuery(true);
        // Select the required fields from the table.

        $instanceQuery->select('*')
            ->from($db->quoteName('#__blc_instances', 'x'))
            ->where($db->quoteName('a.id') . ' = ' . $db->quoteName('x.link_id'));

        $instanceQuery->where(
            $db->quoteName('x.' . $field) . ' = ' . $db->quote($value)
        );

        $query->where('EXISTS (' . $instanceQuery->__toString() . ')');
    }



    /**
     * adds an exists on the instances table to the query
     * this ensures that only links with instances are returned
     * @param QueryInterface $query
     * @return void
     * @since 24.44.6378
     */


    protected function addInstanceToQuery(QueryInterface $query): void
    {
        $db    = $this->getDatabase();
        //  $query->leftJoin($db->quoteName('#__blc_instances', 'i'), $db->quoteName('i.link_id') . ' = ' . $db->quoteName('a.id'));
        //  return;

        $instanceQuery = $db->getQuery(true);
        // Select the required fields from the table.

        $instanceQuery->select('*')
            ->from($db->quoteName('#__blc_instances', 'x'))
            ->where($db->quoteName('a.id') . ' = ' . $db->quoteName('x.link_id'));

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
        $this->updateParked();
        // Create a new query object.
        $db    = $this->getDatabase();

        $query = $db->getQuery(true);



        //only get what's need. Espeicaly ommit the larg e log and data blobs
        $query->select(
            $db->quoteName([
                'a.id',
                'url',
                'final_url',
                'internal_url',
                'http_code',
                'broken',
                'working',
                'mime',
                'redirect_count',
            ])
        );

        $query->from($db->quoteName('#__blc_links', 'a'));

        $this->addToquery($query);

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

        $dispatcher     = Factory::getApplication()->getDispatcher(); //@phpstan-ignore method.deprecatedInterface
        $lastExtractors = $dispatcher->getListeners($eventName);
        foreach ($lastExtractors as $listener) {
            if (\is_array($listener)) {
                $class        = $listener[0]::class;
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

        $event = new BlcExtractEvent('onBlcExtract', $arguments);
        $this->logListeners($event);
        Factory::getApplication()->getDispatcher()->dispatch('onBlcExtract', $event); //@phpstan-ignore method.deprecatedInterface
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
                    $link = $checkLink->checkLinkId((int)$linkId);
                    if ($moreOnThrottle && ($link->http_code == HTTPCODES::BLC_THROTTLE_HTTP_CODE)) {
                        //this avoids an enless loop when the database only contains links to domains that are throttled
                        $seen[] = $link->id;
                        $checkLimit++;
                    }
                    $links[] = $link;
                }
            }
        }
        $this->updateParked();
        return $links;
    }

    public function getBrokenCount()
    {
        $db    = $this->getDatabase();
        $query = $this->getListquery();
        $query->clear('select')
            ->select('count(*) ' . $db->quoteName('c'));

        return $db->setQuery($query)->loadResult();
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
                'msgshort' => Text::_('COM_BLC_LOCKED_SHORT'),
                'msglong'  => Text::_('COM_BLC_LOCKED'),
                'status'   => 'unable',
                'count'    => 1,
                'log'      => '',
                'broken'   => $this->getBrokenCount(),
            ];
            return $response;
        }

        try {
            //only helps partially, since symfony catches fatals.
            PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
        } catch (\Error $e) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_ERROR_IMPORTPLUGINS_BLC') . ':' . $e->getMessage(), 'error');
        }
        $maxExtract = 5;

        $event  = $this->runBlcExtract($maxExtract);
        $parsed = $event->getdidExtract();
        if ($parsed > 0) {
            $lastExtractor = $event->getExtractor();
            $todoExtract   = max(1, $event->getTodo()); //The pseudo cro uses the count as 'keep going' trigger. return 1 of there are no more extrects.  so the cron continues.
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
                'log'      => BlcMessages::getInstance()->getMessageQueue(true),
                'broken'   => $this->getBrokenCount(),
            ];
            return $response;
        }


        //nothing to extract so check a link.

        //in general the admin pseudo cron will be slow enough so we don't hit any host-throttles. So leave the moreOnThrottle to false
        $links = $this->runBlcCheck(checkLimit: 1);
        $count = $this->getToCheck(true);
        if ($links) {
            //only report if anything happened
            BlcHelper::setLastAction('Admin', 'Check');
            $link = $links[0];
            if ($link) {
                switch ($link->http_code) {
                    case HTTPCODES::BLC_THROTTLE_HTTP_CODE:
                        $short  = Text::_('COM_BLC_HTTP_RESPONSE_612_SHORT');
                        $long   =  Text::_('COM_BLC_HTTP_RESPONSE_612');
                        $status = 'throttle';
                        break;
                    case HTTPCODES::BLC_UNABLE_TOCHECK_HTTP_CODE:
                        $short  = Text::_('COM_BLC_HTTP_RESPONSE_609_SHORT');
                        $long   =  Text::_('COM_BLC_HTTP_RESPONSE_609');
                        $status = 'unable';
                        break;
                    default:
                        if ($link->broken) {
                            $short  = Text::_('COM_BLC_BLC_BROKEN_TRUE');
                            $status = 'broken';
                        } else {
                            if ($link->redirect_count && ($link->url != $link->final_url)) {
                                $short  = Text::_('COM_BLC_HTTP_RESPONSE_3_SHORT');
                                $status = 'redirect';
                            } else {
                                $short  = Text::_('COM_BLC_BLC_BROKEN_FALSE');
                                $status = 'success';
                            }
                        }
                        $long = substr($link->url, 0, 200);
                        break;
                }

                $code     = \sprintf('%3s', $link->http_code);
                $duration = \sprintf('[%1.2f]', $link->request_duration);
                $url      = $link->toString();
                // phpcs:disable Generic.Files.LineLength
                $base     = \sprintf("[%d] %s:%3s", $count, $short, $code);
                $response = [
                    'msgshort' => "<a title=\"{$long}\" href=\"{$link}\" target=\"checked\">$base</a>",
                    'msglong'  => "$base $duration - <a href=\"{$url}\" target=\"checked\">$long</a>",
                    'status'   => $status,
                    'count'    => $count,
                    'broken'   => $this->getBrokenCount(),
                ];
                return $response;
            }
        }
        //no links found so the  $count = $this->getToCheck(true); should be zero
        if ($count == 0) {
            $text     = Text::_('COM_BLC_BLC_DONE');
            $response = [
                'msgshort' => '<span class="final success">' . $text . '</span>',
                'msglong'  => '<span class="final success">' . $text . '</span>',
                'status'   => 'success',
                'count'    => $count,
                'broken'   => $this->getBrokenCount(),
            ];
            return $response;
        }
        $text = Text::_('COM_BLC_BLC_WORKING');
        //this is mostyl wrong however there could be an extract between the start of runBlcCheck and the call to  $count = $this->getToCheck(true);
        $response = [
            'msgshort' => '<span class="unable">' . $text . '</span>',
            'msglong'  => '<span class="unable">' . $text . '</span>',
            'status'   => 'unable',
            'count'    => $count,
            'broken'   => $this->getBrokenCount(),
        ];
        $response['log'] =  BlcMessages::getInstance()->getMessageQueue(true);
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
            Factory::getApplication()->setUserState($this->context . '.filter.special', 'broken');
            $objectItems = parent::getItems();

            if ($objectItems === false) {
                throw new \RuntimeException($this->getError()); //@phpstan-ignore method.deprecated
            }

            if (\count($objectItems) == 0) {
                $this->setState('filter.special', '-1');
                Factory::getApplication()->setUserState($this->context . '.filter.special', '-1');
                $objectItems = parent::getItems();
            }
        } else {
            $objectItems = parent::getItems();
        }

        if ($objectItems === false) {
            throw new \RuntimeException($this->getError()); //@phpstan-ignore method.deprecated
        }
        $linkTableItems = [];
        $db             = $this->getDatabase();
        //we need the full LinkTable instance anyhow for the replacement URL
        foreach ($objectItems as $item) {
            $linkTableItems[$item->id] = new LinkTable($db);
            $linkTableItems[$item->id]->bind($item);
        }

        return $linkTableItems;
    }

    protected function getRecheck()
    {
        $now            = Factory::getDate()->toSql();
        $db             = $this->getDatabase();
        $query          = $db->getQuery(); // current query
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
            'never'   => $db->quoteName('http_code') . ' IN (' . implode(',', HTTPCODES::UNCHECKEDHTTPCODES) . ')',
            'working' => '(' . implode(" AND ", [
                $db->quoteName('broken') . ' = ' . HTTPCODES::BLC_BROKEN_FALSE,
                $db->quoteName('last_check') . ' < ' . $query->dateAdd($db->quote($now), -$checkThreshold, 'HOUR'),
            ]) . ')',
            //for now broken-old is twice the working interval todo: make this one more parater
            'broken-old' => '(' . implode(" AND ", [
                $db->quoteName('broken') . ' != ' . HTTPCODES::BLC_BROKEN_FALSE,
                $db->quoteName('last_check') . '  <  ' . $query->dateAdd($db->quote($now), -$checkThreshold * 2, 'HOUR'),
            ]) . ')',
            'broken-recent' => '(' . implode(" AND ", [
                $db->quoteName('broken') . ' != ' . HTTPCODES::BLC_BROKEN_FALSE,
                $db->quoteName('last_check') . '  <  ' . $query->dateAdd($db->quote($now), -$brokenThreshold, 'HOUR'),
                $db->quoteName('check_count') . "  <  $recheckCount",
            ]) . ')',
        ];


        // phpcs:enable Generic.Files.LineLength
    }

    /**
     * @since 24.44.7136
     *
     */

    public function setToCheck()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $now = Factory::getDate('now - 1 minute')->toSql();

        $query->update($db->quoteName('#__blc_links'))
            ->set($db->quoteName('being_checked') . ' =  ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK)
            ->where($db->quoteName('being_checked') . '  = ' . HTTPCODES::BLC_CHECKSTATE_CHECKING)
            ->where($db->quoteName('last_check_attempt') . ' < ' . $db->quote($now));
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__blc_links'))
            ->set($db->quoteName('being_checked') . ' =  ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK)
            ->where($db->quoteName('being_checked') . '  = ' . HTTPCODES::BLC_CHECKSTATE_CHECKED)
            ->extendWhere(
                'AND',
                $this->getRecheck(),
                'OR'
            );

        $db->setQuery($query)->execute();
    }

    public function getToCheck($count = false, $checkLimit = 10, array $ignoreIds = [])
    {

        $db    = $this->getDatabase();
        $this->setToCheck();
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_links', 'l'))
            ->where($db->quoteName('l.being_checked') . '  = ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK)
            ->where($db->quoteName('working') . ' != ' . HTTPCODES::BLC_WORKING_IGNORE)
            ->where('EXISTS (SELECT * FROM ' . $db->quoteName('#__blc_instances', 'i') . ' WHERE ' . $db->quoteName('i.link_id') . ' = ' . $db->quoteName('l.id') . ')');
        if ($count) {
            $query->select('count(*) ' . $db->quoteName('c'));
        } else {
            //just get the id, linkcheck will fetch the whole object.
            $query->select($db->quoteName('l.id', 'id'))
                ->order($db->quoteName('http_code') . ' ASC') //unchecked first
                ->order($db->quoteName('last_check_attempt') . ' ASC')
                ->setLimit($checkLimit);
        }

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
        $query->delete($db->quoteName('#__blc_links'))

            ->whereIn('id', $pks, ParameterType::INTEGER);

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
        $query->update($db->quoteName('#__blc_links'))
            ->set($db->quoteName($column) . ' = ' . $db->quote($value))
            ->whereIn('id', $pks, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
