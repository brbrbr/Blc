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

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
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
        <table style="width:100%;table-layout: fixed;overflow-wrap: break-word;" class="table">
            <tr>
                <th colspan="2"><?php echo Text::_('COM_BLC_FORM_LBL_URLS'); ?></th>
            </tr>
            <tr>
                <td colspan="2">
                    <?php
                    echo '<ul class="list-group list-group-flush">';
echo HTMLHelper::_('blc.linklist', $this->item);
if (\count($this->instances)) {
    echo HTMLHelper::_('blc.editbutton', $this->item);
}
echo "</ul>";

echo HTMLHelper::_('blc.instanceslist', $this->instances);

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
                                echo '<td class="text-danger">' . Text::_('COM_BLC_BLC_BROKEN_TRUE');
                                break;
                            case HTTPCODES::BLC_BROKEN_WARNING:
                                echo '<td class="text-warning">' . Text::_('COM_BLC_BLC_BROKEN_WARNING');
                                break;
                            case HTTPCODES::BLC_BROKEN_TIMEOUT:
                                echo '<td class="text-warning">' . Text::_('COM_BLC_BLC_BROKEN_TIMEOUT');
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
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_CHECK_PENDING'); ?></th>
                    <td><?php echo
                        Text::_(
                            ($this->item->being_checked == HTTPCODES::BLC_CHECKSTATE_CHECKED) ? 'JNO' : 'JYes'
                        ) . " ({$this->item->being_checked})"; ?></td>
                </tr>
                <tr>
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_REQUEST_DURATION'); ?></th>
                    <td><?php echo number_format($this->item->request_duration, 6); ?></td>
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
                    <th><?php echo Text::_('COM_BLC_FORM_LBL_LINK_MIME'); ?></th>
                    <td><?php echo $this->item->mime; ?></td>
                </tr>
                <tr>
                    <th colspan="2"><?php echo Text::_('COM_BLC_FORM_LBL_LINK_LOG'); ?></th>
                </tr>
                <tr>
                    <td colspan="2" style="overflow:hidden">
                        <?php
                        $this->item->loadStorage();
                $log = $this->item->log;

                foreach ($log as $header => $content) {
                    if ($header == 'Last Headers' || $header == 'lastHeaders') {
                        echo "<h4>Last Headers</h4>";
                        $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        echo '<pre style="margin-left:3em;overflow-x:auto;width:100%" class="text-break">' . htmlspecialchars($content) . "</pre>";
                        continue;
                    }
                    echo "<h4>$header</h4>";
                    if (!\is_string($content)) {
                        foreach ($content as $row) {
                            if (\is_string($row)) {
                                if (str_starts_with($row, '>')) {
                                    $row = substr($row, 1);
                                    echo "<h5 style=\"margin-left:1em\">$row</h5>";
                                } else {
                                    echo "<h6 style=\"margin-left:2em\">$row</h6>";
                                }
                            } else {
                                $row = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                echo '<pre style="margin-left:3em;overflow-x:auto;width:100%" class="text-break">' . htmlspecialchars($row) . "</pre>";
                            }
                        }
                    } else {
                        echo '<p style="overflow-x:auto;width:100%;margin-left:1em" class="text-break">' . nl2br(htmlspecialchars($content)) . "</p>";
                    }
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
