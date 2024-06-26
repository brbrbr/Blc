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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Supports a value from an external table
 *
 * @since  1.0.1
 */
class SpecialField extends FilterField
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

    //this gives an order as well
    protected $fields    = [
        "broken"   => "COM_BLC_OPTION_WITH_BROKEN",
        "warning"  => "COM_BLC_OPTION_WITH_WARNING",
        "redirect" => "COM_BLC_OPTION_WITH_REDIRECT",
        "internal" => "COM_BLC_OPTION_WITH_INTERNAL_MISMATCH",
        "timeout"  => "COM_BLC_OPTION_WITH_TIMEOUT",
        "tocheck"  => "COM_BLC_OPTION_WITH_TOCHECK",
        "parked"  => "COM_BLC_OPTION_WITH_PARKED",
    ];




    /**
     *
     *
     * @var    string
     * @since   24.44.dev
     */
    protected $column = 'special';



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
        $query =  $db->getQuery(true);
        $query->from('`#__blc_links` `a`')
            ->select('SUM(CASE WHEN `broken` = ' . HTTPCODES::BLC_BROKEN_TIMEOUT . ' then 1 else 0 end) as `timeout`')
            ->select('SUM(CASE WHEN `broken` = ' . HTTPCODES::BLC_BROKEN_TRUE . ' then 1 else 0 end) as `broken`')
            ->select("SUM(CASE WHEN `redirect_count` > 0 then 1 else 0 end) as `redirect`")
            ->select('SUM(CASE WHEN `broken` = ' . HTTPCODES::BLC_BROKEN_WARNING . ' then 1 else 0 end) as `warning`')
            ->select('SUM(CASE WHEN `internal_url` != "" AND  `internal_url` != `url` then 1 else 0 end) as `internal`')
            ->select('SUM(CASE WHEN `being_checked` = ' . HTTPCODES::BLC_CHECKSTATE_TOCHECK . ' then 1 else 0 end) as `tocheck`')
            ->select('SUM(CASE WHEN `parked` = ' . HTTPCODES::BLC_PARKED_PARKED . ' then 1 else 0 end) as `parked`');
        $this->getModel()->addToquery($query, ['special']);

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

        //use fields for order
        foreach ($this->fields as $key => $string) {
            if (($sums->$key ?? 0) > 0) {
                $options[] = HTMLHelper::_('select.option', $key, Text::_($string) . ' - ' . $sums->$key);
            }
        }

        $value = (string)$this->element->xpath('option')[0]['value'] ?? '';
        //   if ($options) {
        $set  = ($this->value != $value);
        $text = Text::_('COM_BLC_OPTION_' . strtoupper($this->column) . '_' .  ($set ? 'CLEAR' : 'FILTER'));
        //   } else {
        //       $text = Text::_('COM_BLC_OPTION_' . strtoupper($this->column) . '_' . 'FILTER');
        //    }
        array_unshift($options, HTMLHelper::_('select.option', $value, $text));

    

        // Merge any additional options in the XML definition.
        //  $options = array_merge(parent::getOptions(), $options);

        return $options;
    }
}
