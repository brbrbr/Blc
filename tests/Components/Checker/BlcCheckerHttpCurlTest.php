<?php

/**
 * @version   24.44.6882
 * @package    Tests
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Blc\Tests\Component\Checker;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Table\LinkTable;
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
#[Attributes\CoversClass(BlcCheckerHttpCurl::class)]
#[Attributes\TestDox('Test of the BLC Curl Checker')]
class BlcCheckerHttpCurlTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]

    public function setUp(): void
    {
        $this->initApplication();
    }

    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl',  'canCheck' => HTTPCODES::BLC_CHECK_TRUE],
            ['url' => 'ftp://brambring.nl', 'canCheck' => HTTPCODES::BLC_CHECK_FALSE],
        ];
    }

    public static function checkLinkProvider(): array
    {
        return   [
            ['url' => 'https://brambring.nl',  'code' => 200],
            ['url' => 'http://brambring.nl',  'code' => 200], //redirect reported as 200!
            ['url' => 'http://brambring.nl/xyz',  'code' => 404],
            ['url' => 'https://facebook.com',  'code' => 200],

        ];
    }

    public function testCanBoot()
    {
        $checker = BlcCheckerHttpCurl::getInstance();
        $this->assertInstanceOf(BlcCheckerHttpCurl::class, $checker);
    }
    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckLink($url, $canCheck)
    {
        $checker  = BlcCheckerHttpCurl::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $linkItem->bind([
            'url' => $url,
        ]);

        $this->assertSame($checker->canCheckLink($linkItem), $canCheck);
        $this->assertMessageQueue();
    }
    #[Attributes\DataProvider('checkLinkProvider')]
    public function testCheckLink($url, $code)
    {
        $checker  = BlcCheckerHttpCurl::getInstance();
        $linkItem = new LinkTable($this->getDatabase(), $this->getDispatcher());
        $config   = \Joomla\CMS\Component\ComponentHelper::getParams('com_blc');
        $linkItem->bind([
            'url' => $url,

        ]);
        $linkItem->_toCheck = $url;
        $results            = $checker->checkLink($linkItem,  $config);
        $this->assertSame($linkItem->http_code, $code);
    }
}
