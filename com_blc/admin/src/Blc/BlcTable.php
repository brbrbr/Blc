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

class BlcTable extends Table
{
    public function getTypeAlias()
    {
        return $this->typeAlias;
    }

    public function save($src = [], $orderingFilter = '', $ignore = '')
    {
        try {
            if ($src) {
                // Attempt to bind the source to the instance.
                if (!$this->bind($src, $ignore)) {
                    throw new \RuntimeException("Bind of item {$this->id} in table {$this->_tbl} Failed");
                }
            }

            // Run any sanity checks on the instance and verify that it is ready for storage.
            if (!$this->check()) {
                throw new \RuntimeException("Check of item {$this->id} in table {$this->_tbl} Failed");
            }

            // Attempt to store the properties to the database table.
            if (!$this->store()) {
                throw new \RuntimeException("Store of item {$this->id} in table {$this->_tbl} Failed");
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }

        return true;
    }

    public function reset()
    {
        foreach ($this->getFields() as $k => $v) {
            // If the property is not the primary key or private, reset it.
            if (!\in_array($k, $this->_tbl_keys) && (strpos($k, '_') !== 0)) {
                $this->$k = null;
            }
        }
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
            if (parent::delete($pk)) {
                $pkString = json_encode($pk);
                throw new \RuntimeException("Delete of item '{$pkString}' in table {$this->_tbl} Failed");
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
        return true;
    }
}
