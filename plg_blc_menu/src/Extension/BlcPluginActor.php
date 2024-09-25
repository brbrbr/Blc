<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Menu\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
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

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-menu';
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    protected $autoloadLanguage = true;
    protected $catids           = [];
    protected $context          = 'com_menus.item';
    private $replacedUrls       = [];

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


    public function canCheckLink(LinkTable $linkItem): int
    {
        return self::BLC_CHECK_CONTINUE;
    }

    public function getContext(): string
    {
        $option = $this->arguments['parsedUri']->getVar('option', '');
        $view   = $this->arguments['parsedUri']->getVar('view', '');
        return "{$option}.{$view}";
    }
    public function isContext(string $context): string
    {
        return $context == $this->getContext();
    }

    protected function getContainerTable()
    {
        $app        = Factory::getApplication();
        $mvcFactory = $app->bootComponent('com_menus')->getMVCFactory();
        $model      = $mvcFactory->createModel('Item', 'Administrator', ['ignore_request' => true]);

        return $model->getTable('Menu', 'Administrator');
    }


    public function replaceLink(object $link, object $instance, string $newUrl): void
    {



        $table = $this->getContainerTable();
        $table->load($instance->container_id);
        if ($table->type != 'url') {
            Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_ONLY_TYPE_SYSTEM_URL'), 'warning');
            return;
        }

        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_ERROR_NOT_FOUND')),
                'warning'
            );
        }

        $field = $instance->field;
        if ($field == 'link') {
            if ($table->link == $link->url && $newUrl != $table->link) {
                $table->link =  $newUrl;
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
            }
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    // phpcs:disable Generic.Files.LineLength
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR',$link->url,$field,$viewHtml,Text::_('PLG_BLC_ANY_REPLACE_ERROR_NOT_IMPLEMENTED')),
                    // phpcs:enable Generic.Files.LineLength
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
            ->from('`#__menu` `a`');
        if (!$idOnly) {
            $query->select('`a`.`title`,`a`.`link`,`a`.`type`');
        }
        if ($this->params->get('access', 1)) {
            $query->where('`a`.`access` IN (1)');
        }
        if ($this->params->get('visible', 0)) {
            $query->where('JSON_EXTRACT(`a`.`params`, \'$.menu_show\') = 1');
        }

        if ($this->params->get('published', 1)) {
            $nowQuoted         = $db->quote(Factory::getDate()->toSql());
            $nullDateQuoted    = $db->quote($db->getNullDate());
            $query->where('`a`.`published` = 1')
            ->where("(`a`.`publish_up` IS NULL OR  `a`.`publish_up` = $nullDateQuoted OR `a`.`publish_up` <= $nowQuoted)")
            ->where("(`a`.`publish_down` IS NULL OR `a`.`publish_down` = $nullDateQuoted OR  `a`.`publish_down` >= $nowQuoted)");
        } else {
            $query->where('`a`.`published` > -1'); //ignore trashed
        }
        $query->where('`a`.`type` IN ("component","url") ') //only
            ->where('`a`.`client_id` = 0 '); //ignore administrator

        return $query;
    }
    public function getTitle($instance): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('`#__menu`')->select('`title`')->where('`id` = ' . (int)$instance->container_id);
        $db->setQuery($query);
        return $db->loadResult() ?? 'Not found';
    }


    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_menus&task=item.edit&id=' . (int)$instance->container_id
        );
    }
    public function getViewLink($instance): string
    {
        try {
            $url = Route::link('site', 'index.php?Itemid=' . (int)$instance->container_id);
        } catch (\RuntimeException $e) {
            $url = Route::link('administrator', 'index.php?Itemid=' . (int)$instance->container_id);
        }

        return $url;
    }

    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        //menu's don't have a modified date.
        //TOD resync after x days option
        $db     = $this->getDatabase();
        $wheres = [];
        $main   = "SELECT * FROM `#__blc_synch` `s` WHERE `s`.`container_id` = `a`.`{$this->primary}`" .
            ' AND `s`.`plugin_name` = ' . $db->quote($this->_name);
        $wheres[] = "NOT EXISTS ( {$main})";
        $wheres[] = "EXISTS ( {$main} AND `s`.`last_synch` < " . $db->quote($this->reCheckDate->toSql())  . ')';
        $query->extendWhere('AND', $wheres, 'OR');
    }

    protected function parseContainerFields($row): void
    {
        $id = $row->id;

        $synchTable = $this->getItemSynch($id);
        $synchedId  = $synchTable->id;
        $this->purgeInstances($synchedId);
        if ($row->type == 'url') {
            $extraLinks = [[
                "url"    => $row->link,
                "anchor" => $row->title,
            ]];
        } else {
            $extraLinks = [[
                "url"    => 'index.php?Itemid=' . $row->id,
                "anchor" => $row->title,
            ]];

            if ($this->params->get('target', 0)) {
                $extraLinks[] = [
                    "url"    => $row->link,
                    "anchor" => $row->title,
                ];
            }
        }

        $this->processLinks($extraLinks, 'link', $synchedId);
        $synchTable->setSynched();
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
}
