<?php

/**
 * @package     BLC
 * @subpackage  blc.yootheme
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Content\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Blc\BlcParsers;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface, BlcCheckerInterface
{
    use BlcHelpTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-content';
    protected $catids      = [];
    protected $context     = 'com_content.article';
    private $replacedUrls  = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
            'onBlcCheckerRequest'     => 'onBlcCheckerRequest',

        ];
    }
    public function onBlcCheckerRequest($event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker($this, 20);
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        return self::BLC_CHECK_CONTINUE;
    }
    public function checkLink(LinkTable &$linkItem, $results = [], object|array $options = []): array
    {
        //only from 'joomla' links
        if (
            $linkItem->isInternal()
            &&
            strpos($linkItem->internal_url, 'index.php') === 0
        ) {
            //be aware that this instance is shared
            //since we change the stored instance we can't use getInstance -- unsef might changed it incorrectly!
            $parsed = new Uri($linkItem->internal_url);
            $option = $parsed->getVar('option', '');
            $view   = $parsed->getVar('view', '');

            if ($this->context == "{$option}.{$view}") {
                $id = $parsed->getVar('id', 0);
                if ($id) {
                    $catid = $this->getCatForId($id);
                    if ($catid) {
                        $parsed->setVar('catid', $catid);
                        $linkItem->internal_url = $parsed->toString();
                    }
                }
                /* for now we track the query here. As we use it only for internal links and the *content* map */
                $linkItem->data ??= [];
                if (\is_array($linkItem->data)) {
                    $linkItem->data['query'] = $parsed->getQuery(true);
                }
            }
        }

        return $results;
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
        $mvcFactory = $app->bootComponent('com_content')->getMVCFactory();
        $model      = $mvcFactory->createModel('Article', 'Administrator', ['ignore_request' => true]);
        return $model->getTable('Article', 'Administrator');
    }


    public function replaceLink(object $link, object $instance, string $newUrl): void
    {

        //Todo just once
        $language =  Factory::getApplication()->getLanguage();
        $language->load('com_content', JPATH_ADMINISTRATOR);
        //$language->load('com_category', JPATH_ADMINISTRATOR);

        $table = $this->getContainerTable();
        $table->load($instance->container_id);
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }
        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_CHECKED_OUT_ERROR')),
                'warning'
            );
            return;
        }


        $update = false;
        $field  = $instance->field;
        switch ($field) {
            case 'introtext':
            case 'fulltext':
                $text         = $table->{$field};
                $textParsers  =  BlcParsers::getInstance();
                $replacedText = $textParsers->replaceLinksParser($instance->parser, $text, $link->url, $newUrl);

                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }

                break;
            case 'image_intro':
            case 'image_fulltext':
                $images = json_decode($table->images);
                $url    = $images->{$field} ?? '';
                if ($url && $url == $link->url && $url != $newUrl) {
                    $images->{$field} = $newUrl;
                    $update           = true;
                }
                $table->images = json_encode($images);
                break;
            case 'urla':
            case 'urlb':
            case 'urlc':
                $urls = json_decode($table->urls);
                $url  = $urls->{$field} ?? '';
                if ($url && $url == $link->url && $url != $newUrl) {
                    $urls->{$field} = $newUrl;
                    $update         = true;
                }
                $table->urls = json_encode($urls);
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
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $viewHtml),
                'succcess'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                    'warning'
                );
            }
        }
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), "{$db->quoteName('c.id')} = {$db->quoteName('a.catid')}");
        if (!$idOnly) {
            $query->select($db->quoteName(['a.title', 'a.introtext', 'a.fulltext', 'a.images', 'a.urls']))
                ->select($db->quoteName('a.modified'))
                ->order("{$db->quoteName('modified')} DESC");
        }

        if ($this->getParamLocalGlobal('access')) {
            $query->where("{$db->quoteName('a.access')} = 1")
            ->where("{$db->quoteName('c.access')} = 1");
        }
        if ($this->getParamLocalGlobal('published')) {
            $nowQouted = $db->quote(Factory::getDate()->toSql());
            //add the nulldate for legacy timestamps
            $nullDateQuoted    = $db->quote($db->getNullDate());
            $query
                ->where("{$db->quoteName('c.published')} = 1")
                ->where("{$db->quoteName('a.state')} = 1")
                ->where("({$db->quoteName('a.publish_up')} IS NULL OR  {$db->quoteName('a.publish_up')} = $nullDateQuoted OR {$db->quoteName('a.publish_up')} <= $nowQouted)")
                ->where("({$db->quoteName('a.publish_down')} IS NULL OR {$db->quoteName('a.publish_down')} = $nullDateQuoted OR {$db->quoteName('a.publish_down')} >= $nowQouted)");
        } else {
            $query->where("{$db->quoteName('a.state')} > -1"); //ignore trashed
        }


        return $query;
    }
    public function getTitle($instance): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from($db->quoteName('#__content'))
        ->select($db->quoteName('title'))
        ->where("{$db->quoteName('id')} = :containerId")
        ->bind(':containerId', $instance->container_id, ParameterType::INTEGER);
        $db->setQuery($query);
        return $db->loadResult() ?? 'Not found';
    }
    protected function getCatForId($id)
    {
        if (!isset($this->catids[$id])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select($db->quoteName("a.catid", 'id'))
                ->from($db->quoteName('#__content', 'a'))
                ->where("{$db->quoteName('a.id')} = :containerId")
                ->bind(':containerId', $id, ParameterType::INTEGER);
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
            'index.php?option=com_content&task=article.edit&id=' . (int)$instance->container_id
        );
    }
    public function getViewLink($instance): string
    {
        $catid = $this->getCatForId($instance->container_id);
        return Route::link(
            'site',
            ContentRouteHelper::getArticleRoute((int)$instance->container_id, $catid)
        );
    }

    protected function parseContainer(int $id)
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where("{$db->quoteName('a.id')} = :containerId")
            ->bind(':containerId', $id, ParameterType::INTEGER);
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
        $fields = [
            'introtext' => $row->introtext,
            'fulltext'  => $row->fulltext,
        ];


        $this->processText($fields, 'content', $synchedId);



        $images                    = json_decode($row->images);
        $extraLinks                = [];
        $extraLinks["image_intro"] = [
            "url"    => $images->image_intro ?? '',
            "anchor" => $images->image_intro_alt ?? $images->image_intro_caption ?? "Intro Image",
        ];
        $extraLinks["image_fulltext"] = [
            "url"    => $images->image_fulltext ?? '',
            "anchor" => $images->image_intro_alt ?? $images->image_fulltext_alt ?? "Full Image",
        ];
        $urls               = json_decode($row->urls);
        $extraLinks["urla"] = [
            "url"    => $urls->urla ?? '',
            "anchor" => $urls->urlatext ?? "URL A",
        ];
        $extraLinks["urlb"] = [
            "url"    => $urls->urlb ?? '',
            "anchor" => $urls->urlbtext ?? "URL B",
        ];
        $extraLinks["urlc"] = [
            "url"    => $urls->urlc ?? '',
            "anchor" => $urls->urlctext ?? "URl C",
        ];

        $this->processLinkByFields($extraLinks, $synchedId);
        $synchTable->setSynched();
    }




    public function initConfig(Registry $config): void
    {
    }
}
