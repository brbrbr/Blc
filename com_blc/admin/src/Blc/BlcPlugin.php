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

use Blc\Component\Blc\Administrator\Traits\BlcExtractTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;

abstract class BlcPlugin extends CMSPlugin implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use BlcExtractTrait;

    protected $componentConfig;
    protected string $primary = 'id';
    protected string $context = 'joomla';
    /**
     * @var    boolean
     * @since  4.0.0
     */
    protected $allowLegacyListeners = false;

    public function __construct(array $config = [])
    {
        if (version_compare(JVERSION, '5.3', '>=')) {
            parent::__construct($config);
        } else {
            parent::__construct(Factory::getApplication()->getDispatcher(), $config);
        }
        
        $this->componentConfig = ComponentHelper::getParams('com_blc');
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'context' => $this->context,
            'name'    => $this->_name,
            default   => null
        };
    }
}