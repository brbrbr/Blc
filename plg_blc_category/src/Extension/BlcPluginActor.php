<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Category\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Component\Categories\Administrator\Table\CategoryTable;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends CMSPlugin implements SubscriberInterface, BlcExtractInterface, DatabaseAwareInterface
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
    use DatabaseAwareTrait;
    use BlcExtractTrait;

    private const  HELPLINK         = 'https://brokenlinkchecker.dev/extensions/plg-blc-category';
    protected $allowLegacyListeners = false;
    protected $catids               = [];
    protected string $context       = 'com_categories.category';
    private $replacedUrls           = [];
    protected $componentConfig;
    protected $primary = 'id';
    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            parent::__construct(Factory::getApplication()->getDispatcher(), $config);
        }

        $this->componentConfig = ComponentHelper::getParams('com_blc');
        $this->fieldContext    = 'com_content.categories'; //why joomla WHY?
        $this->__cftConstruct();
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

        $messageLinks = $this->getMessageLinks($instance);

        if (!$instance->parser) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ALT_SET_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_PARSER_NOT_SET')),
                'warning'
            );
            return;
        }

        $table        = $this->getContainerTableById($instance->container_id);

        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }

        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_CHECKED_OUT_ERROR')),
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
                $textParsers  =  BlcParseController::getInstance();
                $replacedText = $textParsers->replaceLinkInSourceByParser($instance->parser, $text, $link->url, $newUrl);
                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }

                break;
            case 'image':
                $params = json_decode((string) $table->params);
                $image  = $params->{$field} ?? '';
                if ($image && ($image == $link->url) && ($image != $newUrl)) {
                    $params->{$field} = $newUrl;
                    $table->params    = json_encode($params);
                    $update           = true;
                }

                break;
            case 'Fields':
                $reparse = $this->replaceCustomFieldLink(
                    $link->url,
                    $newUrl,
                    $table,
                    $instance
                );


                break;
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
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $messageLinks),
                'success'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                //or updated in the custom fields
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
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
            "index.php?option=com_categories&task=category.edit&extension={$extension}&id={$instance->container_id}"
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

    protected function parseContainerFields($row): void
    {
        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);
        $synchId    = $synchTable->id;
        if (!$synchId) {
            //creation failed most likely due to concurrent jobs
            //ignore next job will retry
            return;
        }
        $this->purgeInstances($synchId);
        $fields = [
            'description' => $row->description,
        ];

        $this->processText($fields, 'category', $synchId);

        $params = json_decode((string) $row->params);

        $extraLinks = [];
        if (!empty($params->image)) {
            $extraLinks["image"] = [
                "url"    => $params->image ?? '',
                "anchor" => ($params->image_alt ?? PARSE_STRINGS::BLC_EMPTY_ALT) ?: PARSE_STRINGS::BLC_EMPTY_ALT,
            ];
            $this->processLinkByFields($extraLinks, $synchId);
        }

        $this->parseCustomFields($row, $synchId);
        $synchTable->setSynched();
    }

    protected function getUnsynchedQuery(DatabaseQuery $query)
    {
        $db          = $this->getDatabase();
        $wheres      = [];
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
