<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Blc\Component\Blc\Administrator\Interface\BlcSetAltInterface;
use Blc\Component\Blc\Administrator\Table\InstanceTable;

/**
 * Link controller class.
 *
 * @since  1.0.0
 */
class LinkController extends BaseController
{
    protected $view_list = 'links';
    protected $name      = 'link';
    public function trashit()
    {

        $model     = $this->getModel();
        $returnUri = $this->input->get->get('return', '', 'base64');
        $do        = $this->input->get->get('do', 'reset', 'CMD');
        $what      = $this->input->get->get('what', 'synch', 'CMD');
        $plugin    = $this->input->get->get('plugin', '', 'CMD');
        $model->trashit($do, $what, $plugin);

        if (!empty($returnUri)) {
            //JED Cecker Warning: decode return URL like Joomla does
            $redirect = base64_decode($returnUri);
        } else {
            $redirect = 'index.php?option=com_blc&view=links';
        }
        if (!Uri::isInternal($redirect)) {
            $redirect = Uri::base();
        }

        $this->setRedirect(Route::_($redirect, false));


        return true;
    }

    protected function validLink($url)
    {
        $in  = $url;
        $url = strip_tags($url);
        $in  = str_replace(['"', '\''], '', $in);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        ///to stricht - we want relative urls $url = filter_var($url, FILTER_VALIDATE_URL);

        return $url === $in;
    }


    public function editalt()
    {
        $validToken = $this->checkToken('post', false);
        if (!$validToken) {
            throw new \Exception(Text::_('COM_BLC_LINK_NO_VALID_TOKEN'));
        }

        //ready for replacement from the 'links' page.
        $toLinks = false;
        try {
            $canDo = BlcHelper::getActions();
            if (!$canDo->get('core.manage')) {
                throw new \Exception(Text::_('COM_BLC_LINK_REPLACE_NOT_ALLOWED'));
            }

            $instanceId = $this->getIdFromTask();

            if (! $instanceId) {
                throw new \Exception(Text::_('COM_BLC_INVALID_INSTANCE'));
            }

            $setAlt = $this->input->post->get('setalt', [], 'ARRAY');

            $newAlt = $setAlt[$instanceId] ?? '';

            if ($newAlt === '') {
                throw new \Exception(Text::_('COM_BLC_LINKS_NO_ALT_SPECIFIED'));
            }

            $model = $this->getModel();

            //load th instance to get the link id
            $instance = $model->getTable('Instance');

            $instance->load($instanceId);

            if (!$instance->id) {
                throw new \Exception(Text::_('COM_BLC_INVALID_INSTANCE'));
            }

            $itemId = $instance->link_id;
            if (!$itemId) {
                throw new \Exception(Text::_('COM_BLC_INVALID_LINK'));
            }


            $link  = $model->getTable();
            $link->load($itemId);

            //this gets all the instances. I the future we might allow multi edit of ALT's
            $instances      = $model->getSynch($itemId); //returns array join of instance and sync

            foreach ($instances as $instance) {
                //prepared for the future if whe add replace instance/item/component/site
                if ($instance->instance_id != $instanceId) {
                    continue;
                }



                $sourcePlugin = $instance->plugin;

                $activePlugin = $model->getPlugin($sourcePlugin);
                if ($activePlugin) {
                    if ($activePlugin instanceof BlcSetAltInterface) {
                        $activePlugin->setAlt($link, $instance, $newAlt);
                    } else {
                        //plugin does not implement BlcSetAltInterface}
                        //this should not occur in the current version but migt happen in the future with replace instance/item/component/site
                        Factory::getApplication()->enqueueMessage(Text::sprintf('COM_BLC_PLUGIN_NOT_IMPLEMENT_BLCSETALTINTERFACE', $activePlugin->getName()), 'warning');
                    }
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                $e->getMessage(),
                'warning'
            );
        }

        $this->redirectLinkOrLinks($toLinks);
    }

    protected function getIdFromTask()
    {
        //get the ID from the task. This works in the link and the links view
        $command = $this->input->post->get('task', '', 'CMD');
        // Check for a controller.task command.
        if (str_contains($command, '.')) {
            // Explode the controller.task command.
            [,, $id] = explode('.', $command) + ['', '', 0];
        } else {
            $id = 0;
        }
        return $id;
    }

    public function replace()
    {
        $validToken = $this->checkToken('post', false);
        if (!$validToken) {
            throw new \Exception(Text::_('COM_BLC_LINK_NO_VALID_TOKEN'));
        }
        $toLinks          = false;
        $componentConfig = ComponentHelper::getParams('com_blc');

        $newUrls = $this->input->post->get('newurl', [], 'ARRAY');

        $configLink = Route::_('index.php?option=com_config&view=component&component=com_blc');

        try {
            if (0 == $componentConfig->get('replace_links', 0)) {
                throw new \Exception(Text::sprintf('BLC_LINK_REPLACING_NOT_ENABLED', $configLink));
            }
            $canDo = BlcHelper::getActions();
            if (!$canDo->get('core.manage')) {
                throw new \Exception(Text::_('COM_BLC_LINK_REPLACE_NOT_ALLOWED'));
            }


            $itemId = $this->getIdFromTask();
            if (!$itemId) {
                $toLinks          = true;
                throw new \Exception(Text::_('COM_BLC_INVALID_LINK'));
            }

            $model = $this->getModel();
            $link  = $model->getTable();
            $link->load($itemId);
            if (!$link->id) {
                throw new \Exception(Text::_('COM_BLC_INVALID_LINK'));
            }

            $newUrl     = $newUrls[$itemId] ?? BlcHelper::getReplaceUrl($link);

            if ($newUrl === (string)$link->url) {
                throw new \Exception(Text::_('COM_BLC_LINKS_IDENTICAL'));
            }
            if ($newUrl === '') {
                throw new \Exception(Text::_('COM_BLC_LINKS_NO_LINK_SPECIFIED'));
            }

            if (! $this->validLink($newUrl)) {
                throw new \Exception(Text::sprintf('COM_BLC_LINK_NOT_VALID', $newUrl));
            }

            $instances      = $model->getSynch($itemId); //returns array join of instance and sync
            $hasImgTag  = false;

            $replaceInternalImage = $componentConfig->get('replace_internalimg', 0);

            if (!$replaceInternalImage && str_contains($link->url, 'joomlaImage')) {
                throw new \Exception(Text::sprintf('BLC_INTERNAL_IMAGES_NOT_RECOMMENDED', $configLink));
            }
            $replaceImgTag        = $componentConfig->get('replace_igmtag', 0);



            foreach ($instances as $instance) {
                if (!$replaceImgTag && $instance->parser == 'img') {
                    //not an exeption. We want to continue with links that are not images
                    Factory::getApplication()->enqueueMessage(Text::sprintf('BLC_IMAGES_NOT_RECOMMENDED', $configLink), 'warning');

                    continue;
                }
                $sourcePlugin = $instance->plugin;
                $activePlugin = $model->getPlugin($sourcePlugin);
                if ($activePlugin) {
                    $activePlugin->replaceLink($link, $instance, $newUrl);
                }
            }


            $instances = $model->getSynch($itemId);
            if (\count($instances) == 0) {
                $toLinks = true;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                $e->getMessage(),
                'warning'
            );
        }



        $this->redirectLinkOrLinks($toLinks);
    }

    protected function redirectLinkOrLinks(bool $toLinks = false, $msg = null, $type = null)
    {
        if ($toLinks) {
            $itemId = 0;
        } else {
            $model = $this->getModel();
            $itemId = $model->getState($model->getName() . '.id');
        }

        if (!$itemId) {
            $this->setRedirect('index.php?option=com_blc&view=links');
        } else {
            $this->setRedirect('index.php?option=com_blc&view=link&id=' . $itemId);
        }
    }
}
