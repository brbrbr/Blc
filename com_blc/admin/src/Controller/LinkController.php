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

/**
 * Link controller class.
 *
 * @since  1.0.0
 */
class LinkController extends BaseController
{
    protected $view_list = 'links';

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

    public function replace()
    {
        $this->checkToken();
        $toLink          = true;
        $componentConfig = ComponentHelper::getParams('com_blc');
        BlcHelper::setCronState(false);
        $id = null;

        $newUrls = $this->input->post->get('newurl', [], 'ARRAY');
        if (\count($newUrls) > 1) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_MULTI_REPLACE_NOT_SUPPORTED_YET'), 'error');
            $this->setRedirect('index.php?option=com_blc&view=links');
            return;
        }

        $pks = $this->input->post->get('jform', [], 'ARRAY');
        $id  = $pks['id'] ?? null;


        if (!$id) {
            $toLink = false;
            $id     = (int)array_key_first($newUrls);
        }

        if (!$id) {
            $task = $this->input->post->get('task', '', 'STRING');
            if ($task) {
                $parts = explode('.', $task);
                if (\count($parts) == 3) {
                    $id = (int)$parts[2];
                }
            }
        }






        if ($id === null || empty($id)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_NO_ELEMENT_SELECTED'), 'error');
            $this->setRedirect('index.php?option=com_blc&view=links');
            return;
        }
        $configLink = Route::_('index.php?option=com_config&view=component&component=com_blc');


        if ($componentConfig->get('replace_links', 0)) {
            $canDo = BlcHelper::getActions();
            if ($canDo->get('core.manage')) {
                $replaceImgTag        = $componentConfig->get('replace_igmtag', 0);
                $replaceInternalImage = $componentConfig->get('replace_internalimg', 0);
                try {
                    $model = $this->getModel();
                    $link  = $model->getTable();
                    $link->load($id);
                    if (\is_null($link->id)) {
                        throw new \Exception(Text::_('COM_BLC_NO_ELEMENT_SELECTED'));
                    }
                    $newUrl     = $newUrls[$id] ?? BlcHelper::getReplaceUrl($link);
                    $synch      = $model->getSynch($id); //returns instances
                    $hasImgTag  = false;
                    $replaceTag = true;

                    if (!$replaceInternalImage && strpos($link->url, 'joomlaImage') !== false) {
                        $replaceTag = false;
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf('BLC_INTERNAL_IMAGES_NOT_RECOMMENDED', $configLink),
                            'warning'
                        );
                    }

                    if (!$replaceImgTag) {
                        foreach ($synch as $row) {
                            if ($row->parser == 'img') {
                                $hasImgTag = true;
                            }
                        }
                    }

                    if ($hasImgTag) {
                        $replaceTag = false;
                        Factory::getApplication()->enqueueMessage(
                            Text::sprintf('BLC_IMAGES_NOT_RECOMMENDED', $configLink),
                            'warning'
                        );
                    }

                    if ($replaceTag) {
                        foreach ($synch as $row) {
                            $sourcePlugin = $row->plugin;
                            $activePlugin = $model->getPlugin($sourcePlugin);
                            if ($activePlugin) {
                                $activePlugin->replaceLink($link, $row, $newUrl);
                            }
                        }
                    }

                    $synch = $model->getSynch($id);
                    if (\count($synch) == 0) {
                        $toLink = false;
                    }
                } catch (\Exception $e) {
                    Factory::getApplication()->enqueueMessage(
                        $e->getMessage(),
                        'warning'
                    );
                }
            }
        } else {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('BLC_LINK_REPLACING_NOT_ENABLED', $configLink),
                'warning'
            );
        }

        if ($toLink) {
            $this->setRedirect('index.php?option=com_blc&view=link&id=' . $id);
        } else {
            $this->setRedirect('index.php?option=com_blc&view=links');
        }
    }
}
