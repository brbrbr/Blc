<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Service\Html;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Button\TooltipButton;
use Blc\Component\Blc\Administrator\Event\BlcInstanceDisplayEvent;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Component\Blc\Administrator\Interface\BlcSetAltInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;

/**
 * Blc HTML Helper.
 *
 * @since  1.0.0
 */
class BLC
{
    public const MINUTE_IN_SECONDS = 60;
    public const HOUR_IN_SECONDS   = 60 * self::MINUTE_IN_SECONDS;
    public const DAY_IN_SECONDS    = 24 * self::HOUR_IN_SECONDS;
    public const WEEK_IN_SECONDS   =  7 * self::DAY_IN_SECONDS;
    public const MONTH_IN_SECONDS  =  30 * self::DAY_IN_SECONDS;
    public const YEAR_IN_SECONDS   = 365 * self::DAY_IN_SECONDS;

    private static $linkModel;
    /**
     *
     *
     * @param int|array $data either a link Id or a list of instances retrieved earlies ( saves a query in the link view)
     */


    public function instanceslist(int|array $data)
    {
        if (self::$linkModel === null) {
            self::$linkModel = Factory::getApplication()->bootComponent('com_blc')->getMVCFactory()->createModel('Link', 'Administrator', ['ignore_request' => true]);
            try {
                //only helps partially, since symfony catches fatals.
                PluginHelper::importPlugin('blc'); //no need to load the plugins everytime
            } catch (\Error $e) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_BLC_ERROR_IMPORTPLUGINS_BLC') . ':' . $e->getMessage(), 'error');
            }
        }
        if (\is_int($data)) {
            $rows = self::$linkModel->getSynch($data, limit: 999);
        } else {
            $rows = $data;
        }

        $instances = [];
        foreach ($rows as $id => $row) {
            $sourcePlugin = $row->plugin;
            $activePlugin = self::$linkModel->getPlugin($sourcePlugin);
            if (!$activePlugin) {
                continue;
            }

            $row->view  = $activePlugin->getViewLink($row);
            $row->edit  = $activePlugin->getEditLink($row);
            $row->title = $activePlugin->getTitle($row);

            if ($activePlugin instanceof  BlcSetAltInterface) {
                $row->canAltReplace = $activePlugin->canSetAlt($row);
            } else {
                $row->canAltReplace = false;
            }

            $instances[$id]        = $row;
        }




        $app                    = Factory::getApplication();
        $arguments              = [
            'subject' => $instances,
        ];
        $event = new BlcInstanceDisplayEvent('onBlcInstanceBeforeDisplayEvent', $arguments);
        $app->getDispatcher()->dispatch('onBlcInstanceBeforeDisplayEvent', $event); //@phpstan-ignore method.deprecatedInterface
        $instances = $event->getInstances();
        if (!$instances || !\is_array($instances)) {
            return;
        }


        print '<h5 class="mt-2 mb-1" >' . Text::_('COM_BLC_FOUND_ON')  . '</h5>';
        print '<ul class="list-group">';
        foreach ($instances as $instance) {
            print '<li class="list-group-item">';
            print '<ul class="list-group list-group-flush border border-primary">';
            $found = '<span class="float-end">[' . $instance->container_id . ']&nbsp;' . Text::sprintf('COM_BLC_FOUND_BY', $instance->plugin, $instance->field, $instance->parser) . '</span>';

            if ($instance->view) {
                print '<li class="list-group-item">' . $this->linkme($instance->view, $instance->title, 'view-source') . $found . '</li>';
                $found = '';
            }

            if ($instance->edit) {
                print '<li class="list-group-item">'  . $this->linkme($instance->edit, Text::_('JACTION_EDIT'), 'edit-source') .
                    $found .
                    '</li>';
                $found = '';
            }

            if (!$instance->link_text || $instance->link_text == PARSE_STRINGS::BLC_EMPTY_ALT) {
                $link_text           = Text::_('COM_BLC_EMPTY_ALT_OR_ANCHOR');
                $instance->link_text = '';
            } else {
                $link_text = htmlspecialchars((string) $instance->link_text);
            }
            $heading = match ($instance->parser) {
                'href'  => Text::_('COM_BLC_ANCHOR'),
                'img'   => Text::_('COM_BLC_ALT'),
                default => Text::_('COM_BLC_ANCHOR_OR_ALT'),
            };
            print '<li class="list-group-item">' .  "{$heading}:<br>{$link_text} {$found}" . '</li>';
            $found = '';


            if ($found) {
                print '<li class="list-group-item">' . "{$found}</li>";
            }

            if ($instance->canAltReplace) {
                print '<li class="list-group-item"">';
                $this->editaltbutton($instance);
                print '</li>';
            }

            print "</ul>";
            print "</li>";
        }
        print "</ul>";
    }

    public function editbutton(LinkTable $item)
    {
        HTMLHelper::_('jquery.framework');
        $app = Factory::getApplication();
        $doc = $app->getDocument();
        $wa  = $doc->getWebAssetManager();
        $wa->registerAndUseStyle('com_blc-linkedit', 'com_blc/linkedit.css');
        $wa->registerAndUseScript(
            'com_blc-linkedit',
            'com_blc/linkedit.js',
            ['version' => false],
            ['defer'   => true],
            ["jquery"]
        );
        $bar         = Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar('editbar');
        $replaceLink = $item->getReplaceUrl();
        $html        = [];
        $canDo       = BlcHelper::getActions();
        if ($canDo->get('core.manage')) {
            $html[] = '
		<div class="newurlform" id="newurlform_' . $item->id . '" class="row ">
		<div class="col-12 hidden">
		    <div class="control-group">
        	<div class="controls has-success">
        	 <input type="text"
              data-oldurl="' . htmlentities($item->url) .  '"
              value="' . htmlentities((string) $replaceLink) . '" 
              name="newurl[' . $item->id . ']"
              class="form-control newurl" 
              id="newurl' . $item->id . '" 
              aria-invalid="false">
			</div>
			</div></div>';

            $button = new TooltipButton('link-edit-' . $item->id, Text::_('COM_BLC_LINKS_SET_NEW_LINK'), ['onclick' => '']);
            $button->buttonClass('btn link-edit hide-edit btn-info')->listCheck(false);
            $button->icon('icon-edit');
            $bar->appendButton($button);
            $html[] = $button->render();

            $button = new TooltipButton('cancel-edit-' . $item->id, Text::_('JCANCEL'), ['onclick' => '']);
            $button->buttonClass('btn cancel-edit  show-edit btn-info hidden')->listCheck(false);
            $button->icon('icon-cancel');
            $bar->appendButton($button);
            $html[] = $button->render();

            $button = new TooltipButton('link-replace', Text::_('COM_BLC_LINKS_REPLACE'), [
                'disabled' => ($replaceLink == $item->url),
                'task'     => 'link.replace.' . $item->id,
            ]);

            $button->buttonClass('btn link-replace show-edit btn-danger')->listCheck(false);
            $button->icon('icon-tools')->tooltip(Text::_('COM_BLC_LINKS_REPLACE_TOOLTIP'));
            $bar->appendButton($button);
            $html[] = $button->render();
            $html[] = '</div>';
        }
        if ($html) {
            print '<nav class="subhead">' . implode("\n", $html) . '</nav>';
        }
    }


    private function editaltbutton(object $instance)
    {
        HTMLHelper::_('jquery.framework');
        $app = Factory::getApplication();
        $doc = $app->getDocument();
        $wa  = $doc->getWebAssetManager();
        $wa->registerAndUseStyle('com_blc-linkedit', 'com_blc/linkedit.css');
        $wa->registerAndUseScript(
            'com_blc-linkedit',
            'com_blc/linkedit.js',
            ['version' => false],
            ['defer'   => true],
            ["jquery"]
        );

        $id = $instance->instance_id;

        $bar         = Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar('editbar');
        $currentAlt  = $instance->link_text;
        $html        = [];
        $canDo       = BlcHelper::getActions();
        if ($canDo->get('core.manage')) {
            $html[] = '<div class="setaltform row" id="setaltform_' . $id . '">';

            //ALT text input
            $html[] = '
		<div class="col-4">
		    <div class="control-group">
        	<div class="controls has-success">
        	 <input type="text"
              placeholder="New alt text"
              value="' . htmlentities($currentAlt) . '" 
              data-oldalt="' . htmlentities($currentAlt) .  '"
              name="setalt[' . $id . ']"
              class="form-control newalt" 
              id="setalt' . $id . '" 
              aria-invalid="false">
			</div>
			</div></div>';

            //submit button
            $html[] = '<div class="col-4"> <div class="control-group">
        	<div class="controls has-success">';
            $button = new TooltipButton('link-setalt', Text::_('COM_BLC_SET_ALT'), [
                'disabled' => empty($currentAlt),
                'task'     => 'link.editalt.' . $id,
            ]);
            $button->buttonClass('btn set-alt show-edit btn-warning')->listCheck(false);
            $button->icon('icon-tools')->tooltip(Text::_('COM_BLC_SET_ALT_TOOLTIP'));
            $bar->appendButton($button);
            $html[] = $button->render();
            $html[] = '</div></div></div>';

            //select field
            $html[] = '<div class="col-4"> <div class="control-group">
        	<div class="controls has-success">';
            $html[] = "<select title=\"Set the scope where the ALT attribute is set\" name=\"wherealt[$id]\" class=\"form-select wherealt\" id=\"wherealt-$id\">";
            $html[] = '<option value="instance">' . Text::_('COM_BLC_WHERE_ALT_INSTANCE') . '</option>';
            $html[] = '<option selected value="container">' . Text::_('COM_BLC_WHERE_ALT_CONTAINER') . '</option>';
            $html[] = '<option value="extractor">' . Text::_('COM_BLC_WHERE_ALT_EXTRACTOR') . '</option>';
            $html[] = '<option value="site">' . Text::_('COM_BLC_WHERE_ALT_SITE') . '</option>';
            $html[] = '</select>';
            $html[] = '</div></div></div>';


            $html[] = '</div>'; //setaltform
        }
        if ($html) {
            print '<nav class="subhead">' . implode("\n", $html) . '</nav>';
        }
    }


    private function copyMe(string $text)
    {
        //icon- for J4
        return "<span title=\"Click to copy\" class=\"blccopylink\">
        $text
        <i class=\"icon- fa-solid fa-copy\"></i>
        </span>";
    }
    public function linklist(LinkTable $item)
    {

        $seen       = [];
        $isInternal = !empty($item->internal_url);

        //must all be absolute to work from administrator
        $replaceUrl =  $isInternal ? $item->internal_url : ($item->final_url == '' ? $item->url : $item->final_url);
        try {
            $siteUrl = $isInternal ? Route::link('site', $item->internal_url, false) : false;
        } catch (\RuntimeException) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_BLC_ERROR_UNABLE_TO_ROUTE_LINK', $item->internal_url),
                'error'
            );
            $siteUrl = $item->internal_url;
        }


        $id = crc32($item->url);

        $url = $isInternal ? BlcHelper::root(path: $item->url) : $item->url;

        echo '<li id="found-' . $id . '" class="list-group-item found">'
            . $this->linkme($url, $item->url, 'found-source')
            . ' (' . $this->copyMe(Text::_('COM_BLC_LINKS_FOUND')) . ')';
        if (str_starts_with($item->mime, 'image') && $item->http_code >= 200 && $item->http_code < 400) {
            //linkme would truncate the anchor
            echo "<a  href=\"$url\" target=\"view-link\">"
                . "<img src=\"$url\"/ class=\"rounded\" style=\"max-width: 150px;height: auto;float:right\">"
                . "</a>";
        }
        echo '</li>';
        $seen[] =  $item->url;

        if (
            $isInternal &&
            !\in_array($replaceUrl, $seen)
        ) {
            $linkUrl =  BlcHelper::root(path: $replaceUrl);
            echo '<li id="internal-' . $id . '" class="list-group-item internal">'
                . $this->linkme($linkUrl, $replaceUrl, 'internal-source')
                . ' (' . $this->copyMe(Text::_('COM_BLC_LINKS_INTERNAL')) . ')</li>';
            $seen[] = $replaceUrl;
        }
        if (
            $siteUrl &&
            !\in_array($siteUrl, $seen)
        ) {
            $linkUrl =  BlcHelper::root(path: $siteUrl);
            echo '<li id="routed-' . $id . '" class="list-group-item routed">'
                . $this->linkme($linkUrl, $siteUrl, 'routed-source')
                . ' (' . $this->copyMe(Text::_('COM_BLC_LINKS_ROUTED')) . ')</li>';
            $seen[] = $siteUrl;
        }

        if (
            $item->final_url &&
            !\in_array($item->final_url, $seen)
        ) {
            echo '<li id="final-' . $id . '" class="list-group-item final">'
                . $this->linkme($item->final_url, $item->final_url, 'final-source')
                . ' (' . $this->copyMe(Text::_('COM_BLC_LINKS_FINAL')) . ')</li>';
        }
    }

    public function linkme(string $url, ?string $anchor = null, ?string $target = null, $truncate = 128)
    {

        if (!$url) {
            return '';
        }
       
        $anchor ??= str_replace(BlcHelper::root(), '', $url);
  
        if ($anchor == '' || $anchor == '/') {
            $anchor = Text::sprintf('COM_BLC_HOMEPAGE', Factory::getApplication()->get('sitename', 'Homepage'));
        }

        if ($truncate) {
            $anchor = $this->truncate(
                $anchor,
                $truncate
            );
        }

        $anchor = htmlspecialchars($anchor, ENT_QUOTES);
        $target ??= 'view-link';
        return "<a  href=\"$url\" target=\"$target\">"
            . $anchor
            . "</a>";
    }
    /**
     * Truncate a string on a specified boundary character.
     *
     * @param string $text The text to truncate.
     * @param integer $max_characters Return no more than $max_characters
     * @param string $break Break on this character. Defaults to space.
     * @param string $pad Pad the truncated string with this string. Defaults to an HTML ellipsis.
     * @return string
     */
    private function truncate(string $text, int $max_characters = 0, string $break = ' ', string $pad = '&hellip;')
    {
        if (\strlen($text) <= $max_characters) {
            return $text;
        }

        $text      = substr($text, 0, $max_characters);
        $break_pos = strrpos($text, $break);
        if (false !== $break_pos) {
            $text = substr($text, 0, $break_pos);
        }

        return $text . $pad;
    }
}
