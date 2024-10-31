<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Category\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParsers;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\Component\Categories\Administrator\Table\CategoryTable;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    use BlcHelpTrait;
    use CustomFieldsTrait {
        CustomFieldsTrait::__construct as private __cftConstruct;
    }

    private const  HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-category';

    protected $catids     = [];
    protected $context    = 'com_categories.category';
    private $replacedUrls = [];

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        if ($this->params->get('enablecf')) {
            $this->__cftConstruct();
        }
    }


    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }

    protected function getContainerTable()
    {
        try {
            $db    = $this->getDatabase();
            $table = new CategoryTable($db);
        } catch (\Error) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_GETCONTAINERTABLE_ERROR'),
                'warning'
            );
            return false;
        }

        return $table;
    }



    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {
        $table    = $this->getContainerTableById($instance->container_id);
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }

        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_CHECKED_OUT_ERROR')),
                'warning'
            );
            return;
        }

        $update  = false;
        $reparse = false;

        $field = $instance->field;
        switch ($field) {
            case 'description':
                $text         = $table->{$field};
                $textParsers  =  BlcParsers::getInstance();
                $replacedText = $textParsers->replaceLinksParser($instance->parser, $text, $link->url, $newUrl);

                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }

                break;
            case 'image':
                $params = json_decode($table->params);
                $image  = $params->{$field} ?? '';
                if ($image && $image == $image->url && $image != $newUrl) {
                    $params->{$field} = $newUrl;
                    $update           = true;
                }
                $table->params = json_encode($params);
                break;
            case 'Fields':
                $reparse = $this->replaceCustomFieldLink(
                    $link->url,
                    $newUrl,
                    $table,
                    $instance,
                );
        }
        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $reparse              = true;
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $viewHtml),
                'succcess'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                //or updated in the custom fields
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                    'warning'
                );
            }
        }
        if ($reparse) {
            $this->parseContainer($instance->container_id);
        }
    }
    public function getExtension($instance): string
    {
        $table = $this->getContainerTableById($instance->container_id);
        return $table->extension ?? 'com_content';
    }


    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from('`#__categories` `a`');
        if (!$idOnly) {
            $query->select('`a`.`title`,`a`.`description`,`a`.`params`,`a`.`extension`')

                ->order('`a`.`modified_time` DESC');
        }
        if ($this->getParamLocalGlobal('access')) {
            $query->where('`a`.`access` IN (1)');
        }

        if ($this->getParamLocalGlobal('published')) {
            $query->where('`a`.`published` = 1');
        } else {
            $query->where('`a`.`published` > -1'); //ignore trashed
        }

        return $query;
    }


    public function getEditLink($instance): string
    {
        $extension = $this->getExtension($instance);
        return Route::link(
            'administrator',
            "index.php?option=com_categories&task=category.edit&extension={$extension}&&id={$instance->container_id}"
        );
    }
    public function getViewLink($instance): string
    {

        $extension = $this->getExtension($instance);
        $link      = "index.php?option={$extension}&view=category&id={$instance->container_id}";
        return Route::link(
            'site',
            $link
        );
    }

    protected function parseContainer(int $id): void
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where($db->quoteName("a.{$this->primary}") . ' = :containerId')
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
            'description' => $row->description,
        ];

        $this->processText($fields, 'category', $synchedId);

        $params = json_decode($row->params);

        $extraLinks = [];
        if (!empty($params->image)) {
            $extraLinks["image"] = [
                "url"    => $params->image ?? '',
                "anchor" => $params->image__alt ?? "Image of Category: {$row->title}",
            ];
            $this->processLinkByFields($extraLinks, $synchedId);
        }
        if ($this->params->get('enablecf')) {
            $this->parseCustomFields($row, $synchedId);
        }
        $synchTable->setSynched();
    }

    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        $db       = $this->getDatabase();
        $wheres   = [];
        $existsQuery = $db->getQuery(true);
        $existsQuery->select('1')
            ->from($db->quoteName('#__blc_synch', 's'))
            ->where($db->quoteName('s.container_id') . ' = ' . $db->quoteName("a.{$this->primary}"));


        $wheres[] = "EXISTS ( {$existsQuery}" .
            ' AND ' .   $db->quoteName('s.plugin_name') . ' = ' . $db->quote($this->_name)   .
            ' AND ' . $db->quoteName('s.last_synch') . ' < ' . $db->quoteName("a.modified_time") . ')';
        $wheres[] = "NOT EXISTS ( {$existsQuery}" .
            ' AND ' .   $db->quoteName('s.plugin_name') . ' = ' . $db->quote($this->_name) . ')';
        $query->extendWhere('AND', $wheres, 'OR');
    }
}
