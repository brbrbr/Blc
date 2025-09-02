<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\View\Links;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Button\TooltipButton;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a list of Links.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected $items;

    protected $pagination;

    protected $state;

    public $filterForm;

    /**
     *
     * @since 25.44.7548
     */

    protected bool $showInstances = false;

    public $activeFilters;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws \Exception
     */
    public function display($tpl = null)
    {
        Factory::getApplication()->allowCache(false);
        $model               = $this->getModel();
        $this->state         = $model->getState();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = true; //  $model->getActiveFilters();
        $this->showInstances =   ComponentHelper::getParams('com_blc')->get('show_instances_links', 0) == 1;



        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_BLC_TITLE_LINKS'), "generic");
        if (version_compare(JVERSION, '5.0', '<')) {
            $toolbar = Toolbar::getInstance('toolbar'); //@phpstan-ignore staticMethod.deprecated

        } else {
            $toolbar = $this->getDocument()->getToolbar();
        }


        $button = new TooltipButton(
            'link-hide',
            'COM_BLC_ACTION_TO_HIDE_LINK',
            ['tooltip' => Text::_('COM_BLC_ACTION_TO_HIDE_LINK_DESC'), 'task' => 'links.hide']
        );
        $button->buttonClass('js-grid-item-action btn link-hide btn-success')->listCheck(true);
        $button->icon('icon- fa-eye-slash');
        $toolbar->appendButton($button);

        $button = new TooltipButton('links-working', 'COM_BLC_ACTION_TO_WORKING_LINK', ['task' => 'links.working']);
        $button->buttonClass("btn btn-warning")->listCheck(true)
            ->icon("icon-tools");
        $toolbar->appendButton($button);

        $button = new TooltipButton('links-ignore', 'COM_BLC_ACTION_TO_IGNORE_LINK', ['task' => 'links.ignore']);
        $button->buttonClass("btn btn-danger")->listCheck(true)
            ->icon("icon- fa-ban");
        $toolbar->appendButton($button);

        $button = new TooltipButton('link-active', 'COM_BLC_ACTION_TO_ACTIVE_LINK', ['task' => 'links.active']);
        $button->buttonClass("btn btn-info")->listCheck(true)
            ->icon("icon-checkmark");
        $toolbar->appendButton($button);

        $button = new TooltipButton('links-recheck', 'COM_BLC_ACTION_RECHECK_LINKS', ['task' => 'links.recheck']);
        $button->buttonClass("btn btn-success")->listCheck(true)
            ->icon("icon-refresh");
        $toolbar->appendButton($button);
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_blc&view=links');
    }
}
