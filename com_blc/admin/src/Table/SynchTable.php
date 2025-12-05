<?php

declare(strict_types=1);

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
use Joomla\Event\DispatcherInterface;

class SynchTable extends BlcTable
{
    // phpcs:disable PSR2.Classes.PropertyDeclaration

    protected $_db               = null;
    protected $_jsonEncode       = ['data'];

    // phpcs:enable PSR2.Classes.PropertyDeclaration
    /**
     * @var    int
     * @since  23.11.0
     */
    public int $id                    = 0;

    /**
     * @var    string
     * @since  23.11.0
     */
    public string $plugin_name = '';
    /**
     * @var    int
     * @since  23.11.0
     */
    public int $container_id = 0;
    /**
     * @var    int
     * @since  23.11.0
     */
    public int $synched = 0;
    /**
     * @var    string
     * @since  23.11.0
     */
    public string $last_synch = '0000-00-00 00:00:00';
    public $data              = '[]';


    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_blc.synch';
        parent::__construct('#__blc_synch', ['id','plugin_name', 'container_id'], $db, $dispatcher);
    }

    public function setSynched($src = [])
    {
        $this->synched    = 1;

        $this->last_synch = Factory::getDate()->toSql();
        $this->save($src);
    }

    public function reset()
    {

        $this->id                    = 0;
        $this->plugin_name           = '';
        $this->container_id          = 0;
        $this->last_synch            = $this->getDatabase()->getNullDate();
        $this->synched               = 0;
        $this->data                  = '[]';
        parent::reset(); //takes care of jsonencode
    }
}
