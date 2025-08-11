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


use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\InstanceTable;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
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
        parent::__construct($config);
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
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return LinkTable|InstanceTable|SynchTable  A Table object
     *
     * @since   3.0
     * @throws  \Exception
     */
    public function getTable($name = 'Link', $prefix = 'Administrator', $options = []): LinkTable|InstanceTable|SynchTable
    {
        return match (true) {
            $name === 'Link'     => new LinkTable($this->getDatabase()),
            $name === 'Instance' => new InstanceTable($this->getDatabase()),
            $name === 'Synch'    => new SynchTable($this->getDatabase()),
            default              => throw new \Exception(Text::sprintf('JLIB_APPLICATION_ERROR_TABLE_NAME_NOT_SUPPORTED', $name), 0)
        };
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
        if ($pk !== null || $this->item === null) {
            $pk    = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');

            $this->item   = $this->getTable();

            if ($pk) {
                // Attempt to load the row.
                $this->item->load($pk);
            }
        }
        return $this->item;
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
    /**
     *
     *
     * returns a plugin instance if it implements the BlcExtractInterface
     */

    public function getPlugin($sourcePlugin): BlcExtractInterface|false
    {

        if (!PluginHelper::isEnabled('blc', $sourcePlugin)) {
            return false;
        }
        if (empty($this->plugins[$sourcePlugin])) {
            $this->plugins[$sourcePlugin] = Factory::getApplication()->bootPlugin($sourcePlugin, 'blc');
            if (!$this->plugins[$sourcePlugin] instanceof BlcExtractInterface) {
                $this->plugins[$sourcePlugin] = false;
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_BLC_PLUGIN_NOT_FOUND', $sourcePlugin), 'error');
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
                    //foreign keys should take care of _instances
                }

                if ($what == 'links' || $what == 'synch' || $what == 'all') {
                    $query = $db->getQuery(true);
                    $query->delete($db->quoteName('#__blc_synch'))
                        ->where("{$db->quoteName('plugin_name')} != {$db->quote('_Transient')}");
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
            $message[] = Text::sprintf('COM_BLC_NOT_ALLOWED', $plugin);
        }

        if ($message) {
            Factory::getApplication()->enqueueMessage(implode("<br>\n", $message));
        }
    }



    public function getSynch(?int $id = null, int $limit = 25, ?string $plugin = null): array
    {

        if ($id === null) {
            $id    = $this->getItem()->id;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__blc_instances', 'i'))
            ->select($db->quoteName('plugin_name', 'plugin'))
            ->select($db->quoteName('container_id', 'container_id'))
            ->select($db->quoteName('i.id', 'instance_id'))
            ->select($db->quoteName('i.link_text', 'link_text'))
            ->select($db->quoteName('field', 'field'))
            ->select($db->quoteName('parser', 'parser'))
            ->where($db->quoteName('i.link_id') . ' = :id')
            ->join('INNER', $db->quoteName('#__blc_synch', 's'), $db->quoteName('i.synch_id') . ' = ' . $db->quoteName('s.id'))
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit($limit);

        if ($plugin) {
            $query->where($db->quoteName('plugin_name') . ' = :plugin')
                ->bind(':plugin', $plugin, ParameterType::STRING);
        }
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
}
