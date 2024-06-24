<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\CfContent\Extension;

use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\FieldAwareTrait;
use Blc\Plugin\Blc\Content\Extension\BlcPluginActor as BlcContentActor;
use Joomla\Database\Mysqli\MysqliQuery;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcContentActor
{
    use FieldAwareTrait {
        FieldAwareTrait::__construct as private __faConstruct;
    }


    use BlcHelpTrait;

    private const  HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-cfcontent';

    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */


    protected $context      = 'com_content.article';
    protected $fieldContext = 'com_content.article';


    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->__faConstruct();
    }

    protected function getQuery(bool $idOnly = false): MysqliQuery
    {
        $query = $this->baseFieldQuery($idOnly);
        $this->extraFieldQuery($query);
        //This ensures that only fields with the correct category are loaded.
        //joomla does not clear fields when the categorie(s) of a field change.
        $wheres =
            [
                //SPECIFIED
                // phpcs:disable Generic.Files.LineLength
                "EXISTS (SELECT * FROM `#__fields_categories` `fc` WHERE `fc`.`category_id` = `a`.`catid` AND `fc`.`field_id` = `f`.`id`)",
                // phpcs:enable Generic.Files.LineLength
                //ALL
                "NOT EXISTS (SELECT * FROM `#__fields_categories` `fc` WHERE  `fc`.`field_id` = `f`.`id`)",

            ];
        $query->extendWhere('AND', $wheres, 'OR');
        return $query;
    }
}
