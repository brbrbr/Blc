<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\ModCustom\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use  Blc\Component\Blc\Administrator\Blc\BlcParsers;
use  Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Event\BlcEvent;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-modcustom';
    protected $catids      = [];
    protected $context     = 'com_modules.module';
    private $replacedUrls  = [];


    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->setRecheck();
    }

    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }


    public function onBlcExtensionAfterSave(BlcEvent $event): void
    {

        parent::onBlcExtensionAfterSave($event);

        //the save is from extension but it is more ore less content
        $context = $event->getContext();
        if ($context != $this->context) {
            return;
        }
        $table  = $event->getItem();
        $module = $table->get('module');
        if ($module != 'mod_custom') {
            return;
        }
        $id = $table->get('id');
        // generate and empty object

        $arguments =
            [
                'context' => $context,
                'id'      => $id,
                'event'   => 'onsave',
            ];

        $event = new BlcEvent('onBlcContainerChanged', $arguments);

        $this->onBlcContainerChanged($event);
    }

    protected function getModuleTable()
    {
        $app        = Factory::getApplication();
        $mvcFactory = $app->bootComponent('com_modules')->getMVCFactory();
        $model      = $mvcFactory->createModel('Module', 'Administrator', ['ignore_request' => true]);
        return $model->getTable('Module', '\\Joomla\\CMS\\Table\\');
    }


    public function replaceLink(object $link, object $instance, string $newUrl): void
    {
        $table = self::getModuleTable();
        $table->load($instance->container_id);
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_ERROR_NOT_FOUND')),
                'warning'
            );
            return;
        }

        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_ERROR_CHECKED_OUT')),
                'warning'
            );
            return;
        }
        $update = false;
        $field  = $instance->field;
        switch ($field) {
            case 'content':
                $text         = $table->{$field};
                $textParsers  =  BlcParsers::getInstance();
                $replacedText = $textParsers->replaceLinksParser($instance->parser, $text, $link->url, $newUrl);

                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }
                break;
        }
        $field=$instance->field;//just to be consitent
        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS',$link->url,$newUrl,$field,$viewHtml),
                'succcess'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR',$link->url,$field,$viewHtml,Text::_('PLG_BLC_ANY_REPLACE_ERROR_LINK_NOT_FOUND')),
                    'warning'
                );
            }
        }
    }


    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        $db    = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from('`#__modules` `a`');

        if (!$idOnly) {
            $query->select('`a`.`title`,`a`.`content`,`a`.`params`')
                ->where('`a`.`module` = \'mod_custom\'');
        }
        if ($this->getParamLocalGlobal('access')) {
            $query->where('`a`.`access` IN (1)');
        }

        if ($this->params->get('administrator', 1) == 0) {
            $query->where('`a`.`client` IN (0)');
        }

        if ($this->getParamLocalGlobal('published')) {
            $nowQouted         = $db->quote(Factory::getDate()->toSql());
            $nullDateQuoted    = $db->quote($db->getNullDate());
            $query->where('`a`.`published` = 1')
                 ->where("(`a`.`publish_up` IS NULL OR  `a`.`publish_up` = $nullDateQuoted OR `a`.`publish_up` <= $nowQouted)")
                ->where("( `a`.`publish_down` IS NULL OR `a`.`publish_down` = $nullDateQuoted OR  `a`.`publish_down` >= $nowQouted)");
        } else {
            $query->where('`a`.`published` > -1'); //ignore trashed
        }
        return $query;
    }
    public function getTitle($instance): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('`#__modules`')
            ->select('`title`')
            ->where('`id` = ' . (int)$instance->container_id);
        $db->setQuery($query);
        return $db->loadResult() ?? 'Not found';
    }

    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_modules&task=module.edit&id=' . (int)$instance->container_id
        );
    }

    public function getViewLink($instance): string
    {
        return $this->getEditLink($instance);
    }

    protected function parseContainer(int $id)
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where('`a`.`id` = :containerId')
            ->bind(':containerId', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $row = $db->loadObject();
        if ($row) {
            $this->parseContainerFields($row);
        } else {
            $synchTable = $this->getItemSynch($id);
            if ($synchTable->id) {
                $this->purgeInstances($synchTable->id);
            }
        }
    }

    protected function parseContainerFields($row): void
    {
        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);
        $synchedId  = $synchTable->id;
        $this->purgeInstances($synchedId);
        $fields = [
            'content' => $row->content,

        ];

        $this->processText($fields, 'content', $synchedId);

        $synchTable->setSynched();
    }

    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        //modules don't have a modified date.
        //TOD resync after x days option
        $db     = $this->getDatabase();
        $wheres = [];
        $main   = "SELECT * FROM `#__blc_synch` `s` WHERE `s`.`container_id` = `a`.`{$this->primary}`" .
            ' AND `s`.`plugin_name` = ' . $db->quote($this->_name);
        $wheres[] = "NOT EXISTS ( {$main})";
        $wheres[] = "EXISTS ( {$main} AND `s`.`last_synch` < " . $db->quote($this->reCheckDate->toSql())  . ')';
        $query->extendWhere('AND', $wheres, 'OR');
    }
}
