<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Traits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcParsers;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

trait FieldAwareTrait
{
    private $contentFields          = [];
    private $contentLinks           = [];
    private $allowedFields          = [];
    private $extraUrlIds            = [];
    private $fieldToType            = null;
    private $newUrl                 = null;
    private $oldUrl                 = null;
    private $parserInstance         = null;
    protected string $splitOption   = "#(;|,|\r\n|\n|\r)#";

    public function __construct()
    {
        $defaultFields = ['text' => 0, 'textarea' => 0, 'editor' => 1, 'url' => 1, 'media' => 1, 'subform' => 1];
        foreach ($defaultFields as $field => $default) {
            if ($this->params->get($field, $default)) {
                $this->allowedFields[] = $field;
            }
        }

        $this->extraUrlIds = ArrayHelper::toInteger(
            array_filter(
                preg_split(
                    $this->splitOption,
                    $this->params->get('extraurl', '')
                )
            )
        );
    }

    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }

    protected function getUnsynchedRows()
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $this->getUnsynchedQuery($query);
        $this->setLimit($query);
        $db->setQuery($query);
        $rows    = $db->loadObjectList();
        $stacked = [];
        foreach ($rows as $row) {
            $id = $row->id;
            $stacked[$id] ??= [];
            $stacked[$id][] = $row;
        }
        return $stacked;
    }

    protected function getFieldValue(int $fieldId, int $itemId): \stdClass
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->from($db->quoteName('#__fields_values', 'v'))
            ->join('INNER', $db->quoteName('#__fields', 'f'), "{$db->quoteName('f.id')} = {$db->quoteName('v.field_id')}")
            ->select($db->quoteName('v.field_id', 'field_id'))
            ->select($db->quoteName('v.value', 'field_value'))
            ->select($db->quoteName('f.type', 'field_type'))
            ->where("{$db->quoteName('v.field_id')} = :fieldId")
            ->bind(':fieldId', $fieldId, ParameterType::INTEGER)
            ->where("{$db->quoteName('v.item_id')} = :itemId")
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject();
    }

    private function baseFieldQuery(bool $idOnly = false): DatabaseQuery
    {
        $db    = $this->getDatabase();
        $query = parent::getQuery($idOnly);
        $query->clear('select')
            ->select($db->quoteName("a.{$this->primary}", 'id'))
            ->join('INNER', $db->quoteName('#__fields_values', 'v'), "{$db->quoteName('a.id')} = {$db->quoteName('v.item_id')}")
            ->join('INNER', $db->quoteName('#__fields', 'f'), "{$db->quoteName('f.id')} = {$db->quoteName('v.field_id')}")
            ->where("{$db->quoteName('f.context')} = {$db->quote($this->fieldContext)}"); //no bind used as subquery;

        if ($this->params->get('field_state', 1)) {
            $query->where("{$db->quoteName('f.state')} = 1");
        } else {
            $query->where("{$db->quoteName('f.state')} > -1"); //ignore trashed
        }

        if ($idOnly) {
            $query->group($db->quoteName('id'));
        } else {
            $query->select($db->quoteName('v.field_id', 'field_id'))
                ->select($db->quoteName('v.value', 'field_value'))
                ->select($db->quoteName('f.type', 'field_type'));
        }
        return $query;
    }

    private function extraFieldQuery(&$query)
    {
        $db    = $this->getDatabase();
        $or    = [];
        //bind doesn't work since we are using the query as a stringed subquery in cleanupSynch
        if ($this->allowedFields) {
            // phpcs:disable Generic.Files.LineLength
            $or[] = $db->quoteName('f.type') . ' IN (' . implode(',', array_map([$db, 'quote'], $this->allowedFields)) . ')'; //bind werkt niet.
            // phpcs:enable Generic.Files.LineLength
        }
        if ($this->extraUrlIds) {
            // phpcs:disable Generic.Files.LineLength
            $or[] = $db->quoteName('f.id') . ' IN (' . implode(',', array_map([$db, 'quote'], $this->extraUrlIds)) . ')';
            // phpcs:enable Generic.Files.LineLength
        }

        if ($or) {
            $query->where('(' . join(' OR ', $or) . ')');
        } else {
            $query->where('0'); // nothing selected use everyting instead?
        }
    }

    protected function parseSubForm(object|string $subform): object
    {
        $this->loadFieldToType();

        if (\is_string($subform)) {
            $subform = json_decode($subform);
        }

        foreach ($subform as $key => &$field) {
            if (preg_match('#row[0-9]+#', $key)) {
                $this->parseSubForm($field);
            } else {
                $field_id = (int)preg_replace('#^field#', '', $key);
                if (isset($this->fieldToType[$field_id])) {
                    $row              = new \StdClass();
                    $row->field_type  = $this->fieldToType[$field_id]->type;
                    $row->field_value = $field;
                    $row->field_id    = $field_id;
                    $this->parseCustomField($row);
                }
            }
        }

        if (\is_array($subform)) {
            $subform = (object)$subform;
        }

        return $subform;
        //   exit;
    }
    /**
     *  @since 24.44.6611
     *
     * helper function to load the allowed field types for each #__fields.id
     * custom fields are pnly referenced by their #__fields.id, the type is not included.
     *
     *
     */
    protected function loadFieldToType()
    {
        if ($this->fieldToType === null) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select($db->quoteName(['id', 'type']))
                ->from($db->quoteName('#__fields', 'f'));
            $this->extraFieldQuery($query);
            $db->setQuery($query);
            $this->fieldToType = $db->loadObjectList('id');
        }
    }
    /**
     *  @since 24.44.6611
     *
     * similair to parseSubForm, this version modifies the subform object
     */
    protected function replaceSubForm(object|string $subform): object
    {

        $this->loadFieldToType();
        if (\is_string($subform)) {
            $subform = json_decode($subform);
        }

        foreach ($subform as $key => &$field) {
            if (preg_match('#row[0-9]+#', $key)) {
                $field = $this->replaceSubForm($field);
            } else {
                $field_id = (int)preg_replace('#^field#', '', $key);
                if (isset($this->fieldToType[$field_id])) {
                    $row              = new \StdClass();
                    $row->field_type  = $this->fieldToType[$field_id]->type;
                    $row->field_value = $field;
                    $row->field_id    = $field_id;
                    $ret              =  $this->replaceCustomField($row);
                    if ($ret) {
                        $field = $ret;
                    }
                }
            }
        }

        return $subform;
        //   exit;
    }


    protected function parseCustomField($row)
    {
        switch ($row->field_type) {
            case 'url':
                //the parser would take care of empty url's however we might want to show empty a and img tags later
                if ($row->field_value ?? '') {
                    $this->contentLinks[] = ['url' => $row->field_value, 'anchor' => 'URL Custom Field'];
                }
                break;
            case 'editor':
            case 'textarea':
            case 'text':
                $text =  $row->field_value;
                if (strpos($text, '<') !== false) {
                    $this->contentFields[] = $text;
                }
                break;
            case 'mediajce':
            case 'media':
                if (\is_string($row->field_value)) {
                    $image = json_decode($row->field_value);
                } else {
                    $image = $row->field_value;
                }
                $image_url = $image->imagefile ?? $image ?? ''; //old format
                $image_alt = !empty(trim($image->alt_text ?? '')) ? "{$image->alt_text}" : 'No Alt text'; //old format
                if ($image_url) {
                    $this->contentLinks[] = ['url' => $image_url, 'anchor' => $image_alt];
                }
                break;
            case 'subform':
                $this->parseSubForm($row->field_value);
                break;
        }

        $field_id = $row->field_id;
        if (\in_array($field_id, $this->extraUrlIds)) {
            if ($row->field_value ?? '') {
                $this->contentLinks[] = ['url' => $row->field_value, 'anchor' => 'URL Custom Field'];
            }
        }
    }

    protected function parseContainer(int $id): void
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where("{$db->quoteName('a.id')} = :containerId")
            ->bind(':containerId', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $rows = $db->loadObjectList(); // there are posibly multiple fields
        if ($rows) {
            $this->parseContainerFields($rows);
        } else {
            $synchTable = $this->getItemSynch($id);
            if ($synchTable->id) {
                $this->purgeInstances($synchTable->id);
            }
        }
    }

    protected function getFieldModel()
    {
        $app        = Factory::getApplication();
        $mvcFactory = $app->bootComponent('com_fields')->getMVCFactory();
        return $mvcFactory->createModel('Field', 'Administrator', ['ignore_request' => true]);
    }
    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {

        $fieldModel        = $this->getFieldModel();
        $fieldValue        = $this->getFieldValue($instance->field, $instance->container_id);
        $custumfieldString = "{$fieldValue->field_type}/{$fieldValue->field_id}";
        $viewHtml          = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if ($fieldValue === null) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_ERROR', $link->url, $custumfieldString, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }

        $this->parserInstance = $instance->parser;
        $this->newUrl         = $newUrl;
        $this->oldUrl         = $link->url;

        $replacedValue = $this->replaceCustomField($fieldValue);

        if ($replacedValue) {
            if (!\is_string($replacedValue)) {
                $replacedValue = json_encode($replacedValue);
            }
            if ($replacedValue != $fieldValue->field_value) {
                $fieldModel->setFieldValue($instance->field, $instance->container_id, $replacedValue);
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_SUCCESS', $link->url, $newUrl, $custumfieldString, $viewHtml),
                    'succcess'
                );
                $this->parseContainer($instance->container_id);
            }
        } else {
            if ($replacedValue === null) {
                Factory::getApplication()->enqueueMessage(
                    // phpcs:disable Generic.Files.LineLength
                    Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_ERROR', $link->url, $custumfieldString, $viewHtml, Text::_('PLG_BLC_ANY_REPLAC_NOT_IMPLEMENTEDE_ERROR')),
                    // phpcs:enable Generic.Files.LineLength
                    'warning'
                );
            } else {
                $this->purgeInstance($instance->instance_id);
            }
        }
    }

    protected function replaceCustomField($row)
    {

        $fieldValue = false;

        switch ($row->field_type) {
            case 'url':
                if ($row->field_value == $this->oldUrl) {
                    $fieldValue = $this->newUrl;
                }

                break;
            case 'editor':
            case 'textarea':
            case 'text':
                $text =  $row->field_value;
                if (strpos($text, '<') !== false) {
                    $textParsers =  BlcParsers::getInstance();
                    $fieldValue  = $textParsers->replaceLinksParser(
                        $this->parserInstance,
                        $text,
                        $this->oldUrl,
                        $this->newUrl
                    );
                }
                break;
            case 'mediajce':
            case 'media':
                if (\is_string($row->field_value)) {
                    $image = json_decode($row->field_value);
                    if ($image->imagefile == $this->oldUrl) {
                        $image->imagefile = $this->newUrl;
                    }
                } else {
                    if ($row->field_value == $this->oldUrl) {
                        $fieldValue = $this->newUrl;
                    }
                }
                break;
            case 'subform':
                $fieldValue = $this->replaceSubForm($row->field_value);
                break;
        }

        $field_id = $row->field_id;
        if (\in_array($field_id, $this->extraUrlIds)) {
            if ($row->field_value == $this->oldUrl) {
                $fieldValue = $this->newUrl;
            }
        }
        return $fieldValue;
    }

    protected function parseContainerFields($rows): void
    {

        $id         = $rows[0]->id ?? 0; // TODO bail out
        $synchTable = $this->getItemSynch($id);
        $synchId    = $synchTable->id;
        $this->purgeInstances($synchId);
        foreach ($rows as $row) {
            // a subform might contain serveral fields
            $this->contentFields = [];
            $this->contentLinks  = [];
            $this->parseCustomField($row);
            if ($this->contentLinks) {
                $this->processLinks($this->contentLinks, $row->field_id, $synchId);
            }
            if ($this->contentFields) {
                $this->processText(join('', $this->contentFields), $row->field_id, $synchId);
            }
        }
        $synchTable->setSynched();
    }
}
