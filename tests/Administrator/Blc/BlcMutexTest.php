<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Blc;

use Blc\Component\Blc\Administrator\Blc\BlcMutex;
use Blc\Tests\UnitTestCase;
use Joomla\Database\DatabaseFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use PHPUnit\Framework\Attributes;

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */

#[Attributes\CoversClass(BlcMutex::class)]
class BlcMutexTest extends UnitTestCase
{
    private string $lockName = '';
    public function setUp(): void
    {
        $this->initApplication();
        $this->lockName = 'blc-test-' . uniqid();
    }


    public function testCanBoot()
    {
        $mutex = BlcMutex::getInstance();
        $this->assertInstanceOf(BlcMutex::class, $mutex);
        $this->isSingeTon($mutex);
    }

    public function testAcquireLock()
    {
        $mutex = BlcMutex::getInstance();
        $mutex->setConfigOption('lockLevel', BlcMutex::LOCK_SERVER);
        $lock = $mutex->acquire($this->lockName, 0, BlcMutex::LOCK_SERVER);
        $this->assertTrue($lock);
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('IS_USED_LOCK (:name)')
            ->bind(':name', $this->lockName, ParameterType::STRING);

        $connectionId = $db->setQuery($query)->loadResult();
        $this->assertNotSame(0, $connectionId);
    }

    public function testReleaseLock()
    {
        $this->testAcquireLock();
        $mutex = BlcMutex::getInstance();
        $lock  = $mutex->release($this->lockName);
        $this->assertTrue($lock);
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('IS_USED_LOCK (:name)')
            ->bind(':name', $this->lockName, ParameterType::STRING);

        $connectionId = $db->setQuery($query)->loadResult();

        $this->assertNull($connectionId);
    }
    protected function getExtraDatabaseConnection()
    {
        $config      = new \JConfig();
        $db_host     = $config->host;
        $db_user     = $config->user;
        $db_password = $config->password;
        $db_database = $config->db;




        $dbFactory = new DatabaseFactory();
        $db        = $dbFactory->getDriver(
            'mysqli',
            [
                'host'     => $db_host,
                'user'     => $db_user,
                'password' => $db_password,
                'database' => $db_database,

            ]
        );

        return $db;
    }
    public function testAnotherConnection()
    {


        $mutex = BlcMutex::getInstance();
        $mutex->setConfigOption('lockLevel', BlcMutex::LOCK_SERVER);
        $lock = $mutex->acquire($this->lockName, 0, BlcMutex::LOCK_SERVER);
        $this->assertTrue($lock);
        $mutex2 = BlcMutex::getInstance(false);
        $mutex2->setDatabase($this->getExtraDatabaseConnection());
        $mutex2->setConfigOption('lockLevel', BlcMutex::LOCK_SERVER);
        $lock2 = $mutex2->acquire($this->lockName, 0, BlcMutex::LOCK_SERVER);
        $this->assertFalse($lock2);
    }
    /**
     *
     * this mimicks a different site by setting a param
     */
    public function testAnotherConnectionAnotherSiteServer()
    {


        $mutex = BlcMutex::getInstance();
        $mutex->setConfigOption('lockLevel', BlcMutex::LOCK_SERVER);
        $lock = $mutex->acquire($this->lockName, 0, BlcMutex::LOCK_SERVER);
        $this->assertTrue($lock);
        $mutex2 = BlcMutex::getInstance(false);
        $mutex2->setParamsOption('siteName', 'https://example.com');
        $mutex2->setDatabase($this->getExtraDatabaseConnection());
        $mutex2->setConfigOption('lockLevel', BlcMutex::LOCK_SERVER);
        $lock2 = $mutex2->acquire($this->lockName, 0, BlcMutex::LOCK_SERVER);
        $this->assertFalse($lock2);
    }

    /**
     *
     * this mimicks a different site by setting a param
     */
    public function testAnotherConnectionAnotherSiteSIte()
    {

        $mutex = BlcMutex::getInstance();
        $mutex->setConfigOption('lockLevel', BlcMutex::LOCK_SITE);
        $lock = $mutex->acquire($this->lockName, 0, BlcMutex::LOCK_SITE);
        $this->assertTrue($lock);
        $mutex2 = BlcMutex::getInstance(false);
        $mutex2->setParamsOption('siteName', 'https://example.com');
        $mutex2->setDatabase($this->getExtraDatabaseConnection());
        $mutex2->setConfigOption('lockLevel', BlcMutex::LOCK_SITE);
        $lock2 = $mutex2->acquire($this->lockName, 0, BlcMutex::LOCK_SITE);
        $this->assertTrue($lock2);
    }


    public function testAnotherConnectionAnotherSiteNone()
    {

        $mutex = BlcMutex::getInstance();
        $mutex->setConfigOption('lockLevel', BlcMutex::LOCK_NONE);
        $lock = $mutex->acquire($this->lockName, 0, BlcMutex::LOCK_NONE);
        $this->assertTrue($lock);
        $mutex2 = BlcMutex::getInstance(false);
        $mutex2->setParamsOption('siteName', 'https://example.com');
        $mutex2->setDatabase($this->getExtraDatabaseConnection());
        $mutex2->setConfigOption('lockLevel', BlcMutex::LOCK_NONE);
        $lock2 = $mutex2->acquire($this->lockName, 0, BlcMutex::LOCK_NONE);
        $this->assertTrue($lock2);
    }

    public function testPostgressDummy()
    {

        $dbMock    = $this->createMock(DatabaseInterface::class);
        $queryMock = $this->createMock(QueryInterface::class);
        $queryMock->method('select')->willReturnSelf();
        $mutex = BlcMutex::getInstance(false);
        $dbMock->method('getQuery')->willReturn($queryMock);
        $dbMock->method('setQuery')->willReturnSelf();
        $dbMock->method('loadResult')->willReturn(1);
        $mutex->setDatabase($dbMock);
        $lock = $mutex->acquire($this->lockName, 0, BlcMutex::LOCK_NONE);
        $this->assertTrue($lock);
        $lock = $mutex->release($this->lockName);
        $this->assertTrue($lock);
    }
}
