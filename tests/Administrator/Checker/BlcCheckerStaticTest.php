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

use Blc\Component\Blc\Administrator\Checker\BlcCheckerStatic;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
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
#[Attributes\CoversClass(BlcCheckerStatic::class)]
#[Attributes\TestDox('Test of the BLC Static Checker')]
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
            ['path' => 'images/images example.png', 'url' => urlencode('images/images example.png')],
            ['path' => 'images/images example.gif', 'url' => rawurlencode('images/images example.gif')],
            ['path' => 'images/images-example.png', 'url' => 'images/images-example.png?width=400'],
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

    /**
     * @param string $file relative path of file to remove
     */

    private function unlink($file)
    {
        $fullPath     = Path::clean(JPATH_ROOT . '/' . $file);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * @param string $file relative path of file to get mime
     *
     * The mime is preset using the extionsion. The Checker uses mime_content_type, so those should match
     * note that some mime ( like css text/plain versis text/css) might differ when send thru a webserver.
     * can be improved using https://github.com/ralouphie/mimey
     */

    private function mime($file)
    {
        $fullPath     = Path::clean(JPATH_ROOT . '/' . $file);
        $ext          = pathinfo($fullPath, PATHINFO_EXTENSION);

        return match ($ext) {
            'jpg'   => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'css'   => 'text/plain',
            default => 'text/plain',
        };
    }
    /**
     * @param string $file relative path of file to touch
     */
    private function touch(string $file)
    {
        $fullPath     = Path::clean(JPATH_ROOT . '/' . $file);
        $ext          = pathinfo($fullPath, PATHINFO_EXTENSION);
        match ($ext) {
            'jpg'   => $this->touchJpg($fullPath),
            'png'   => $this->touchPng($fullPath),
            'gif'   => $this->touchGif($fullPath),
            'css'   => $this->touchCss($fullPath),
            default => $this->touchTxt($fullPath),
        };
    }

    /**
     * @param string $fullPath absolute path of file to create
     */
    private function touchGif($fullPath)
    {
        $gifData = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
        file_put_contents($fullPath, $gifData);
    }


    /**
     * @param string $fullPath absolute path of file to create
     */
    private function touchPng($fullPath)
    {
        $pngData =   base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
        file_put_contents($fullPath, $pngData);
    }

    /**
     * @param string $fullPath absolute path of file to create
     */
    private function touchJpg($fullPath)
    {
        $jpgData = base64_decode("/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEB/8QAHwAAAQAAAAAAAAAAAAAAAAAAAf/aAAgBAQAAPwDtAv/Z");



        file_put_contents($fullPath, $jpgData);
    }
    /**
     * @param string $fullPath absolute path of file to create
     */
    private function touchTxt($fullPath)
    {
        file_put_contents($fullPath, 'Hello World!');
    }
    /**
     * @param string $fullPath absolute path of file to create
     */
    private function touchCss($fullPath)
    {
        file_put_contents($fullPath, '.red { color: red; }');
    }

    protected function bootInstance()
    {
        $checker = BlcCheckerStatic::getInstance();
        $checker->setConfigOption('static_paths', 'images,templates')
        ->setConfigOption('static_checker', 1, true);

        return $checker;
    }

    public function testCanBoot()
    {
        $checker = $this->bootInstance();
        $this->assertInstanceOf(BlcCheckerStatic::class, $checker);
      
        $this->isSingeTon($checker);
    }

    #[Attributes\DataProvider('canCheckLinkProvider')]
    public function testcanCheckHost($url, $ret)
    {
        $checker = $this->bootInstance();

        $linkItem = $this->loadLinkItem($url);

        $result = $checker->canCheckLink($linkItem);
        $this->assertSame($result, $ret);
        $this->assertMessageQueue();
    }

    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testcheckStaticFoundPathOnly($path, $url)
    {
        $checker = $this->bootInstance();


        $this->touch($path);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);

        $this->unlink($path);
        $mime = $this->mime($path);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);
        $this->assertSame($linkItem->mime, $mime);
    }

    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testcheckStaticFoundWithHost($path, $url)
    {
        $checker = $this->bootInstance();
        $this->touch($path);
        $root = Uri::root();
        $url  = $root . '/' . ltrim($url, '/');

        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $mime = $this->mime($path);

        $this->unlink($path);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);
        $this->assertSame($linkItem->mime, $mime);
    }

    #[Attributes\DataProvider('checkLinkProviderZero')]
    public function testcheckStaticZero($url)
    {
        $checker  = $this->bootInstance();
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }

    public function testcheckMayNot()
    {
        $path     = 'tmp/image.jpg';
        $url      = $path;
        $checker  = $this->bootInstance();
        $this->touch($path);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);

        $this->unlink($path);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_CHECK_UNSET);
    }
    public function testcheckUrlencode()
    {
        $path     = 'images/image example.jpg';
        $url      = $path;
        $checker  = $this->bootInstance();
        $checker->setConfigOption('urlencodefix', 0, true);
        $this->touch($path);
        $linkItem = $this->loadLinkItem($url);
        $checker->checkLink($linkItem);
        $this->assertEmpty($linkItem->final_url, 'final_url should be empty');
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);

        $checker->setConfigOption('urlencodefix', 1, true);
        $linkItem = $this->loadLinkItem($url);
        $parsed   = Uri::getInstance($url);
        $checker->checkLink($linkItem);

        $this->unlink($path);
        UrlHelper::urlencodeFixParts($parsed, ['path']);
        $this->assertSame($linkItem->final_url, $parsed->__toString(), 'final_url not same as parsed');
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);
    }
    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testsubDirPathOnly($path, $url)
    {

        $this->setLiveSiteSubDir();
        $root = Uri::root(pathonly: true);
        $url  = $root . '/' . ltrim($path, '/');

        $checker = $this->bootInstance();
        $checker->setConfigOption('urlencodefix', 0, true);
        $this->touch($path);

        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);
        $this->unlink($path);
        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);




        $this->resetLiveSiteSubDir();
    }
    protected function setLiveSiteSubDir()
    {

        $liveUrl = Factory::getApplication()->get('live_site');
        $this->assertNotEmpty($liveUrl, 'ilive site must be set');
        if (!str_ends_with($liveUrl, $this->subdir)) {
            $testSite = $liveUrl .  $this->subdir;
            Factory::getApplication()->set('live_site', $testSite);
        }

        $this->resetUriInstances();
    }

    protected function resetLiveSiteSubDir()
    {

        $liveUrl = Factory::getApplication()->get('live_site');
        if (str_ends_with($liveUrl, $this->subdir)) {
            $restoreSite = preg_replace("#{$this->subdir}$#", '', $liveUrl);
            Factory::getApplication()->set('live_site', $restoreSite);
        }

        $this->resetUriInstances();
    }
    #[Attributes\DataProvider('checkLinkProviderStaticFound')]
    public function testsubDirWithHost($path, $url)
    {

        $this->setLiveSiteSubDir();
        $root = Uri::root();

        $url  =  rtrim($root, '/') . '/' . ltrim($path, '/');

        $checker = $this->bootInstance();
        $checker->setConfigOption('urlencodefix', 0, true);
        $this->touch($path);
        $linkItem = $this->loadLinkItem($url);

        $checker->checkLink($linkItem);

        $this->assertSame($linkItem->http_code, HTTPCODES::BLC_STATIC_FOUND_HTTP_CODE);

        $this->unlink($path);
        $this->resetLiveSiteSubDir();
    }
}
