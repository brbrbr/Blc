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
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Supports a value from an external table
 *
 * @since  1.0.1
 */
class ResponseField extends GroupedlistField
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
     *
     *
     * @var    string
     * @since   __DEPLOY_VERSION__
     */
    protected $column = 'response';

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
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   1.0.1
     */
    protected function processQuery()
    {

        $db            = Factory::getContainer()->get(DatabaseInterface::class);

        $query =  $db->getQuery(true);
        $query->from('`#__blc_links` `a`')
            ->select('`http_code` `value`')
            ->select('count(*) `c`')
            ->group('`value`')
            ->order('`value` ASC');

        $this->getModel()->addToquery($query, ['response']);

        return $query;
    }

    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     *
     * @since   1.0.1
     */
    protected function getGroups()
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery($this->processQuery());
        $singles = $db->loadObjectList();
        if ($singles) {
            array_walk($singles, function (&$single) use (&$grouped) {
                $single->text = BlcHelper::responseCode($single->value) . ' - ' . $single->c;
                $floored      = floor($single->value / 100);
                if (!isset($grouped[$floored])) {
                    $group             = new \Stdclass();
                    $group->value      = $floored;
                    $group->c          = $single->c;
                    $group->text       = BlcHelper::responseCode($floored)  . ' - ' . $group->c;
                    $grouped[$floored] = $group;
                } else {
                    $grouped[$floored]->c += $single->c;
                    $grouped[$floored]->text       = BlcHelper::responseCode($floored)  . ' - ' .  $grouped[$floored]->c;
                }
            });
            $groups = parent::getGroups();

            unset($grouped[0]);
            $groups['Range']   = $grouped;
            $groups['Code']    = $singles;
        }
        $value = (string)$this->element->xpath('option')[0]['value'] ?? '';
        if ($singles) {
            $set  = ($this->value != $value);
            $text = Text::_('COM_BLC_OPTION_' . strtoupper($this->column) . '_' .  ($set ? 'CLEAR' : 'FILTER'));
        } else {
            $text = Text::_('COM_BLC_OPTION_NOTHING_TO_SELECT');
        }
        unset($groups[0][0]);
        $groups[0] ??= [];
        array_unshift($groups[0], HTMLHelper::_('select.option', $value, $text));

        // Merge any additional options in the XML definition.
        // $options = array_merge(parent::getOptions(), $grouped,$options);
        return $groups;
    }

    /**
     * Wrapper method for getting attributes from the form element
     *
     * @param   string  $attr_name  Attribute name
     * @param   mixed   $default    Optional value to return if attribute not found
     *
     * @return  mixed The value of the attribute if it exists, null otherwise
     */
    public function getAttribute($attr_name, $default = null)
    {
        if (!empty($this->element[$attr_name])) {
            return $this->element[$attr_name];
        }
        return $default;
    }
}
