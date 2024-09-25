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
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

class SynchTable extends BlcTable
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
     * @var    string
     * @since  23.11.0
     */
    public $plugin_name;
    /**
     * @var    int
     * @since  23.11.0
     */
    public $container_id;
    /**
     * @var    int
     * @since  23.11.0
     */
    public $synched;
    /**
     * @var    string
     * @since  23.11.0
     */
    public $last_synch;
    public $data         = [];
    // phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
    protected $_tbl_keys = ['id','plugin_name', 'container_id'];
    // phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore



    /**
     * Constructor
     *
     * @param   DatabaseDriver  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_blc.synch';
        parent::__construct('#__blc_synch', 'id', $db);
        $this->_db = $db;
    }

    public function setSynched($src = [])
    {
        $this->synched    = 1;
        $this->last_synch = Factory::getDate()->toSql();
        $this->save($src);
    }
}
