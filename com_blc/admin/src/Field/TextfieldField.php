<?php

/**
 * @version     24.44.6817
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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * get destination internal / external
 *
 * @since   24.44.dev
 */
class TextfieldField extends Listfield
{
    /**
     * The form field type.
     *
     * @var    string
     * @since   24.44.dev
     */
    protected $type = 'textfield';

    protected $layout = 'joomla.form.field.list-fancy-select';


    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   24.44.6817
     */
    protected function processQuery()
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query =  $db->getQuery(true);


        $query->from($db->quoteName('#__fields', 'a'))
            ->select($db->quoteName('a.id', 'value'))
            ->select($db->quoteName('a.title', 'text'))
            ->select($db->quoteName('a.type', 'type'))
            ->select($db->quoteName('a.context', 'context'))
            ->whereIn($db->quoteName('a.type'), ['text', 'textarea'], ParameterType::STRING)
            ->whereIn($db->quoteName('a.state'), [1, 2], ParameterType::INTEGER);

        return $query;
    }
    protected function getLayoutData()
    {
        if (\is_string($this->value)) {
            $this->value = explode(',', $this->value);
        }
        return parent::getLayoutData();
    }

    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     *
     * @since   24.44.6817
     */
    protected function getOptions()
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery($this->processQuery());
        $items       = $db->loadObjectList('value');
        $options     = [];
        foreach ($items as $item) {
            $value = $item->value;
            $text  = $item->text ?? $value;

            $options[] = HTMLHelper::_('select.option', $value, $text   . ' - ' . $item->context . ' (' . $item->type . ')');
        }
        // Merge any additional options in the XML definition.
        //  $options = array_merge(parent::getOptions(), $options);

        return $options;
    }
}
