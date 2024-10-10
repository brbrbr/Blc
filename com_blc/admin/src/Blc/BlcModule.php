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

class BlcModule
{
    protected string $splitOption = "#(;|,|\r\n|\n|\r)#";
    protected $componentConfig    = null;  //A reference to the plugin's global configuration object.
    protected static $instance    = null;

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
    protected function mark(string $str)
    {
        !JDEBUG ?: \Joomla\CMS\Profiler\Profiler::getInstance('Application')->mark(\get_class($this) . '-' . $str);
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
        $this->componentConfig = ComponentHelper::getParams('com_blc');
    }

    public function __clone()/*: void*/
    {

        trigger_error('Class singleton ' . \get_class($this) . ' cant be cloned.');
    }

    public function __wakeup(): void
    {

        trigger_error('Classe singleton ' . \get_class($this) . ' cant be serialized.');
    }
}
