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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Utilities\ArrayHelper;


trait CustomFieldsTrait
{
    private $contentFields          = [];
    private $contentLinks           = [];
    private $parseAllowedFields          = [];
    private $replaceAllowedFields          = [];
    private $extraUrlIds            = [];
    private $fieldToType            = null;
    private $newUrl                 = null;
    private $oldUrl                 = null;
    private $parserInstance         = null;
    protected string $fieldContext  = '';
    protected string $splitOption   = "#(;|,|\r\n|\n|\r)#";


    public function __construct()
    {
        /**
         *
         * @since 24.44.6752
         */
      
         
        if (!$this->params->get('enablecf')) {
            return;
        }
        $this->fieldContext = $this->fieldContext ?: $this->context;
        $defaultFields = ['text' => 0, 'textarea' => 0, 'editor' => 1, 'url' => 1, 'media' => 1, 'mediajce' => 0, 'subform' => 0];

        $cf = $this->params->get('cf', new \stdClass());
       

        foreach ($defaultFields as $field => $default) {
            $setting = $cf->$field ?? $default;
            if ($setting) {
                $this->parseAllowedFields[] = $field;
                if ($setting == 2) {
                    $this->replaceAllowedFields[] = $field;
                }
            }
        }
    
        $this->extraUrlIds = ArrayHelper::toInteger(
            \is_array($cf->extraurl??[]) ?  $cf->extraurl??[] :
                array_filter(
                    preg_split(
                        $this->splitOption,
                        $cf->extraurl ?? ''
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
        if (!$this->params->get('enablecf')) {
            return;
        }
        $this->loadFieldToType();
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

        $rawValue =  $row->rawvalue;
        if (! $rawValue) {
            //nothing to do
            return;
        }


        $id = $row->id;
        if (\in_array($id, $this->extraUrlIds)) {
            $row->type = 'url';
        }

        $type = $row->type;

        if (!\in_array($type, $this->parseAllowedFields)) {
            return;
        }
        $title = $this->fieldToType[$row->id]->title??null;
        switch ($type) {
            case 'url':
                //the parser would take care of empty url's however we might want to show empty a and img tags later
                $this->contentLinks[] = ['url' => $rawValue, 'anchor' => $title??'URL Custom Field'];
                break;
            case 'editor':
            case 'textarea':
            case 'text':
                if (strpos($rawValue, '<') !== false) {
                    $this->contentFields[] = $rawValue;
                }
                break;
            case 'mediajce':
                $image_url = '';
                $fieldValue = $this->itMightBeAJsonField($rawValue);
                if (\is_string($fieldValue)) {
                    $image_url = $fieldValue;
                    $image_alt =  $title??'No Alt text';
                } else {
                    $image_url = $fieldValue->media_src  ?? '';
                    $image_alt = !empty(trim($fieldValue->media_text ?? '')) ? $fieldValue->media_text :  $title??'No Alt text'; //old format
                }

                if ($image_url) {
                    $this->contentLinks[] = ['url' => $image_url, 'anchor' => $image_alt];
                }
                break;

            case 'media':
                $image_url = '';
              
                $fieldValue = $this->itMightBeAJsonField($rawValue);
                if (\is_string($fieldValue)) {
                    $image_url = $fieldValue;
                    $image_alt =  $title??'No Alt text';
                } else {
                    $image_url = $fieldValue->imagefile  ?? '';
                    $image_alt = !empty(trim($fieldValue->alt_text ?? '')) ? $fieldValue->alt_text : $title??'No Alt text'; //old format
                }

                if ($image_url) {
                    $this->contentLinks[] = ['url' => $image_url, 'anchor' => $image_alt];
                }
                break;
            case 'subform':
                $this->parseSubForm($rawValue);
                break;
            default:
                Log::add(
                    \sprintf('Unknown custom field type %s', $type),
                    Log::DEBUG
                );
        }
    }

    protected function parseSubForm(object|string $subform)
    {
     

        if (\is_string($subform)) {
            $subform = json_decode($subform);
        }

        foreach ($subform as $key => &$field) {
            if (preg_match('#^row[0-9]?$#', $key)) {
                $this->parseSubForm($field);
            } else {
                $id = (int)preg_replace('#^field#', '', $key);
                if (isset($this->fieldToType[$id])) {
                    $row              = new \StdClass();
                    $row->type        = $this->fieldToType[$id]->type;
                    $row->rawvalue    = $field;
                    $row->id          = $id;
                    $this->parseCustomField($row);
                }
            }
        }
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
            $query->select($db->quoteName(['id', 'type', 'title']))
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

       
        if (\is_string($subform)) {
            $subform = json_decode($subform);
        }
        if (! $subform) {
            return new \StdClass();
        }
        foreach ($subform as $key => &$field) {
            if (preg_match('#^row[0-9]?$#', $key)) {
                $field = $this->replaceSubForm($field);
            } else {
                $id = (int)preg_replace('#^field#', '', $key);
                if (isset($this->fieldToType[$id])) {
                    $row              = $this->fieldToType[$id];
                    $row->rawvalue       = $field;
                    $row->id          = $id;
                    $ret              =  $this->replaceCustomField($row);
                    if ($ret) {
                        $field = $ret;
                    }
                }
            }
        }

        return $subform;
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
     * @since __DEPLOY_VERSION__
     */

    public function setURLS(
        string $oldUrl,
        string $newUrl,
    ) {
        $this->newUrl         = $newUrl;
        $this->oldUrl         = $oldUrl;
    }
    /**
     *
     *
     * @since 24.44.6752
     */
    public function replaceCustomFieldLink(
        string $oldUrl,
        string $newUrl,
        Table $item, //master table of ArticleTable CategoryTable and more
        object $instance,
    ): bool {
        $this->loadFieldToType();
        $this->setURLS($oldUrl, $newUrl);
        $this->replacedUrls[] =  $this->newUrl;


        if (!$this->params->get('enablecf')) {
            return false;
        }
        $messageLinks = $this->getMessageLinks($instance);
        $this->parserInstance = $instance->parser;
        $this->newUrl         = $newUrl;
        $this->oldUrl         = $oldUrl;
        $rows                 = FieldsHelper::getFields($this->fieldContext, $item);
        $reparse              = false;
        $fieldModel           = $this->getFieldModel();

        foreach ($rows as $row) {

            $replacedValue = $this->replaceCustomField($row);

            if ($replacedValue) {
                if (!\is_string($replacedValue)) {
                    $replacedValue = json_encode($replacedValue);
                }

                if ($replacedValue != $row->rawvalue) {
                    $this->replacedUrls[] = $newUrl;
                    $custumfieldString    = "{$row->title} (id:{$row->id})";
                    if ($fieldModel->setFieldValue($row->id, $item->id, $replacedValue)) {
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_SUCCESS', $oldUrl, $newUrl, $custumfieldString, $messageLinks),
                            'succcess'
                        );
                        $reparse = true;
                    } else {
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf('PLG_BLC_ANY_REPLACE_CUSTOM_FIELD_ERROR', $oldUrl, $custumfieldString, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                            'warning'
                        );
                        return false;
                    }
                }
            }
        }

        return $reparse;
    }
    private function replaceNotAllowed($type)
    {
        $typeLbl = Text::_(strtoupper("PLG_SYSTEM_BLC_FIELD_{$type}_LBL"));
        $configLink = Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . $this->extension_id);
        Factory::getApplication()->enqueueMessage(
            Text::sprintf('PLG_SYSTEM_BLC_MESSAGE_REPLACING_NOT_ENABLED', $typeLbl, $configLink),
            'warning'
        );
    }

    protected function replaceCustomField($row)
    {

        $rawValue =  $row->rawvalue ?? '';
        if (! $rawValue) {
            //nothing to do
            return;
        }

        $fieldValue = false;


        $id = $row->id;
        if (\in_array($id, $this->extraUrlIds)) {
            $row->type = 'url';
        }

        $type = $row->type;
        if (!\in_array($type, $this->replaceAllowedFields)) {
            $this->replaceNotAllowed($type);
            return;
        }

        switch ($type) {
            case 'url':
                if ($rawValue == $this->oldUrl) {
                    $fieldValue = $this->newUrl;
                }
                break;
            case 'editor':
            case 'textarea':
            case 'text':
                if (strpos($rawValue, '<') !== false) {
                    $textParsers =  BlcParsers::getInstance();
                    $fieldValue  = $textParsers->replaceLinksParser(
                        $this->parserInstance,
                        $rawValue,
                        $this->oldUrl,
                        $this->newUrl
                    );
                }
                break;
            case 'mediajce':
                //can be stored as string or object depending on versiopn and settings
                $fieldValue = $this->itMightBeAJsonField($rawValue);
                if (\is_string($fieldValue)) {
                    if ($fieldValue == $this->oldUrl) {
                        $fieldValue = $this->newUrl;
                    }
                } else {
                    if ($fieldValue->media_src == $this->oldUrl) {
                        $fieldValue->media_src = $this->newUrl;
                    }
                }

                break;

            case 'media':
                //can be stored as string or object depending on versiopn
                $fieldValue = $this->itMightBeAJsonField($rawValue);
                if (\is_string($fieldValue)) {
                    if ($fieldValue == $this->oldUrl) {
                        $fieldValue = $this->newUrl;
                    }
                } else {
                    if ($fieldValue->imagefile == $this->oldUrl) {

                        $fieldValue->imagefile = $this->newUrl;
                    }
                }

                break;
            case 'subform':
                $fieldValue = $this->replaceSubForm($rawValue);
                break;
        }



        return $fieldValue;
    }

    /**
     * 
     * @since __DEPLOY_VERSION__
     */
    private function itMightBeAJsonField($value)
    {

        if (\is_object($value)) {
            return $value;
        }
        try {
            //the try is probably not needed since jon_decode does not throw
            $object = json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $object;
            }
        } catch (\Error) {
        }
        //should be a string now.
        return $value;
    }
}
