<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 * @since 24.44.6670

 *
 */

namespace Blc\Component\Blc\Administrator\Traits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcMessages;
use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Event\BlcExtractEvent;
use Blc\Component\Blc\Administrator\Table\SynchTable;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

trait BlcExtractTrait
{
    protected $reCheckDate;
    protected $parseLimit             = 1;

    public function pluginCanReplaceLink()
    {
        return (bool)$this->getParamLocalGlobal('plugin_can_replace_link', 1);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }

    public function __get($name)
    {
        return match ($name) {
            'context' => $this->context,
            'name'    => $this->_name,
            default   => null
        };
    }



    protected function checkCanReplaceLink($oldUrl, $messageLinks): bool
    {
        if ($this->pluginCanReplaceLink()) {
            return true;
        }

        $extension = 'Plg_' . $this->_type . '_' . $this->_name;

        $extension = strtolower($extension);
        $this->loadLanguage($extension . '.sys');
        if (isset($this->extensionId) && $this->extensionId) {
            $configLink = Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' .  $this->extensionId);
        } else {
            $configLink = Route::_('index.php?option=com_plugins&view=plugins');
        }
        $extensionKey = strtoupper($extension);
        Factory::getApplication()->enqueueMessage(
            Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $oldUrl, $messageLinks, Text::sprintf('PLG_BLC_ANY_REPLACE_DISABLED', Text::_($extensionKey), $configLink)),
            'warning'
        );
        return false;
    }


    /**
     *
     * @since 24.44.6744
     * @param int $id
     *
     * @return Table
     *
     */

    protected function getContainerTable()
    {
        throw new \RuntimeException(\sprintf("Method %s in class %s must be overriden", __METHOD__, __CLASS__));
    }

    /**
     *
     * @since 24.44.6744
     * @param int $id
     *
     * @return Table
     *
     */

    protected function getContainerTableById(int $id): Table
    {
        $table = $this->getContainerTable();
        $table->load($id);
        return $table;
    }



    public function getViewLink($instance): string
    {
        throw new \RuntimeException(\sprintf("Method %s in class %s must be overriden", __METHOD__, __CLASS__));
    }

    public function getEditLink($instance): string
    {

        throw new \RuntimeException(\sprintf("Method %s in class %s must be overriden", __METHOD__, __CLASS__));
    }


    public function getTitle($instance): string
    {
        $table = $this->getContainerTableById($instance->container_id);
        return $table->title ?? Text::sprintf('COM_BLC_PLUGIN_TITLE_NOT_FOUND', $instance->container_id);
    }

    protected function getMessageLinks($instance, $target = "replaced")
    {
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), $target);
        $editHtml = HTMLHelper::_('blc.linkme', $this->getEditLink($instance), Text::_('JACTION_EDIT'), $target);
        return "$viewHtml  ($editHtml)";
    }


    protected function parseContainer(int $id): void
    {
        $table        = $this->getContainerTableById($id);
        if ($table->id) {
            $this->parseContainerFields($table);
        } else {
            $this->cleanupSynchId($id);
        }
    }

    protected function parseContainerFields($rows): void
    {
        throw new \RuntimeException(\sprintf("Method %s in class %s must be overriden", __METHOD__, __CLASS__));
    }
    //this is the default Extract execution for normal database based extractors.
    public function onBlcExtract(BlcExtractEvent $event): void
    {

        $this->cleanupSynch();
        $todo             = $this->getUnsynchedCount();
        $this->parseLimit = $event->getMax();

        if ($todo === 0) {
            return;
        }
        $event->setExtractor($this->_name);
        $event->updateTodo($todo);

        BlcMessages::getInstance()->enqueueMessage(Text::sprintf('COM_BLC_EXTRACT_MESSAGE', $this->_name, $todo), 'alert');
        $rows = $this->getUnsynchedRows();
        if ($rows) {
            $event->updateDidExtract(\count($rows));
            $event->updateTodo(-\count($rows));
            foreach ($rows as $row) {
                $this->parseContainerFields($row);
            }
        }
    }


    /**
     * this will clean up all synch data for deleted and expired content
     * @param bool $onlyOrhpans delete only orphans (true) or purge all (false)
     *
     */

    protected function cleanupSynch(): void
    {

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->bind(':containerPlugin', $this->_name, ParameterType::STRING);


        $elementsQuery = $this->getQuery(true)->__toString();
        $query->where($db->quoteName('container_id') . " NOT IN  ($elementsQuery) ");

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            $this->getApplication()->enqueueMessage(Text::sprintf('COM_BLC_EXECUTION_FAILED', __METHOD__, $this->_name, $e->getMessage()), 'error');
        }
    }

    public function onBlcContainerChanged(BlcEvent $event): void
    {
        //logging might confuse applications

        $context   = $event->getContext();


        if ($context != $this->context) {
            return;
        }

        $id      = $event->getId();


        //Joomla never has items with Id = 0
        if (!$id) {
            return;
        }
        $event   = $event->getEvent();
        $action  = $this->getParamLocalGlobal($event, 'nothing');


        BlcMessages::getInstance()->enqueueMessage(
            "BLC Container update $context $id action: $event do $action",
            'info'
        );

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
        if ($this->getParamLocalGlobal('plgmessages', 1)) {
            BlcMessages::getInstance()->moveToApplication($this->getApplication());
        }
    }



    private function getModel(string $component = 'com_blc', string $name = 'Link', string $prefix = 'Administrator', array $config = ['ignore_request' => true]): mixed
    {
        $mvcFactory = $this->getApplication()->bootComponent($component)->getMVCFactory();
        return $mvcFactory->createModel($name, $prefix, $config);
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
            try {
                $synchTable->save($pk);
            } catch (\RuntimeException $e) {
                BlcMessages::getInstance()->enqueueMessage(
                    $e->getMessage(),
                    'error'
                );
            }
        }

        return $synchTable;
    }
    /**
     * this function is called in some add situatins where the container is deleted.
     * mainly used in tests
     * @since 25.44.7545
     */
    protected function cleanupSynchId($id)
    {
        $synchTable = $this->getItemSynch($id, false); //no need to create if it does not exist
        if ($synchTable->id) {
            $this->purgeInstances($synchTable->id);
            $synchTable->delete();
        }

        BlcMessages::getInstance()->enqueueMessage(
            Text::sprintf('COM_BLC_CLEANUP_SYNCH_ID', $this->_name, $id),
            'warning'
        );
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
        try {
            $db->setQuery($query);
            $count = $db->loadResult();
        } catch (\RuntimeException $e) {
            $this->getApplication()->enqueueMessage(Text::sprintf('COM_BLC_EXECUTION_FAILED', __METHOD__, $this->_name, $e->getMessage()), 'error');
            $count = 0;
        }

        return $count;
    }

    protected function setLimit($query)
    {
        $query->setLimit($this->parseLimit);
    }
    /**
     * Get's the base query for the items
     * @throws \RuntimeException;
     *
     */

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        throw new \RuntimeException(\sprintf("Method %s in class %s must be overriden", __METHOD__, __CLASS__));
    }




    protected function purgeInstances(int $synchId) //BY sync ID
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__blc_instances'))
            ->where($db->quoteName('synch_id') . ' = :synchId')
            ->bind(':synchId', $synchId, ParameterType::INTEGER);
        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            $this->getApplication()->enqueueMessage(Text::sprintf('COM_BLC_EXECUTION_FAILED', __METHOD__, $this->_name, $e->getMessage()), 'error');
        }
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

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            $this->getApplication()->enqueueMessage(Text::sprintf('COM_BLC_EXECUTION_FAILED', __METHOD__, $this->_name, $e->getMessage()), 'error');
        }
    }
    protected function cleanField($field)
    {
        return strtolower(explode('.', $field)[0]);
    }

    protected function processText(string|array $text, string|int $fieldName, int $synchId): array
    {
        $meta = [
            'field'   => $fieldName,
            'synchId' => $synchId,
        ];

        $parseController =  BlcParseController::getInstance();
        return  $parseController->extractAndStoreLinks($text, $meta);
    }


    //The link parser behaves diffently.
    //it takes a list of links. So there is on step less for the parser
    //untill now all plugins need it. So it's implemented seperattly.

    protected function processLinks(array $links, string $fieldName, int $synchId)
    {
        $meta = [
            'field'   => $fieldName,
            'synchId' => $synchId,
            'parser'  => 'links', //this is a stub. Links can be replaced directly by the extractors
        ];
        $parseController =  BlcParseController::getInstance();
        $parseController->storeLinks($links, $meta);
    }

    protected function processLink(string $link, string $fieldName, int $synchId)
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

        try {
            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            $this->getApplication()->enqueueMessage(Text::sprintf('COM_BLC_EXECUTION_FAILED', __METHOD__, $this->_name, $e->getMessage()), 'error');
            $rows = [];
        }

        return $rows;
    }




    protected function getParamLocalGlobal(string $what, $default = ''): bool|int|string
    {

        $only = $this->params->get($what, -1);
        if ($only == 'default') {
            $only = -1;
            @trigger_error(
                "Using 'default' is deprecated use -1",
                E_USER_DEPRECATED
            );
        }
        return ($only != -1) ? $only : $this->componentConfig->get($what, $default);
    }
    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {

        //this->params holds the old config
        if (!$this->params) {
            return; //after pluging enable
        }

        $table = $event->getItem();
        $type  = $table->type ?? '';

        if ($type != 'plugin') {
            return;
        }

        $folder = $table->folder ?? '';
        if ($folder != $this->_type) {
            return;
        }

        $element = $table->element ?? '';
        if ($element != $this->_name) {
            return;
        }

        $params = new Registry($table->params ?? []); // the new config is already saved. The plugin stil has the old one.

        if ($params->get('enablecf')) {
            $cf = $this->params->get('cf', new \stdClass());
            if (isset($cf->sql) && $cf->sql == 1) {
                if ((int)$this->componentConfig->get('field_checker', 0) == 0) {
                    $optionsUrl = Route::link('administrator', 'index.php?option=com_config&view=component&component=com_blc');
                    $this->getApplication()->enqueueMessage(
                        Text::sprintf('PLG_BLC_CHECK_FIELD_CHECKER_DISABLED', $optionsUrl),
                        'warning'
                    );
                }
            }
        }

        if ($this->params->toArray() !== $params->toArray()) {
            $this->params = $params;
            if ($this->getParamLocalGlobal('deleteonsavepugin')) {
                $model = $this->getModel();
                $model->trashit('delete', 'synch', $this->_name);
                return;
            }
        }
        //delete on unpublish
        if (($table->enabled ?? 0) == 0) {
            $model = $this->getModel();
            $model->trashit('delete', 'synch', $this->_name);
            return;
        }
    }
}
