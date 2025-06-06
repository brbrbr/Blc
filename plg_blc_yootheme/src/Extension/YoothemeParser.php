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
use Blc\Component\Blc\Administrator\Interface\BlcParserInterface as PARSE_STRINGS;
use Blc\Component\Blc\Administrator\Parser\BlcParser;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

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
    private $params;
    /**
     * @var array
     *
     *  the type is used to detect yootheme content.
     *
     */
    protected string $parserName = 'Yootheme';
    private $allowedTypes        = ['fragment', 'layout'];
    protected bool $canSetAlt    = true;
    private $yoothemeTypes;

    /**
     * @since __DEPLOY_VERSION__
     *
     */

    public function getcanSetAlt(string $field = ''): bool
    {

        return ($field === '' || str_ends_with($field, '.' . self::ALT_TYPE)) ? true : false;
    }

    /**
     * @since __DEPLOY_VERSION__
     *
     */

    public function setAltInSource(string $source, string $currentUrl, string $newValue): string
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

        $textParsers  =  BlcParseController::getInstance();
        foreach ($this->contentFields as &$contentField) {
            //references referecnes
            //within the yootheme tree we have no clue how the link was found.
            $contentField  = $textParsers->setAltInSourceByParser('img', $contentField, $currentUrl, $newValue);
        }

        foreach ($this->contentImages as $contentImage) {
            if ($contentImage['url'] === $currentUrl) {
                $contentImage['anchor'] = $newValue; // url is reference
            }
        }

        $replacedText = json_encode($node);

        $replacedText = "{$preComment}{$replacedText}{$postComment}";

        return $replacedText;
    }

    /**
     *

     */
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
        //do not set the ARSE_STRINGS::BLC_EMPTY_ALT during parsing. We don't want to set it during replaceInSource
        foreach ($this->contentImages as &$imageLink) {
            switch ($imageLink['suffix'] ?? '') {
                case  self::ALT_TYPE_FILTER:
                    if (empty($imageLink['anchor'])) {
                        $imageLink['anchor'] = PARSE_STRINGS::BLC_EMPTY_ALT;
                    }
                    //intensional fall thru
                case  self::ALT_TYPE_EDIT: //could be set directly in parseYoothemeTree
                    $imageLink['suffix'] = self::ALT_TYPE;
                    break;
                default: //no default
            }
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


        if (\is_array($node)) {
            foreach ($node as &$child) {
                if (!empty($child->children)) {
                    self::parseYoothemeTree($child->children);
                }
                if (empty($child->props)) {
                    continue;
                }
                $objectId                                          = spl_object_id($child);
                $type                                              = $child->type;
                $fields                                            = $this->yoothemeTypes[$type] ?? [];
                if (! $fields) {
                    continue;
                }

                foreach ($fields as $field => $function) {
                    $childPropField = &$child->props->{$field};
                    if (empty($childPropField)) {
                        continue;
                    }
                    $paramKey = "{$type}_{$field}";
                    $paramKey = str_replace(['-', '.'], '_', $paramKey);
                    $paramDefault = match ($function) {
                        'image-with-image-alt' => 'f',
                        'image-field-with-background-image-alt' => 'f',
                        'image-field-with-title-label' => 'n',
                        'image-field-alt-no-edit' => 'n',
                        default => "n"
                    };
                    $whatAction = $this->params->get($paramKey, $paramDefault);



                    $key = "$type - $field - $objectId";
                    switch ($function) {
                        case 'plain':
                            //ignore
                            break;
                        case 'html':
                            /* almost always content but not always */

                            if (str_contains($childPropField, '<')) {
                                $this->contentFields[$key] = &$childPropField;
                            }

                            break;
                        case 'image-field-no-alt':
                            $this->contentImages[$key]       = ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_IMAGE_DECORATIVE'), 'suffix' => $type];

                            break;

                        case 'image-field-alt-no-edit':

                            $this->contentImages[$key]  = match ($whatAction) {
                                'f' => ['url' => &$childPropField, 'anchor' => &$child->props->image_alt, 'suffix' => self::ALT_TYPE_FILTER],
                                'e' => ['url' => &$childPropField, 'anchor' => &$child->props->image_alt, 'suffix' => self::ALT_TYPE_EDIT],
                                'd' => ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_IMAGE_DECORATIVE'), 'suffix' => $field],
                                default => ['url' => &$childPropField, 'anchor' => $child->props->image_alt, 'suffix' => $field],
                            };



                            break;

                        case 'image-field-with-background-image-alt':

                            if ($whatAction !== 'f' && !empty($child->props->background_image_alt)) {
                                $whatAction = 'e';
                            }
                            $this->contentImages[$key]  = match ($whatAction) {
                                'f' => ['url' => &$childPropField, 'anchor' => &$child->props->background_image_alt, 'suffix' => self::ALT_TYPE_FILTER],
                                'e' => ['url' => &$childPropField, 'anchor' => &$child->props->background_image_alt, 'suffix' => self::ALT_TYPE_EDIT],
                                'd' => ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_IMAGE_DECORATIVE'), 'suffix' => $field],
                                default => ['url' => &$childPropField, 'anchor' => $child->props->background_image_alt, 'suffix' => $field],
                            };

                            break;
                        case 'image-with-image-alt':
                            if ($whatAction !== 'f' && !empty($child->props->image_alt)) {
                                $whatAction = 'e';
                            }
                            $this->contentImages[$key]  = match ($whatAction) {
                                'f' => ['url' => &$childPropField, 'anchor' => &$child->props->image_alt, 'suffix' => self::ALT_TYPE_FILTER],
                                'e' => ['url' => &$childPropField, 'anchor' => &$child->props->image_alt, 'suffix' => self::ALT_TYPE_EDIT],
                                'd' => ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_IMAGE_DECORATIVE'), 'suffix' => $field],
                                default => ['url' => &$childPropField, 'anchor' => $child->props->image_alt, 'suffix' => $field],
                            };


                            break;


                        case 'image-field-with-title-label':

                            $this->contentImages[$key]  = match ($whatAction) {
                                'f' => ['url' => &$childPropField, 'anchor' => &$child->props->label, 'suffix' => self::ALT_TYPE_FILTER],
                                'e' => ['url' => &$childPropField, 'anchor' => &$child->props->label, 'suffix' => self::ALT_TYPE_EDIT],
                                'd' => ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_IMAGE_DECORATIVE'), 'suffix' => $field],
                                default => ['url' => &$childPropField, 'anchor' == match (true) {
                                    !empty($child->props->label)            => $child->props->label,
                                    !empty($child->props->title)           => $child->props->title,
                                    default                                => PARSE_STRINGS::BLC_EMPTY_ALT
                                }, 'suffix' => $field],
                            };
                            break;


                        case 'link-with-author':
                            $anchor = match (true) {
                                !empty($child->props->author) => $child->props->author,
                                default                       => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];
                            break;
                        case 'link-with-content':
                            $anchor = match (true) {
                                !empty($child->props->content) => $child->props->content,
                                default                        => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];

                            break;
                        case 'link-with-link-title':
                            $anchor = match (true) {
                                !empty($child->props->link_title) => $child->props->link_title,
                                default                           => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];

                            break;
                        case 'link-with-icon':
                            $anchor = match (true) {
                                !empty($child->props->icon) => $child->props->icon,
                                default                     => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];

                            break;
                        case 'link-with-icon-or-image-or-aria':
                            $anchor = match (true) {
                                !empty($child->props->icon)            => $child->props->icon,
                                !empty($child->props->image)           => $child->props->image,
                                !empty($child->props->link_aria_label) => $child->props->link_aria_label,
                                default                                => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];
                            break;

                        case 'link-with-image':
                            $anchor = match (true) {
                                !empty($child->props->image) => $child->props->image,
                                default                      => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];
                            break;
                        case 'link-with-link-text':
                            $anchor = match (true) {
                                !empty($child->props->link_text) => $child->props->link_text,
                                default                          => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];
                            break;
                        case 'link-with-no-anchor':
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_LINK_WITHOUT_ANCHOR'), 'suffix' => $type];
                            break;
                        case 'link-with-title-content':
                            $anchor = match (true) {
                                !empty($child->props->title)   => $child->props->title,
                                !empty($child->props->content) => $child->props->content,
                                default                        => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor, 'suffix' => $type];
                            break;
                        case 'video-with-no-title':
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => Text::_('COM_BLC_VIDEO_LINK'), 'suffix' => $type];

                            break;
                        case 'video-with-title':
                            $anchor = match (true) {
                                !empty($child->props->title) => $child->props->title,
                                default                      => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor];
                            break;
                        case 'video-with-video-title':
                            $anchor = match (true) {
                                !empty($child->props->video_title) => $child->props->video_title,
                                default                            => PARSE_STRINGS::BLC_EMPTY_ANCHOR
                            };
                            $this->contentLinks[$key] = ['url' => &$childPropField, 'anchor' => $anchor];
                            break;
                        case 'skip': /*do nothing*/
                            break;
                        default:
                            print "\nno match $type $field\n";
                            break;
                    }
                }
            }
        }
    }

    /**
     * Load the lookup table for the types and fields
     * @since __DEPLOY_VERSION__
     */
    private function loadYoothemeTypes()
    {
        $this->yoothemeTypes ??= include(JPATH_PLUGINS  .  '/blc/yootheme/includes/yoothemetree.php');
    }

    /**
     * Load the lookup table for the types and fields
     * @since __DEPLOY_VERSION__
     */
    private function loadParams()
    {
        $this->params = new Registry(PluginHelper::getPlugin('blc', 'yootheme')->params);
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

        //init and reset the parser

        $this->contentFields = [];
        //under the hood links and images are the same
        $this->contentImages = [];
        $this->contentLinks  = [];
        // unset($node->children);
        //there is no constructor for the parsers so do it here
        $this->loadYoothemeTypes();
        $this->loadParams();
        $this->parseYoothemeTree($node->children);

        return $node;
    }
}
