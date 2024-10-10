<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Yootheme\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParsers;
use Blc\Plugin\Blc\Content\Extension\BlcPluginActor as BlcContentActor;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\Database\DatabaseQuery;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class BlcPluginActor extends BlcContentActor
{
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    private const PATTERN = '/^<!-- (\{.*\}) -->/';


    protected $context     = 'com_content.article';
    private $contentFields = [];
    private $contentImages = [];
    private $contentLinks  = [];

    public static function getSubscribedEvents(): array
    {

        return [
            'onBlcExtract'            => 'onBlcExtract',
            'onBlcContainerChanged'   => 'onBlcContainerChanged',
            'onBlcExtensionAfterSave' => 'onBlcExtensionAfterSave',
        ];
    }


    public function replaceLink(object $link, object $instance, string $newUrl): void
    {
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
        $field = 'Yootheme';

        $node = $this->parseYoothemeContent($table->fulltext);

        if ($node === false) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_CONTAINER_ERROR', $link->url, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_INVALID_ERROR')),
                'warning'
            );
            return;
        }

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
        foreach ($this->contentImages as $contentImage) {
            if ($contentImage['url'] === $link->url) {
                $contentImage['url'] = $newUrl; // url is reference
            }
        }
        foreach ($this->contentLinks as $contentLink) {
            if ($contentLink['url'] === $link->url) {
                $contentLink['url'] = $newUrl; // url is reference
            }
        }

        $replacedText = json_encode($node);
        $replacedText = "<!-- {$replacedText} -->";
        if ($replacedText !== $table->fulltext) {
            $table->fulltext = $replacedText;
            if (!$table->check()) {
                throw new GenericDataException($table->getError(), 500);
            } elseif (!$table->store()) {
                throw new GenericDataException($table->getError(), 500);
            }

            $this->parseContainer($instance->container_id);
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_SUCCESS', $link->url, $newUrl, $field, $viewHtml),
                'succcess'
            );
        } else {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_BLC_ANY_REPLACE_FIELD_ERROR', $link->url, $field, $viewHtml, Text::_('PLG_BLC_ANY_REPLACE_LINK_NOT_FOUND_ERROR')),
                'warning'
            );
        }
    }

    protected function getQuery(bool $idOnly = false): DatabaseQuery
    {
        $query = parent::getQuery($idOnly);
        $query->where('`a`.`fulltext` like \'<!--%\'')
            ->where('`a`.`fulltext` like \'%-->\'');
        return $query;
    }


    private function parseYoothemeTree(&$node)
    {
        //technically this is a parser, however only used here so not a lot of benefit to create a seperate parsers
        //RecursiceIteratorItaraor might work as well, but not everthing is needed.

        //a lot of referecing, so we can use the parsed arrays to replace.
        if (\is_array($node)) {
            foreach ($node as &$child) {
                if (!empty($child->children)) {
                    self::parseYoothemeTree($child->children);
                }

                if (isset($child->props->content)) {
                    $text = &$child->props->content;
                    if (strpos($text, '<') !== false) {
                        $objectId                                = spl_object_id($child);
                        $this->contentFields['text' . $objectId] = &$text;
                    }
                }

                if (isset($child->props->image)) {
                    $image                                    = &$child->props->image;
                    $anchor                                   = $child->props->title ?? 'Img without Title';
                    $objectId                                 = spl_object_id($child);
                    $this->contentImages['image' . $objectId] = ['url' => &$image, 'anchor' => $anchor];
                }

                if (isset($child->props->link)) {
                    $link                                   = &$child->props->link;
                    $anchor                                 = $child->props->content ?? $child->props->link_text ?? 'Link without Anchor';
                    $objectId                               = spl_object_id($child);
                    $this->contentLinks['link' . $objectId] = ['url' => &$link, 'anchor' => $anchor];
                }
            }
        }
    }


    protected function parseYoothemeContent($content): bool | object
    {

        $content = preg_match(self::PATTERN, $content, $matches) ? $matches[1] : null;
        $node    = json_decode($content);
        if (!$node) {
            return false;
        }
        $this->contentFields = [];
        //under the hood links and images are the same
        $this->contentImages = [];
        $this->contentLinks  = [];
        // unset($node->children);
        $this->parseYoothemeTree($node->children);
        return $node;
    }
    protected function parseContainerFields($row): void
    {
        $id         = $row->id;
        $synchTable = $this->getItemSynch($id);

        if ($this->parseYoothemeContent($row->fulltext) !== false) {
            $synchedId = $synchTable->id;
            $this->purgeInstances($synchedId);
            if ($this->contentFields) {
                $this->processText($this->contentFields, 'yootheme-content', $synchedId);
            }
            if ($this->contentImages) {
                $this->processLinks($this->contentImages, 'yootheme-images', $synchedId);
            }
            if ($this->contentLinks) {
                $this->processLinks($this->contentLinks, 'yootheme-links', $synchedId);
            }
        }

        $synchTable->setSynched();
    }
}
