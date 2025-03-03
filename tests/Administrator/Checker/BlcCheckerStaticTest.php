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

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerStatic;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Tests\UnitTestCase;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
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
#[Attributes\CoversClass(BlcCheckerDns::class)]
#[Attributes\TestDox('Test of the BLC DNS Checker')]
class BlcCheckerStaticTest extends UnitTestCase
{
    #[Attributes\TestDox('boot the plugin')]
    protected string $subdir = 'subdir/';
    public function setUp(): void
    {
        $this->initApplication();
    }


    public static function canCheckLinkProvider(): array
    {
        return   [
            ['url' => 'images/images-example.jpg', 'ret' => HTTPCODES::BLC_CHECK_TRUE],
            ['url' => '/images/images-example.jpg', 'ret' => HTTPCODES::BLC_CHECK_TRUE],
            ['url' => 'images/images-example.jpg', 'ret' => HTTPCODES::BLC_CHECK_TRUE],
            ['url' => 'https://mail.fiets4daagsen.nl', 'ret' => HTTPCODES::BLC_CHECK_FALSE], //cname
            ['url' => 'https://mail.fiets4daagsen.nl/image.jpg', 'ret' => HTTPCODES::BLC_CHECK_FALSE],
            ['url' => 'fonts/images-example.jpg', 'ret' => HTTPCODES::BLC_CHECK_TRUE],
        ];
    }

    public static function checkLinkProviderStaticFound(): array
    {
        return   [
            ['path' => 'images/images-example.jpg', 'url' => 'images/images-example.jpg'],
            ['path' => 'images/images example.jpg', 'url' => urlencode('images/images example.jpg')],
            ['path' => 'images/images example.jpg', 'url' => rawurlencode('images/images example.jpg')],
            ['path' => 'images/images-example.jpg', 'url' => 'images/images-example.jpg?width=400'],
            ['path' => 'images/images-example.jpg', 'url' => 'images/images-example.jpg#joomla-dir'],
            ['path' => 'images/images-example.jpg', 'url' => '/images/images-example.jpg'],
            ['path' => 'images/images-ÄÖÜäéöü.txt', 'url' => urlencode('images/images-ÄÖÜäéöü.txt')],
            ['path' => 'images/images-ÄÖÜäéöü.txt', 'url' => 'images/images-ÄÖÜäéöü.txt'],
            ['path' => 'templates/images-example.css', 'url' => 'templates/images-example.css'],
        ];
    }

    public static function checkLinkProviderZero(): array
    {
        return   [
            ['url' => 'fonts/images-example.jpg'],
            ['url' => 'https://example.com/images/example.jpg'],
            ['url' => 'images/' . uniqid() . '.jpg'],

        ];
    }


    private function mime($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return match ($ext) {
            'jpg' => 'image/jpeg',
            'css' => 'text/plain',
            default => 'text/plain',
        };
    }
    private function touch($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        match ($ext) {
            'jpg' => $this->touchJpg($file),
            'css' => $this->touchCss($file),
            default => $this->touchTxt($file),
        };
    }

    private function touchJpg($file)
    {
        $jpgData = base64_decode("/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEB/8QAHwAAAQAAAAAAAAAAAAAAAAAAAf/aAAgBAQAAPwDtAv/Z");



        file_put_contents($file, $jpgData);
    }

    private function touchTxt($file)
    {
        file_put_contents($file, 'Hello World!');
    }
    private function touchCss($file)
    {
        file_put_contents($file, '.red { color: red; }');
    }


    public function testCanBoot()
    {
        $checker = BlcCheckerStatic::getInstance();
        $this->assertInstanceOf(BlcCheckerStatic::class, $checker);
        $checker->setConfigOption('static_paths', 'images,templates')
            ->init();
        return $checker;
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckHost($url, $ret)
    {
        $checker  = $this->testCanBoot();

        $linkItem = $this->loadLinkItem($url);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, $ret);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testcheckStaticFoundPathOnly($path, $url)
    {
        $checker  = $this->testCanBoot();
        $file     = Path::clean(JPATH_ROOT . '/' . $path);

        $this->touch($file);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        if (is_file($file)) {
            unlink($file);
        }
        $mime = $this->mime($file);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);
        $this->assertSame($linkItem->mime, $mime);
    }

    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testcheckStaticFoundWithHost($path, $url)
    {
        $checker  = $this->testCanBoot();
        $file     = Path::clean(JPATH_ROOT . '/' . $path);

        $this->touch($file);
        $root = Uri::root();
        $url  = $root . '/' . ltrim($url, '/');

        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $mime = $this->mime($file);

        if (is_file($file)) {
            unlink($file);
        }
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);
        $this->assertSame($linkItem->mime, $mime);
    }

    #[Attributes\DataProvider('checkLinkProviderZero')]
    public function testcheckStaticZero($url)
    {
        $checker  = $this->testCanBoot();
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }

    public function testcheckMayNot()
    {
        $path     = 'tmp/image.jpg';
        $url      = $path;
        $checker  = $this->testCanBoot();
        $file     = Path::clean(JPATH_ROOT . '/' . $path);
        $this->touch($file);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);

        if (is_file($file)) {
            unlink($file);
        }
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }
    public function testcheckUrlencode()
    {
        $path     = 'images/image example.jpg';
        $url      = $path;
        $checker  = $this->testCanBoot();
        $checker->setConfigOption('urlencodefix', 0);
        $file = Path::clean(JPATH_ROOT . '/' . $path);
        $this->touch($file);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertEmpty($linkItem->final_url, 'final_url should be empty');
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);

        $checker->setConfigOption('urlencodefix', 1);
        $linkItem = $this->loadLinkItem($url);
        $parsed   = Uri::getInstance($url);
        $checker->checkLink($linkItem);

        if (is_file($file)) {
            unlink($file);
        }
        BlcCheckLink::urlencodeFixParts($parsed, ['path']);
        $this->assertSame($linkItem->final_url, $parsed->__toString(), 'final_url not same as parsed');
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);
    }
    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testsubDirPathOnly($path, $url)
    {

        $this->setLiveSiteSubDir();
        $root = Uri::root(pathonly: true);
        $url  = $root . '/' . ltrim($path, '/');

        $checker  = $this->testCanBoot();
        $checker->setConfigOption('urlencodefix', 0);
        $file = Path::clean(JPATH_ROOT . '/' . $path);

        $this->touch($file);

        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);
        if (is_file($file)) {
            unlink($file);
        }
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);




        $this->resetLiveSiteSubDir();
    }
    protected function setLiveSiteSubDir()
    {

        $liveUrl = Factory::getApplication()->get('live_site');
        if (!str_ends_with($liveUrl, $this->subdir)) {
            $restoreSite = preg_replace("#{$this->subdir}$#", '', $liveUrl);
            Factory::getApplication()->set('live_site', $restoreSite);
        }

        Uri::reset();
    }

    protected function resetLiveSiteSubDir()
    {

        $liveUrl = Factory::getApplication()->get('live_site');
        if (str_ends_with($liveUrl, $this->subdir)) {
            $testSite = $liveUrl .  $this->subdir;
            Factory::getApplication()->set('live_site', $testSite);
        }

        Uri::reset();
    }
    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testsubDirWithHost($path, $url)
    {

        $this->setLiveSiteSubDir();
        $root = Uri::root();
        $url  = $root . '/' . ltrim($path, '/');

        $checker  = $this->testCanBoot();
        $checker->setConfigOption('urlencodefix', 0);
        $file = Path::clean(JPATH_ROOT . '/' . $path);
        $this->touch($file);
        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);

        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);



        if (is_file($file)) {
            unlink($file);
        }
        $this->resetLiveSiteSubDir();
    }
}
