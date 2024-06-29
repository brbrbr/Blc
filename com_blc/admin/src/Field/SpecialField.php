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
        "parked"   => "COM_BLC_OPTION_WITH_PARKED",
    //   "all"   => "COM_BLC_OPTION_WITH_ALL",
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
        return $this->getFieldOptions();
    }
}
