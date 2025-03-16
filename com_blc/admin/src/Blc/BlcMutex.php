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

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class BlcMutex extends BlcModule
{
    use DatabaseAwareTrait;

    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;
    //add/change in config.xml as wel
    public const LOCK_SERVER = 1;
    public const LOCK_SITE   = 2;
    public const LOCK_NONE   = 5;
    /**
     * Get an exclusive named lock.
     *
     * @param string $name
     * @param integer $timeout
     * @param bool $siteOnly
     * @return bool
     */


    public function acquire(string $name = 'broken-link-checker', int $timeOut = 0, int $minLevel = self::LOCK_SERVER): bool
    {

        $lockLevel = max($minLevel, (int)$this->componentConfig->get('lockLevel', self::LOCK_SERVER));

        $siteName       = $this->siteOnlyName($name);
        $return         = false;
        switch ($lockLevel) {
            case self::LOCK_SITE:
                $return = $this->getLock($siteName, $timeOut);
                $this->getLock($name, 0); //lock on site level to signal others BLC is working. But ignore the actual aquisition off the lock

                break;
            case self::LOCK_NONE:
                $return = true;
                $this->getLock($siteName, $timeOut); //lock on site level as wel. just in case some other instance is running with different settings.
                $this->getLock($name, 0); //lock on site level to signal others BLC is working. But ignore the actual aquisition off the lock
                break;

            default:
            case self::LOCK_SERVER:
                $this->getLock($siteName, $timeOut); //lock on site level as wel.
                $return = $this->getLock($name, $timeOut);
                break;
        }

        return $return;
    }
    protected function init()
    {
        parent::init();
        $this->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Release a named lock.
     *
     * @param string $name
     * @param bool $siteOnly
     * @return bool
     */
    public function release(string $name = 'broken-link-checker'): bool
    {

        $serverLock     = $this->releaseLock($name);
        $siteName       = $this->siteOnlyName($name);
        $siteLock       = $this->releaseLock($siteName);
        return $serverLock & $siteLock;
    }
    /**
     *
     *
     *
     */

    private function getLock(string $name, int $timeout)
    {
        $db                      = $this->getDatabase();
        $query                   = $db->getQuery(true);


        $driver = $db->getServerType();
        if ($driver === 'mysql') {
            $query->select('GET_LOCK (:name,:timeout)')
                ->bind(':name', $name, ParameterType::STRING)
                ->bind(':timeout', $timeout, ParameterType::INTEGER);
            return 1 == $db->setQuery($query)->loadResult();
        }
        $key = crc32($name);
        $query->select('pg_try_advisory_lock (:id)')
            ->bind(':id', $key, ParameterType::INTEGER); //$key is a int , pg_try_advisory_lock requires 64 bit int
        return $db->setQuery($query)->loadResult();
    }

    private function releaseLock($name)
    {
        $db                      = $this->getDatabase();
        $query                   = $db->getQuery(true);
        $driver                  = $db->getServerType();
        if ($driver === 'mysql') {
            $query->select('RELEASE_LOCK (:name)')
                ->bind(':name', $name, ParameterType::STRING);
            return 1 == $db->setQuery($query)->loadREsult();
        }
        $key = crc32($name);
        $query->select('pg_advisory_unlock (:id)')
            ->bind(':id', $key, ParameterType::INTEGER); //$key is a int , pg_advisory_unlock requires 64 bit int
        return $db->setQuery($query)->loadResult();
    }



    /**
     * Given a generic lock name, create a new one that's unique to the current website.
     *
     * @access private
     *
     * @param string $name
     * @return string
     */
    private function siteOnlyName($name)
    {
        //Uri::root does not get correct url when runnning the CLI ( Joomla 4.4.0 and 5.0.0 at least)
        return $name . ' - ' . $this->params->get('siteName', BlcHelper::root());
    }
}
