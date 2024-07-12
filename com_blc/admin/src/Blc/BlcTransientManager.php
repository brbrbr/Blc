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
use Joomla\Database\ParameterType;

class BlcTransientManager extends BlcModule
{
    protected static $instance  = null;
    protected $pseudoPluginName = '_Transient';

    public function get($key, $asArray = false)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $id    = $this->hashKey($key);
        $query = $this->getBaseQuery($id);
        $query->select($db->quoteName('data'))
            ->where($db->quoteName('last_synch') . ' > ' . $db->quote(Factory::getDate()->toSql()));

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

        $query = $this->getBaseQuery($id, true);
        $db->setQuery($query)->execute();
    }

    public function clear($expired = true)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->delete($db->quoteName('#__blc_synch'))
            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->bind(':containerPlugin', $this->pseudoPluginName, ParameterType::STRING);

        if ($expired) {
            $query->where($db->quoteName('last_synch') . ' < ' . $db->quote(Factory::getDate()->toSql()));
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
        $id    = $this->hashKey($key);
        $data  = json_encode($value);

        $query = $this->getBaseQuery($id);
        $query->select($db->quoteName('id'));
        $synchId = $db->setQuery($query)->loadResult();

     
        if ($synchId) {
            $set   = (object) [
                'id'  => $synchId,
                'data'         => $data,
                'last_synch'  => Factory::getDate("now + $lifetime SECONDS")->toSql(),
            ];
         
            $db->updateObject('#__blc_synch', $set, 'id', false);
        } else {

            $set   = (object) [
                'plugin_name'  => $this->pseudoPluginName,
                'container_id' => $id,
                'data'         => $data,
                'last_synch'  => Factory::getDate("now + $lifetime SECONDS")->toSql(),
            ];
            $db->insertObject('#__blc_synch', $set, $synchId);
        }

        return true;
    }
    private function getBaseQuery(int $id, bool $delete = false)
    {

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query

            ->where($db->quoteName('plugin_name') . ' = :containerPlugin')
            ->bind(':containerPlugin', $this->pseudoPluginName, ParameterType::STRING)
            ->where($db->quoteName('container_id') . ' = :containerId')
            ->bind(':containerId', $id, ParameterType::INTEGER);
        if ($delete) {
            $query->delete($db->quoteName('#__blc_synch'));
        } else {
            $query->from($db->quoteName('#__blc_synch'));
        }
        return $query;
    }
}
