<?php

/**
 * @package     BLC
 * @subpackage  blc.sppagebuilder
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\SpPageBuilder\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcExtractInterface;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcExtractInterface
{
    use BlcHelpTrait;



    private const HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-sppagebuilder';
    protected $catids      = [];
    protected $context     = 'com_sppagebuilder.editor';
    private $replacedUrls  = [];
    private $contentFields = [];
    private $counter       = 0;
    private $contentLinks  = [];
    private $parsing       = '';

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
        $tableFile = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/tables/page.php';
        if (!class_exists('SppagebuilderTablePage') && file_exists($tableFile)) {
            require_once  $tableFile;
        }
        try {
            $db    = $this->getDatabase();
            $table = new \SppagebuilderTablePage($db);
        } catch (\Error) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_GETCONTAINERTABLE_ERROR'),
                'warning'
            );
            return false;
        }

        return $table;
    }




    public function replaceLink(object $link, object $instance, string $newUrl): void
    {
        $table = $this->getContainerTableById($instance->container_id);
        if (!$table) {
            return;
        }

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
        $where          = explode('-', $instance->field);
        $field          = $where[0] ?? 'text';
        $orginalContent = $table->$field;


        $contentNodes = $this->parseSpPageBuilderContent($orginalContent);
        if ($contentNodes === null) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_INVALID_ERROR')),
                'warning'
            );
        }

        $textParsers  =  BlcParseController::getInstance();
        foreach ($this->contentFields as &$contentField) {
            //references referecnes

            $contentField =  $textParsers->replaceLinkInSourceByParser(
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


        $replacedContent = json_encode($contentNodes);
        if ($replacedContent !== $orginalContent) {
            $table->$field = $replacedContent;
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }

            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, "SpPageBuilder $field", $messageLinks),
                'success'
            );
        } else {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, "SpPageBuilder $field", $messageLinks, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                'warning'
            );
        }
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.{$this->primary}", 'id'))
            ->from('`#__sppagebuilder` `a`');

        if (!$idOnly) {
            $query->select($db->quoteName('a.title'))
                ->select($db->quoteName('a.text'))
                ->select($db->quoteName('a.content'))
                ->select($db->quoteName('a.modified'))
                ->order($db->quoteName('a.modified') . ' DESC');
        }

        if ($this->getParamLocalGlobal('access')) {
            $query->where($db->quoteName('a.access') . '= 1');
        }
        if ($this->getParamLocalGlobal('published')) {
            $query->where($db->quoteName('a.published') . '= 1');
        } else {
            $query->where($db->quoteName('a.published') . '> -1'); //ignore trashed
        }


        return $query;
    }



    public function getEditLink($instance): string
    {
        $table = $this->getContainerTableById($instance->container_id);
        if (!$table) {
            return '';
        }

        list(
            'extension'      => $extension,
            'extension_view' => $extension_view,
            'view_id'        => $view_id,
            'id'             => $pageId
        ) = (array)$table;
        return match ($extension) {
            'com_content' => Route::link(
                'administrator',
                'index.php?option=com_sppagebuilder&view=editor&tmpl=component&extension=' . $extension . '&article_id=' . $view_id . '&extension_view=' . $extension_view . '#/editor/' . $pageId
            ),
            default => Route::link(
                'administrator',
                'index.php?option=com_sppagebuilder&view=editor&tmpl=component&extension=' . $extension .  '&extension_view=' . $extension_view . '#/editor/' . $pageId
            ),
        };
    }

    public function getViewLink($instance): string
    {

        $table = $this->getContainerTableById($instance->container_id);
        if (!$table) {
            return '';
        }

        $routerHelperFile = JPATH_ROOT . '/components/com_sppagebuilder/helpers/route.php';
        if (!class_exists('SppagebuilderHelperRoute') && file_exists($routerHelperFile)) {
            require_once  $routerHelperFile;
        }

        list(
            'extension' => $extension,
            'language'  => $language,
            'view_id'   => $view_id,
            'catid'     => $catid,
            'id'        => $pageId
        ) = (array)$table;
        return match ($extension) {
            'mod_sppagebuilder' => '',
            'com_content'       => Route::link(
                'site',
                'index.php?option=com_content&view=article&id=' . $view_id . '&catid=' . $catid
            ),
            default => \SppagebuilderHelperRoute::getPageRoute($pageId, $language),
        };
    }


    protected function parseContainerFields($row): void
    {
        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);
        $synchId    = $synchTable->id;
        $synchId    = $synchTable->id;
        if (!$synchId) {
            //creation failed most likely due to concurrent jobs
            //ignore next job will retry
            return;
        }
        $this->purgeInstances($synchId);

        foreach (['text', 'content'] as $field) {
            $this->parsing = $field;
            $this->parseSpPageBuilderContent($row->$field);
            if ($this->contentFields) {
                foreach ($this->contentFields as $content) {
                    //we could pass the arre of comtentFields, but then they will have a field like content-1 content-2.
                    //this save the links with $field
                    $this->processText($content, $field, $synchId);
                }
            }

            if ($this->contentLinks) {
                $this->processLinks($this->contentLinks, $field, $synchId);
            }
        }

        $synchTable->setSynched();
    }


    private function parseSpPageBuilderContent($content): bool | object | array
    {
        $this->contentFields = [];
        //under the hood links and images are the same

        $this->contentLinks = [];


        $node = json_decode($content);
        if (\is_string($node)) {
            //imported content is not always saved correctly
            $node = json_decode($node);
        }

        if (!$node) {
            return false;
        }

        // unset($node->children);
        //   $this->parseSpPageBuildertree($node->children);
        $this->parseSpPageBuilderTree($node);
        return $node;
    }

    private function parseSpPageBuilderTree(&$node)
    {
        //technically this is a parser, however only used here so not a lot of benefit to create a seperate parsers
        //RecursiceIteratorItaraor might work as well, but not everthing is needed.

        //a lot of referecing, so we can use the parsed arrays to replace.


        foreach ($node as $key => &$child) {
            if (\is_object($child)) {
                self::parseSpPageBuilderTree($child);
            }
            if (\is_array($child)) {
                self::parseSpPageBuilderTree($child);
            }

            switch ($key) {
                case 'text':
                    if (\is_string($child)) {
                        if (str_contains($child, '<')) {
                            $this->counter++;
                            $this->contentFields["{$this->parsing}-{$this->counter}"] = &$child;
                        }
                    }
                    break;
                case 'image':
                    $this->counter++;
                    if (\is_string($child)) {
                        $this->contentLinks["{$this->parsing}-{$this->counter}"] = ['url' => &$child];
                    } elseif (isset($child->src)) {
                        $this->contentLinks["{$this->parsing}-{$this->counter}"] = ['url' => &$child->src];
                    }
                    break;
            }
        }
    }
}
