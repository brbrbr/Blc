<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 * @since __DEPLOY_VERSION__

 *
 */

namespace Blc\Component\Blc\Administrator\Traits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcParsers;

use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Parser\LinksParser;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Joomla\Database\ParameterType;
use Joomla\CMS\Date\Date;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\Database\DatabaseQuery;
use Joomla\Registry\Registry;

trait  BlcExtractTrait
{

    protected $reCheckDate;
    protected $parseLimit           = 1;
    public function getLinks($data): object
    {
 
        return (object)[
            'view'  => $this->getViewLink($data),
            'edit'  => $this->getEditLink($data),
            'title' => $this->getTitle($data),
        ];
    }
    //this is the default Extract execution for normal database based extractors.
    public function onBlcExtract(BlcExtractEvent $event): void
    {

        $this->cleanupSynch();
        $todo = $this->getUnsynchedCount();

        $this->parseLimit = $event->getMax();

        if ($todo === 0) {
            return;
        }

        $event->setExtractor($this->_name);
        $event->updateTodo($todo);

        print "Starting Extraction:  {$this->_name} - todo $todo\n";
        $rows = $this->getUnsynchedRows();
        if ($rows) {
            $event->updateDidExtract(\count($rows));
            $event->updateTodo(-\count($rows));
            foreach ($rows as $row) {
                $this->parseContainerFields($row);
            }
        }
    }

    public function onBlcPurge()
    {
        $this->cleanupSynch(false);
    }

    /**
     * this will clean up all synch data for deleted and expired content
     * @param bool $onlyOrhpans delete only orphans (true) or purge all (false)
     *
     */

    protected function cleanupSynch(bool $onlyOrhpans = true): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->bind(':containerPlugin', $this->_name, ParameterType::STRING);

        if ($onlyOrhpans) {
            $elementsQuery = $this->getQuery(true)->__toString();
            $query->where($db->quoteName('container_id') . " NOT IN  ($elementsQuery) ");
        }

        $db->setQuery($query)->execute();
    }

    public function onBlcContainerChanged(BlcEvent $event): void
    {
        //logging might confuse applications
        ob_start();
        $context   = $event->getContext();

        if ($context != $this->context) {
            return;
        }
        //    $this->getApplication()->enqueueMessage( $context . ' - ' . $this->context . ' - '. get_class($this));

        $id      = $event->getId();
        $event   = $event->getEvent();
        $action  = $this->params->get($event, 'default');
        if ($action == 'default') {
            $action = $this->componentConfig->get($event, 'nothing');
        }

        if ($this->getApplication()->get('debug')) {
            $this->getApplication()->enqueueMessage(
                "BLC Container update $context $id action: $event do $action",
                'info'
            );
        }

        switch ($action) {
            case 'parse':
                $this->parseContainer($id);
                break;
            case 'nothing':
                break;
            default:
            case 'delete':
                $this->purgeContainer($id);
                break;
        }
        ob_get_clean();
    }

    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {
        //this->params holds the old config
        if (!$this->params) {
            return; //after pluging enable
        }
        $table = $event->getItem();
        $type  = $table->get('type');
        if ($type != 'plugin') {
            return;
        }

        $folder = $table->get('folder');
        if ($folder != $this->_type) {
            return;
        }

        $element = $table->get('element');
        if ($element != $this->_name) {
            return;
        }

        $params = new Registry($table->get('params')); // the new config is already saved
        if (
            $this->getParamLocalGlobal('deleteonsavepugin')
            &&
            $this->params->toArray() !== $params->toArray()
        ) {
            $model = $this->getModel('Link');
            $model->trashit('delete', 'synch', $this->_name);
            return;
        }
        //delete on unpublish
        if ($table->state == 0) {
            $model = $this->getModel('Link');
            $model->trashit('delete', 'synch', $this->_name);
            return;
        }
    }

    protected function getItemSynch(int $containerId, bool $create = true): SynchTable
    {

        $synchTable = new SynchTable($this->getDatabase());
        $pk         = [
            'container_id' => $containerId,
            'plugin_name'  => $this->_name,
        ];
        $synchTable->load($pk);
        if ($create && !$synchTable->id) {
            //  $pk['data'] = [];
            if (!$synchTable->save($pk)) {
                throw new GenericDataException($synchTable->getError(), 500);
            }
        }
        return $synchTable;
    }

    //should work with most (joomla) tables where 'a.id' is primary key
    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        $db    = $this->getDatabase();
        $main  = $db->getQuery(true);
        $main->select('*')
            ->from($db->quoteName('#__blc_synch', 's'))
            ->where($db->quoteName('s.container_id') . ' = ' . $db->quoteName("a.{$this->primary}"))
            ->where($db->quoteName('s.plugin_name') . ' = ' . $db->quote($this->_name)); //bind fiai query used twice
        $mainString =  $main->__toString();

        $wheres[] = "EXISTS ( {$mainString} AND " . $db->quoteName('s.last_synch') . ' < ' . $db->quoteName("a.modified") . ")";
        $wheres[] = "NOT EXISTS ({$mainString})";
        $query->extendWhere('AND', $wheres, 'OR');
    }

    protected function getUnsynchedCount()
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $this->getUnsynchedQuery($query);

        $query->clear('select')
            ->clear('order')
            ->select('count(*)');
        $db->setQuery($query);

        return $db->loadResult();
    }

    protected function setLimit($query)
    {
        $query->setLimit($this->parseLimit);
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        print "Get's the base query for the elements:{$this->_name}" . (int)$idOnly;
        $db    = $this->getDatabase();
        return $db->getQuery(true);
    }


    protected function purgeInstance(int $instanceId)   //BY instance ID
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_instances'))
            ->where($db->quoteName('id') . ' = :instanceId')
            ->bind(':instanceId', $instanceId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    protected function purgeInstances($synchedId) //BY sync ID
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_instances'))
            ->where($db->quoteName('synch_id') . ' = :synchedId')
            ->bind(':synchedId', $synchedId, ParameterType::INTEGER);
        $db->setQuery($query)->execute();
        //Instances via foreign key
    }

    protected function purgeContainer(int $containerID): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->bind(':containerPlugin', $this->_name, ParameterType::STRING)
            ->where($db->quoteName('container_id') . ' = :containerID')
            ->bind(':containerID', $containerID, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    protected function processText(string|array $text, string|int $fieldName, int $synchId)
    {
        $meta = [
            'field'   => $fieldName,
            'synchId' => $synchId,
        ];

        $textParsers =  BlcParsers::getInstance();
        $textParsers->setMeta($meta)
            ->extractAndStoreLinks($text);
    }


    //The link parser behaves diffently.
    //it takes a list of links. So there is on step less for the parser
    //untill now all plugins need it. So it's implemented seperattly.

    protected function processLinks(array $links, string $fieldName, int $synchId)
    {
        $meta = [
            'field'   => $fieldName,
            'synchId' => $synchId,
        ];

        $linkParser = LinksParser::getInstance();
        $linkParser->setMeta($meta)
            ->extractAndStoreLinks($links);
    }

    protected function processLink(array $link, string $fieldName, int $synchId)
    {
        $this->processLinks([$link], $fieldName, $synchId);
    }


    protected function processLinkByFields(array $input, int $synchId)
    {
        foreach ($input as $field => $link) {
            $this->processLinks([$link], $field, $synchId);
        }
    }

    protected function setRecheck()
    {

        $reCheckFreq = $this->params->get('freq', 1) * 3600 * 24;
        if ($reCheckFreq > 0) {
            $this->reCheckDate = new Date("- {$reCheckFreq} SECONDS");
        } else {
            $this->reCheckDate = new Date("01-01-2024");
        }
    }
    protected function getUnsynchedRows()
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $this->getUnsynchedQuery($query);
        $this->setLimit($query);
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        return $rows;
    }
}
