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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

/**
 * Base module class with singleton pattern and configuration management.
 */
abstract class BlcModule
{
    /**
     * Singleton instance per subclass.
     *
     * @var array<string, static>
     */
    private static array $instances = [];

    /**
     * The component's global configuration object.
     */
    protected Registry $componentConfig;

    /**
     * The local configuration object.
     */
    protected Registry $params;

    /**
     * Component name constant.
     */
    private const COMPONENT_NAME = 'com_blc';

    /**
     * Class constructor - private to enforce singleton/factory pattern.
     */
    final private function __construct()
    {
        // Intentionally empty - use init() for initialization
    }

    /**
     * Get instance of the module (singleton or new instance).
     *
     * @param bool $singleton Return a singleton or create a new instance (mainly for testing)
     * @return static
     */
    final public static function getInstance(bool $singleton = true): static
    {
        if (!$singleton) {
            return self::createNewInstance();
        }

        $className = static::class;

        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = self::createNewInstance();
        }

        return self::$instances[$className];
    }

    /**
     * Create a new instance and initialize it.
     *
     * @return static
     */
    private static function createNewInstance(): static
    {
        $instance = new static();
        $instance->init();

        return $instance;
    }

    /**
     * Reset singleton instance (useful for testing).
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        $className = static::class;
        unset(self::$instances[$className]);
    }

    /**
     * Set a specific configuration option.
     *
     * @param string $key     Configuration key
     * @param mixed  $value   Configuration value
     * @param bool   $runInit Whether to re-run initialization after setting
     * @return static Fluent interface
     * @since 24.44.6970
     */
    public function setConfigOption(string $key, mixed $value, bool $runInit = false): static
    {
        $this->ensureConfigInitialized();
        $this->componentConfig->set($key, $value);

        if ($runInit) {
            $this->init();
        }

        return $this;
    }

    /**
     * Get a specific configuration option.
     *
     * @param string $key     Configuration key
     * @param mixed  $default Default value if key doesn't exist
     * @return mixed
     */
    public function getConfigOption(string $key, mixed $default = null): mixed
    {
        $this->ensureConfigInitialized();

        return $this->componentConfig->get($key, $default);
    }

    /**
     * Set the component configuration.
     *
     * @param Registry|null $config Custom configuration or null to use global
     * @return static Fluent interface
     * @since 24.44.6970
     */
    public function setConfig(?Registry $config = null): static
    {
        if ($config !== null) {
            $this->componentConfig = $config;
        } elseif (!isset($this->componentConfig)) {
            // Clone to allow each module to tweak its own config
            $this->componentConfig = clone ComponentHelper::getParams(self::COMPONENT_NAME);
        }

        return $this;
    }

    /**
     * Get the component configuration.
     *
     * @return Registry
     */
    public function getConfig(): Registry
    {
        $this->ensureConfigInitialized();

        return $this->componentConfig;
    }

    /**
     * Set the local parameters.
     *
     * @param Registry|null $params Custom parameters or null to initialize empty
     * @return static Fluent interface
     * @since 24.44.6970
     */
    public function setParams(?Registry $params = null): static
    {
        $this->params = $params ?? new Registry();

        return $this;
    }

    /**
     * Get the local parameters.
     *
     * @return Registry
     */
    public function getParams(): Registry
    {
        $this->ensureParamsInitialized();

        return $this->params;
    }

    /**
     * Get a specific parameter option.
     *
     * @param string $key     Parameter key
     * @param mixed  $default Default value if key doesn't exist
     * @return mixed
     * @since 25.44.7315
     */
    public function getParamsOption(string $key, mixed $default = null): mixed
    {
        $this->ensureParamsInitialized();

        return $this->params->get($key, $default);
    }

    /**
     * Set a specific parameter option.
     *
     * @param string $key   Parameter key
     * @param mixed  $value Parameter value
     * @return static Fluent interface
     * @since 24.44.6970
     */
    public function setParamsOption(string $key, mixed $value): static
    {
        $this->ensureParamsInitialized();
        $this->params->set($key, $value);

        return $this;
    }

    /**
     * Ensure component config is initialized.
     *
     * @return void
     */
    private function ensureConfigInitialized(): void
    {
        if (!isset($this->componentConfig)) {
            $this->setConfig();
        }
    }

    /**
     * Ensure params are initialized.
     *
     * @return void
     */
    private function ensureParamsInitialized(): void
    {
        if (!isset($this->params)) {
            $this->setParams();
        }
    }

    /**
     * Module initializer. Called when the module is first instantiated.
     * Override in subclass to specify custom initialization behavior.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->setConfig();
        $this->setParams();
        $this->params->set('class', static::class);
    }

   /**
     * Prevent cloning of singleton instances.
     *
     * @throws \Error
     */
    public function __clone(): void
    {
        throw new \Error(
            sprintf('Singleton class cannot be cloned (%s)', static::class)
        ); 
    }

    /**
     * Prevent unserialization of singleton instances.
     *
     * @throws \Error
     */
    public function __wakeup(): void
    {
        throw new \Error(
            sprintf('Singleton class cannot be unserialized (%s)', static::class)
        );
    }

    /**
     * Prevent serialization of singleton instances.
     *
     * @throws \Error
     */
    public function __sleep(): array
    {
        throw new \Error(
            sprintf('Singleton class cannot be serialized (%s)', static::class)
        );
    }
}