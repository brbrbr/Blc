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



use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\CMS\Factory;


abstract class BlcPlugin extends CMSPlugin implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use BlcExtractTrait; /* for now. This must move to implementations of blcExtractInterface */

    protected $componentConfig;
    protected $primary              =  'id';
    protected $context              = 'joomla';
    protected $allowLegacyListeners = false;


    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            $dispatcher =  Factory::getApplication()->getDispatcher();
            parent::__construct($dispatcher, $config);
        }
        $this->componentConfig = ComponentHelper::getParams('com_blc');
    }

    public function __get($name)
    {
        return match ($name) {
            'context' => $this->context,
            'name'    => $this->_name,
            default   => null
        };
    }
}
