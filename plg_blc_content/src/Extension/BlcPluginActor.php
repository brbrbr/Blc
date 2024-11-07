<?php

/**
 * @package     BLC
 * @subpackage  blc.yootheme
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Content\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParsers;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Administrator\Table\ArticleTable;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Form\Form;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface, BlcCheckerInterface
{
    use BlcHelpTrait;
    use CustomFieldsTrait {
        CustomFieldsTrait::__construct as private __cftConstruct;
    }


    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-content';
    protected $catids      = [];
    protected $context     = 'com_content.article';
    private $replacedUrls  = [];

    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        if ($this->params->get('enablecf')) {
            $this->__cftConstruct();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
            'onBlcCheckerRequest'     => 'onBlcCheckerRequest'
        ];
    }
    public function onBlcCheckerRequest($event): void
    {
        if (
            $this->params->get('check_catid', 0)
            ||
            $this->params->get('article_alias', 0)
            ||
            $this->params->get('category_alias', 0)
        ) {
            $checker = $event->getItem();
            $checker->registerChecker($this, 20);
        }
    }


    public function canCheckLink(LinkTable $linkItem): int
    {

        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_CONTINUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, $results = []): array
    {
        if (strpos($linkItem->internal_url, 'index.php') !== 0) {
            return $results;
        }
        $parsed = new Uri($linkItem->internal_url);
        $option = $parsed->getVar('option', '');
        $view   = $parsed->getVar('view', '');

        if ($this->context != "{$option}.{$view}") {
            return $results;
        }

        $origId      = $parsed->getVar('id', 0);
        //this would be very wrong
        if (!$origId) {
            return $results;
        }

        $origCatId   = $parsed->getVar('catid', 0);
        [$currentId, $currentAlias]    = explode(':', $origId) + [0, ''];
        [$currentCatid, $currentCatalias] = explode(':', $origCatId) + [0, ''];

        if (
            $this->params->get('category_alias', 0) == 2
        ) {
            $currentCatalias = ''; //used a boolean below
        }
        if (
            $this->params->get('article_alias', 0) == 2
        ) {
            $currentAlias = '';  //used a boolean below
        }
        //the unsef or another plugin might have changed the link so check it here and not in canCheckLink

        //be aware that this instance is shared
        //since we change the stored instance we can't use getInstance -- unsef might changed it incorrectly!


        ['catid' => $catid, 'alias' => $alias, 'calias' => $calias] = $this->getInfoForId($currentId,'#__content');
        if ($catid) {
            if ($this->params->get('check_catid', 0)) {
                $currentCatid = $catid;
                //currentCatalias is set when it always be set ( option 1) or the currentCatalias is not empty (if option = 2 cleared above)
                if ($this->params->get('category_alias', 0) == 1 ||  $currentCatalias) {
                    $currentCatalias = $calias;
                }
            }
            //see comment above
            if ($this->params->get('article_alias', 0) == 1 || $currentAlias) {
                $currentAlias = $alias;
            }
        }

        //
        if ($currentCatalias) {
            $currentCatid .= ':' . $currentCatalias;
        }

        if ($currentAlias) {
            $currentId .= ':' . $currentAlias;
        }

        if ($currentId != $origId || $currentCatid != $origCatId) {

            $parsed->setVar('id', $currentId); //in case it is cleaned from id:alias -> id
            $parsed->setVar('catid', $currentCatid); //in case it is cleaned from catid:alias ->catid
            /* for now we track the query here. As we use it only for internal links and the *content* map */
            $linkItem->data ??= [];
            $linkItem->internal_url = $parsed->toString();
            if (\is_array($linkItem->data)) {
                $linkItem->data['query'] = $parsed->getQuery(true);
            }
        }

        return $results;
    }

    protected function getContainerTable()
    {
        try {
            $db    = $this->getDatabase();
            $table = new ArticleTable($db);
        } catch (\Error) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_GETCONTAINERTABLE_ERROR'),
                'warning'
            );
            return false;
        }

        return $table;
    }


    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {

        //Todo just once
        $language =  Factory::getApplication()->getLanguage();
        $language->load('com_content', JPATH_ADMINISTRATOR);
        //$language->load('com_category', JPATH_ADMINISTRATOR);

        $table = $this->getContainerTableById($instance->container_id);

        $messageLinks = $this->getMessageLinks($instance);
        
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }
        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_CHECKED_OUT_ERROR')),
                'warning'
            );
            return;
        }

        $update  = false;
        $reparse = false;

        $field = $instance->field;
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
            case 'Fields':
                $reparse = $this->replaceCustomFieldLink(
                    $link->url,
                    $newUrl,
                    $table,
                    $instance,
                );
                //custom field
        }

        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $reparse              = true;
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $messageLinks),
                'succcess'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                //or updated in the custom fields
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                    'warning'
                );
            }
        }
        if ($reparse) {
            $this->parseContainer($instance->container_id);
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



    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_content&task=article.edit&id=' . (int)$instance->container_id
        );
    }
    public function getViewLink($instance): string
    {
        $currentId = $instance->container_id;
        if ($this->params->get('check_catid', 0)) {
            ['catid' => $catid, 'alias' => $alias, 'calias' => $calias] = $this->getInfoForId($currentId,'#__content');
            //we have all the stuff. So lets add it, save a query latet
            $link =  ContentRouteHelper::getArticleRoute($currentId . ':' . $alias, $catid . ':' . $calias);
        } else {
            $link =  ContentRouteHelper::getArticleRoute($currentId);
        }
        return Route::link(
            'site',
            $link

        );
    }

    protected function parseContainer(int $id): void
    {

        $table = $this->getContainerTableById($id);
        if ($table) {
            $this->parseContainerFields($table);
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
        if (!empty($images->image_intro)) {
            $extraLinks["image_intro"] = [
                "url"    => $images->image_intro,
                "anchor" => $images->image_intro_alt ?? $images->image_intro_caption ?? "Intro Image",
            ];
        }
        if (!empty($images->image_fulltext)) {
            $extraLinks["image_fulltext"] = [
                "url"    => $images->image_fulltext,
                "anchor" => $images->image_fulltext_alt ?? $images->image_fulltext_caption ?? "Full Image",
            ];
        }
        $urls               = json_decode($row->urls);
        if (!empty($urls->urla)) {
            $extraLinks["urla"] = [
                "url"    => $urls->urla,
                "anchor" => $urls->urlatext ?? "URL A",
            ];
        }
        if (!empty($urls->urlb)) {
            $extraLinks["urlb"] = [
                "url"    => $urls->urlb,
                "anchor" => $urls->urlbtext ?? "URL B",
            ];
        }
        if (!empty($urls->urlc)) {
            $extraLinks["urlc"] = [
                "url"    => $urls->urlc,
                "anchor" => $urls->urlctext ?? "URl C",
            ];
        }
        $this->processLinkByFields($extraLinks, $synchedId);
        if ($this->params->get('enablecf')) {
            $this->parseCustomFields($row, $synchedId);
        }
        $synchTable->setSynched();
    }
}
