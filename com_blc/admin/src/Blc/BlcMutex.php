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

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class BlcMutex extends BlcModule
{
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
        //get all locks. This is to signal other site that BLC is active
        $serverLock = $this->getLock($name, $timeOut);
        $name       = $this->siteOnlyName($name);
        $siteLock   = $this->getLock($name, $timeOut);
        return match ($lockLevel) {
            self::LOCK_SERVER => $serverLock,
            self::LOCK_SITE   => $siteLock,
            self::LOCK_NONE   => true,
            default           => $serverLock
        };
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
        $serverLock = $this->releaseLock($name);
        $name       = $this->siteOnlyName($name);
        $siteLock   = $this->releaseLock($name);
        return $serverLock & $siteLock;
    }


    private function getLock($name, $timeout)
    {
        $db                      = Factory::getContainer()->get(DatabaseInterface::class);
        $query                   = $db->getQuery(true);
        $query->select('GET_LOCK (:name,:timeout)')
            ->bind(':name', $name)
            ->bind(':timeout', $timeout);
        return 1 == $db->setQuery($query)->loadREsult();
    }

    private function releaseLock($name)
    {
        $db                      = Factory::getContainer()->get(DatabaseInterface::class);
        $query                   = $db->getQuery(true);
        $query->select('RELEASE_LOCK (:name)')
            ->bind(':name', $name);
        return 1 == $db->setQuery($query)->loadREsult();
    }



    /**
     * Given a generic lock name, create a new one that's unique to the current blog.
     *
     * @access private
     *
     * @param string $name
     * @return string
     */
    private function siteOnlyName($name)
    {
        //Uri::root does not get correct url when runnning the CLI ( Joomla 4.4.0 and 5.0.0 at least)
        return $name . ' - ' . BlcHelper::root();
    }
}
