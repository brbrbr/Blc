<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
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



    // Lock level constants - add/change in config.xml as well
    public const LOCK_SERVER = 1;
    public const LOCK_SITE   = 2;
    public const LOCK_NONE   = 5;

    // Database driver constants
    private const DRIVER_MYSQL    = 'mysql';
    private const DRIVER_POSTGRES = 'postgresql';

    // Lock acquisition results
    private const LOCK_SUCCESS = 1;

    /**
     * @var array<string, bool> Track acquired locks for proper cleanup
     */
    private array $acquiredLocks = [];

    /**
     * Get an exclusive named lock.
     *
     * @param string  $name     Lock name
     * @param int     $timeOut  Timeout in seconds (0 = no wait)
     * @param int     $minLevel Minimum lock level required
     * @return bool True if lock acquired successfully
     */
    public function acquire(
        string $name = 'broken-link-checker',
        int $timeOut = 0,
        int $minLevel = self::LOCK_SERVER
    ): bool {
        $lockLevel = max($minLevel, (int)$this->componentConfig->get('lockLevel', self::LOCK_SERVER));
        $siteName  = $this->siteOnlyName($name);

        return match ($lockLevel) {
            self::LOCK_SITE => $this->acquireSiteLock($name, $siteName, $timeOut),
            self::LOCK_NONE => $this->acquireNoLock($name, $siteName, $timeOut),
            default         => $this->acquireServerLock($name, $siteName, $timeOut),
        };
    }

    /**
     * Release a named lock.
     *
     * @param string $name Lock name
     * @return bool True if both locks released successfully
     */
    public function release(string $name = 'broken-link-checker'): bool
    {
        $siteName = $this->siteOnlyName($name);

        $serverLock = $this->releaseLock($name);
        $siteLock   = $this->releaseLock($siteName);

        // Clean up tracking
        unset($this->acquiredLocks[$name], $this->acquiredLocks[$siteName]);

        return $serverLock && $siteLock;
    }

    /**
     * Release all acquired locks (cleanup on shutdown/error).
     *
     * @return void
     */
    public function releaseAll(): void
    {
        foreach (array_keys($this->acquiredLocks) as $lockName) {
            $this->releaseLock($lockName);
        }

        $this->acquiredLocks = [];
    }

    /**
     * Initialize database connection.
     *
     * @return void
     */
    protected function init(): void
    {
        parent::init();
        $this->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Acquire site-level lock only.
     *
     * @param string $name     Server lock name
     * @param string $siteName Site lock name
     * @param int    $timeOut  Timeout in seconds
     * @return bool
     */
    private function acquireSiteLock(string $name, string $siteName, int $timeOut): bool
    {
        // Lock on site level to signal others BLC is working (but ignore acquisition)
        $this->getLock($name, 0);

        return $this->getLock($siteName, $timeOut);
    }

    /**
     * Acquire no lock (signals only).
     *
     * @param string $name     Server lock name
     * @param string $siteName Site lock name
     * @param int    $timeOut  Timeout in seconds
     * @return bool
     */
    private function acquireNoLock(string $name, string $siteName, int $timeOut): bool
    {
        // Signal locks but don't enforce
        $this->getLock($siteName, $timeOut);
        $this->getLock($name, 0);

        return true;
    }

    /**
     * Acquire server-level lock.
     *
     * @param string $name     Server lock name
     * @param string $siteName Site lock name
     * @param int    $timeOut  Timeout in seconds
     * @return bool
     */
    private function acquireServerLock(string $name, string $siteName, int $timeOut): bool
    {
        // Lock on site level as well
        $this->getLock($siteName, $timeOut);

        return $this->getLock($name, $timeOut);
    }

    /**
     * Get a database lock.
     *
     * @param string $name    Lock name
     * @param int    $timeout Timeout in seconds
     * @return bool True if lock acquired
     */
    private function getLock(string $name, int $timeout): bool
    {
        $db     = $this->getDatabase();
        $driver = $db->getServerType();

        $result = match ($driver) {
            self::DRIVER_MYSQL    => $this->getMysqlLock($db, $name, $timeout),
            self::DRIVER_POSTGRES => $this->getPostgresLock($db, $name),
            default               => false,
        };

        if ($result) {
            $this->acquiredLocks[$name] = true;
        }

        return $result;
    }

    /**
     * Get MySQL lock.
     *
     * @param DatabaseInterface $db      Database instance
     * @param string            $name    Lock name
     * @param int               $timeout Timeout in seconds
     * @return bool
     */
    private function getMysqlLock(DatabaseInterface $db, string $name, int $timeout): bool
    {
        $query = $db->getQuery(true)
            ->select('GET_LOCK(:name, :timeout)')
            ->bind(':name', $name, ParameterType::STRING)
            ->bind(':timeout', $timeout, ParameterType::INTEGER);

        return self::LOCK_SUCCESS === (int)$db->setQuery($query)->loadResult();
    }

    /**
     * Get PostgreSQL lock.
     *
     * @param DatabaseInterface $db   Database instance
     * @param string            $name Lock name
     * @return bool
     */
    private function getPostgresLock(DatabaseInterface $db, string $name): bool
    {
        $key = crc32($name);

        $query = $db->getQuery(true)
            ->select('pg_try_advisory_lock(:id)')
            ->bind(':id', $key, ParameterType::INTEGER);

        return (bool)$db->setQuery($query)->loadResult();
    }

    /**
     * Release a database lock.
     *
     * @param string $name Lock name
     * @return bool True if lock released
     */
    private function releaseLock(string $name): bool
    {
        if (!isset($this->acquiredLocks[$name])) {
            return true; // Not acquired, consider it released
        }

        $db     = $this->getDatabase();
        $driver = $db->getServerType();

        return match ($driver) {
            self::DRIVER_MYSQL    => $this->releaseMysqlLock($db, $name),
            self::DRIVER_POSTGRES => $this->releasePostgresLock($db, $name),
            default               => false,
        };
    }

    /**
     * Release MySQL lock.
     *
     * @param DatabaseInterface $db   Database instance
     * @param string            $name Lock name
     * @return bool
     */
    private function releaseMysqlLock(DatabaseInterface $db, string $name): bool
    {
        $query = $db->getQuery(true)
            ->select('RELEASE_LOCK(:name)')
            ->bind(':name', $name, ParameterType::STRING);

        return self::LOCK_SUCCESS === (int)$db->setQuery($query)->loadResult();
    }

    /**
     * Release PostgreSQL lock.
     *
     * @param DatabaseInterface $db   Database instance
     * @param string            $name Lock name
     * @return bool
     */
    private function releasePostgresLock(DatabaseInterface $db, string $name): bool
    {
        $key = crc32($name);

        $query = $db->getQuery(true)
            ->select('pg_advisory_unlock(:id)')
            ->bind(':id', $key, ParameterType::INTEGER);

        return (bool)$db->setQuery($query)->loadResult();
    }

    /**
     * Given a generic lock name, create a new one that's unique to the current website.
     *
     * @param string $name Generic lock name
     * @return string Site-specific lock name
     */
    private function siteOnlyName(string $name): string
    {
        // Uri::root does not get correct url when running the CLI (Joomla 4.4.0 and 5.0.0 at least)
        $siteName = $this->params->get('siteName', BlcHelper::root());

        return \sprintf('%s - %s', $name, $siteName);
    }
}
