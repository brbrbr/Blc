<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Based on Wordpress Broken Link Checker by WPMU DEV https://wpmudev.com/
 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class BlcTransientManager extends BlcModule
{
    protected static $instance  = null;
    protected $pseudoPluginName = '_Transient';

    public function get($key, $asArray = false)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $id    = $this->hashKey($key);

        $query->select('`data`')
            ->from('`#__blc_synch`')
            ->where('`plugin_name` = :containerPlugin')
            ->bind(':containerPlugin', $this->pseudoPluginName)
            ->where('`container_id` = :containerId')
            ->bind(':containerId', $id)
            ->where('`last_synch` > ' . $db->quote(Factory::getDate()->toSql()));

        $value = $db->setQuery($query)->loadResult();
        return $value ? json_decode($value, $asArray) : false;
    }

    protected function hashKey(string $key): int
    {
        return crc32($key);
    }
    public function delete($key)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $id    = $this->hashKey($key);

        $query
            ->delete('`#__blc_synch`')
            ->where('`plugin_name` = :containerPlugin')
            ->bind(':containerPlugin', $this->pseudoPluginName)
            ->where('`container_id` = :containerId')
            ->bind(':containerId', $id);
        $db->setQuery($query)->execute();
    }

    public function clear($expired = true)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);


        $query
            ->delete('`#__blc_synch`')
            ->where('`plugin_name` = :containerPlugin')
            ->bind(':containerPlugin', $this->pseudoPluginName);

        if ($expired) {
            $query->where('`last_synch` < ' . $db->quote(Factory::getDate()->toSql()));
        }
        $db->setQuery($query)->execute();
    }
    public function set(string $key, $value = null, bool| int $lifetime = 3600)
    {
        if ($value === null) {
            $this->delete($key);
            return true;
        }
        if ($lifetime === true) { //short cut
            $lifetime = 315360000; // 10 YEAR
        }
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $id    = $this->hashKey($key);
        $data  = json_encode($value);
        $set   = [
            '`plugin_name`'  => $db->quote($this->pseudoPluginName),
            '`container_id`' => $db->quote($id),
            '`data`'         => $db->quote($data),
            '`last_synch`'   => $db->quote(Factory::getDate("now + $lifetime SECONDS")->toSql()),
        ];
        $query
            ->insert('`#__blc_synch`')
            ->columns(array_keys($set))
            ->values(implode(',', array_values($set)));
        $onDuplicate = "ON DUPLICATE KEY UPDATE `last_synch` = VALUES(`last_synch`), `data` = VALUES(`data`) ";
        $query       = "$query $onDuplicate";
        $db->setQuery($query)->execute();
        return true;
    }
}
