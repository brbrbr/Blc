<?php

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
        $this->state         = $this->get('State');
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = true; // $this->get('ActiveFilters');
        // Check for errors.
        if (\count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
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
        $toolbar = Toolbar::getInstance('toolbar'); //J5 $this->getDocument()->getToolbar();


        $button = new TooltipButton(
            'link-hide',
            'COM_BLC_ACTION_TO_HIDE_LINK',
            ['tooltip' => 'Temporarily hide this link untill next check', 'task' => 'links.hide']
        );
        $button->buttonClass('js-grid-item-action btn link-hide text-success')->listCheck(true);
        $button->icon('icon- fa-eye-slash text-success');
        $toolbar->appendButton($button);

        $button = new TooltipButton('links-working', 'COM_BLC_ACTION_TO_WORKING_LINK', ['task' => 'links.working']);
        $button->buttonClass("btn text-warning")->listCheck(true)
            ->icon("icon-tools text-warning ");
        $toolbar->appendButton($button);

        $button = new TooltipButton('links-ignore', 'COM_BLC_ACTION_TO_IGNORE_LINK', ['task' => 'links.ignore']);
        $button->buttonClass("btn text-danger")->listCheck(true)
            ->icon("icon- fa-ban text-danger");
        $toolbar->appendButton($button);

        $button = new TooltipButton('link-active', 'COM_BLC_ACTION_TO_ACTIVE_LINK', ['task' => 'links.active']);
        $button->buttonClass("btn text-info")->listCheck(true)
            ->icon("icon-checkmark text-info");
        $toolbar->appendButton($button);

        $button = new TooltipButton('links-recheck', 'COM_BLC_ACTION_RECHECK_LINKS', ['task' => 'links.recheck']);
        $button->buttonClass("btn text-success")->listCheck(true)
            ->icon("icon-refresh text-success");
        $toolbar->appendButton($button);
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_blc&view=links');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields()
    {
        return [
            'a.`id`'           => Text::_('JGRID_HEADING_ID'),
            'a.`broken`'       => Text::_('COM_BLC_LINKS_BROKEN'),
            'a.`url`'          => Text::_('COM_BLC_LINKS_URL'),
            'a.`final_url`'    => Text::_('COM_BLC_LINKS_FINAL_URL'),
            'a.`internal_url`' => Text::_('COM_BLC_LINKS_INTERNAL_URL'),
        ];
    }

    /**
     * Check if state is set
     *
     * @param   mixed  $state  State
     *
     * @return bool
     */
    public function getState($state)
    {
        return isset($this->state->{$state}) ? $this->state->{$state} : false;
    }
}
