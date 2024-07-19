<?php

/**
 * @package     BLC
 * @subpackage  blc.rspagebuilder
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\RsPageBuilder\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Blc\BlcParsers;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;

    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-rspagebuilder';
    protected $catids      = [];
    protected $context     = 'com_rspagebuilder.page';
    private $replacedUrls  = [];
    private $contentFields = [];
    private $counter       = 0;
    private $contentLinks  = [];

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
        //for some reason the thing doesn't boot correctly
        // $app = Factory::getApplication();
        // $mvcFactory = $app->bootComponent('com_rspagebuilder')->getMVCFactory();
        //  $model = $mvcFactory->createModel('page', 'RspagebuilderModel', ['ignore_request' => true]);
        // return $model->getTable('Article', 'Administrator');

        //  BaseModel::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_rspagebuilder/models');

        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_rspagebuilder/tables');
        $table = Table::getInstance('Page', 'RSPageBuilderTable', []);


        return $table;
    }


    public function replaceLink(object $link, object $instance, string $newUrl): void
    {
        $table = $this->getContainerTable();
        $table->load($instance->container_id);
        $viewHtml = HTMLHelper::_('blc.linkme', $this->getViewLink($instance), $this->getTitle($instance), 'replaced');
        if (!$table->id) {
            Factory::getApplication()->enqueueMessage(
                "Unable to replace {$link->url} in: $viewHtml by Yootheme (Article not found)",
                'warning'
            );
            return;
        }


        $node = $this->parseRsPageBuilderContent($table->content);

        if ($node !== false) {
            foreach ($this->contentFields as &$contentField) {
                //references referecnes
                $textParsers  =  BlcParsers::getInstance();
                $contentField =  $textParsers->replaceLinksParser(
                    $instance->parser,
                    $contentField,
                    $link->url,
                    $newUrl
                );
            }

            foreach ($this->contentLinks as $contentLink) {
                if ($contentLink['url'] === $link->url) {
                    $contentLink['url'] = $newUrl; // url is reference
                }
            }
        } else {
            Factory::getApplication()->enqueueMessage(
                "Unable to replace {$link->url} in: $viewHtml  by RsPageBuilder  (content invalid)",
                'warning'
            );
        }
        $replacedText = json_encode($node);
        if ($replacedText !== $table->content) {
            $table->content = $replacedText;
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }

            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                "{$link->url} Replaced with $newUrl in: $viewHtml / Yootheme",
                'succcess'
            );
        } else {
            Factory::getApplication()->enqueueMessage(
                "Unable to replace {$link->url} in: $viewHtml  / Yootheme (Link not found)",
                'warning'
            );
        }
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from('`#__rspagebuilder` `a`');

        if (!$idOnly) {
            $query->select('`a`.`title`,`a`.`content`')
                ->select('`a`.`modified`')
                ->order('`modified` DESC');
        }

        if ($this->getParamLocalGlobal('access')) {
            $query->where('`a`.`access` IN (1)');
        }
        if ($this->getParamLocalGlobal('published')) {
            $query->where('`a`.`published` = 1');
        } else {
            $query->where('`a`.`published` > -1'); //ignore trashed
        }


        return $query;
    }

    public function getTitle($instance): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('`#__rspagebuilder`')->select('`title`')->where('`id` = ' . (int)$instance->container_id);
        $db->setQuery($query);
        return $db->loadResult() ?? 'Not found';
    }


    public function getEditLink($instance): string
    {
        return Route::link(
            'administrator',
            'index.php?option=com_rspagebuilder&view=page&layout=edit&id=' . (int)$instance->container_id
        );
    }

    public function getViewLink($instance): string
    {

        return Route::link(
            'site',
            'index.php?option=com_rspagebuilder&view=page&id=' . (int)$instance->container_id
        );
    }

    protected function parseContainer(int $id)
    {
        $db    = $this->getDatabase();
        $query = $this->getQuery();
        $query->where('`a`.`id` = :containerId')
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
        $this->parseRsPageBuilderContent($row->content);
        $synchedId = $synchTable->id;
        $this->purgeInstances($synchedId);

        if ($this->contentFields) {
            $this->processText($this->contentFields, 'yootheme-content', $synchedId);
        }

        if ($this->contentLinks) {
            $this->processLinks($this->contentLinks, 'yootheme-links', $synchedId);
        }


        $synchTable->setSynched();
    }


    private function parseRsPageBuilderContent($content): bool | object | array
    {
        $node = json_decode($content);

        if (!$node) {
            return false;
        }
        $this->contentFields = [];
        //under the hood links and images are the same

        $this->contentLinks = [];
        // unset($node->children);
        //   $this->parseRsPageBuildertree($node->children);
        $this->parseRsPageBuilderTree($node);
        return $node;
    }

    private function parseRsPageBuilderTree(&$node)
    {
        //technically this is a parser, however only used here so not a lot of benefit to create a seperate parsers
        //RecursiceIteratorItaraor might work as well, but not everthing is needed.

        //a lot of referecing, so we can use the parsed arrays to replace.
        $this->counter++;
        foreach ($node as $key => &$child) {
            if (\is_object($child)) {
                self::parseRsPageBuilderTree($child);
            }
            if (\is_array($child)) {
                self::parseRsPageBuilderTree($child);
            }

            switch ($key) {
                case 'content':
                case 'item_content':
                    if (strpos($child, '<') !== false) {
                        $this->contentFields[$key . '-' . $this->counter] = &$child;
                    }
                    break;
                case 'video_url':
                case 'button_url':
                case 'button_url-1':
                case 'button_url-2':
                case 'button_url-3':
                case 'button_url-4':
                case 'item_url-1':
                case 'item_url-2':
                case 'item_url-3':
                case 'item_url-4':
                case 'url':
                case 'image':
                case 'client_avatar_url':
                    $this->contentLinks[$key . '-' . $this->counter] = ['url' => &$child];
                    break;
            }
        }
    }
}
