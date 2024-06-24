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


use Blc\Component\Blc\Administrator\Button\BrokenButton;
use Blc\Component\Blc\Administrator\Button\HideButton;

use Blc\Component\Blc\Administrator\Button\IgnoreButton;
use Blc\Component\Blc\Administrator\Button\WorkingButton;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;

use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route; //using constants but not implementing

HTMLHelper::_('bootstrap.tooltip');



$user       = Factory::getApplication()->getIdentity();
$userId     = $user->id;
$canEdit    = $user->authorise('core.edit', 'com_blc');
$listOrder  = $this->state->get('list.ordering', 'a.id');
$listDirn   = $this->state->get('list.direction', 'ASC');
// phpcs:disable Generic.Files.LineLength
?>
<form action="<?php echo Route::_('index.php?option=com_blc&view=links'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <div class="clearfix"></div>
                <?php
                if ($this->items) {
                    ?>
                    <table class="table table-striped" id="linklist">
                        <thead>
                            <tr>
                                <th class="w-1 text-center">
                                    <input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_BLC_LINKS_HTTP_CODE', 'a.http_code', $listDirn, $listOrder); ?>
                                </th>

                                <th class='left'>
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_BLC_LINKS_URL', 'a.url', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <td colspan="<?php echo isset($this->items[0]) ? \count(get_object_vars($this->items[0])) : 10; ?>">
                                    <?php echo $this->pagination->getListFooter(); ?>
                                </td>
                            </tr>
                        </tfoot>
                        <tbody <?php if (!empty($saveOrder)) :
                            ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php
                               endif; ?>>
                            <?php foreach ($this->items as $i => $item) :
                                ?>
                                <tr class="row<?php echo $i % 2; ?>" data-draggable-group='0' data-transition>
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $detailsLink = Route::_('index.php?option=com_blc&task=link.view&id=' . (int) $item->id);
                                        ?>
                                        <a href="<?= $detailsLink; ?>">
                                            <?= BlcHelper::responseCode($item->http_code ?? 0); ?>
                                        </a>
                                        <?php


                                        echo '<br>';

                                        $broken   = (bool)$item->broken;
                                        $redirect = (bool)($item->redirect_count != 0);
                                        $options  = [
                                        'task_prefix' => 'links.',
                                        'disabled'    => false,
                                        'id'          => 'working-' . $item->id,
                                        ];


                                        switch (true) {
                                            case $broken:
                                                $state = 1;
                                                break;
                                            case $redirect:
                                                $state = 2;
                                                break;
                                            case $item->internal_url && ($item->internal_url != $item->url):
                                                $state = 3;
                                                break;
                                            case $item->http_code == HTTPCODES::BLC_TIMEOUT_HTTP_CODE:
                                                $state = 4;
                                                break;
                                            case $item->http_code == 0:
                                                $state = 5;
                                                break;
                                            default:
                                                $state = 0;
                                                break;
                                        }

                                        echo (new BrokenButton())
                                        ->render($state, $i, $options, '', '');

                                        echo '<br>';

                                        $options = [
                                        'task_prefix' => 'links.',
                                        'disabled'    => false,
                                        'id'          => 'hide-' . $item->id,
                                        ];

                                        echo (new HideButton())
                                        ->render((int) $item->working, $i, $options, '', '');


                                        $options = [
                                        'task_prefix' => 'links.',
                                        'disabled'    => false,
                                        'id'          => 'working-' . $item->id,
                                        ];
                                        echo (new WorkingButton())
                                        ->render((int) $item->working, $i, $options, '', '');
                                        ?>
                                        <?php



                                        $options = [
                                        'task_prefix' => 'links.',
                                        'disabled'    => false,
                                        'id'          => 'ignore-' . $item->id,
                                        ];

                                        echo (new IgnoreButton())
                                        ->render((int) $item->working, $i, $options, '', '');



                                        echo '<br><a class="mt-1 btn btn-primary" href="' . $detailsLink . '">Details</a>';
                                        ?>



                                    </td>
                                    <td class="left">
                                        <?php
                                        print '<ul class="list-group list-group-flush">';

                                        HTMLHelper::_('blc.linklist', $item);
                                        echo '<li class="list-group-item">';
                                        echo HTMLHelper::_('blc.editbutton', $item);
                                        echo '</li>';

                                        print "</ul>";

                                        ?>

                                    </td>





                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                } else {
                    ?>
                    <h2>No links Found for this selection</h2>
                    <div class="clearfix"></div>
                    <?php
                    echo $this->get('EmptyInfo');
                }

                ?>
                <input type="hidden" name="task" value="" />
                <input type="hidden" name="boxchecked" value="0" />
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
<?php
// phpcs:enanle Generic.Files.LineLength
echo  BlcHelper::footer('https://brokenlinkchecker.dev/documents/blc/links-menu');
?>
<style>
    /*For Joomla 4*/
    .fa-chain::before {
        content: "\f0c1";
    }

    .fa-chain-broken::before {
        content: "\f127";
    }
</style>