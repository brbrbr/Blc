<?php

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
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;

/**
 * Blc HTML Helper.
 *
 * @since  1.0.0
 */
class BLC
{
    use DatabaseAwareTrait;

    private $sitename;

    /**
     * Public constructor.
     *
     * @param   DatabaseDriver  $db  The Joomla DB driver object for the site's database.
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->setDatabase($db);
        $this->sitename = Factory::getApplication()->get('sitename', 'Homepage');
    }

    public function editbutton($item)
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
        $bar         =  Factory::getContainer()->get(ToolbarFactoryInterface::class)->createToolbar('editbar');
        $replaceLink = BlcHelper::getReplaceUrl($item);
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
              value="' . htmlentities($replaceLink) . '" 
              name="newurl[' . $item->id . ']"
              class="form-control newurl" 
              id="newurl' . $item->id . '" 
              aria-invalid="false">
			</div>
			</div></div>';

            $button = new TooltipButton('link-edit-' . $item->id, 'Set New Link', ['onclick' => '']);
            $button->buttonClass('btn link-edit hide-edit text-info')->listCheck(false);
            $button->icon('icon-edit text-info');
            $bar->appendButton($button);
            $html[] = $button->render();


            $button = new TooltipButton('cancel-edit-' . $item->id, 'Cancel', ['onclick' => '']);
            $button->buttonClass('btn cancel-edit  show-edit text-info hidden')->listCheck(false);
            $button->icon('icon-cancel text-info');
            $bar->appendButton($button);
            $html[] = $button->render();


            $button = new TooltipButton('link-replace', 'Replace', [
                'disabled' => ($replaceLink == $item->url),
                'task'     => 'link.replace.' . $item->id,
            ]);

            $button->buttonClass('btn link-replace show-edit btn-danger')->listCheck(false);
            $button->icon('icon-tools')->tooltip("Replace all links");
            $bar->appendButton($button);
            $html[] = $button->render();
            $html[] = '</div>';
        }
        if ($html) {
            print '<nav class="subhead">' . join("\n", $html) . '</nav>';
        }
    }


    private function copyMe($text)
    {
        //icon- for J4
        return "<span title=\"Click to copy\" class=\"blccopylink\">
        $text
        <i class=\"icon- fa-solid fa-copy\"></i>
        </span>";
    }
    public function linklist($item)
    {

        $seen       = [];
        $isInternal = !empty($item->internal_url);

        //must al be absolute to work from administrator
        $replaceUrl =  $isInternal ? $item->internal_url : ($item->final_url == '' ? $item->url : $item->final_url);
        try {
            $siteUrl = $isInternal ? Route::link('site', $item->internal_url, false) : false;
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf("COM_BLC_ERROR_UNABLE_TO_ROUTE_LINK",$item->internal_url),
                'error'
            );
            $siteUrl = $item->internal_url;
        }


        $id = crc32($item->url);

        $url = $isInternal ? BlcHelper::root(path: $item->url) : $item->url;

        echo '<li id="found-' . $id . '" class="list-group-item found">'
            . HTMLHelper::_('blc.linkme', $url, $item->url, 'found-source')
            . ' (' . $this->copyMe('Found') . ')';
        if (strpos($item->mime, 'image') === 0 && $item->http_code >= 200 && $item->http_code < 400) {
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
                . HTMLHelper::_('blc.linkme', $linkUrl, $replaceUrl, 'internal-source')
                . ' (' . $this->copyMe('Internal') . ')</li>';
            $seen[] = $replaceUrl;
        }
        if (
            $siteUrl &&
            !\in_array($siteUrl, $seen)
        ) {
            $linkUrl =  BlcHelper::root(path: $siteUrl);
            echo '<li id="routed-' . $id . '" class="list-group-item routed">'
                . HTMLHelper::_('blc.linkme', $linkUrl, $siteUrl, 'routed-source')
                . ' (' . $this->copyMe('routed') . ')</li>';
            $seen[] = $siteUrl;
        }

        if (
            $item->final_url &&
            !\in_array($item->final_url, $seen)
        ) {
            echo '<li id="final-' . $id . '" class="list-group-item final">'
                . HTMLHelper::_('blc.linkme', $item->final_url, $item->final_url, 'final-source')
                . ' (' . $this->copyMe('Final') . ')</li>';
        }
    }

    public function linkme($url, $anchor = null, $target = false)
    {

        if (!$url) {
            return '';
        }
        $anchor = self::truncate(
            htmlspecialchars($anchor ?? str_replace(BlcHelper::root(), '', $url), ENT_QUOTES),
            128
        );
        if ($anchor == '' || $anchor == '/') {
            $anchor = Text::sprintf('COM_BLC_HOMEPAGE', $this->sitename);
        }
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
    public static function truncate($text, $max_characters = 0, $break = ' ', $pad = '&hellip;')
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

    /**
     * todo not used yet convert from WP to Joomla
     * Format a time delta using a fuzzy format, e.g. '2 minutes ago', '2 days', etc.
     *
     * @param int $delta Time period in seconds.
     * @param string $type Optional. The output template to use.
     * @return string
     */
    public static function fuzzyDelta($delta, $template = 'default')
    {

        $templates = [
            'seconds' => [
                'default' => _n_noop('%d second', '%d seconds'),
                'ago'     => _n_noop('%d second ago', '%d seconds ago'),
            ],
            'minutes' => [
                'default' => _n_noop('%d minute', '%d minutes'),
                'ago'     => _n_noop('%d minute ago', '%d minutes ago'),
            ],
            'hours' => [
                'default' => _n_noop('%d hour', '%d hours'),
                'ago'     => _n_noop('%d hour ago', '%d hours ago'),
            ],
            'days' => [
                'default' => _n_noop('%d day', '%d days'),
                'ago'     => _n_noop('%d day ago', '%d days ago'),
            ],
            'months' => [
                'default' => _n_noop('%d month', '%d months'),
                'ago'     => _n_noop('%d month ago', '%d months ago'),
            ],
        ];

        if ($delta < 1) {
            $delta = 1;
        }

        if ($delta < MINUTE_IN_SECONDS) {
            $units = 'seconds';
        } elseif ($delta < HOUR_IN_SECONDS) {
            $delta = \intval($delta / MINUTE_IN_SECONDS);
            $units = 'minutes';
        } elseif ($delta < DAY_IN_SECONDS) {
            $delta = \intval($delta / HOUR_IN_SECONDS);
            $units = 'hours';
        } elseif ($delta < MONTH_IN_SECONDS) {
            $delta = \intval($delta / DAY_IN_SECONDS);
            $units = 'days';
        } else {
            $delta = \intval($delta / MONTH_IN_SECONDS);
            $units = 'months';
        }

        return sprintf(
            _n(
                $templates[$units][$template][0], //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle
                $templates[$units][$template][1], //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralPlural
                $delta,
                'broken-link-checker'
            ),
            $delta
        );
    }
}
