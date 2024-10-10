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


use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

/**
 * Link model.
 *
 * @since  1.0.0
 */
class LinkModel extends BaseDatabaseModel
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
     * @var    object  Item data
     *
     * @since  1.0.0
     */
    protected $item;

    protected $plugins = [];

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
     * @return  LinkTable    A database object
     *
     * @since   1.0.0
     */
    public function getTable($type = 'Link', $prefix = 'Administrator', $config = []): LinkTable
    {
        return new LinkTable($this->getDatabase());
    }


    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return false; //not used
    }


    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  LinkTable    Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem($pk = null): LinkTable
    {

        $pk    = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');

        $item   = $this->getTable();
        $result = $item->load();

        if ($pk > 0) {
            // Attempt to load the row.
            $result = $item->load($pk);
        }

        if (!$result) {
            $url = Route::_('index.php?option=com_blc&view=links', false);
            Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_LINK_NOT_FOUND'), 'error');
            Factory::getApplication()->redirect($url, 404);
            return (object)[];
        }

        $this->item = $item;

        return $item;
    }


    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_blc.edit.link.data', []);

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
        }

        return $data;
    }
    public function getPlugin($sourcePlugin)
    {

        if (!PluginHelper::isEnabled('blc', $sourcePlugin)) {
            return false;
        }
        if (empty($this->plugins[$sourcePlugin])) {
            $this->plugins[$sourcePlugin] = Factory::getApplication()->bootPlugin($sourcePlugin, 'blc');
            if (!$this->plugins[$sourcePlugin] instanceof BlcExtractInterface) {
                $this->plugins[$sourcePlugin] = false;
                Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_PLUGIN_NOT_FOUND') . $sourcePlugin, 'error');
            }
        }
        return $this->plugins[$sourcePlugin];
    }
    public function trashit(string $do = 'reset', string $what = 'synch', string $plugin = '', array|int $pks = [])
    {
        $lang =  Factory::getApplication()->getLanguage();
        $lang->load('com_blc');

        $db    = $this->getDatabase();
        $canDo = BlcHelper::getActions();

        $message = [];

        if (strtolower($plugin) == 'transient') {
            $plugin = '_Transient';
        }
        if ($pks && !\is_array($pks)) {
            ///whereIn will validate
            $pks = [$pks];
        }

        if (Factory::getApplication()->isClient('cli') || $canDo->get('core.manage')) {
            if ($what == 'synch' && $do === 'reset') {
                $do = 'delete';
            }

            if ($what === 'synch' && $do === 'delete' && $plugin === '') {
                $do = 'truncate';
            }
            if ($do === 'orphans') {
                $query = $db->getQuery(true);
                $query->delete($db->quoteName('#__blc_synch'))
                    ->where("{$db->quoteName('#__blc_synch.plugin_name')} != {$db->quote('_Transient')}")
                    //WHERE IN AND EXISTS are basicly the same.Let's is WHERE IN since the list from #__extensions is small
                    ->where("{$db->quoteName('#__blc_synch.plugin_name')} NOT IN (SELECT {$db->quoteName('e.element')} FROM {$db->quoteName('#__extensions', 'e')} WHERE  {$db->quoteName('e.enabled')} = 1 AND {$db->quoteName('e.folder')} = {$db->quote('blc')})");

                //   ->where("NOT EXISTS (SELECT * FROM {$db->quoteName('#__extensions', 'e')} WHERE  {$db->quoteName('e.enabled')} = 1 AND {$db->quoteName('e.folder')} = {$db->quote('blc')} AND {$db->quoteName('e.element')}  = {$db->quoteName('#__blc_synch.plugin_name')})");
                $db->setQuery($query)->execute();
                $c         = $db->getAffectedRows();
                $message[] = Text::sprintf('COM_BLC_LINKS_TABLE_ORPHANS_SYNCH_DELETE_MESSAGE', $c);

                $query->clear();
                $query->delete($db->quoteName('#__blc_instances'))
                    ->where("NOT EXISTS (SELECT * FROM {$db->quoteName('#__blc_synch', 's')} WHERE  {$db->quoteName('#__blc_instances.synch_id')}  = {$db->quoteName('s.id')})");


                $db->setQuery($query)->execute();
                $c         = $db->getAffectedRows();
                $message[] = Text::sprintf('COM_BLC_LINKS_TABLE_ORPHANS_INSTANCES_DELETE_MESSAGE', $c);


                $query->clear();
                $query->delete($db->quoteName('#__blc_links'))
                    ->where('NOT EXISTS (SELECT * FROM ' . $db->quoteName('#__blc_instances', 'i') . ' WHERE ' . $db->quoteName('i.link_id') . ' = ' . $db->quoteName('#__blc_links.id') . ')');
                $db->setQuery($query)->execute();
                $c         = $db->getAffectedRows();
                $message[] = Text::sprintf('COM_BLC_LINKS_TABLE_ORPHANS_LINKS_DELETE_MESSAGE', $c);
            }

            if ($do === 'reset') {
                if ($what == 'links') {
                    //$nullDate = $db->getNullDate();
                    $query = $db->getQuery(true);
                    $query->update($db->quoteName('#__blc_links'))
                        //where to mix them in the recheck order?
                        //with the last_check reset to the nulldate the order would be the database order ( id )
                        //with the lastc_check untouched they will be rechecked after all really new links
                        ->set($db->quoteName('being_checked') . ' = ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK)
                        ->where($db->quoteName('being_checked') . '  = ' . HTTPCODES::BLC_CHECKSTATE_CHECKED);

                    if ($pks) {
                        $query->whereIn('id', $pks, ParameterType::INTEGER);
                    }
                    $db->setQuery($query)->execute();
                    $message[] = Text::_('COM_BLC_LINKS_TABLE_CHECK_RESET_MESSAGE');
                }
            }

            if ($do === 'truncate') {
                if ($what == 'links' || $what == 'all') {
                    $query = $db->getQuery(true);
                    //Truncate not possible with foreigh keys. And psotgresql speaks a different language
                    $query->delete($db->quoteName('#__blc_links'));
                    $db->setQuery($query)->execute();

                    $message[] = Text::_('COM_BLC_LINKS_TABLE_TRUNCATED_MESSAGE');
                }
                //in case of links the contraint should solve the deletion.
                //does not harm to run it once more
                if ($what == 'links' || $what == 'synch' || $what == 'all') {
                    $query = $db->getQuery(true);
                    $query->delete($db->quoteName('#__blc_synch'))
                        ->where("{$db->quoteName('plugin_name')} != {$db->quote('_Transient')}");
                    $db->setQuery($query)->execute();

                    $query = $db->getQuery(true);
                    $query->delete($db->quoteName('#__blc_links'));
                    $db->setQuery($query)->execute();

                    $message[] = Text::_('COM_BLC_SYNCH_TABLE_TRUNCATED_MESSAGE');
                }
            }

            if ($do === 'delete') {
                if ($what == 'synch') {
                    $query = $db->getQuery(true);
                    $query->delete($db->quoteName('#__blc_synch'))
                        ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
                        ->bind(':containerPlugin', $plugin, ParameterType::STRING);
                    if ($pks) {
                        $message[] = Text::_('COM_BLC_SYNC_DELETE_CALLED_WITH_PKS_PLEASE_REPORT_BUG');
                        $query->whereIn('id', $pks, ParameterType::INTEGER);
                    }
                    //foreign keys should take care of _instances
                    $db->setQuery($query)->execute();
                }
                $message[] = Text::sprintf('COM_BLC_SYNCH_TABLE_DELETED_PLUGIN_MESSAGE', $plugin);
            }
        } else {
            print "not allowed";
        }

        if ($message) {
            Factory::getApplication()->enqueueMessage(join("<br>\n", $message));
        }
    }


    public function getInstances(?int $id = null)
    {
        if ($id === null) {
            $id    = $this->getItem()->id;
        }
        $db    = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances', 'i'))
            ->select('*')
            ->select($db->quoteName('i.id', 'id'))
            ->where($db->quoteName('i.link_id') . ' = :id')
            ->join('INNER', $db->quoteName('#__blc_synch', 's'), $db->quoteName('i.synch_id') . ' = ' . $db->quoteName('s.id'))
            ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $rows      = $db->loadObjectList('id');
        $instances = [];
        foreach ($rows as $id => $row) {
            $sourcePlugin = $row->plugin_name;
            $activePlugin = $this->getPlugin($sourcePlugin);
            if (!$activePlugin) {
                continue;
            }


            if ($activePlugin) {
                $links = $activePlugin->getLinks($row);
            }
            $links->anchor   = $row->link_text;
            $links->plugin   = $row->plugin_name;
            $links->field    = $row->field;
            $links->parser   = $row->parser;
            $instances[$id]  = $links;
        }
        return $instances;
    }

    public function getSynch(int $id, $limit = 25)
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances', 'i'))
            ->select($db->quoteName('plugin_name', 'plugin'))
            ->select($db->quoteName('container_id', 'container_id'))
            ->select($db->quoteName('i.id', 'instance_id'))
            ->select($db->quoteName('field', 'field'))
            ->select($db->quoteName('parser', 'parser'))
            ->where($db->quoteName('i.link_id') . ' = :id')
            ->join('INNER', $db->quoteName('#__blc_synch', 's'), $db->quoteName('i.synch_id') . ' = ' . $db->quoteName('s.id'))
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit($limit);
        $db->setQuery($query);
        $rows = $db->loadObjectList('instance_id');

        return $rows;
    }



    protected function populateState()
    {
        $table = $this->getTable();
        $key   = $table->getKeyName();

        // Get the pk of the record from the request.
        $pk = Factory::getApplication()->getInput()->getInt($key);
        $this->setState($this->getName() . '.id', $pk);

        // Load the parameters.
        $value = ComponentHelper::getParams($this->option);
        $this->setState('params', $value);
    }



    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   LinkTable  $table  LinkTable Object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function prepareTable(LinkTable $table): void
    {


        if (empty($table->id)) {
            // Set ordering to the last item if not set
            if (@$table->ordering === '') {
                $db = $this->getDatabase();
                $db->setQuery('SELECT MAX(ordering) FROM #__blc_links');
                $max             = $db->loadResult();
                $table->ordering = $max + 1;
            }
        }
    }
}
