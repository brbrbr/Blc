<?php

declare(strict_types=1);

namespace Blc\Tests\Install;

use Blc\Tests\UnitTestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

use Joomla\CMS\Installer\InstallerScriptInterface;


final class PkgBlcInstallTest extends UnitTestCase
{
    private object $provider;

    public function setUp(): void
    {
        parent::setUp();

        $scriptPath = dirname(__DIR__, 2) . '/pkg_blc/script.php';
       

        if (!is_file($scriptPath)) {
            $this->markTestSkipped('Installer script.php not found.');
        }

        // Provide a minimal Joomla guard value used in script.php.
        if (!\defined('_JEXEC')) {
            \define('_JEXEC', 1);
        }

        // script.php returns an anonymous service provider instance.
        $provider = require $scriptPath;

        if (!\is_object($provider) || !method_exists($provider, 'register')) {
            $this->markTestSkipped('Unable to load installer service provider from script.php.');
        }

        $provider->register($this->container);
        $this->provider = $this->container->get(InstallerScriptInterface::class);
    }

    public function testCheckCurlReturnsFalseWhenCurlInitMissing(): void
    {
        if (\function_exists('curl_init')) {
            $this->markTestSkipped('Environment has ext-curl; missing-curl branch cannot be tested here.');
        }

        $result = $this->invokeCheckCurl('https://www.example.com/');

        $this->assertFalse($result);
    }

    public function testCheckCurlReturnsBoolWhenCurlIsAvailable(): void
    {
        if (!\function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl not available in this environment.');
        }

        $result = $this->invokeCheckCurl('https://www.example.com/');

        $this->assertTrue($result);
    }

    public function testCheckCurlReturnsFalseWhenNonExistentUrl(): void
    {
        if (!\function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl not available in this environment.');
        }

        $result = $this->invokeCheckCurl('blabla://www.example.com');
      

        $this->assertFalse($result);
    }

    private function invokeCheckCurl(string $url): bool
    {
        $method = $this->getCheckCurlMethod();
        $result = $method->invoke($this->provider, $url);

        if (!\is_bool($result)) {
            throw new RuntimeException('checkCurl() did not return a bool.');
        }

        return $result;
    }

    private function getCheckCurlMethod(): ReflectionMethod
    {
        $reflection = new ReflectionClass($this->provider);

        if (!$reflection->hasMethod('checkCurl')) {
            throw new RuntimeException('Method checkCurl() not found in provider script.');
        }

        $method = $reflection->getMethod('checkCurl');


        return $method;
    }
}
