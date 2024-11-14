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
    protected $extension_id         = 0;

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        $this->extension_id    = $config['id'] ?? 999;
    }

    public function __get($name)
    {
        return match ($name) {
            'context' => $this->context,
            default   => null
        };
    }
}
