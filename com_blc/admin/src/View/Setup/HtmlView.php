<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\View\Setup;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Button\TooltipButton;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Button\LinkButton;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * View class for a single Link.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected $stats;





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
        if (!PluginHelper::isEnabled('system', 'blc')) {
            Factory::getApplication()->enqueueMessage(Text::_("COM_BLC_SETUP_PLUGING_REQUIRED"), 'error');
        }
        $this->addToolbar();
        Factory::getApplication()->allowCache(false);
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
        ToolbarHelper::title(Text::_('COM_BLC_TITLE_MAINTENANCE'), "generic");
        ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_blc&view=links'));

        $toolbar = Toolbar::getInstance('toolbar'); //J5 $this->getDocument()->getToolbar();



        $canDo = BlcHelper::getActions();
        if ($canDo->get('core.manage')) {
            $uri    = (string) Uri::getInstance();
            //JED Cecker Warning: encode return URL like Joomla does
            $return = urlencode(base64_encode($uri));

            $task   = Route::_('index.php?option=com_blc&do=reset&what=links&task=link.trashit&return=' . $return);
            $button = new TooltipButton('reset-checks', "COM_BLC_TOOLBAR_RESET_CHECKS_LBL");
            $button->buttonClass('btn btn-warning')
                ->listCheck(false)
                ->url($task)
                ->icon('icon-refresh fa-flip-horizontal')
                ->tooltip(Text::_("COM_BLC_TOOLBAR_RESET_CHECKS_DESC"))
                ->message(Text::_("COM_BLC_TOOLBAR_SURE"));

            $toolbar->appendButton($button);
            $button = new TooltipButton('purge-extracted', "COM_BLC_TOOLBAR_PURGE_EXTRACTED_LBL");
            $task   = Route::_('index.php?option=com_blc&do=truncate&what=synch&task=link.trashit&return=' . $return);
            $button->buttonClass('btn btn-danger')
                ->listCheck(false)
                ->url($task)
                ->icon('icon-purge')
                ->tooltip(Text::_("COM_BLC_TOOLBAR_PURGE_EXTRACTED_DESC"));
            $toolbar->appendButton($button);

            $button = new TooltipButton('purge-links', "COM_BLC_TOOLBAR_PURGE_LINKS_LBL");
            $task   = Route::_('index.php?option=com_blc&do=truncate&what=all&task=link.trashit&return=' . $return);
            $button->buttonClass('btn btn-danger')
                ->listCheck(false)
                ->url($task)
                ->icon('icon-purge')
                ->tooltip(Text::_("COM_BLC_TOOLBAR_PURGE_LINKS_DESC"))
                ->message(Text::_("COM_BLC_TOOLBAR_SURE"));
            $toolbar->appendButton($button);


            $button = new TooltipButton('cleanup-db', "COM_BLC_TOOLBAR_CLEANUP_DB_LBL");
            $task   = Route::_(
                'index.php?option=com_blc&do=orphans&task=link.trashit&return=' . $return
            );
            $button->buttonClass('btn btn-success')
                ->listCheck(false)
                ->url($task)
                ->icon('icon-purge')
                ->tooltip(Text::_("COM_BLC_TOOLBAR_CLEANUP_DB_DESC"))
                ->message(Text::_("COM_BLC_TOOLBAR_SURE"));
            $toolbar->appendButton($button);
            $button = new TooltipButton('purge-transients', "COM_BLC_TOOLBAR_PURGE_TRANSIENTS_LBL");
            $task   = Route::_(
                'index.php?option=com_blc&do=delete&what=synch&plugin=_Transient&task=link.trashit&return=' . $return
            );
            $button->buttonClass('btn btn-danger')
                ->listCheck(false)
                ->url($task)
                ->icon('icon-purge')
                ->tooltip(Text::_("COM_BLC_TOOLBAR_PURGE_TRANSIENTS_DESC"))
                ->message(Text::_("COM_BLC_TOOLBAR_SURE"));
            $toolbar->appendButton($button);
        }


        if ($canDo->get('core.options')) {
            $toolbar->preferences('com_blc');
        }

        $button = (new LinkButton('help-button'))
            ->url('https://brokenlinkchecker.dev/')
            ->text('JTOOLBAR_HELP')
            ->target('_blank')
            ->buttonClass('button-help btn btn-info')
            ->icon('icon-question');
        $toolbar->appendButton($button);
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_blc&view=setup');
    }
}
