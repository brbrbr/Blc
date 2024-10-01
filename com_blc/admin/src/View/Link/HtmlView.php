<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\View\Link;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Button\TooltipButton;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as  HTTPCODES;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/**
 * View class for a single Link.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected $state;

    protected $item;

    protected $form;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null)
    {
        Factory::getApplication()->allowCache(false);
        $model = $this->getModel();
        $this->item       = $model->getItem();
        $this->instances  = $model->getInstances();
        // Check for errors.
        if (\count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }
        $db             = Factory::getContainer()->get(DatabaseInterface::class);
        $this->nullDate = $db->getNullDate();
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        ToolbarHelper::title(
            Text::_('COM_BLC_TITLE_LINK')
                . ' : '
                . htmlspecialchars(BlcHelper::responseCode($this->item->http_code)),
            "generic"
        );
        ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_blc&view=links'));
        $canDo = BlcHelper::getActions();

        if ($canDo->get('core.manage')) {
            $toolbar =  Toolbar::getInstance('toolbar'); //J5 $this->getDocument()->getToolbar();

            $ignored = $this->item->working == HTTPCODES::BLC_WORKING_IGNORE;
            $working = $this->item->working == HTTPCODES::BLC_WORKING_WORKING;
            $hide    = $this->item->working == HTTPCODES::BLC_WORKING_HIDDEN;

            $task   = $hide ? 'active' : 'hide';
            $class  = $hide ? 'text-info' : 'text-success';
            $text   = $hide ? 'COM_BLC_ACTION_UNSET_HIDE_LINK' : 'COM_BLC_ACTION_TO_HIDE_LINK';
            $button = new TooltipButton('link-hide', $text, ['task' => 'links.' . $task]);
            $button->buttonClass("btn $class")->listCheck(false);
            $button->icon("icon- fa-eye-slash $class");
            $toolbar->appendButton($button);

            $task  = $working ? 'active' : 'working';
            $class = $working ? 'text-info' : 'text-warning';
            $text  = $working ? 'COM_BLC_ACTION_UNSET_WORKING_LINK' : 'COM_BLC_ACTION_TO_WORKING_LINK';


            $button = new TooltipButton('link-working', $text, ['task' => 'links.' . $task]);
            $button->buttonClass("btn $class")->listCheck(false);
            $button->icon("icon-tools  $class");
            $toolbar->appendButton($button);

            $task   = $ignored ? 'active' : 'ignore';
            $class  = $ignored ? 'text-info' : 'text-danger';
            $text   = $ignored ? 'COM_BLC_ACTION_UNSET_IGNORE_LINK' : 'COM_BLC_ACTION_TO_IGNORE_LINK';
            $button = new TooltipButton('link-ignore', $text, ['task' => 'links.' . $task]);
            $button->buttonClass("btn $class")->listCheck(false);
            $button->icon("icon- fa-ban $class");
            $toolbar->appendButton($button);

            if ($canDo->get('core.manage')) {
                if (\count($this->instances)) {
                    $button = new TooltipButton('link-replace', 'Replace', [
                        'disabled' => 'disabled',
                        'task'     => 'link.replace',
                    ]);
                    $button->buttonClass('btn link-replace btn-danger')->listCheck(false);
                    $button->icon('icon-tools')->tooltip("Replace all links");

                    $toolbar->appendButton($button);
                }
                if ($ignored) {
                    $text = Text::_('COM_BLC_FORCE_CHECK');
                } else {
                    $text = Text::_('COM_BLC_CHECK_NOW');
                }
                $button = new TooltipButton('link-refresh', $text, ['task' => 'links.recheck']);
                $button->buttonClass('btn  text-success')->listCheck(false);
                $button->icon('icon-refresh text-success');
                $toolbar->appendButton($button);
            }
        }
    }
}
