<?php

declare(strict_types=1);

/**
 * @package     BLC
 * @subpackage  blc.yootheme
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Weblinks\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\Component\Weblinks\Administrator\Table\WeblinkTable;
use Joomla\Component\Weblinks\Site\Helper\RouteHelper as WeblinkRouteHelper;
use Joomla\Database\DatabaseQuery;
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


    protected function getContainerTable()
    {
        try {
            $db    = $this->getDatabase();
            $table = new WeblinkTable($db);
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
        $language->load('com_weblinks', JPATH_ADMINISTRATOR);


        $messageLinks = $this->getMessageLinks($instance);


        if (!$instance->parser) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ALT_SET_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_PARSER_NOT_SET')),
                'warning'
            );
            return;
        }

        $table        = $this->getContainerTableById($instance->container_id);

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
            }
            if (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }
            $this->replacedUrls[] = $newUrl;
            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                "Successful replaced $link->url} with $newUrl for field {$instance->field} in: $messageLinks",
                'success'
            );
        } else {
            if (\in_array($newUrl, $this->replacedUrls)) {
                //already replaced. This occurs if the same link is in the same container twice
                // should be cleared as we reach this point by the parseContainer above
            } else {
                Factory::getApplication()->enqueueMessage(
                    "Failed to replace {$link->url} with $newUrl for field {$instance->field} in: $messageLinks ",
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





    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_weblinks&task=weblink.edit&id=' . (int)$instance->container_id
        );
    }

    public function getViewLink($instance): string
    {
        $currentId                                                                           = (int)$instance->container_id;
        ['catid' => $catid, 'alias' => $alias, 'calias' => $calias, 'language' => $language] = $this->getInfoForId($currentId);
        return Route::link(
            'site',
            WeblinkRouteHelper::getWeblinkRoute("{$currentId}:{$alias}", "{$catid}:{$calias}", $language) //lets not fix
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
            ->from($db->quoteName('#__weblinks', 'a'))
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

        $extraLinks        = [];
        $extraLinks["url"] = [
            "url"    => $row->url, //required fields so both should have an value
            "anchor" => $row->title,
        ];


        if ($this->params->get('extractdescription', 0)) {
            $fields = [
                'description' => $row->description,
            ];
            $this->processText($fields, 'weblinks', $synchId);
        }

        if ($this->params->get('extractimages', 0)) {
            $images = json_decode($row->images);

            $extraLinks["image_first"] = [
                "url"    => $images->image_first ?? '', //these properties should exist. Might be empty
                "anchor" => ($images->image_first_alt ?? PARSE_STRINGS::BLC_EMPTY_ALT) ?: PARSE_STRINGS::BLC_EMPTY_ALT,
            ];
            $extraLinks["image_second"] = [
                "url"    => $images->image_second ?? '',
                "anchor" => ($images->image_second_alt ?? PARSE_STRINGS::BLC_EMPTY_ALT) ?: PARSE_STRINGS::BLC_EMPTY_ALT,
            ];
        }

        $this->processLinkByFields($extraLinks, $synchId);

        $synchTable->setSynched();
    }
}
