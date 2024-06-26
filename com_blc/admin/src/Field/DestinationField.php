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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * get destination internal / external
 *
 * @since   __DEPLOY_VERSION__
 */
class DestinationField extends FilterField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since   __DEPLOY_VERSION__
     */
    protected $type = 'destination';

    /**
     * The translate.
     *
     * @var    boolean
     * @since  1.0.1
     */
    protected $translate = false;
    protected $header    = false;
    protected $fields    = [
        "internal" => "COM_BLC_OPTION_INTERNAL",
        "external" => "COM_BLC_OPTION_EXTERNAL",
    ];
    protected $column = 'destination';


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
            ->select('SUM(CASE WHEN `internal_url` = ""  then 1 else 0 end) as `external`')
            ->select('SUM(CASE WHEN `internal_url` != ""  then 1 else 0 end) as `internal`');
        $this->getModel()->addToquery($query, ['destination']);
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
        foreach ($this->fields as $key => $string) {
            if (($sums->$key ?? 0) > 0) {
                $options[] = HTMLHelper::_('select.option', $key, Text::_($string) . ' - ' . $sums->$key);
            }
        }

        $default =(string)$this->element['default'] ?: '';
        if ($options) {
            //  if (count($options) > 1 ) {
            $set  = ($this->value != $default);
            $text = Text::_('COM_BLC_OPTION_' . strtoupper($this->column) . '_' .  ($set ? 'CLEAR' : 'FILTER'));
        } else {
           // $options=[];
            $text = Text::_('COM_BLC_OPTION_NOTHING_TO_SELECT');
        }
        array_unshift($options, HTMLHelper::_('select.option', $default, $text));


        return $options;
    }
}
