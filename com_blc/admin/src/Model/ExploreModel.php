<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Methods supporting a list of article records.
 *
 * @since  1.6
 */
class ExploreModel extends ListModel
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
                'tag',
                'rating_count', 'rating',

            ];

            if (Associations::isEnabled()) {
                $config['filter_fields'][] = 'association';
            }
        }

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
        $form = parent::getFilterForm($data, $loadData);
        return $form;
    }

    private function getPlugins($context = 'com_content')
    {
        return "'content','cfcontent','yootheme'";
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
        $app   = Factory::getApplication();
        $input = $app->getInput();

        $forcedLanguage = $input->get('forcedLanguage', '', 'cmd');

        // Adjust the context to support modal layouts.
        if ($layout = $input->get('layout')) {
            $this->context .= '.' . $layout;
        }

        // Adjust the context to support forced languages.
        if ($forcedLanguage) {
            $this->context .= '.' . $forcedLanguage;
        }

        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $featured = $this->getUserStateFromRequest($this->context . '.filter.featured', 'filter_featured', '');
        $this->setState('filter.featured', $featured);

        $links = $this->getUserStateFromRequest($this->context . '.filter.links', 'filter_links', '');
        $this->setState('filter.links', $links);

        $published = $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '');
        $this->setState('filter.published', $published);

        $level = $this->getUserStateFromRequest($this->context . '.filter.level', 'filter_level');
        $this->setState('filter.level', $level);

        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

        $formSubmitted = $input->post->get('form_submitted');

        // Gets the value of a user state variable and sets it in the session
        $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access');
        $this->getUserStateFromRequest($this->context . '.filter.author_id', 'filter_author_id');
        $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id');
        $this->getUserStateFromRequest($this->context . '.filter.tag', 'filter_tag', '');

        if ($formSubmitted) {
            $access = $input->post->get('access');
            $this->setState('filter.access', $access);

            $authorId = $input->post->get('author_id');
            $this->setState('filter.author_id', $authorId);

            $categoryId = $input->post->get('category_id');
            $this->setState('filter.category_id', $categoryId);

            $tag = $input->post->get('tag');
            $this->setState('filter.tag', $tag);
        }

        // List state information.
        parent::populateState($ordering, $direction);

        // Force a language
        if (!empty($forcedLanguage)) {
            $this->setState('filter.language', $forcedLanguage);
            $this->setState('filter.forcedLanguage', $forcedLanguage);
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
     * @return  string  A store id.
     *
     * @since   1.6
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . serialize($this->getState('filter.access'));
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . serialize($this->getState('filter.category_id'));
        $id .= ':' . serialize($this->getState('filter.author_id'));
        $id .= ':' . $this->getState('filter.language');
        $id .= ':' . serialize($this->getState('filter.tag'));
        $id .= ':' . $this->getState('filter.links');




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
        $query = $db->getQuery(true);
        $user  = $this->getCurrentUser();

        $params = ComponentHelper::getParams('com_content');

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.asset_id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.alias'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                    $db->quoteName('a.catid'),
                    $db->quoteName('a.state'),
                    $db->quoteName('a.access'),
                    $db->quoteName('a.created'),
                    $db->quoteName('a.created_by'),
                    $db->quoteName('a.created_by_alias'),
                    $db->quoteName('a.modified'),
                    $db->quoteName('a.ordering'),
                    $db->quoteName('a.featured'),
                    $db->quoteName('a.language'),
                    $db->quoteName('a.hits'),
                    $db->quoteName('a.publish_up'),
                    $db->quoteName('a.publish_down'),
                    $db->quoteName('a.note'),
                    $db->quoteName('a.images'),

                ]
            )
        )
            ->select(
                [
                    //  $db->quoteName('fp.featured_up'),
                    //  $db->quoteName('fp.featured_down'),
                    $db->quoteName('l.title', 'language_title'),
                    $db->quoteName('l.image', 'language_image'),
                    $db->quoteName('uc.name', 'editor'),
                    $db->quoteName('ag.title', 'access_level'),
                    $db->quoteName('c.title', 'category_title'),

                    $db->quoteName('c.level', 'category_level'),
                    $db->quoteName('c.published', 'category_published'),
                    $db->quoteName('parent.title', 'parent_category_title'),
                    $db->quoteName('parent.id', 'parent_category_id'),
                    $db->quoteName('parent.created_user_id', 'parent_category_uid'),
                    $db->quoteName('parent.level', 'parent_category_level'),
                    $db->quoteName('ua.name', 'author_name'),

                ]
            )
            ->from($db->quoteName('#__content', 'a'))
            //     ->where($db->quoteName('wa.extension') . ' = ' . $db->quote('com_content.article'))
            ->join(
                'LEFT',
                $db->quoteName('#__languages', 'l'),
                $db->quoteName('l.lang_code') . ' = ' . $db->quoteName('a.language')
            )
            //  ->join('LEFT',
            // $db->quoteName('#__content_frontpage',
            //'fp'), $db->quoteName('fp.content_id') . ' = ' . $db->quoteName('a.id'))
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'uc'),
                $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__viewlevels', 'ag'),
                $db->quoteName('ag.id') . ' = ' . $db->quoteName('a.access')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__categories', 'c'),
                $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__categories', 'parent'),
                $db->quoteName('parent.id') . ' = ' . $db->quoteName('c.parent_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'ua'),
                $db->quoteName('ua.id') . ' = ' . $db->quoteName('a.created_by')
            );
        //  ->join('INNER', $db->quoteName('#__blc_synch', 'blc_s'),
        // $db->quoteName('a.id')  . ' = ' . $db->quoteName('blc_s.container_id')
        // . ' AND ' .  $db->quoteName('blc_s.plugin_name') . ' IN ("content","yootheme","cf_content")')
        // ->group($db->quoteName('a.id'))
        $plugins = $this->getPlugins();
        //first is a INNER so we count only parsed content
        $query->join(
            'INNER',
            '`#__blc_synch` `fromsynch`',
            "`fromsynch`.`container_id` = `a`.`id` AND `fromsynch`.`plugin_name` IN ({$plugins})"
        )
            //second must be a left otherwise we will miss content without links
            ->join(
                'LEFT',
                '`#__blc_instances` `frominstance`',
                "`fromsynch`.`id` = `frominstance`.`synch_id` AND  `frominstance`.`field` IN ('introtext','fulltext')"
            )
            ->join(
                'LEFT',
                '`#__blc_links` `fromlink`',
                "`frominstance`.`link_id` = `fromlink`.`id` AND `fromlink`.`mime` = 'text/html' AND `fromlink`.`internal_url` = ''"
            )
            ->join(
                'LEFT',
                '`#__blc_links_storage` `fromstorage`',
                "`fromstorage`.`link_id` = `frominstance`.`link_id` 
                AND JSON_CONTAINS(`fromstorage`.`data`,'{\"option\":\"com_content\"}','$.query')
                  "
            )


            // to links
            ->join(
                'LEFT',
                '`#__blc_links_storage` `tostorage`',
                "JSON_CONTAINS(`tostorage`.`data`,'{\"option\":\"com_content\"}','$.query') 
                AND JSON_VALUE(`tostorage`.`data`,'$.query.id') =  `a`.`id`"
            )
            ->join(
                'LEFT',
                '`#__blc_instances` `toinstance`',
                "`tostorage`.`link_id` = `toinstance`.`link_id`  AND  `toinstance`.`field` IN ('introtext','fulltext')"
            )
            ->join(
                'LEFT',
                '`#__blc_synch` `tosynch`',
                "`tosynch`.`id` = `toinstance`.`synch_id`  AND `tosynch`.`plugin_name` IN ({$plugins})"
            )

            ->select("\nCOUNT(DISTINCT(`fromlink`.`id`)) `external`")
            ->select("\nCOUNT(DISTINCT(`fromstorage`.`link_id`)) `from`")
            //->select("\nCOUNT(DISTINCT(`toinstance`.`link_id`)) `to`")
            ->select("\nCOUNT(DISTINCT(`tosynch`.`container_id`)) `to`")
            ->group('`a`.`id`');


        $linksFilter = $this->getState('filter.links');
        if ($linksFilter) {
            //todo digg into these expensive having's
            switch ($this->getState('filter.links')) {
                case '-from':
                    $query->having('`from` = 0');
                    break;
                case '+from':
                    $query->having('`from` > 0');
                    break;
                case '-to':
                    $query->having('`to` = 0');
                    break;
                case '+to':
                    $query->having('`to` > 0');
                    break;

                case '-external':
                    $query->having('`external` = 0');
                    break;
                case '+external':
                    $query->having('`external` > 0');
                    break;
            }
        }




        // Join over the associations.
        if (Associations::isEnabled()) {
            $subQuery = $db->getQuery(true)
                ->select('COUNT(' . $db->quoteName('asso1.id') . ') > 1')
                ->from($db->quoteName('#__associations', 'asso1'))
                ->join(
                    'INNER',
                    $db->quoteName('#__associations', 'asso2'),
                    $db->quoteName('asso1.key') . ' = ' . $db->quoteName('asso2.key')
                )
                ->where(
                    [
                        $db->quoteName('asso1.id') . ' = ' . $db->quoteName('a.id'),
                        $db->quoteName('asso1.context') . ' = ' . $db->quote('com_content.item'),
                    ]
                );

            $query->select('(' . $subQuery . ') AS ' . $db->quoteName('association'));
        }

        // Filter by access level.
        $access = $this->getState('filter.access');

        if (is_numeric($access)) {
            $access = (int) $access;
            $query->where($db->quoteName('a.access') . ' = :access')
                ->bind(':access', $access, ParameterType::INTEGER);
        } elseif (\is_array($access)) {
            $access = ArrayHelper::toInteger($access);
            $query->whereIn($db->quoteName('a.access'), $access);
        }

        // Filter by featured.
        $featured = (string) $this->getState('filter.featured');

        if (\in_array($featured, ['0', '1'])) {
            $featured = (int) $featured;
            $query->where($db->quoteName('a.featured') . ' = :featured')
                ->bind(':featured', $featured, ParameterType::INTEGER);
        }

        // Filter by access level on categories.
        if (!$user->authorise('core.admin')) {
            $groups = $user->getAuthorisedViewLevels();
            $query->whereIn($db->quoteName('a.access'), $groups);
            $query->whereIn($db->quoteName('c.access'), $groups);
        }

        $published = (string) $this->getState('filter.published');

        if ($published !== '*') {
            if (is_numeric($published)) {
                $state = (int) $published;
                $query->where($db->quoteName('a.state') . ' = :state')
                    ->bind(':state', $state, ParameterType::INTEGER);
            } else {
                $query->whereIn(
                    $db->quoteName('a.state'),
                    [
                        ContentComponent::CONDITION_PUBLISHED,
                        ContentComponent::CONDITION_UNPUBLISHED,
                    ]
                );
            }
        }

        // Filter by categories and by level
        $categoryId = $this->getState('filter.category_id', []);
        $level      = (int) $this->getState('filter.level');

        if (!\is_array($categoryId)) {
            $categoryId = $categoryId ? [$categoryId] : [];
        }

        // Case: Using both categories filter and by level filter
        if (\count($categoryId)) {
            $categoryId       = ArrayHelper::toInteger($categoryId);
            $categoryTable    = Table::getInstance('Category', '\\Joomla\\CMS\\Table\\');
            $subCatItemsWhere = [];

            foreach ($categoryId as $key => $filter_catid) {
                $categoryTable->load($filter_catid);

                // Because values to $query->bind() are passed by reference,
                // using $query->bindArray() here instead to prevent overwriting.
                $valuesToBind = [$categoryTable->lft, $categoryTable->rgt];

                if ($level) {
                    $valuesToBind[] = $level + $categoryTable->level - 1;
                }

                // Bind values and get parameter names.
                $bounded = $query->bindArray($valuesToBind);




                $categoryWhere = $db->quoteName('c.lft') . ' >= ' . $bounded[0]
                    . ' AND ' . $db->quoteName('c.rgt') . ' <= ' . $bounded[1];

                if ($level) {
                    $categoryWhere .= ' AND ' . $db->quoteName('c.level') . ' <= ' . $bounded[2];
                }

                $subCatItemsWhere[] = '(' . $categoryWhere . ')';
            }

            $query->where('(' . implode(' OR ', $subCatItemsWhere) . ')');
        } elseif ($level = (int) $level) {
            // Case: Using only the by level filter
            $query->where($db->quoteName('c.level') . ' <= :level')
                ->bind(':level', $level, ParameterType::INTEGER);
        }


        // Filter by author
        $authorId = $this->getState('filter.author_id');

        if (is_numeric($authorId)) {
            $authorId = (int) $authorId;
            $type     = $this->getState('filter.author_id.include', true) ? ' = ' : ' <> ';
            $query->where($db->quoteName('a.created_by') . $type . ':authorId')
                ->bind(':authorId', $authorId, ParameterType::INTEGER);
        } elseif (\is_array($authorId)) {
            // Check to see if by_me is in the array
            if (\in_array('by_me', $authorId)) {
                // Replace by_me with the current user id in the array
                $authorId['by_me'] = $user->id;
            }

            $authorId = ArrayHelper::toInteger($authorId);
            $query->whereIn($db->quoteName('a.created_by'), $authorId);
        }

        // Filter by search in title.
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :search')
                    ->bind(':search', $search, ParameterType::INTEGER);
            } elseif (stripos($search, 'author:') === 0) {
                $search = '%' . substr($search, 7) . '%';
                $query->where('(' . $db->quoteName('ua.name') . ' LIKE :search1'
                    . ' OR '    . $db->quoteName('ua.username') . ' LIKE :search2)')
                    ->bind([':search1', ':search2'], $search);
            } elseif (stripos($search, 'content:') === 0) {
                $search = '%' . substr($search, 8) . '%';
                $query->where('(' . $db->quoteName('a.introtext') . ' LIKE :search1'
                    . ' OR '    . $db->quoteName('a.fulltext') . ' LIKE :search2)')
                    ->bind([':search1', ':search2'], $search);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :search1'
                        . ' OR ' . $db->quoteName('a.alias') . ' LIKE :search2'
                        . ' OR ' . $db->quoteName('a.note') . ' LIKE :search3)'
                )
                    ->bind([':search1', ':search2', ':search3'], $search);
            }
        }

        // Filter on the language.
        if ($language = $this->getState('filter.language')) {
            $query->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language);
        }

        // Filter by a single or group of tags.
        $tag = $this->getState('filter.tag');

        // Run simplified query when filtering by one tag.
        if (\is_array($tag) && \count($tag) === 1) {
            $tag = $tag[0];
        }

        if ($tag && \is_array($tag)) {
            $tag = ArrayHelper::toInteger($tag);

            $subQuery = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('content_item_id'))
                ->from($db->quoteName('#__contentitem_tag_map'))
                ->where(
                    [
                        $db->quoteName('tag_id') . ' IN (' . implode(',', $query->bindArray($tag)) . ')',
                        $db->quoteName('type_alias') . ' = ' . $db->quote('com_content.article'),
                    ]
                );

            $query->join(
                'INNER',
                '(' . $subQuery . ') AS ' . $db->quoteName('tagmap'),
                $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
            );
        } elseif ($tag = (int) $tag) {
            $query->join(
                'INNER',
                $db->quoteName('#__contentitem_tag_map', 'tagmap'),
                $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
            )
                ->where(
                    [
                        $db->quoteName('tagmap.tag_id') . ' = :tag',
                        $db->quoteName('tagmap.type_alias') . ' = ' . $db->quote('com_content.article'),
                    ]
                )
                ->bind(':tag', $tag, ParameterType::INTEGER);
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

        $query->order($ordering);
        // $query->select('breakIt');
        return $query;
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
        $items = parent::getItems();
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

        $linkTree   = [];
        $plugins    = $this->getPlugins();
        $fromSelect = "
     
        JSON_VALUE(`ls`.`data`,'$.query.id') is not null 
        AND 
        JSON_CONTAINS(`ls`.`data`,'{\"option\":\"com_content\"}','$.query')
        AND
        JSON_VALUE(`ls`.`data`,'$.query.id') !=  `s`.`container_id`
        ";


        $toSelect = "
       
        JSON_VALUE(`ls`.`data`,'$.query.id') is not null 
        AND 
        JSON_CONTAINS(`ls`.`data`,'{\"option\":\"com_content\"}','$.query')
        AND
        JSON_VALUE(`ls`.`data`,'$.query.id') !=  `s`.`container_id`
        ";

        $externalSelect = "
       
        `l`.`internal_url` = ''
        AND 
        `mime` = 'text/html'";




        if (\count($ids)) {
            $idsString = join(',', $ids);
            $db        = $this->getDatabase();


            $allQuery = "
        ((({$fromSelect}) OR ({$externalSelect}))  AND `s`.`container_id` IN ({$idsString}))
        OR
        (
            ({$toSelect}) AND JSON_EXTRACT(`ls`.`data`,'$.query.id')  IN ({$idsString})
        )
        ";

            $query = $db->getQuery(true)
                ->from('`#__blc_synch` `s`')
                ->join('INNER', '`#__blc_instances` `i`', '`s`.`id` = `i`.`synch_id`')
                ->join('LEFT', '`#__blc_links` `l`', '`l`.`id` = `i`.`link_id`')
                ->join('LEFT', '`#__blc_links_storage` `ls`', '`l`.`id` = `ls`.`link_id`')
                ->select('`s`.`container_id` `from`')
                ->select("JSON_EXTRACT(`ls`.`data`,'$.query') `query`")
                ->select("JSON_UNQUOTE(JSON_EXTRACT(`ls`.`data`,'$.query.id')) `toid`")
                ->select('`l`.`url`')
                ->select('`l`.`id` `lid`')
                ->select('`l`.`internal_url`')
                ->where("`s`.`plugin_name` IN ({$plugins})")
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
                    ->from('`#__content` `a`')
                    ->select(['`id`', '`title`', '`catid`', 'created_by'])
                    ->whereIn('id', $foundIds);
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
