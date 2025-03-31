<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Yootheme\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface;
use Blc\Component\Blc\Administrator\Parser\BlcParser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class YoothemeParser extends BlcParser implements BlcParserInterface
{
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    private const PATTERN = '/^(<!-- )?(\{.*\})( -->)?$/'; //match both article as module

    private $contentFields = [];
    private $contentImages = [];
    private $contentLinks  = [];

    /**
     * @var array
     *
     *  the type is used to detect yootheme content.
     *
     */
    protected string $parserName = 'Yootheme';
    private $allowedTypes        = ['fragment', 'layout'];


    #[\Override]
    public function replaceInSource(string $source, string $oldUrl, string $newUrl): string
    {

        //$matches is used further down.
        if (! preg_match(self::PATTERN, $source, $matches)) {
            return $source;
        }
        //modules and articles are saved differently
        $preComment  = $matches[1] ?? '';
        $content     = $matches[2] ?? '';
        $postComment = $matches[3] ?? '';
        if (!$content) {
            return $source;
        }

        $node = $this->parseYoothemeContent($content);

        if ($node === false) {
            return $source;
        }

        $parseController  =  BlcParseController::getInstance();

        foreach ($this->contentFields as &$contentField) {
            //references referecnes
            //within the yootheme tree we have no clue how the link was found.
            $contentField =  $parseController->replaceLinkInSourceInAllParsers(
                $contentField,
                $oldUrl,
                $newUrl
            );
        }
        foreach ($this->contentImages as $contentImage) {
            if ($contentImage['url'] === $oldUrl) {
                $contentImage['url'] = $newUrl; // url is reference
            }
        }
        foreach ($this->contentLinks as $contentLink) {
            if ($contentLink['url'] === $oldUrl) {
                $contentLink['url'] = $newUrl; // url is reference
            }
        }

        $replacedText = json_encode($node);
        $replacedText = "{$preComment}{$replacedText}{$postComment}";
        return $replacedText;
    }

    public function extractfromSource(string $content): array
    {

        $textLinks = [];
        $content   = preg_match(self::PATTERN, $content, $matches) ? $matches[2] : null;
        if (!$content) {
            return [];
        }
        if ($this->parseYoothemeContent($content) === false) {
            return [];
        }
        if ($this->contentFields) {
            $parseController   =  BlcParseController::getInstance();
            $textLinks         = $parseController->extractAndStoreLinks($this->contentFields, [], store: false);
        }


        return
            array_merge(
                array_merge(...array_values($textLinks)),
                array_values($this->contentImages),
                array_values($this->contentLinks)
            );
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

                if (!empty($child->props->content)) {
                    if (str_contains($child->props->content, '<')) {
                        $objectId                                   = spl_object_id($child);
                        $this->contentFields['text - ' . $objectId] = &$child->props->content;
                    }
                }
                if (!empty($child->props->hover_image)) {
                    $anchor                                            = $child->props->title ?? 'Img without Title';
                    $objectId                                          = spl_object_id($child);
                    $this->contentImages['hover_image - ' . $objectId] = ['url' => &$child->props->hover_image, 'anchor' => $anchor];
                }

                if (!empty($child->props->image)) {
                    $anchor                                      = $child->props->title ?? 'Img without Title';
                    $objectId                                    = spl_object_id($child);
                    $this->contentImages['image - ' . $objectId] = ['url' => &$child->props->image, 'anchor' => $anchor];
                }

                if (!empty($child->props->icon)) {
                    //not clear what yootheme does with icons. Appears that custom image links can't be used
                    //so this could be removed completley
                    //let's add the link only of it doesn't look like an icon tag
                    if (!preg_match('#^[0-9a-z\-]+$#', $child->props->icon)) {
                        $anchor                                      = $child->props->type ?? 'Icon';
                        $objectId                                    = spl_object_id($child);
                        $this->contentImages['icon - ' . $objectId]  = ['url' => &$child->props->icon, 'anchor' => $anchor];
                    }
                }


                if (!empty($child->props->link)) {
                    $anchor                                   = $child->props->content ?? $child->props->link_text ?? 'Link without Anchor';
                    $objectId                                 = spl_object_id($child);
                    $this->contentLinks['link -' . $objectId] = ['url' => &$child->props->link, 'anchor' => $anchor];
                }

                if (!empty($child->props->video)) {
                    $anchor                                    = $child->props->content ?? $child->props->link_text ?? 'Link without Anchor';
                    $objectId                                  = spl_object_id($child);
                    $this->contentLinks['video -' . $objectId] = ['url' => &$child->props->video, 'anchor' => $anchor];
                }
                if (!empty($child->props->hover_video)) {
                    $anchor                                          = $child->props->content ?? $child->props->link_text ?? $child->props->title ?? 'Link without Anchor';
                    $objectId                                        = spl_object_id($child);
                    $this->contentLinks['hover_video -' . $objectId] = ['url' => &$child->props->hover_video, 'anchor' => $anchor];
                }
            }
        }
    }


    private function parseYoothemeContent($content): bool | object
    {

        $node    = json_decode($content);
        //try to detect 'valid'' yootheme content
        if (
            !$node ||
            empty($node->version) ||
            empty($node->type) ||
            !\in_array($node->type, $this->allowedTypes) ||
            empty($node->children)
        ) {
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
}
