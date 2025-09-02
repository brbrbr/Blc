<?php

declare(strict_types=1);

/**
 * @package     BLC
 * @subpackage  blc.yootheme
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Content\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Component\Blc\Administrator\Interface\BlcSetAltInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Blc\Component\Blc\Administrator\Traits\BlcSetAltTrait;
use Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Administrator\Table\ArticleTable;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface, BlcSetAltInterface
{
    use BlcHelpTrait;
    use BlcSetAltTrait;
    use CustomFieldsTrait {
        CustomFieldsTrait::__construct as private __cftConstruct;
    }


    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-content';
    protected $catids      = [];
    protected $context     = 'com_content.article';
    private $replacedUrls  = [];

    protected $canSetAltFields = [
        'fulltext'                            => BlcSetAltInterface::BLC_REPLACE_ALT_PARSER,
        'fulltext.' . PARSE_STRINGS::ALT_TYPE => BlcSetAltInterface::BLC_REPLACE_ALT_PARSER,
        'introtext'                           => BlcSetAltInterface::BLC_REPLACE_ALT_PARSER,
        'image_intro'                         => BlcSetAltInterface::BLC_REPLACE_ALT_YES,
        'image_fulltext'                      => BlcSetAltInterface::BLC_REPLACE_ALT_YES,
    ];

    public function __construct(array $config = [])
    {

        parent::__construct($config);

        $this->__cftConstruct();
    }

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
        $checker        = $event->getItem();
        $contentChecker = ContentChecker::getInstance();
        $contentChecker->setParams($this->params);
        $contentChecker->setDatabase($this->getDatabase());
        $checker->registerChecker($contentChecker, 20);
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

    /**
     * This wil set the alt attribute for a given link
     * that might be a 'alt' attribute in html or fields like image_alt
     *
     * @param LinkTable $link
     * @param object $instance  - join of instance and synch
     * @param string $newAlt
     *
     * @since 25.44.7548
     *
     */
    public function setAlt(LinkTable $link, object $instance, string $newAlt): void
    {
        //Todo just once
        $language =  Factory::getApplication()->getLanguage();
        $language->load('com_content', JPATH_ADMINISTRATOR);
        //$language->load('com_category', JPATH_ADMINISTRATOR);

        $messageLinks = $this->getMessageLinks($instance);

        if (!$instance->parser) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ALT_SET_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_PARSER_NOT_SET')),
                'warning'
            );
            return;
        }

        $table = $this->getContainerTableById($instance->container_id);



        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ALT_SET_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_NOT_FOUND_ERROR')),
                'warning'
            );
            return;
        }
        //Actually it is not to bad if someone is editing. The replaced link is simply overwritten again.
        if ($table->checked_out) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ALT_SET_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_CHECKED_OUT_ERROR')),
                'warning'
            );
            return;
        }

        $update  = false;
        $reparse = false;

        $field = $this->cleanField($instance->field);

        switch ($field) {
            case 'introtext':
            case 'fulltext':
                $text         = $table->{$field};
                $textParsers  =  BlcParseController::getInstance();
                $replacedText = $textParsers->setAltInSourceByParser($instance->parser, $text, $link->url, $newAlt);

                if ($replacedText !== $text) {
                    $table->{$field} = $replacedText;
                    $update          = true;
                }
                break;
            case 'image_intro':
            case 'image_fulltext':
                $images   = json_decode($table->images);
                $altField = "{$field}_alt";
                $alt      = $images->{$altField} ?? '';
                $url      = $images->{$field} ?? '';

                if ($url == $link->url && $alt != $newAlt) {
                    $images->{$altField} = $newAlt;
                    $table->images       = json_encode($images);
                    $update              = true;
                }
                break;
        }

        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } 
            
            if (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }

            $this->replacedUrls[] = $newAlt;
            $reparse              = true;
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ALT_SET_FIELD_SUCCESS', $link->url, $newAlt, $field, $messageLinks),
                'success'
            );
        } else {
            if (\in_array($newAlt, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                //or updated in the custom fields
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('PLG_BLC_ALT_SET_FIELD_ERROR', $link->url, $field, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                    'warning'
                );
            }
        }

        if ($reparse) {
            $this->parseContainer($instance->container_id);
        }
    }


    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {
        //Todo just once
        $language =  Factory::getApplication()->getLanguage();
        $language->load('com_content', JPATH_ADMINISTRATOR);
        //$language->load('com_category', JPATH_ADMINISTRATOR);

        $messageLinks = $this->getMessageLinks($instance);

        if (!$instance->parser) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_PARSER_NOT_SET')),
                'warning'
            );
            return;
        }

        $table = $this->getContainerTableById($instance->container_id);

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

        $field = $this->cleanField($instance->field);

        switch ($field) {
            case 'introtext':
            case 'fulltext':
                $text         = $table->{$field};
                $textParsers  =  BlcParseController::getInstance();
                $replacedText = $textParsers->replaceLinkInSourceByParser($instance->parser, $text, $link->url, $newUrl);

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
                    $table->images    = json_encode($images);
                    $update           = true;
                }

                break;
            case 'urla':
            case 'urlb':
            case 'urlc':
                $urls = json_decode($table->urls);
                $url  = $urls->{$field} ?? '';
                if ($url == $link->url && $url != $newUrl) {
                    $urls->{$field} = $newUrl;
                    $table->urls    = json_encode($urls);
                    $update         = true;
                }

                break;
            case 'fields':
                $reparse = $this->replaceCustomFieldLink(
                    $link->url,
                    $newUrl,
                    $table,
                    $instance
                );
        }

        if ($update) {
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } 
            
            if (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $reparse              = true;
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $messageLinks),
                'success'
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
        $currentId                                                                           = $instance->container_id;
        ['catid' => $catid, 'alias' => $alias, 'calias' => $calias, 'language' => $language] = $this->getInfoForId($currentId);
        if ($this->params->get('check_catid', 0)) {
            //we have all the stuff. So lets add it, save a query latet
            $link =  ContentRouteHelper::getArticleRoute($currentId . ':' . $alias, $catid . ':' . $calias, $language);
        } else {
            $link =  ContentRouteHelper::getArticleRoute($currentId, language: $language);
        }
        return Route::link(
            'site',
            $link
        );
    }




    /**
     * Helper function to get some meta data from the container
     *
     * @since 25.44.7314
     * @var int $id

     *
     * @return array
     */

    private function getInfoForId(int $id): array
    {
        //caching? Maybe.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.catid", 'catid'))
            ->select($db->quoteName("a.alias", 'alias'))
            ->select($db->quoteName("c.alias", 'calias'))
            ->select($db->quoteName("a.language", 'language'))
            ->from($db->quoteName('#__content', 'a'))
            ->innerJoin($db->quoteName('#__categories', 'c'), $db->quoteName("a.catid") . ' = ' . $db->quoteName("c.id"))
            ->where("{$db->quoteName('a.id')} = :containerId")
            ->bind(':containerId', $id);
        $db->setQuery($query);

        return  $db->loadAssoc() ?? ['catid' => 0, 'alias' => '', 'calias' => '', 'language' => ''];
    }


    protected function parseContainerFields($row): void
    {
        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);
        $synchId    = $synchTable->id;
        if (!$synchId) {
            //creation failed most likely due to concurrent jobs
            //ignore next job will retry
            return;
        }
        $this->purgeInstances($synchId);
        $fields = [
            'introtext' => $row->introtext,
            'fulltext'  => $row->fulltext,
        ];

        $this->processText($fields, 'content', $synchId);

        $images                    = json_decode($row->images);
        $extraLinks                = [];
        //all properties should have a value ( might be empty). the null-colate just to ensure.
        if (!empty($images->image_intro)) {
            $extraLinks["image_intro"] = [
                "url"    => $images->image_intro,
                "anchor" => ($images->image_intro_alt ?? PARSE_STRINGS::BLC_EMPTY_ALT) ?: PARSE_STRINGS::BLC_EMPTY_ALT,
            ];
        }
        if (!empty($images->image_fulltext)) {
            $extraLinks["image_fulltext"] = [
                "url"    => $images->image_fulltext,
                "anchor" => ($images->image_fulltext_alt ?? PARSE_STRINGS::BLC_EMPTY_ALT) ?: PARSE_STRINGS::BLC_EMPTY_ALT,
            ];
        }
        $urls               = json_decode($row->urls);
        if (!empty($urls->urla)) {
            $extraLinks["urla"] = [
                "url"    => $urls->urla,
                "anchor" => ($urls->urlatext ?? PARSE_STRINGS::BLC_EMPTY_ANCHOR) ?: PARSE_STRINGS::BLC_EMPTY_ANCHOR,
            ];
        }
        if (!empty($urls->urlb)) {
            $extraLinks["urlb"] = [
                "url"    => $urls->urlb,
                "anchor" => ($urls->urlbtext ?? PARSE_STRINGS::BLC_EMPTY_ANCHOR) ?: PARSE_STRINGS::BLC_EMPTY_ANCHOR,
            ];
        }
        if (!empty($urls->urlc)) {
            $extraLinks["urlc"] = [
                "url"    => $urls->urlc,
                "anchor" => ($urls->urlctext ?? PARSE_STRINGS::BLC_EMPTY_ANCHOR) ?: PARSE_STRINGS::BLC_EMPTY_ANCHOR,
            ];
        }
        $this->processLinkByFields($extraLinks, $synchId);
        if ($this->params->get('enablecf')) {
            $this->parseCustomFields($row, $synchId);
        }
        $synchTable->setSynched();
    }
}
