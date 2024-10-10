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


use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\DispatcherInterface;

abstract class BlcPlugin extends CMSPlugin
{
    use DatabaseAwareTrait;
    use BlcExtractTrait; /* for now. This must move to implementations of blcExtractInterface */


    protected $componentConfig;
    protected $primary              =  'id';

    protected $context              = 'joomla';

    protected $allowLegacyListeners = false;

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->componentConfig = ComponentHelper::getParams('com_blc');
    }

    protected function mark(string $str)
    {
        !JDEBUG ?: \Joomla\CMS\Profiler\Profiler::getInstance('Application')->mark(\get_class($this) . '-' . $str);
    }



    public function __get($name)
    {
        return match ($name) {
            'context' => $this->context,
            default   => null
        };
    }

    //TODO rework to get the first 'real' checker
    protected function getChecker()
    {
        //TODO function like getUrl and getProvider change the settings so use a clone
        return  BlcCheckerHttpCurl::getInstance();
    }

    protected function getParamLocalGlobal(string $what): bool
    {
        $only = $this->params->get($what, -1);
        return (bool)($only != -1 ? $only : $this->componentConfig->get($what, 1));
    }
}
