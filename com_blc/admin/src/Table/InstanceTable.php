<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;

class InstanceTable extends BlcTable
{
    // phpcs:disable PSR2.Classes.PropertyDeclaration
    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;
    protected $_db               = null;
    protected $_jsonEncode       = ['data'];
    // phpcs:enable PSR2.Classes.PropertyDeclaration
    /**
     * @var    int
     * @since  23.11.0
     */
    public $id;
    /**
     * @var    int
     * @since  23.11.0
     */
    public $link_id;
    /**
     * @var    int
     * @since  23.11.0
     */
    public $synch_id;
    /**
     * @var    string
     * @since  23.11.0
     */
    public $field;
    /**
     * @var    string
     * @since  23.11.0
     */
    public $link_text;
    /**
     * @var    string
     * @since  23.11.0
     */
    public $parser;
    public $data = '[]';


    public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_blc.instances';
        parent::__construct('#__blc_instances', 'id', $db, $dispatcher);
        $this->_db = $db;
    }

    public function store($updateNulls = false)
    {
        $this->link_text = mb_substr($this->link_text, 0, 512); //Joomla has polyfill
        return parent::store($updateNulls); // BlcTable wil throw the exeption
    }
}
