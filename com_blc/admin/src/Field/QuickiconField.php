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

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Supports a value from an external table
 *
 * @since  1.0.1
 */
class QuickiconField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.1
     */
    protected $type = 'response';


    /**
     * The translate.
     *
     * @var    boolean
     * @since  1.0.1
     */
    protected $translate = true;
    protected $header    = false;



    /**
     * Method to get the field input markup.
     *
     * @return  array  The field input markup.
     *
     * @since   1.0.1
     */
    protected function processQuery(): array
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query =  $db->getQuery(true);
        $query->from($db->quoteName('#__modules'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . '  = 1')
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_quickicon'))
            ->order($db->quoteName('value') . ' ASC')

            ->select(BlcHelper::jsonExtract('params', 'context', 'value'));



        return $db->setQuery($query)->loadObjectList();
    }


    public function getOptions()
    {

        $this->form->setValue('name', 'group', 'value');
        $contexts  = $this->processQuery();
        $options   = [];
        $options[] = HTMLHelper::_('select.option', 0, Text::_('JOff'));
        foreach ($contexts as $context) {
            // Add an option to the module group
            $value                  = $context->value;
            $text                   = ucwords(str_replace('_', ' ', $value));
            $options[]              = HTMLHelper::_('select.option', $value, $text);
        }
        return $options;
    }
    //ughh
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        if ($data['value'] == 1) {
            $data['value'] = 'system_quickicon';
        }
        return $data;
    }
}
