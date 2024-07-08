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


use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as  HTTPCODES;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Input\Input;

/**
 * Links list controller class.
 *
 * @since  1.0.0
 */
class LinksController extends AdminController
{
    public function __construct(
        $config = [],
        MVCFactoryInterface $factory = null,
        ?CMSWebApplicationInterface $app = null,
        ?Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        // Define standard task mappings.

        // Value = 0

        $this->registerTask('ignore', 'working');
        $this->registerTask('hide', 'working');
        $this->registerTask('active', 'working');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object  The Model
     *
     * @since   1.0.0
     */
    public function getModel($name = 'Links', $prefix = 'Administrator', $config = [])
    {
        return parent::getModel($name, $prefix, ['ignore_request' => true]);
    }
    public function filter()
    {
        $context = $this->app->getUserStateFromRequest('com_blc.links.filter', 'filter');

        $this->setRedirect(
            Route::_(
                'index.php?option=com_blc&view=links',
                false
            )
        );
    }

    public function cron()
    {

        if (!Session::checkToken('get')) {
            $this->app->setHeader('status', 403, true);
            $this->app->sendHeaders();
            echo Text::_('JINVALID_TOKEN_NOTICE');
            $this->app->close();
        }

        $this->app->mimeType = 'application/json';
        $this->app->allowCache(false);
        $this->app->setHeader('Content-Type', $this->app->mimeType . '; charset=' . $this->app->charSet);
        $this->app->setHeader('Expires', 'Wed, 1 Apr 2023 00:00:00 GMT', true);
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', false);
        $this->app->sendHeaders();
        if (!PluginHelper::isEnabled('system', 'blc')) {
            $response = [
                'msgshort' => '<span class="Broken">System - BLC Required</span>',
                'msglong'  => '<span class="Broken">The System - BLC must be enabled</span>',
                'status'   => 'Broken',
                'count'    => 0,
            ];
        } else {
            $model    = $this->getModel();
            $response = $model->cron(1);
        }

        echo new JsonResponse($response);
        $this->app->close();
    }




    public function working()
    {
        // Check for request forgeries
        $this->checkToken();
        $return = 'index.php?option=com_blc&view=links';
        // Get id(s)
        $pks = $this->input->post->get('cid', [], 'array'); //FROM view=links
        if (empty($pks)) {
            $jform = $this->input->post->get('jform', [], 'ARRAY'); //FROM view=link
            $id    = $jform['id'] ?? null;
            if ($id) {
                $pks    = [$id];
                $return = 'index.php?option=com_blc&view=link&id=' . $id;
            }
        }

        $task = $this->getTask();

        switch ($task) {
            case 'hide':
                $working  =  HTTPCODES::BLC_WORKING_HIDDEN;
                $response = 'COM_BLC_LINKS_SUCCESS_HIDE';
                break;
            case 'ignore':
                $working  = HTTPCODES::BLC_WORKING_IGNORE;
                $response = 'COM_BLC_LINKS_SUCCESS_IGNORE';
                break;
            case 'working':
                $working  = HTTPCODES::BLC_WORKING_WORKING;
                $response = 'COM_BLC_LINKS_SUCCESS_WORKING';
                break;
            case 'active':
                $working  = HTTPCODES::BLC_WORKING_ACTIVE;
                $response = 'COM_BLC_LINKS_SUCCESS_ACTIVE';
                break;
            default:
                $working  = HTTPCODES::BLC_WORKING_ACTIVE;
                $response = '';
        }

        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_BLC_NO_ELEMENT_SELECTED'));
            }
            $model = $this->getModel();
            $model->changeState($pks, 'working', $working);
            $this->setMessage(Text::_($response));
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect($return);
    }

    public function recheck()
    {

        $return = 'index.php?option=com_blc&view=links';
        // Check for request forgeries
        $this->checkToken();

        // Get id(s)
        $pks = $this->input->post->get('cid', [], 'array'); //FROM view=links
        if (empty($pks)) {
            $jform = $this->input->post->get('jform', [], 'ARRAY'); //FROM view=link
            $id    = $jform['id'] ?? null;
            if ($id) {
                $pks    = [$id];
                $return = 'index.php?option=com_blc&view=link&id=' . $id;
            }
        }


        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_BLC_NO_ELEMENT_SELECTED'));
            }

            if (\count($pks) === 1) {
                //not using locking
                $checkLink  = BlcCheckLink::getInstance();
                $linkItem   = $checkLink->checkLinkId($pks[0]);
                $this->getModel()->updateParked(id:$pks[0]);
                if (!$linkItem) {
                    Factory::getApplication()->enqueueMessage("Link checking failed", 'warning');
                } else {
                    Factory::getApplication()->enqueueMessage("Link rechecked", 'success');
                }
            } else {
                $model = $this->getModel('Link');
                $model->trashit('reset', 'links', pks: $pks);
                $this->setMessage(Text::_('COM_BLC_LINKS_SUCCESS_RECHECK'));
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect($return);
    }
}
