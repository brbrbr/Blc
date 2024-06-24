<?php

/**
 * @version   24.44
 * @package    BLC Packge
 * @module    mod_blc_admin
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Module\Blc\Administrator\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData()
    {
        $data          = parent::getLayoutData();
        $app           = Factory::getApplication();
        $checkCompleet = (bool)BlcHelper::getCronState();
        $checkCompleet = false;
        if ($checkCompleet === false) {
            $params = $data['params'];
            $doc    = $app->getDocument();
            /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
            $wa = $doc->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('mod_blc');
            //  $wa->UseScript('jquery');
            $wa->useScript('mod_blc.admin');
            $wa->useStyle('mod_blc.admin');
            $query =
                [
                    'option'                => 'com_blc',
                    'task'                  => 'links.cron',
                    Session::getFormToken() => 1,
                ];

            $url     = Route::link('administrator', 'index.php?' . Uri::buildQuery($query), xhtml: false, absolute: true);
            $timeout = 1000 * $params->get('interval', 5);
            $wa->addInlineScript("window.blcCronUrl='$url';window.blcInterval=$timeout;", [], [], ["jquery", "mod_blc.admin"]);
        }

        $data['checkCompleet'] = $checkCompleet;
        return $data;
    }


    public function dispatch()
    {
        if (!ComponentHelper::isEnabled('com_blc')) {
            return;
        }

        $canDo = BlcHelper::getActions();
        if (!$canDo->get('core.manage')) {
            return;
        }

        parent::dispatch();
    }
}
