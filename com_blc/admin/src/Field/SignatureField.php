<?php

/**
 * @version   24.44
 * @package    Com_Gvs
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * Supports a value from an external table
 *
 * @since  1.0.1
 */
class SignatureField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.1
     */
    protected $type = 'signature';


    /**
     * The translate.
     *
     * @var    boolean
     * @since  1.0.1
     */
    protected $translate = false;

    protected $header = false;



    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   1.0.1
     */




    /**
     * Method to get the field options.
     *
     * @return  array  The field option objects.
     *
     * @since   1.0.1
     */
    protected function getOptions()
    {
        $options    = [];
        $admin_info = ApplicationHelper::getClientInfo('administrator', true);
        $dir        = Path::clean($admin_info->path  . "/components/com_blc/forms/signatures/");
        if (is_dir($dir) && ($signatures = Folder::files($dir, '^.*\.json$'))) {
            // Create the group for the module


            foreach ($signatures as $file) {
                // Add an option to the module group
                $value                  = basename($file, '.json');
                $text                   = ucfirst($value);
                $options[]              = HTMLHelper::_('select.option', $value, $text);
            }
        }




        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $options);

        return $options;
    }

    /**
     * Wrapper method for getting attributes from the form element
     *
     * @param   string  $attr_name  Attribute name
     * @param   mixed   $default    Optional value to return if attribute not found
     *
     * @return  mixed The value of the attribute if it exists, null otherwise
     */
    public function getAttribute($attr_name, $default = null)
    {
        if (!empty($this->element[$attr_name])) {
            return $this->element[$attr_name];
        }
        return $default;
    }
}
