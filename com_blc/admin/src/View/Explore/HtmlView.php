<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\View\Explore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for a list of articles.
 *
 * @since  1.6
 */
class HtmlView extends BaseHtmlView
{
    /**
     * An array of items
     *
     * @var  array
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var   \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var  \Joomla\CMS\Form\Form
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var  array
     */
    public $activeFilters;


    /**
     * Are hits being recorded on the site?
     *
     * @var   boolean
     * @since 4.4.0
     */
    protected $hits = false;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->hits          = ComponentHelper::getParams('com_content')->get('record_hits', 1) == 1;

        if (!\count($this->items)) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (\count($errors = $model->getErrors())) {
            $errors = array_unique($errors);
            Factory::getApplication()->enqueueMessage("<h3>Finding links Failed, most likely due to an outdated database version</h3>" . join("<br>", $errors), 'error');
        }

        // We don't need toolbar in the modal window.
        if ($this->getLayout() !== 'modal') {
            // We do not need to filter by language when multilingual is disabled
            if (!Multilanguage::isEnabled()) {
                unset($this->activeFilters['language']);
                $this->filterForm->removeField('language', 'filter');
            }
        } else {
            // In article associations modal we need to remove language filter if forcing a language.
            // We also need to change the category filter to show show categories with All or the forced language.
            if ($forcedLanguage = Factory::getApplication()->getInput()->get('forcedLanguage', '', 'CMD')) {
                // If the language is forced we can't allow to select the language,
                // so transform the language selector filter into a hidden field.
                $languageXml = new \SimpleXMLElement(
                    '<field name="language" type="hidden" default="' . $forcedLanguage . '" />'
                );
                $this->filterForm->setField($languageXml, 'filter', true);

                // Also, unset the active language filter so the search tools is not open by default with this filter.
                unset($this->activeFilters['language']);

                // One last changes needed is to change the category filter to just show categories
                // with All language or with the forced language.
                $this->filterForm->setFieldAttribute('category_id', 'language', '*,' . $forcedLanguage, 'filter');
            }
        }
        $this->addToolbar();
        parent::display($tpl);
    }


    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_BLC_TITLE_EXPLORE'), "generic");
    }
}
