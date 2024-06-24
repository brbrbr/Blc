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

use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
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
class SpecialField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.1
     */
    protected $type = 'special';

    /**
     * The translate.
     *
     * @var    boolean
     * @since  1.0.1
     */
    protected $translate = false;
    protected $header    = false;
    protected $fields    = [
        "broken"   => "COM_BLC_OPTION_WITH_BROKEN",
        "warning"  => "COM_BLC_OPTION_WITH_WARNING",
        "redirect" => "COM_BLC_OPTION_WITH_REDIRECT",
        "internal" => "COM_BLC_OPTION_WITH_INTERNAL_MISMATCH",
        "timeout"  => "COM_BLC_OPTION_WITH_TIMEOUT",
    ];

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   1.0.1
     */
    protected function processQuery()
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);

        $instanceQuery = $db->getQuery(true);
        $instanceQuery->select('*')
            ->from('`#__blc_instances` `i`')
            ->where('`l`.`id` = `i`.`link_id`');
        $query =  $db->getQuery(true);
        $query->from('`#__blc_links` `l`')
            ->select('SUM(CASE WHEN `broken` = ' . HTTPCODES::BLC_BROKEN_TIMEOUT . ' then 1 else 0 end) as `timeout`')
            ->select('SUM(CASE WHEN `broken` = ' . HTTPCODES::BLC_BROKEN_TRUE . ' then 1 else 0 end) as `broken`')
            ->select("SUM(CASE WHEN `redirect_count` > 0 then 1 else 0 end) as `redirect`")
            ->select('SUM(CASE WHEN `broken` = ' . HTTPCODES::BLC_BROKEN_WARNING . ' then 1 else 0 end) as `warning`')
            ->select('SUM(CASE WHEN `internal_url` != "" AND  `internal_url` != `url` then 1 else 0 end) as `internal`')
            ->where('EXISTS (' . $instanceQuery->__toString() . ')');
        return $query;
    }



    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     *
     * @since   1.0.1
     */
    protected function getOptions()
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery($this->processQuery());
        $sums      = $db->loadObject();
        $options   = [];
        $options[] = HTMLHelper::_('select.option', 'all', Text::_('COM_BLC_OPTION_SPECIAL_ALL'));
        foreach ($this->fields as $key => $string) {
            if (($sums->$key ?? 0) > 0) {
                $options[] = HTMLHelper::_('select.option', $key, Text::_($string) . ' - ' . $sums->$key);
            }
        }
        $options[] = HTMLHelper::_('select.option', 'pending', Text::_('COM_BLC_OPTION_PENDING'));
        $options[] = HTMLHelper::_('select.option', 'parked', Text::_('COM_BLC_OPTION_PARKEDDOMAINS'));

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
     */
    public function getAttribute($attr_name, $default = null)
    {
        if (!empty($this->element[$attr_name])) {
            return $this->element[$attr_name];
        }
        return $default;
    }
}
