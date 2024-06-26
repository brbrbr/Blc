<?php

/**
 * @version   24.44
 * @package    Com_Gvs
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * get destination internal / external
 *
 * @since   __DEPLOY_VERSION__
 */
class FilterField extends Listfield
{
    /**
     * The form field type.
     *
     * @var    string
     * @since   __DEPLOY_VERSION__
     */
    protected $type = 'list';

    /**
     * The translate.
     *
     * @var    boolean
     * @since  1.0.1
     */

    protected $translate = false;

    /**
     *
     *
     * @var    string
     * @since   __DEPLOY_VERSION__
     */
    protected $column = '';

    /**
     * quick helper to get the model.
     *
     * @return  object  The field input markup.
     *
     * @since   __DEPLOY_VERSION__
     */

    protected function getModel()
    {
        $app        = Factory::getApplication();
        $mvcFactory = $app->bootComponent('com_blc')->getMVCFactory();
        return $mvcFactory->createModel('Links', 'Administrator');
    }


    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function getOptions()
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery($this->processQuery());

        $items       = $db->loadObjectList();
        $transPrefix = "COM_BLC_OPTION_" . strtoupper($this->column ?? '') . '_';
        $options     = [];
        foreach ($items as $item) {
            $value = $item->value;
            $text  = $item->text ?? $value;
            if ($this->translate == true) {
                $text = Text::_($transPrefix . $text);
            }

            $options[] = HTMLHelper::_('select.option', $value, $text .   ' - ' . $item->c);
        }
        $value = (string)$this->element->xpath('option')[0]['value'] ?? '';
        if ($options) {
            $set  = ($this->value != $value);
            $text = Text::_('COM_BLC_OPTION_' . strtoupper($this->column) . '_' .  ($set ? 'CLEAR' : 'FILTER'));
        } else {
            $text = Text::_('COM_BLC_OPTION_NOTHING_TO_SELECT');
        }
        array_unshift($options, HTMLHelper::_('select.option', $value, $text));


        // Merge any additional options in the XML definition.
        //  $options = array_merge(parent::getOptions(), $options);

        return $options;
    }

    /**
     * Wrapper method for getting attributes from the form element
     *
     * @param   string  $attr_name  Attribute name
     * @param   mixed   $default    Optional value to return if attribute not found
     *
     * @return  mixed The value of the attribute if it exists, null otherwise
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getAttribute($attr_name, $default = null)
    {

        return $this->element[$attr_name] ?? $default;
    }

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value. This acts as an array container for the field.
     *                                       For example if the field has name="foo" and the group value is set to "bar" then the
     *                                       full field name would end up being "bar[foo]".
     *
     * @return  boolean  True on success.
     *
     * @see     FormField::setup()
     * @since   __DEPLOY_VERSION__
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $return = parent::setup($element, $value, $group);

        if ($return) {
            $this->translate  = (bool) $this->element['translate'] ?: $this->translate;
            $this->column     = (string)$this->element['column'] ?: $this->column;
        }

        return $return;
    }


    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function processQuery()
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query =  $db->getQuery(true);
        $query->from('`#__blc_links` `a`')
            ->select($query->quoteName($this->column, 'value'))
            ->select('count(*) `c`')
            ->group('`value`')
            ->order('`value` ASC');

        $this->getModel()->addToquery($query, [$this->column]);
        return $query;
    }
}
