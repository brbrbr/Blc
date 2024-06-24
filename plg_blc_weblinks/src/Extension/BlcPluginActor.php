<?php

/**
 * @package     BLC
 * @subpackage  blc.yootheme
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Weblinks\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\Component\Weblinks\Site\Helper\RouteHelper as WeblinkRouteHelper;
use Joomla\Database\Mysqli\MysqliQuery;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-weblinks';
    protected $catids      = [];
    protected $context     = 'com_weblinks.weblink';
    private $replacedUrls  = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',


        ];
    }


    public function getContext(): string
    {
        $option = $this->arguments['parsedUri']->getVar('option', '');
        $view   = $this->arguments['parsedUri']->getVar('view', '');
        return "{$option}.{$view}";
    }
    public function isContext(string $context): string
    {
        return $context == $this->getContext();
    }

    protected function getContainerTable()
    {
        $app        = Factory::getApplication();
        $mvcFactory = $app->bootComponent('com_weblinks')->getMVCFactory();
        $model      = $mvcFactory->createModel('Weblink', 'Administrator', ['ignore_request' => true]);
        return $model->getTable('Weblink', 'Administrator');
    }


    public function replaceLink(object $link, object $instance, string $newUrl): void
    {

        //Todo just once
        $language =  Factory::getApplication()->getLanguage();
        $language->load('com_weblinks', JPATH_ADMINISTRATOR);

        $table = $this->getContainerTable();
        $table->load($instance->container_id);
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage("Unable to replace {$link->url} in: $viewHtml ", 'warning');
            return;
        }
        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage("Unable to replace, checked out: $viewHtml ", 'warning');
            return;
        }


        $update = false;
        $field  = $instance->field;
        switch ($field) {
            case 'image_first':
            case 'image_second':
                $images = json_decode($table->images);
                $url    = $images->{$field} ?? '';
                if ($url && $url == $link->url && $url != $newUrl) {
                    $images->{$field} = $newUrl;
                    $update           = true;
                }
                $table->images = json_encode($images);
                break;
            case 'url':
                $url = $table->url ?? '';
                if ($url && $url == $link->url && $url != $newUrl) {
                    $table->url = $newUrl;
                    $update     = true;
                }
                break;
        }

        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                "{$link->url} Replaced with $newUrl for field {$instance->field} in: $viewHtml",
                'succcess'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    "Unable to replace {$link->url} for field {$instance->field} in: $viewHtml ",
                    'warning'
                );
            }
        }
    }

    protected function getQuery(bool $idOnly = false): MysqliQuery
    {

        $db    = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from('`#__weblinks` `a`')
            ->leftJoin('`#__categories`  `c` ON `c`.`id` = `a`.`catid`');
        if (!$idOnly) {
            $query->select('`a`.`title`,`a`.`description`,`a`.`images`,`a`.`url`')
                ->select('`a`.`modified`')
                ->order('`modified` DESC');
        }

        if ($this->getParamLocalGlobal('access')) {
            $query
                ->where('`a`.`access` IN (1)')
                ->where('`c`.`access` IN (1)');
        }
        if ($this->getParamLocalGlobal('published')) {
            $nowQouted = $db->quote(Factory::getDate()->toSql());
            //add  the nulldate for legacy timestamps
            $nullDateQuoted    = $db->quote($db->getNullDate());
            $query
                ->where('`c`.`published` = 1')
                ->where('`a`.`state` = 1')
                ->where("(`a`.`publish_up` IS NULL OR  `a`.`publish_up` = $nullDateQuoted OR `a`.`publish_up` <= $nowQouted)")
                ->where("( `a`.`publish_down` IS NULL OR `a`.`publish_down` = $nullDateQuoted OR  `a`.`publish_down` >= $nowQouted)");
        } else {
            $query->where('`a`.`state` > -1'); //ignore trashed
        }

        return $query;
    }
    public function getTitle($instance): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('`#__weblinks`')->select('`title`')->where('`id` = ' . (int)$instance->container_id);
        $db->setQuery($query);
        return $db->loadResult() ?? 'Not found';
    }

    protected function getCatForId($id)
    {
        if (!isset($this->catids[$id])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select($db->quoteName("a.catid", 'id'))
                ->from('`#__weblinks` `a`')
                ->where('`a`.`id` = :containerId')
                ->bind(':containerId', $id);
            $db->setQuery($query);
            $catid             = $db->loadResult();
            $this->catids[$id] = $catid;
        }

        return $this->catids[$id];
    }


    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_weblinks&task=weblink.edit&id=' . (int)$instance->container_id
        );
    }
    public function getViewLink($instance): string
    {
        $catid = $this->getCatForId($instance->container_id);
        return Route::link(
            'site',
            WeblinkRouteHelper::getWeblinkRoute((int)$instance->container_id, $catid)
        );
    }

    protected function parseContainer(int $id)
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where('`a`.`id` = :containerId')
            ->bind(':containerId', $id);
        $db->setQuery($query);
        $row = $db->loadObject();
        if ($row) {
            $this->parseContainerFields($row);
        } else {
            $synchTable = $this->getItemSynch($id);
            if ($synchTable->id) {
                $this->purgeInstances($synchTable->id);
            }
        }
    }

    protected function parseContainerFields($row): void
    {
        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);
        $synchedId  = $synchTable->id;
        $this->purgeInstances($synchedId);

        $extraLinks        = [];
        $extraLinks["url"] = [
            "url"    => $row->url ?? '',
            "anchor" => $row->title ?? '',
        ];


        if ($this->params->get('extractdescription', 0)) {
            $fields = [
                'description' => $row->description,
            ];
            $this->processText($fields, 'weblinks', $synchedId);
        }

        if ($this->params->get('extractimages', 0)) {
            $images = json_decode($row->images);

            $extraLinks["image_first"] = [
                "url"    => $images->image_first ?? '',
                "anchor" => $images->image_first_alt ?? $images->image_first_caption ?? "First Image",
            ];
            $extraLinks["image_second"] = [
                "url"    => $images->image_second ?? '',
                "anchor" => $images->image_second_alt ?? $images->image_second_caption ?? "Second Image",
            ];
        }

        $this->processLinkByFields($extraLinks, $synchedId);

        $synchTable->setSynched();
    }
}
