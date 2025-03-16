<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Administrator\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerPre;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes;

//using constants but not implementing

/**
 * Test class for SiteStatus plugin
 *
 * @package     Joomla.UnitTest
 * @subpackage  SiteStatus

 *
 * @since       4.2.0
 */
#[Attributes\CoversClass(BlcCheckerPre::class)]
#[Attributes\TestDox('Test of the BLC Curl Checker')]
class BlcCheckerPreTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]

    public function setUp(): void
    {
        $this->initApplication();
    }


    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'https://example.com/path1-example'],
            ['url' => 'https://www.example.com/path2-example'],
            ['url' => 'ftp://www.example2.com/example-path2'],
            ['url' => 'https://m.example2.com/part-3?query=query-part-4'], //no check on query
        ];
    }

    protected function bootInstance()
    {
        $checker = BlcCheckerPre::getInstance();

        return $checker;
    }

    public function testCanBoot()
    {
        $checker = $this->bootInstance();
        $this->assertInstanceOf(BlcCheckerPre::class, $checker);
        $this->isSingeTon($checker);
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckHost($url)
    {
        $checker = $this->bootInstance();
        $checker->setConfigOption('ignore_hosts', "example.com\nexample2.com")
            ->setConfigOption('ignore_paths', "")
            ->setConfigOption('ignore_hosts_action', 1, true);

        $linkItem = $this->loadLinkItem($url);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_TRUE);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testCheckLinkHost($url)
    {
        $checker = $this->bootInstance();
        $checker->setConfigOption('ignore_hosts', "example.com\nexample2.com")
            ->setConfigOption('ignore_paths', "")
            ->setConfigOption('ignore_hosts_action', 1, true);
        $linkItem = $this->loadLinkItem($url);
        $checker->CheckLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_UNCHECKED_IGNORELINK);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanIgnoreCheckHost($url)
    {
        $checker = $this->bootInstance();
        $checker->setConfigOption('ignore_hosts', "example.com\nexample2.com")
            ->setConfigOption('ignore_paths', "")
            ->setConfigOption('ignore_hosts_action', 0, true);
        $linkItem = $this->loadLinkItem($url);
        $result   = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_IGNORE);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanNotCheckHost($url)
    {
        $checker = $this->bootInstance();
        $checker->setConfigOption('ignore_hosts', "brokenlinkchecker.dev")
            ->setConfigOption('ignore_paths', "")
            ->setConfigOption('ignore_hosts_action', 1, true);
        $linkItem = $this->loadLinkItem($url);
        $result   = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_FALSE);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckPath($url)
    {
        $checker = $this->bootInstance();
        $checker->setConfigOption('ignore_hosts', "")
            ->setConfigOption('ignore_paths', "path1;path2;part-3;")
            ->setConfigOption('ignore_paths_action', 1, true);
        $linkItem = $this->loadLinkItem($url);
        $result   = $checker->canCheckLink($linkItem);
        $this->assertSame($result, HTTPCODES::BLC_CHECK_TRUE);
        $this->assertMessageQueue();
    }
}
