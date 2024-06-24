<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

// No direct access
\defined('_JEXEC') or die;

use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as  HTTPCODES;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive');
HTMLHelper::_('bootstrap.tooltip');
// phpcs:disable Generic.Files.LineLength
?>
<form action="<?php echo Route::_('index.php?option=com_blc&layout=default&id=' . (int) $this->item->id); ?>" method="post" enctype="multipart/form-data" name="adminForm" id="link-form" class="form-validate form-horizontal">

    <div class="item_fields">
        <table class="table">
            <tr>
                <th colspan="2"><?php echo Text::_('COM_BLC_FORM_LBL_URLS'); ?></th>
            </tr>
            <tr>
                <td colspan="2">

                    <?php
                    print '<ul class="list-group list-group-flush">';
                    echo HTMLHelper::_('blc.linklist', $this->item);
                    echo HTMLHelper::_('blc.editbutton', $this->item);
                    print "</ul>";
                    if (\count($this->instances)) { //shoud never be empty
                        print '<h5 class="mt-2 mb-1" >' . Text::_('COM_BLC_FOUND_ON')  . '</h5>';

                        print '<ul class="list-group">';
                        foreach ($this->instances as $instance) {
                            print '<li class="list-group-item">';
                            print '<ul class="list-group list-group-flush">';
                            $found = '<span class="float-end">' . Text::sprintf('COM_BLC_FOUND_BY', $instance->plugin, $instance->field) . '</span>';
                            if ($instance->anchor != $instance->title) {
                                $anchor = htmlspecialchars($instance->anchor);
                                print '<li class="list-group-item">' . "Anchor: {$anchor} {$found}</li>";
                                $found = '';
                            }
                            if ($instance->view) {
                                print '<li class="list-group-item">' . HTMLHelper::_('blc.linkme', $instance->view, $instance->title, 'view-source') . $found . '</li>';
                                $found = '';
                            }

                            if ($instance->edit) {
                                print '<li class="list-group-item">' . HTMLHelper::_('blc.linkme', $instance->edit, Text::_('JACTION_EDIT'), 'edit-source') .
                                    $found .
                                    '</li>';
                                $found = '';
                            }
                            if ($found) {
                                print "<li>{$found}</li>";
                            }

                            print "</ul>";
                            print "</li>";
                        }
                        print "</ul>";
                    }

                    ?>

                </td>
            </tr>
            <?php
            if (Factory::getApplication()->get('debug') || $this->item->http_code) {
                ?>
                <tr>
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_HTTP_CODE'); ?></th>
                    <td><?php echo $this->item->http_code; ?>
                        <br>
                        <?php echo BlcHelper::responseCode($this->item->http_code); ?>
                    </td>
                </tr>
                <?php
                if ($this->item->broken) {
                    ?>
                    <tr>
                        <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_STATE'); ?></th>
                
                        <?php
                        switch ($this->item->broken) {
                            case HTTPCODES::BLC_BROKEN_TRUE:
                                echo '<td class="text-danger">' . Text::_('COM_BLC_FORM_LBL_LINK_BROKEN');
                                break;
                            case HTTPCODES::BLC_BROKEN_WARNING:
                                echo '<td class="text-warning">' . Text::_('COM_BLC_FORM_LBL_LINK_WARNING');
                                break;
                            case HTTPCODES::BLC_BROKEN_TIMEOUT:
                                echo '<td class="text-warning">' . Text::_('COM_BLC_FORM_LBL_LINK_TIMEOUT');
                                break;
                            case HTTPCODES::BLC_BROKEN_FALSE:
                            default:
                                echo '<td>&nbsp';
                                break;
                        }



                        ?>
                        </td>
                    </tr>
                    <?php
                }
                ?>


                <?php
                if ($this->item->first_failure != $this->nullDate) {
                    ?>
                    <tr>
                        <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_FIRST_FAILURE'); ?></th>
                        <td><?php echo HtmlHelper::date($this->item->first_failure, Text::_('DATE_FORMAT_FILTER_DATETIME')); ?></td>
                    </tr>
                <?php } ?>
                <tr>
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_CHECK_COUNT'); ?></th>
                    <td><?php echo $this->item->check_count; ?></td>
                </tr>
                <tr>
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_REQUEST_DURATION'); ?></th>
                    <td><?php echo $this->item->request_duration; ?></td>
                </tr>


                <?php
                if ($this->item->last_check != $this->nullDate) {
                    ?>
                    <tr>
                        <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_LAST_CHECK'); ?></th>
                        <td><?php echo HtmlHelper::date($this->item->last_check, Text::_('DATE_FORMAT_FILTER_DATETIME')); ?></td>
                    </tr>
                    <?php
                }
                ?>
                <?php
                if ($this->item->last_check_attempt != $this->nullDate) {
                    ?>
                    <tr>
                        <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_LAST_CHECK_ATTEMPT'); ?></th>
                        <td><?php echo HtmlHelper::date($this->item->last_check_attempt, Text::_('DATE_FORMAT_FILTER_DATETIME')); ?></td>
                    </tr>
                    <?php
                }
                ?>
                <?php
                if ($this->item->last_success != $this->nullDate) {
                    ?>

                    <tr>
                        <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_LAST_SUCCESS'); ?></th>
                        <td><?php echo HtmlHelper::date($this->item->last_success, Text::_('DATE_FORMAT_FILTER_DATETIME')); ?></td>
                    </tr>
                    <?php
                }
                ?>
                <tr>
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_REDIRECT_COUNT'); ?></th>
                    <td><?php echo $this->item->redirect_count; ?></td>
                </tr>
            


    

                    <tr>
                        <th colspan="2"><?php echo Text::_('COM_BLC_FORM_LBL_LINK_LOG'); ?></th>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <?php
                            $this->item->loadStorage();
                            $log = $this->item->log;
                            foreach ($log as $header => $content) {
                                print "<h5>$header</h5>";
                                if (!\is_string($content)) {
                                    $content = json_encode($content);
                                }

                                print "<p class=\"text-break\">" . nl2br(htmlspecialchars($content)) . "</p>";
                            }
                            ?>
                        </td>
                    </tr>
                <?php
            }
            ?>
        </table>

    </div>
    <input type="hidden" name="jform[id]" value="<?php echo $this->item->id; ?>" />
    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php
// phpcs:enable Generic.Files.LineLength
