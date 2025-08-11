<?php

declare(strict_types=1);

/**
 * Fri, 06 Jun 2025 12:45:52 +0000
 * image-field-no-alt - decorative - filter:no edit:no
 * image-field-alt-no-edit - filter:yes edit:no
 * image-with-image-alt - filter:yes edit:yes
 * image-field-with-background-image-alt - filter:yes edit:yes
 * image-field-with-title-label - filter:yes (should never happen) edit:no
 *
 * filter value: PARSE_STRINGS::BLC_EMPTY_ALT - 'Empty-Alternative-Text'
 *
 */

\defined('_JEXEC') or die;

return  [
  'accordion_item' => [
    'content' => 'html',
    'image'   => 'image-with-image-alt',
    'link'    => 'link-with-link-text',
    'video'   => 'video-with-no-title',
  ],
  'alert' => [
    'content' => 'html',
    'link'    => 'link-with-title-content',
  ],
  'button_item' => [
    'dialog' => 'html',
    'link'   => 'link-with-link-title',
  ],
  'column' => [
    'image' => 'image-field-no-alt',
    'video' => 'video-with-video-title',
  ],
  'content' => [
    'content' => 'html',
  ],
  'description_list' => [
    'content' => 'html',
  ],
  'description_list_item' => [
    'content' => 'html',
    'link'    => 'link-with-content',
  ],
  'gallery_item' => [
    'content'     => 'html',
    'hover_image' => 'image-field-no-alt',
    'hover_video' => 'video-with-no-title',
    'image'       => 'image-with-image-alt',
    'link'        => 'link-with-link-text',
    'video'       => 'video-with-title',
  ],
  'grid_item' => [
    'content'     => 'html',
    'hover_image' => 'image-field-no-alt',
    'hover_video' => 'video-with-no-title',
    'image'       => 'image-with-image-alt',
    'link'        => 'link-with-link-text',
    'video'       => 'video-with-no-title',
  ],
  'headline' => [
    'content' => 'html',
    'link'    => 'link-with-content',
  ],
  'html' => [
    'content' => 'html',
  ],
  'icon' => [
    'link' => 'link-with-icon',
  ],
  'image' => [
    'image' => 'image-with-image-alt',
    'link'  => 'link-with-image',
  ],
  'list_item' => [
    'content' => 'html',
    'image'   => 'image-with-image-alt',
    'link'    => 'link-with-content',
  ],
  'map' => [
    'cluster_icon_1' => 'image-field-no-alt',
    'cluster_icon_2' => 'image-field-no-alt',
    'cluster_icon_3' => 'image-field-no-alt',
    'marker_icon'    => 'image-field-no-alt',
  ],
  'map_item' => [
    'content' => 'html',
    'image'   => 'image-with-image-alt',
    'link'    => 'link-with-link-text',
  ],
  'nav_item' => [
    'content' => 'plain',
    'image'   => 'image-with-image-alt',
    'link'    => 'link-with-content',
  ],
  'overlay' => [
    'content'     => 'html',
    'hover_image' => 'image-field-no-alt',
    'hover_video' => 'video-with-no-title',
    'image'       => 'image-with-image-alt',
    'link'        => 'link-with-link-text',
    'video'       => 'video-with-video-title',
  ],
  'overlay-slider_item' => [
    'content'     => 'html',
    'hover_image' => 'image-field-no-alt',
    'hover_video' => 'video-with-no-title',
    'image'       => 'image-with-image-alt',
    'link'        => 'link-with-link-text',
    'video'       => 'video-with-video-title',
  ],
  'panel' => [
    'content'     => 'html',
    'hover_image' => 'image-field-no-alt',
    'hover_video' => 'video-with-no-title',
    'image'       => 'image-with-image-alt',
    'link'        => 'link-with-link-text',
    'video'       => 'video-with-no-title',
  ],
  'panel-slider_item' => [
    'content'     => 'html',
    'hover_image' => 'image-field-no-alt',
    'hover_video' => 'video-with-no-title',
    'image'       => 'image-with-image-alt',
    'link'        => 'link-with-link-text',
    'video'       => 'video-with-no-title',
  ],
  'popover' => [
    'background_image' => 'image-field-with-background-image-alt',
    'image'            => 'image-with-image-alt',
  ],
  'popover_item' => [
    'content' => 'html',
    'image'   => 'image-with-image-alt',
    'link'    => 'link-with-link-text',
  ],
  'quotation' => [
    'content' => 'html',
    'link'    => 'link-with-author',
  ],
  'section' => [
    'image' => 'image-field-no-alt',
    'video' => 'video-with-video-title',
  ],
  'slideshow' => [
    'link' => 'link-with-no-anchor',
  ],
  'slideshow_item' => [
    'content'   => 'html',
    'image'     => 'image-with-image-alt',
    'link'      => 'link-with-link-text',
    'thumbnail' => 'image-field-alt-no-edit',
    'video'     => 'video-with-no-title',
  ],
  'social_item' => [
    'image' => 'image-field-no-alt',
    'link'  => 'link-with-icon-or-image-or-aria',
  ],
  'subnav_item' => [
    'content' => 'plain',
    'link'    => 'link-with-content',
  ],
  'switcher_item' => [
    'content'   => 'html',
    'image'     => 'image-with-image-alt',
    'link'      => 'link-with-link-text',
    'thumbnail' => 'image-field-with-title-label',
  ],
  'table_item' => [
    'content' => 'html',
    'image'   => 'image-with-image-alt',
    'link'    => 'link-with-link-text',
  ],
  'text' => [
    'content' => 'html',
  ],
  'video' => [
    'video'        => 'video-with-video-title',
    'video_poster' => 'image-field-no-alt',
  ],
];
