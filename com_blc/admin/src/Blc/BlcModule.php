<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Blc;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

class BlcModule
{
    /**
     * Property instance.
     *
     * @var  Blc\Component\Blc\Administrator\Blc\BlcModule
     *
     */
    private static $instance = null;

    protected string $splitOption = "#(;|,|\r\n|\n|\r)#";
    protected Registry $componentConfig; //The components's global configuration object.
    protected Registry $params; //The local configuration object.


    /**
     * Class constructor
     *
     * @param string $module_id


     * @return void
     */
    final private function __construct()
    {
    }

    final public static function getInstance()
    {

        if (!static::$instance instanceof static) {
            static::$instance = new static();
            static::$instance->init();
        }

        return static::$instance;
    }

    /**
     *
     * @since 24.44.6970
     * sets the configuration
     */
    public function setConfigOption(string $key, mixed $value): self
    {
        //set to global configuration if nothing set.
        $this->componentConfig->set($key, $value);
        return $this;
    }
    /**
     *
     * @since 24.44.6970
     * sets the configuration
     */
    public function setConfig(?Registry $config = null): self
    {
        //set to global configuration if nothing set.
        $this->componentConfig = $config ?? ComponentHelper::getParams('com_blc');
        return $this;
    }

    /**
     *
     * @since 24.44.6970
     * sets the configuration
     */
    public function setParams(?Registry $config = null): self
    {
        $config ??= new Registry();
        //set to global configuration if nothing set.
        $this->params = $config ;
        return $this;
    }

    /**
    *
    * @since 24.44.6970
    * sets the configuration
    */
    public function setParamsOption(string $key, mixed $value): self
    {
        //set to global configuration if nothing set.
        $this->params->set($key, $value);
        return $this;
    }

    /**
     * Module initializer. Called when the module is first instantiated.
     * The default implementation does nothing. Override it in a subclass to
     * specify some sort of start-up behaviour.
     *
     * @return void
     */
    protected function init()
    {
        $this->setConfig();
        $this->setParams();
    }

    public function __clone()/*: void*/
    {
        throw new \Error('Class singleton cant be cloned. (' . static::class . ' )');
    }

    public function __wakeup(): void
    {
        throw new \Error('Class singleton cant be serialized. (' . static::class . ' )');
    }
}
