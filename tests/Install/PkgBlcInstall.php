<?php

declare(strict_types=1);

namespace Blc\Tests\Install;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class PkgBlcInstall extends TestCase
{
    private object $installerScript;

    protected function setUp(): void
    {
        parent::setUp();

        $scriptPath = dirname(__DIR__, 2) . '/script.php';

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

        // Build a tiny container test-double that captures the registered InstallerScriptInterface instance.
        $captured = null;

        $container = new class($captured) {
            public ?object $capturedScript = null;

            public function __construct(&$capturedScriptRef)
            {
                $this->capturedScript = &$capturedScriptRef;
            }

            public function set($id, $value): void
            {
                $this->capturedScript = $value;
            }
        };

        $provider->register($container);

        if (!\is_object($container->capturedScript)) {
            $this->markTestSkipped('Installer script object was not registered.');
        }

        $this->installerScript = $container->capturedScript;
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

        $this->assertIsBool($result);
    }

    private function invokeCheckCurl(string $url): bool
    {
        $method = $this->getCheckCurlMethod();
        $result = $method->invoke($this->installerScript, $url);

        if (!\is_bool($result)) {
            throw new RuntimeException('checkCurl() did not return a bool.');
        }

        return $result;
    }

    private function getCheckCurlMethod(): ReflectionMethod
    {
        $reflection = new ReflectionClass($this->installerScript);

        if (!$reflection->hasMethod('checkCurl')) {
            throw new RuntimeException('Method checkCurl() not found on installer script.');
        }

        $method = $reflection->getMethod('checkCurl');
        $method->setAccessible(true);

        return $method;
    }
}