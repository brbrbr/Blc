<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as Table;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

class BlcTable extends Table implements DatabaseAwareInterface
{
    // phpcs:disable PSR2.Classes.PropertyDeclaration
    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    use DatabaseAwareTrait;

    protected $_supportNullValue = false;
    public function __construct($table, $key, DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct($table, $key, $db, $dispatcher);
        //this is not needed in version 5.4 PR45165
        if (version_compare(JVERSION, '5.4', '<')) {
            $this->setDatabase($db);
        }
    }

    public function save($src = [], $orderingFilter = '', $ignore = '')
    {
        try {
            if ($src) {
                // Attempt to bind the source to the instance.
                //bind always returns true or Thows
                $this->bind($src, $ignore);
            }

            // Run any sanity checks on the instance and verify that it is ready for storage.
            //check always returns true
            $this->check();


            // Attempt to store the properties to the database table.
            if (!$this->store()) {
                //@codeCoverageIgnoreStart
                throw new \RuntimeException("Store of item {$this->id} in table {$this->_tbl} Failed" . $this->getError());
                //@codeCoverageIgnoreEnd
            }
            //@codeCoverageIgnoreStart
        } catch (\Exception $e) {
            throw new \RuntimeException("Save of item {$this->id} in table {$this->_tbl} Failed: " . $e->getMessage());
            //@codeCoverageIgnoreEnd
        }

        return true;
    }

    public function reset()
    {

        if (!empty($this->_jsonEncode)) {
            foreach ($this->_jsonEncode as $field) {
                $this->$field = '[]';
            }
        }
    }

    public function delete($pk = null)
    {

        try {
            $this->load($pk);
            //item not found
            if (! $this->id) {
                return false;
            }
            if (!parent::delete($pk)) {
                // @codeCoverageIgnoreStart
                $pkString = json_encode($pk);
                throw new \RuntimeException("Delete of item '{$pkString}' in table {$this->_tbl} Failed");
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
        // @codeCoverageIgnoreEnd
        return true;
    }
}
