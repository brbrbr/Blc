<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Model;

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use  Joomla\Component\Content\Administrator\Model\ArticlesModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Methods supporting a list of article records.
 *
 * @since  1.6
 */
class ExploreModel extends ArticlesModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.6
     * @see     \Joomla\CMS\MVC\Controller\BaseController
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'alias', 'a.alias',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'catid', 'a.catid', 'category_title',
                'state', 'a.state',
                'access', 'a.access', 'access_level',
                'created', 'a.created',
                'modified', 'a.modified',
                'created_by', 'a.created_by',
                'created_by_alias', 'a.created_by_alias',
                'ordering', 'a.ordering',
                'featured', 'a.featured',
                //   'featured_up', 'fp.featured_up',
                //  'featured_down', 'fp.featured_down',
                'language', 'a.language',
                'links',
                'to',
                'from',
                'external',
                'hits', 'a.hits',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
                'published', 'a.published',
                'author_id',
                'category_id',
                'level',
                'tag'

            ];

            if (Associations::isEnabled()) {
                $config['filter_fields'][] = 'association';
            }
        }

        ListModel::__construct($config);
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
        return $form;
    }
    /**
     * @return string
     */

    private function getPlugins(): string
    {
        $db    = $this->getDatabase();
        return join(
            ',',
            [
                $db->quote('content'),
                $db->quote('cfcontent'),
                $db->quote('yootheme'),
            ]
        );
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {



        // List state information.
        parent::populateState($ordering, $direction);

        //todo check eigen fiilters
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
     * @return  string  A store id.
     *
     * @since   1.6
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.

        $id .= ':' . $this->getState('filter.links');
        $id .= ':' . ($this->getState('count') ? 'count' : '');
        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   1.6
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db    = $this->getDatabase();

        //get the parent so we have all the filtered stuff.

        $articleQuery = Parent::getListQuery();
        $articleQuery->clear('order');

        $query = clone $articleQuery;
        $query->clear('select');
        $plugins = $this->getPlugins();
        //first is a INNER so we count only parsed content
        $query->join(
            'INNER',
            $db->quoteName('#__blc_synch', 'fromsynch'),

            "{$db->quoteName('fromsynch.container_id')} = {$db->quoteName('a.id')} AND {$db->quoteName('fromsynch.plugin_name')} IN ({$plugins})"
        )
            //second must be a left otherwise we will miss content without links
            ->join(
                'LEFT',
                $db->quoteName('#__blc_instances', 'frominstance'),
                "{$db->quoteName('fromsynch.id')} = {$db->quoteName('frominstance.synch_id')} AND  {$db->quoteName('frominstance.field')} IN ('introtext','fulltext')"
            )
            ->join(
                'LEFT',
                $db->quoteName('#__blc_links', 'fromlink'),
                "{$db->quoteName('frominstance.link_id')} = {$db->quoteName('fromlink.id')} AND {$db->quoteName('fromlink.mime')} = 'text/html' AND {$db->quoteName('fromlink.internal_url')} = ''"
            )
            ->join(
                'LEFT',
                $db->quoteName('#__blc_links_storage', 'fromstorage'),

                "{$db->quoteName('fromstorage.link_id')} = {$db->quoteName('frominstance.link_id')} 
                AND " .    BlcHelper::jsonExtract('fromstorage.data', 'query.option', '') . " = 'com_content'"
            )

            // to links
            ->join(
                'LEFT',
                $db->quoteName('#__blc_links_storage', 'tostorage'),
                BlcHelper::jsonExtract('tostorage.data', 'query.option', '') . " = 'com_content'
                
                 AND " . BlcHelper::jsonExtract('tostorage.data', 'query.id', '', cast: ParameterType::INTEGER) . " =  {$db->quoteName('a.id')}"

            )
            ->join(
                'LEFT',
                $db->quoteName('#__blc_instances', 'toinstance'),
                "{$db->quoteName('tostorage.link_id')} = {$db->quoteName('toinstance.link_id')}  AND  {$db->quoteName('toinstance.field')} IN ('introtext','fulltext')"
            )
            ->join(
                'LEFT',
                $db->quoteName('#__blc_synch', 'tosynch'),
                "{$db->quoteName('tosynch.id')} = {$db->quoteName('toinstance.synch_id')}  AND {$db->quoteName('tosynch.plugin_name')} IN ({$plugins})"
            )
            ->select("{$db->quoteName('a.id')}")
            ->select("\nCOUNT(DISTINCT({$db->quoteName('fromlink.id')})) {$db->quoteName('external')}")
            ->select("\nCOUNT(DISTINCT({$db->quoteName('fromstorage.link_id')})) {$db->quoteName('from')}")
            ->select("\nCOUNT(DISTINCT({$db->quoteName('tosynch.container_id')})) {$db->quoteName('to')}")
            ->group("{$db->quoteName('a.id')}");
        // print "<pre>";print $query;print "</pre>";

        $linksFilter = $this->getState('filter.links');
        if ($linksFilter) {
            //todo digg into these expensive having's
            switch ($this->getState('filter.links')) {
                case '-from':
                    $query->having("{$db->quoteName('from')} = 0");
                    break;
                case '+from':
                    $query->having("{$db->quoteName('from')} > 0");
                    break;
                case '-to':
                    $query->having("{$db->quoteName('to')} = 0");
                    break;
                case '+to':
                    $query->having("{$db->quoteName('to')} > 0");
                    break;

                case '-external':
                    $query->having("{$db->quoteName('external')} = 0");
                    break;
                case '+external':
                    $query->having("{$db->quoteName('external')} > 0");
                    break;
            }
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        if ($orderCol === 'a.ordering' || $orderCol === 'category_title') {
            $ordering = [
                $db->quoteName('c.title') . ' ' . $db->escape($orderDirn),
                $db->quoteName('a.ordering') . ' ' . $db->escape($orderDirn),
            ];
        } else {
            $ordering = $db->quoteName($orderCol) . ' ' . $db->escape($orderDirn);
        }
        //if only the count is requested the last join is not needed.
        //we need the 'expensive' stuff above for the filters.
        $count = $this->getState('count', 0);
        if ($count) {
            return $query;
        }
        // $query->order($ordering);

        //this is needed to please postgresql
        $articleQuery->clear('where')
            ->select($db->quoteName('external'))
            ->select($db->quoteName('from'))
            ->select($db->quoteName('to'))
            ->join(
                'INNER',
                "({$query}) as {$db->quoteName('explorer')}",
                "{$db->quoteName('explorer.id')} = {$db->quoteName('a.id')}"

            );

        $articleQuery->order($ordering);
        // $query->select('breakIt');
        return $articleQuery;
    }

    /**
     * This override has a workaround for counting the articles only
     * 
     * we can't override with the method with a simple _getListQuery(bool $count)
     * 
     *
     * @return  integer  The total number of items available in the data set.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getTotal()
    {

        // Get a storage key.
        $store = $this->getStoreId('getTotal');

        // Try to load the data from internal storage.
        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }

        try {
            $this->setState('count', true);
            // Load the total and add the total to the internal cache.
            $this->cache[$store] = (int) $this->_getListCount($this->_getListQuery());
            $this->setState('count', null);
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return $this->cache[$store];
    }

    /**
     * Method to cache the last query constructed.
     *
     * This method ensures that the query is constructed only once for a given state of the model.
     *
     * @return  DatabaseQuery  A DatabaseQuery object
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function _getListQuery()
    {

        // Compute the current store id.
        $currentStoreId = $this->getStoreId();
        // If the last store id is different from the current, refresh the query.
        if ($this->lastQueryStoreId !== $currentStoreId || empty($this->query)) {
            $this->lastQueryStoreId = $currentStoreId;
            $this->query            = $this->getListQuery();
        }
        return $this->query;
    }


    /**
     * Method to get a list of articles.
     * Overridden to add item type alias.
     *
     * @return  mixed  An array of data items on success, false on failure.
     *
     * @since   4.0.0
     */
    public function getItems()
    {
        $items = ListModel::getItems();
        if ($items === false) {
            return [];
        }
        $ids      = array_column($items, 'id');
        $linkTree = $this->getAllLinks($ids);
        foreach ($items as $k => $item) {
            $items[$k]->linkTree = $linkTree[$item->id] ?? [];
        }

        return $items;
    }
    protected function getAllLinks(array $ids)
    {
        $db = $this->getDatabase();
        $linkTree   = [];
        $plugins    = $this->getPlugins();

        $fromSelect =
            BlcHelper::jsonExtract('ls.data', 'query.id', '', true) . " is not null " .
            " AND " .
            BlcHelper::jsonExtract('ls.data', 'query.option', '', true) . " = {$db->quote('com_content')}" .
            " AND " .
            BlcHelper::jsonExtract('ls.data', 'query.id', '', true, cast: ParameterType::INTEGER) . " != {$db->quoteName('s.container_id')}";


        $toSelect =  $fromSelect;



        $externalSelect = " 
        {$db->quoteName('l.internal_url')} = ''
        AND 
        {$db->quoteName('mime')} = 'text/html'";

        if (\count($ids)) {
            $idsString = join(',', $ids);
            $db        = $this->getDatabase();


            $allQuery = "
        (
            (
              ({$fromSelect}) OR ({$externalSelect}))  AND {$db->quoteName('s.container_id')} IN ({$idsString})
            )
              OR
            (
              ({$toSelect}) AND  " . BlcHelper::jsonExtract('ls.data', 'query.id', '', true, cast: ParameterType::INTEGER) . " IN ({$idsString})
            )
        ";

            $query = $db->getQuery(true)
                ->from($db->quoteName('#__blc_synch', 's'))
                ->join('INNER', $db->quoteName('#__blc_instances', 'i'), "{$db->quoteName('s.id')} = {$db->quoteName('i.synch_id')}")
                ->join('LEFT',  $db->quoteName('#__blc_links', 'l'), "{$db->quoteName('l.id')} = {$db->quoteName('i.link_id')}")
                ->join('LEFT', $db->quoteName('#__blc_links_storage', 'ls'), "{$db->quoteName('l.id')} = {$db->quoteName('ls.link_id')}")
                ->select($db->quoteName('s.container_id', 'from'))
                ->select(BlcHelper::jsonExtract('ls.data', 'query', 'query', false))
                ->select(BlcHelper::jsonExtract('ls.data', 'query.id', 'toid', true))
                ->select($db->quoteName('l.url'))
                ->select($db->quoteName('l.id', 'lid'))
                ->select($db->quoteName('l.internal_url'))
                ->where("{$db->quoteName('s.plugin_name')} IN ({$plugins})")
                ->where($allQuery);

            $db->setQuery($query);

            $links = $db->loadObjectList();


            if (\count($links)) {
                foreach ($ids as $id) {
                    $linkTree[$id] =  (object)[
                        'from'     => [],
                        'to'       => [],
                        'external' => [],

                    ];
                }
                $foundIds = array_filter(array_unique(array_merge(
                    array_column($links, 'from'),
                    array_column($links, 'toid')
                )));

                $query = $db->getQuery(true)
                    ->from($db->quoteName('#__content', 'a'))
                    ->select($db->quoteName(['id', 'title', 'catid', 'created_by']))
                    ->whereIn('id', $foundIds, ParameterType::INTEGER);
                $db->setQuery($query);
                $content = $db->loadObjectList('id');
                foreach ($links as $link) {
                    $link = clone ($link);
                    $lid  = $link->lid;
                    $fid  = $link->from;
                    if (isset($linkTree[$fid])) {
                        if ($link->internal_url != '') {
                            $link->content              = $content[$link->toid] ?? null;
                            $linkTree[$fid]->from[$lid] = clone $link;
                        } else {
                            $linkTree[$fid]->external[$lid] = $link;
                        }
                    }

                    $tid = $link->toid;
                    if (isset($linkTree[$tid])) {
                        $link->content = $content[$link->from] ?? null;
                        if ($link->internal_url != '') {
                            $linkTree[$tid]->to[$fid] = $link;
                        }
                    }
                }
            }
        }

        return $linkTree;
    }
}
