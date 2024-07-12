<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\CfCategory\Extension;

use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\FieldAwareTrait;
use Blc\Plugin\Blc\Category\Extension\BlcPluginActor as BlcCategoryActor;
use Joomla\Database\DatabaseQuery;

use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcCategoryActor
{
    use FieldAwareTrait {
        FieldAwareTrait::__construct as private __faConstruct;
    }

    use BlcHelpTrait;

    private const  HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-cfcategory';

    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */


    protected $context      = 'com_categories.category';
    protected $fieldContext = 'com_content.categories';



    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {

        parent::__construct($dispatcher, $config);
        $this->__faConstruct();
    }
    //spot the difference with cfcontent
    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {

        $query = $this->baseFieldQuery($idOnly);
        $this->extraFieldQuery($query);
        //This ensures that only fields with the correct category are loaded.
        //joomla does not clear fields when the categorie(s) of a field change.
        $wheres =
            [
                // phpcs:disable Generic.Files.LineLength
                "EXISTS (SELECT * FROM `#__fields_categories` `fc` WHERE `fc`.`category_id` = `a`.`id` AND `fc`.`field_id` = `f`.`id`)", //SPECIFIED
                "NOT EXISTS (SELECT * FROM `#__fields_categories` `fc` WHERE  `fc`.`field_id` = `f`.`id`)", //ALL
                // phpcs:enable Generic.Files.LineLength
            ];
        $query->extendWhere('AND', $wheres, 'OR');
        return $query;
    }
}
