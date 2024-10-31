<?php

/**
 * @version   24.44.6752
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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Component\Content\Administrator\Table\ArticleTable;

trait CustomFieldsTrait
{
    private $contentFields          = [];
    private $contentLinks           = [];
    private $allowedFields          = [];
    private $extraUrlIds            = [];
    private $fieldToType            = null;
    private $newUrl                 = null;
    private $oldUrl                 = null;
    private $parserInstance         = null;
    protected string $fieldContext = '';
    protected string $splitOption   = "#(;|,|\r\n|\n|\r)#";

    public function __construct()
    {
        /**
         * 
         * @since 24.44.6752
         */
        $this->fieldContext = $this->fieldContext ?: $this->context;
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
    /**
     * 
     * @since 24.44.6752
     */
    protected function parseCustomFields($item, $synchId)
    {

        $rows = FieldsHelper::getFields($this->fieldContext, $item);
        //collect all fields in a single instance
        $this->contentFields = [];
        $this->contentLinks  = [];
        foreach ($rows as $row) {
            $this->parseCustomField($row);
        }
        if ($this->contentLinks) {
            //intentialy not translatable
            $this->processLinks($this->contentLinks, 'Fields', $synchId);
        }
        if ($this->contentFields) {
            //intentialy not translatable
            $this->processText(join('', $this->contentFields), 'Fields', $synchId);
        }
    }
    /**
     * 
     * @since 24.44.6752
     */

    protected function parseCustomField($row)
    {
        if (empty($row->rawvalue)) {
            return;
        }
        $rawvalue =  $row->rawvalue;
        switch ($row->type) {
            case 'url':
                //the parser would take care of empty url's however we might want to show empty a and img tags later

                $this->contentLinks[] = ['url' => $rawvalue, 'anchor' => 'URL Custom Field'];

                break;
            case 'editor':
            case 'textarea':
            case 'text':

                if (strpos($rawvalue, '<') !== false) {
                    $this->contentFields[] = $rawvalue;
                }
                break;
            case 'mediajce':
            case 'media':
                if (\is_string($rawvalue)) {
                    $image = json_decode($rawvalue);
                } else {
                    $image = $rawvalue;
                }
                $image_url = $image->imagefile ?? $image ?? ''; //old format
                $image_alt = !empty(trim($image->alt_text ?? '')) ? "{$image->alt_text}" : 'No Alt text'; //old format
                if ($image_url) {
                    $this->contentLinks[] = ['url' => $image_url, 'anchor' => $image_alt];
                }
                break;
            case 'subform':
                $this->parseSubForm($rawvalue);
                break;
        }

        $id = $row->id;
        if (\in_array($id, $this->extraUrlIds)) {
            $this->contentLinks[] = ['url' => $rawvalue, 'anchor' => 'URL Custom Field'];
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
                $id = (int)preg_replace('#^field#', '', $key);
                if (isset($this->fieldToType[$id])) {
                    $row              = new \StdClass();
                    $row->type  = $this->fieldToType[$id]->type;
                    $row->rawvalue = $field;
                    $row->id    = $id;
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
                ->where($db->quoteName('context') . '= :context')
                ->bind(':context', $this->fieldContext)
                ->from($db->quoteName('#__fields', 'f'));

            $db->setQuery($query);
            $this->fieldToType = $db->loadObjectList('id');
        }
    }
    /**
     *  @since 24.44.6752
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
                $id = (int)preg_replace('#^field#', '', $key);
                if (isset($this->fieldToType[$id])) {
                    $row              = new \StdClass();
                    $row->type  = $this->fieldToType[$id]->type;
                    $row->value = $field;
                    $row->id    = $id;
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


    protected function getFieldModel()
    {
        $app        = Factory::getApplication();
        $mvcFactory = $app->bootComponent('com_fields')->getMVCFactory();
        return $mvcFactory->createModel('Field', 'Administrator', ['ignore_request' => true]);
    }
    /**
     * 
     * 
     * @since 24.44.6752
     */
    public function replaceCustomFieldLink(
        string $oldUrl,
        string $newUrl,
        ArticleTable $item,
        object $instance,

    ): bool {
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        $this->parserInstance = $instance->parser;
        $this->newUrl         = $newUrl;
        $this->oldUrl         = $oldUrl;
        $rows = FieldsHelper::getFields($this->fieldContext, $item);
        $reparse = false;
        $fieldModel        = $this->getFieldModel();
        foreach ($rows as $row) {
            $replacedValue = $this->replaceCustomField($row);
            if ($replacedValue) {
                if (!\is_string($replacedValue)) {
                    $replacedValue = json_encode($replacedValue);
                }
                if ($replacedValue != $row->value) {
                    $this->replacedUrls[] = $newUrl;
                    $custumfieldString = "{$row->title} (id:{$row->id})";
                    if ($fieldModel->setFieldValue($row->id, $item->id, $replacedValue)) {
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_SUCCESS', $oldUrl, $newUrl, $custumfieldString, $viewHtml),
                            'succcess'
                        );
                        $reparse = true;
                    } else {
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_ERROR', $oldUrl, $custumfieldString, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                            'warning'
                        );
                        return false;
                    }
                }
            }
        }
        return $reparse;
    }

    protected function replaceCustomField($row)
    {

        $fieldValue = false;

        switch ($row->type) {
            case 'url':
                if ($row->value == $this->oldUrl) {
                    $fieldValue = $this->newUrl;
                }

                break;
            case 'editor':
            case 'textarea':
            case 'text':
                $text =  $row->value;
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
                if (\is_string($row->value)) {
                    $image = json_decode($row->value);
                    if ($image->imagefile == $this->oldUrl) {
                        $image->imagefile = $this->newUrl;
                    }
                } else {
                    if ($row->value == $this->oldUrl) {
                        $fieldValue = $this->newUrl;
                    }
                }
                break;
            case 'subform':
                $fieldValue = $this->replaceSubForm($row->value);
                break;
        }

        $id = $row->id;
        if (\in_array($id, $this->extraUrlIds)) {
            if ($row->value == $this->oldUrl) {
                $fieldValue = $this->newUrl;
            }
        }
        return $fieldValue;
    }
}
