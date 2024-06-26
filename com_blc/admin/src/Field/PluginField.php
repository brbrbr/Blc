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
use Joomla\Database\DatabaseInterface;

/**
 * get destination internal / external
 *
 * @since   24.44.dev
 */
class PluginField extends FilterField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since   24.44.dev
     */
    protected $type = 'plugin';

    /**
     * The translate.
     *
     * @var    boolean
     * @since  1.0.1
     */
    protected $translate = false;
    protected $header    = false;

    protected $column = 'plugin';

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
            ->select('`s`.`plugin_name` `value`')
            ->select('count(DISTINCT `a`.`id`) `c`')
            ->leftJoin('`#__blc_instances` `i`', '`i`.`link_id` = `a`.`id`')
            ->leftJoin('`#__blc_synch` `s`', '`i`.`synch_id` = `s`.`id`')
            ->where('`s`.`plugin_name` != "_Transient"')
            ->group('`plugin_name`')
            ->order('`plugin_name` ASC');


        //   ->select('SUM(CASE WHEN `internal_url` = ""  then 1 else 0 end) as `external`')
        //   ->select('SUM(CASE WHEN `internal_url` != ""  then 1 else 0 end) as `internal`');
        $this->getModel()->addToquery($query, ['plugin']);

        return $query;
    }
}
