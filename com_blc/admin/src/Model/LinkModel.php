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

use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use  Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
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
     * @return  Table    A database object
     *
     * @since   1.0.0
     */
    public function getTable($type = 'Link', $prefix = 'Administrator', $config = [])
    {
        return parent::getTable($type, $prefix, $config);
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
        return false;//not used
    }


    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  Table    Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem($pk = null): object
    {

        $pk    = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');

        $item   = $this->getTable();
        $result = $item->load();
        print $item->parked . '<br>';
        print $item->being_checked;
        exit;

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
        BlcHelper::setCronState(false);
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
                if ($what == 'links') {
                    $query = $db->getQuery(true);
                    $query->delete('`#__blc_links`')
                        ->where('NOT EXISTS (SELECT * FROM `#__blc_instances` `i` WHERE `i`.`link_id` = `#__blc_links`.`id`)');
                    $db->setQuery($query)->execute();
                    $c         = $db->getAffectedRows();
                    $message[] = Text::sprintf('COM_BLC_LINKS_TABLE_ORPHANS_DELETE_MESSAGE', $c);
                }
            }

            if ($do === 'reset') {
                if ($what == 'links') {
                    //$nullDate = $db->getNullDate();
                    $query = $db->getQuery(true);
                    $query->update('`#__blc_links`')
                        //where to mix them in the recheck order?
                        //with the last_check reset to the nulldate the order would be the database order ( id )
                        //with the lastc_check untouched they will be rechecked after all really new links

                        //  ->set('last_check = :nullDate')->bind(':nullDate', $nullDate)
                       // ->set('http_code = 0')
                        ->set('`being_checked` = ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK)
                        //->set('`broken` = 0')
                        //->set('`redirect_count` = 0')
                        ->where('`being_checked` = ' . HTTPCODES::BLC_CHECKSTATE_CHECKED);
                    //->set('`final_url` = \'\'');
                    if ($pks) {
                        $query->whereIn('`id`', $pks, ParameterType::INTEGER);
                    }
                    $db->setQuery($query)->execute();
                    $message[] = Text::_('COM_BLC_LINKS_TABLE_CHECK_RESET_MESSAGE');
                }
            }

            if ($do === 'truncate') {
                $db->setQuery("SET FOREIGN_KEY_CHECKS=0")->execute();
                if ($what == 'links' || $what == 'all') {
                    $query = $db->getQuery(true);
                    $query = "TRUNCATE TABLE `#__blc_links`";
                    $db->setQuery($query)->execute();
                    $message[] = Text::_('COM_BLC_LINKS_TABLE_TRUNCATED_MESSAGE');
                }

                if ($what == 'links' || $what == 'synch' || $what == 'all') {
                    $query = $db->getQuery(true);
                    $query->delete('`#__blc_synch`')
                        ->where('`plugin_name` != "_Transient"');
                    $db->setQuery($query)->execute();
                    $query = "TRUNCATE TABLE `#__blc_instances`";
                    $db->setQuery($query)->execute();
                    $message[] = Text::_('COM_BLC_SYNCH_TABLE_TRUNCATED_MESSAGE');
                }
                $db->setQuery("SET FOREIGN_KEY_CHECKS=1")->execute();
            }

            if ($do === 'delete') {
                if ($what == 'synch') {
                    $query = $db->getQuery(true);
                    $query->delete('`#__blc_synch`')
                        ->where('`plugin_name` = :containerPlugin')
                        ->bind(':containerPlugin', $plugin);
                    if ($pks) {
                        $query->whereIn('`id`', $pks, ParameterType::INTEGER);
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
            Factory::getApplication()->enqueueMessage(join("\n", $message));
        }
    }


    public function getInstances()
    {

        $id    = $this->getItem()->id;
        $db    = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->from('`#__blc_instances` `i`')
            ->select('*,`i`.`id` `id`')
            ->where('`i`.`link_id` = :id')
            ->innerJoin('`#__blc_synch` `s` ON `i`.`synch_id` = `s`.`id`')
            ->bind(':id', $id);
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
            $links->anchor  = $row->link_text;
            $links->plugin  = $row->plugin_name;
            $links->field   = $row->field;
            $instances[$id] = $links;
        }
        return $instances;
    }

    public function getSynch(int $id, $limit = 25)
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('`#__blc_instances` `i`')
            ->select($db->quoteName('plugin_name', 'plugin'))
            ->select($db->quoteName('container_id', 'container_id'))
            ->select($db->quoteName('i.id', 'instance_id'))
            ->select($db->quoteName('field', 'field'))
            ->select($db->quoteName('parser', 'parser'))
            ->where('`i`.`link_id` = :id')
            ->innerJoin('`#__blc_synch` `s` ON `i`.`synch_id` = `s`.`id`')
            ->bind(':id', $id)
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
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function prepareTable($table)
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
