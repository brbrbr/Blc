<?php

/**
 * @package         BLC Login
 * @version   24.44
 *
 * @author          Bram Brambring <info@brokenlinkchecker.dev>
 * @link            https://brokenlinkchecker.dev
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextField;
use Joomla\Utilities\IpHelper;

class ServerIpField extends TextField
{
    protected function getInput()
    {
        $serverIP = Factory::getApplication()->getInput()->server->get('SERVER_ADDR', '127.0.0.1', 'ip');

        $text = parent::getInput();
        if ($this->value !== '' && !IpHelper::IPinList($serverIP, $this->value)) {
            $transientmanager = BlcTransientManager::getInstance();
            $transient        = "BLC LOGIN REQUEST";
            $data             = $transientmanager->get($transient);
            $ip               = $data->ip ?? $serverIP;
            $text .= '<br>Server IP most likely: <code>' . $ip . '</code>';
        }

        if ($this->value == '') {
            $this->value = $serverIP;
        }
        return $text;
    }
}
