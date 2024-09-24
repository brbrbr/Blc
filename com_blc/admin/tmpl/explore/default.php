<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

\defined('_JEXEC') or die;


use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Button\ActionButton;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$app       = Factory::getApplication();
$user      = $this->getCurrentUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if (strpos($listOrder, 'publish_up') !== false) {
    $orderingColumn = 'publish_up';
} elseif (strpos($listOrder, 'publish_down') !== false) {
    $orderingColumn = 'publish_down';
} elseif (strpos($listOrder, 'modified') !== false) {
    $orderingColumn = 'modified';
} else {
    $orderingColumn = 'created';
}
// phpcs:disable Generic.Files.LineLength
?>
<form action="<?php echo Route::_('index.php?option=com_blc&view=explore'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php
                // Search tools bar
                echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
                ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList" id="articleList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_CONTENT_ARTICLES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JFEATURED', 'a.featured', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th title="<?= Text::_('To this page from other (internal)');?>" scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_BLC_LINK_TO', 'to', $listDirn, $listOrder); ?>
                                </th>
                                <th title="<?= Text::_('From this page to other (internal)');?>" cope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_BLC_LINK_FROM', 'from', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_BLC_LINK_EXTERNAL', 'external', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" style="min-width:100px">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                                </th>
                                <?php if (Multilanguage::isEnabled()) : ?>
                                    <th scope="col" class="w-10 d-none d-md-table-cell">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_LANGUAGE', 'language', $listDirn, $listOrder); ?>
                                    </th>
                                <?php endif; ?>
                                <?php if ($this->hits) : ?>
                                    <th scope="col" class="w-3 d-none d-lg-table-cell text-center">
                                        <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_HITS', 'a.hits', $listDirn, $listOrder); ?>
                                    </th>
                                <?php endif; ?>
                                <th scope="col" class="w-3 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $featureButton = new ActionButton();
                            $featureButton
                                ->addState(0, 'featured', 'icon-unfeatured', '', ['tip_title' => Text::_('JUNFEATURED')])
                                ->addState(1, 'unfeatured', 'icon-color-featured icon-star', '', ['tip_title' => Text::_('JFEATURED')]);

                            $publishButton = new ActionButton();
                            $publishButton->addState(1, 'unpublish', 'publish', '', ['tip_title' => Text::_('JPUBLISHED')])
                                ->addState(0, 'publish', 'unpublish', '', ['tip_title' => Text::_('JUNPUBLISHED')])
                                ->addState(2, 'unpublish', 'archive', '', ['tip_title' => Text::_('JARCHIVED')])
                                ->addState(-2, 'publish', 'trash', '', ['tip_title' => Text::_('JTRASHED')]);


                            foreach ($this->items as $i => $item) :
                                $item->max_ordering   = 0;
                                $canEdit              = $user->authorise('core.edit', 'com_content.article.' . $item->id);
                                $canEditOwn           = $user->authorise('core.edit.own', 'com_content.article.' . $item->id) && $item->created_by == $userId;
                                ?>
                                <tr class="row<?php echo $i % 2; ?>">

                                    <td class="text-center d-none d-md-table-cell">
                                        <?php
                                        $options = [
                                            'task_prefix' => 'articles.',
                                            'disabled'    => true,
                                            'id'          => 'featured-' . $item->id,


                                        ];

                                        echo $featureButton->render((int) $item->featured, $i, $options);
                                        ?>
                                    </td>
                                    <td class="article-status text-center">
                                        <?php
                                        $options = [
                                            'task_prefix'        => 'articles.',
                                            'disabled'           => true,
                                            'id'                 => 'state-' . $item->id,
                                            'category_published' => $item->category_published,
                                        ];

                                        echo $publishButton->render((int) $item->state, $i, $options);
                                        ?>
                                    </td>
                                    <td class="d-md-table-cell text-center">
                                        <?php
                                        if ($item->to > 0) {
                                            $class = 'bg-success';
                                        } else {
                                            $class = 'bg-warning text-dark';
                                        }
                                        ?>
                                        <span class="badge <?= $class; ?>">
                                            <?php echo (int) $item->to; ?>
                                        </span>
                                    </td>
                                    <td class="d-md-table-cell text-center">
                                        <?php
                                        if ($item->from > 0) {
                                            $class = 'bg-success';
                                        } else {
                                            $class = 'bg-warning text-dark';
                                        }
                                        ?>
                                        <span class="badge <?= $class; ?>">
                                            <?php echo (int) $item->from; ?>
                                        </span>
                                    </td>
                                    <td class="d-md-table-cell text-center">
                                        <?php
                                        if ($item->external > 0) {
                                            $class = 'bg-success';
                                        } else {
                                            $class = 'bg-warning text-dark';
                                        }
                                        ?>
                                        <span class="badge <?= $class; ?>">
                                            <?php echo (int) $item->external; ?>
                                        </span>
                                    </td>
                                    <th scope="row" class="has-context">
                                        <div class="break-word">
                                            <a target="_view" href="<?php echo Route::link('site', ContentRouteHelper::getArticleRoute($item->id, $item->catid)); ?>" title="<?= Text::_('COM_BLC_VIEW'); ?> <?php echo $this->escape($item->title); ?>">
                                                <?php echo $this->escape($item->title); ?></a>
                                            <?php if ($canEdit || $canEditOwn) : ?>
                                                - <a target="_edit" href="<?php echo Route::_('index.php?option=com_content&task=article.edit&id=' . $item->id); ?>" title="<?= Text::_('JACTION_EDIT'); ?>"><?= Text::_('JACTION_EDIT'); ?></a>
                                            <?php endif; ?>
                                            <div class="small">
                                                <?php
                                                if (isset($item->linkTree->to) && \count($item->linkTree->to)) {
                                                    print '<ul class="list-group list-group-flush">';
                                                    print '<li class="fs-3 fw-bold list-group-item list-group-item-primary">Internal to this page from other</li>';

                                                    foreach ($item->linkTree->to as $link) {
                                                        $catid      = $link->content->catid ?? '';
                                                        $created_by = $link->content->created_by ?? '';
                                                        $itemId     = $link->from ?? 0;

                                                        $canEditLink              = $user->authorise('core.edit', 'com_content.article.' . $itemId);
                                                        $canEditOwnLink           = $user->authorise('core.edit.own', 'com_content.article.' . $itemId) && $created_by == $userId;

                                                        $url   = Route::link('site', ContentRouteHelper::getArticleRoute($itemId, $catid));
                                                        $title = $link->content->title ?? $url;

                                                        echo '<li class="list-group-item list-group-item-info">' . HTMLHelper::_('blc.linkme', Route::link('site', $url), $title, '_view');
                                                        ;
                                                        if ($canEdit || $canEditOwn) : ?>
                                                            - <a target="_edit" href="<?php echo Route::_('index.php?option=com_content&task=article.edit&id=' .  $itemId); ?>">"<?= Text::_('JACTION_EDIT'); ?></a>
                                                        <?php endif;
                                                        echo  '</li>';
                                                    }
                                                    print "</ul>";
                                                }

                                                if (isset($item->linkTree->from) && \count($item->linkTree->from)) {
                                                    print '<ul class="list-group list-group-flush">';
                                                    print '<li class="fs-3 fw-bold list-group-item list-group-item-primary">Internal from this to other</li>';

                                                    foreach ($item->linkTree->from as $link) {
                                                        $title      = $link->content->title ?? $link->url;
                                                        $catid      = $link->content->catid ?? '';
                                                        $created_by = $link->content->created_by ?? '';
                                                        $itemId     = $link->toid ?? 0;

                                                        $canEditLink              = $user->authorise('core.edit', 'com_content.article.' . $itemId);
                                                        $canEditOwnLink           = $user->authorise('core.edit.own', 'com_content.article.' . $itemId) && $created_by == $userId;
                                                        echo '<li class="list-group-item list-group-item-info">' . HTMLHelper::_('blc.linkme', Route::link('site', $link->url), $title, '_view');
                                                        if ($canEdit || $canEditOwn) : ?>
                                                            - <a target="_edit" href="<?php echo Route::_('index.php?option=com_content&task=article.edit&id=' .  $itemId); ?>">Edit</a>
                                                        <?php endif;
                                                        print '</li>';
                                                    }
                                                    print "</ul>";
                                                }

                                                if (isset($item->linkTree->external) && \count($item->linkTree->external)) {
                                                    print '<ul class="list-group list-group-flush">';
                                                    print '<li class="fs-3 fw-bold list-group-item list-group-item-primary">External</li>';

                                                    foreach ($item->linkTree->external as $link) {
                                                        $title = $link->content->title ?? $link->url;
                                                        echo '<li class="list-group-item list-group-item-info">' . HTMLHelper::_('blc.linkme', $link->url, $link->url, '_external');
                                                        ;
                                                        print '</li>';
                                                    }
                                                    print "</ul>";
                                                }


                                                ?>

                                                <div class="small">
                                                    <?php
                                                    echo Text::_('JCATEGORY') . ': ';
                                                    if ($item->category_level != '1') :
                                                        if ($item->parent_category_level != '1') :
                                                            echo ' &#187; ';
                                                        endif;
                                                    endif;
                                                    if ($this->getLanguage()->isRtl()) {
                                                        echo $this->escape($item->category_title);

                                                        if ($item->category_level != '1') :
                                                            echo ' &#171; ';

                                                            echo $this->escape($item->parent_category_title);
                                                        endif;
                                                    } else {
                                                        if ($item->category_level != '1') :
                                                            echo $this->escape($item->parent_category_title);

                                                            echo ' &#187; ';
                                                        endif;

                                                        echo $this->escape($item->category_title);
                                                    }
                                                    if ($item->category_published < '1') :
                                                        echo $item->category_published == '0' ? ' (' . Text::_('JUNPUBLISHED') . ')' : ' (' . Text::_('JTRASHED') . ')';
                                                    endif;
                                                    ?>
                                                </div>
                                            </div>
                                    </th>
                                    <td class="small d-none d-md-table-cell">
                                        <?php echo $this->escape($item->access_level); ?>
                                    </td>
                                    <?php if (Multilanguage::isEnabled()) : ?>
                                        <td class="small d-none d-md-table-cell">
                                            <?php echo LayoutHelper::render('joomla.content.language', $item); ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($this->hits) : ?>
                                        <td class="d-none d-lg-table-cell text-center">
                                            <span class="badge bg-info">
                                                <?php echo (int) $item->hits; ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo (int) $item->id; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php // load the pagination.
                    ?>
                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>
                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
<?php

echo BlcHelper::footer('https://brokenlinkchecker.dev/documents/blc/explore-links');