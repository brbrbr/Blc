<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Component\Blc\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;

class InstanceTable extends BlcTable
{
    //phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
    protected $_jsonEncode       = ['data'];
    // phpcs:enable PSR2.Classes.PropertyDeclaration.Underscorecat .
    /**
     * @var    int
     * @since  23.11.0
     */
    public int $id = 0;
    /**
     * @var    int
     * @since  23.11.0
     */
    public int $link_id = 0;
    /**
     * @var    int
     * @since  23.11.0
     */
    public int $synch_id = 0;
    /**
     * @var    string
     * @since  23.11.0
     */
    public string $field = '';
    /**
     * @var    string
     * @since  23.11.0
     */
    public string $link_text = '';
    /**
     * @var    string
     * @since  23.11.0
     */
    public string $parser = '';
    public $data          = '[]';


    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_blc.instances';
        parent::__construct('#__blc_instances', 'id', $db, $dispatcher);
    }

    public function store($updateNulls = false)
    {
        $this->link_text = mb_substr($this->link_text, 0, 512); //Joomla has polyfill
        return parent::store($updateNulls); // BlcTable will throw the exception
    }
    public function reset()
    {
        $this->id        = 0;
        $this->link_id   = 0;
        $this->synch_id  = 0;
        $this->field     = '';
        $this->link_text = '';
        $this->parser    = '';

        parent::reset(); //takes care of the json fields
    }
    
}
