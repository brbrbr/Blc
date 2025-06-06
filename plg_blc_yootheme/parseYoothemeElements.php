<?php

/***
 * Generate a lookup table for the Yootheme Parser
 * Generate a test yootheme builder json
 * 
 * 
 */

// phpcs:disable PSR1.Files.SideEffects
$files        = glob(__DIR__ . '/../../templates/yootheme/packages/builder/elements/*/element.json');

$skipTypes   = [];
\define("_JEXEC", "1");
$mappedTypes = include(__DIR__ . '/includes/yoothemetree-raw.php');
$defaults    = [];
foreach ($files as $file) {
    $json    = file_get_contents($file);
    $element = json_decode($json);
    $name    = $element->name;

    $defaults[$name] = $element->defaults ?? new stdClass();

    $fieldset = $element->fieldset ?? null;
    if (! $fieldset) {
        continue;
    }

    $typeFields = $element->fields;
    $k          = get_object_vars($fieldset);
    if (\count(($k)) > 1) {
        print "ERROR fieldset with more then one element\n";
    }
    foreach ($fieldset->default->fields as $field) {
        if (!\is_object($field)) {
            continue;
        }
        $title = $field->title;
        if ($title !== 'Content') {
            continue;
        }
        $fields = $field->fields;
        foreach ($fields as $field) {
            if (\is_object($field)) {
                continue;
            }
            $type = $typeFields->{$field}->type ?? 'no-type';

            if (isset($mappedTypes[$name][$field])) {
                continue;
            }
            print "== $name with field $field and type $type\n";
            print "case '$field':\n";
        }
    }
}
$allAnchors = [];
$allLinks   = [];
//the extra should be in the middle so we can search for type-field and type-extra-field
$genImage   = function ($field, $extra, $type, ...$params) use (&$allLinks) {
    $allLinks[$extra] ??= [];
    $path = join(' ', array_filter(['invalid', $field, $extra, $type, ...$params, (string)\count($allLinks[$extra])]));
    $link = "https://dummyimage.com/600x400/000/fff&text=" . urlencode($path);
    $allLinks[$extra][] = $link;
    return $link;
};

$genAlt = function ($field, $extra, $type, ...$params) use ($allAnchors) {
    $allAnchors[$extra] ??= [];
    $text                 = join(' ', array_filter([$field, $extra, $type, ...$params, (string)\count($allAnchors[$extra])]));
    $anchor               = "ALT $text";
    $allAnchors[$extra][] = $anchor;
    return $anchor;
};

$genTitle = function ($field, $extra, $type, ...$params) use ($allAnchors) {
    $allAnchors[$extra] ??= [];
    $text                 = join(' ', array_filter([$field, $extra, $type, ...$params]));
    $anchor               = "Title $text";
    $allAnchors[$extra][] = $anchor;
    return $anchor;
};
$genLink = function ($field, $extra, $type, ...$params)  use (&$allLinks) {
    $allLinks[$extra] ??= [];
    $path               = join('/', array_filter([$field, $extra, $type, ...$params, (string)\count($allLinks[$extra])]));
    $link               = "https://phpunit.invalid/$path/page.html";
    $allLinks[$extra][] = $link;
    return $link;
};

$genVideo = function ($field, $extra, $type, ...$params) use (&$allLinks) {

    $allLinks[$extra] ??= [];
    $path               = join('/', array_filter([$field, $extra, $type, ...$params, (string)\count($allLinks[$extra])]));
    $link               = "https://youtube.com/$path";
    $allLinks[$extra][] = $link;
    return $link;
};

$genAnchor = function ($field, $extra, $type, ...$params) use ($allAnchors) {

    $allAnchors[$extra] ??= [];
    $text                 = join(' ', array_filter([$field, $extra, $type, ...$params]));
    $anchor               = "Anchor $text";
    $allAnchors[$extra][] = $anchor;
    return $anchor;
};

$genContent = function ($field, $extra, $type, ...$params)  use ($genImage, $genLink, $genAnchor, $genAlt) {
    $image   = $genImage($field, $extra, $type, ...$params);
    $link    = $genLink($field, $extra, $type, ...$params);
    $anchor  = $genAnchor($field, $extra, $type, ...$params);
    $alt     = $genAlt($field, $extra, $type, ...$params);
    $content = "<p>Dit is genereerde content. Niet alles werkt daardoor even lekker.</p><p>Dat er in alle afbeeldingen invalid staat heeft te maken met mijn test omgeving.</p><a href=\"$link\">$anchor</a><br><img src=\"$image\" alt=\"$alt\"/>";
    return $content;
};
$xmlList = [];
$addFormField = function ($type, $field, $default = 'f') use (&$xmlList) {
    $name = "{$type}_{$field}";
    $name = str_replace(['-', '.'], '_', $name);
    $nameLabel = strtoupper($name);
    $nameLower = strtolower($name);
    $default = strtolower($default);


    $xmlList[$name] = '
     <field name="' . $nameLower . '" type="radio" label="PLG_BLC_YOOTHEME_FIELD_' . $nameLabel . '_LBL" default="' . $default . '" class="btn-group rl-btn-group btn-group-md" description="PLG_BLC_YOOTHEME_FIELD_' . $nameLabel . '_DESC">
        <option value="f" class="btn btn-outline-info">PLG_BLC_YOOTHEME_FIELD_F_OPTION</option>
        <option value="e" class="btn btn-outline-info">PLG_BLC_YOOTHEME_FIELD_E_OPTION</option>
        <option value="d" class="btn btn-outline-info">PLG_BLC_YOOTHEME_FIELD_D_OPTION</option>
          <option value="n" class="btn btn-outline-info">PLG_BLC_YOOTHEME_FIELD_N_OPTION</option>
    </field>';
};
//have the _list items last this will create the parensts first
uksort($mappedTypes, fn($a, $b) => str_ends_with($a, '_item'));

$tree = [];

$pairs = [];

foreach ($mappedTypes as $type => $mappedType) {
    if (!str_contains($type, 'gallery')) {
        // continue;
    }
    if (str_ends_with($type, '_item')) {
        if (isset($mappedType['_media'])) {
            $k = 3;
        } else {
            $k = 2;
        }
    } else {
        $k = 1;
    }
    $alt = true;
    for ($i = 0; $i < $k; $i++) {
        $current        = new stdClass();
        $current->type  = $type;
        $current->props = isset($defaults[$type]) ? clone $defaults[$type] : new stdClass();

        if (isset($mappedType['_media'])) {
            $skip = ($i === 0) ? 'image' : 'video';
        } else {
            $skip = '';
        }


        foreach ($mappedType as $field => $function) {
            if ($field == $skip) {
                continue;
            }

            switch ($function) {
                case 'html':
                    $current->props->{$field} = $genContent($type, '', $field);
                    break;
                case 'plain':
                    $current->props->{$field} = $genAnchor($type, '', $field);
                    break;
                case 'image-field-no-alt':
                    $current->props->{$field} = $genImage($type, '', $field);
                    $pairs[]                  = [$current->props->{$field}, 'Decorative image (no alt)'];
                    break;
                case 'image-field-with-background-image-alt':
                    $addFormField($type, $field, 'f');
                    $current->props->background_image     = $genImage($type, '', $field);
                    $current->props->background_image_alt = $genAlt($type, '', $field);
                    $pairs[]                              = [$current->props->background_image, $current->props->background_image_alt];
                    break;
                case 'image-field-with-title-label':
                    $addFormField($type, $field, 'n');
                    $current->props->{$field} = $genImage($type, '', $field);
                    $current->props->label    = $genAlt($type, '', $field);
                    break;
                //no eentje
                case 'image-field-alt-no-edit':
                    $addFormField($type, $field, 'n');
                    if (!isset($current->props->image_alt)) {
                        $current->props->image_alt = $genAlt($type, '', $field);
                    }
                    $current->props->{$field} = $genImage($type, '', $field);
                    break;
                case 'image-with-image-alt':
                    $addFormField($type, $field, 'f');
                    if (!isset($current->props->image)) {
                        $current->props->image = $genImage($type, '', $field);
                    }
                    if ($alt) {
                        $current->props->image_alt = $genAlt($type, '', $field);
                    } else {
                        $current->props->image_alt = '';
                    }

                    $alt = false;
                    break;
                case 'link-with-author':
                    $current->props->link   = $genLink($type, '', $field);
                    $current->props->author = $genAnchor($type, '', $field);
                    break;
                case 'link-with-content':
                    $current->props->link = $genLink($type, '', $field);
                    //content should br set
                    break;
                case 'link-with-icon':
                    $current->props->link = $genLink($type, '', $field);
                    $current->props->icon = $genAnchor($type, '', $field);
                    break;
                //no een of twee
                case 'link-with-icon-or-image-or-aria':
                    $current->props->link = $genLink($type, '', $field);


                    break;
                case 'link-with-image':
                    if (!isset($current->props->image)) {
                        $current->props->image = $genImage($type, '', $field);
                    }
                    $current->props->link = $genLink($type, '', $field);
                    $pairs[]              = [$current->props->link, $current->props->image];
                    break;
                case 'link-with-link-text':
                    $current->props->link_text = $genAnchor($type, '', $field);
                    $current->props->link      = $genLink($type, '', $field);
                    $pairs[]                   = [$current->props->link, $current->props->link_text];
                    break;
                case 'link-with-link-title':
                    $current->props->link_title = $genAnchor($type, '', $field);
                    $current->props->link       = $genLink($type, '', $field);
                    break;
                case 'link-with-no-anchor':
                    $current->props->link = $genLink($type, '', $field);

                    break;
                //no eentje
                case 'link-with-title-content':
                    $current->props->title = $genAnchor($type, '', $field);
                    $current->props->link  = $genLink($type, '', $field);
                    break;
                case 'video-with-no-title':
                    $current->props->{$field} = $genVideo($type, '', $field);
                    break;
                case 'video-with-title':
                    $current->props->{$field} = $genVideo($type, '', $field);
                    $current->props->title    = $genTitle($type, '', $field);
                    break;
                case 'video-with-video-title':
                    $current->props->{$field}    = $genVideo($type, '', $field);
                    $current->props->video_title = $genAnchor($type, '', $field);
                    break;
                    case 'skip' :
                        //well skip
                        break;
                    default :
                    print "BOE BOE $type $field $function - not defined\n";
            }
        }

        if (str_ends_with($type, '_item')) {
            $parent                    = str_replace('_item', '', $type);
            $tree[$parent]->children[] = $current;
        } else {
            $current->children = [];
            $tree[$type]       = $current;
        }
        unset($current);
    }
}




$yoothemeTestFile = __DIR__ . '/../tests/assets/yootheme.json';

$yoothemwJson = json_decode(file_get_contents($yoothemeTestFile));


$yoothemwJson->children[0]->children[0]->children[0]->children = array_values($tree);

file_put_contents($yoothemeTestFile, json_encode($yoothemwJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$date = date(DATE_RFC2822);
$phpHeader = "<?php
/**
 * $date
 * image-field-no-alt - decorative - filter:no edit:no
 * image-field-alt-no-edit - filter:yes edit:no
 * image-with-image-alt - filter:yes edit:yes
 * image-field-with-background-image-alt - filter:yes edit:yes
 * image-field-with-title-label - filter:yes (should never happen) edit:no
 *
 * filter value: PARSE_STRINGS::BLC_EMPTY_ALT - 'Empty-Alternative-Text'
 * 
 */
defined('_JEXEC') or die;

return ";

//re- sort alphab
ksort($mappedTypes);

$data         = $phpHeader . var_export($mappedTypes, true) . ";\n";
//file_put_contents(__DIR__ . '/includes/yoothemetree-raw.php', $data);

$functionList = [];
foreach ($mappedTypes as &$mappedType) {
    $mappedType = array_filter($mappedType, fn($f) => strtolower($f) != 'skip');
    ksort($mappedType);
    foreach ($mappedType as $field => $function) {
        $functionList[$function] = $field;
    }
}

$mappedTypes  = array_filter($mappedTypes);
$data         = $phpHeader . var_export($mappedTypes, true) . ";\n";
file_put_contents(__DIR__ . '/includes/yoothemetree.php', $data);


if (true) {
    $yoothemeContentFile = __DIR__ . '/../tests/assets/yootheme-content.txt';
    $y                   = json_encode($yoothemwJson);
    file_put_contents($yoothemeContentFile, "<!-- $y -->");
}

$data         = "<?php\ndefined('_JEXEC') or die;\nreturn " . var_export($allLinks[''], true) . ";\n";
file_put_contents(__DIR__ . '/../tests/assets/expectedYoothemeLinks.php', $data);

$data         = "<?php\ndefined('_JEXEC') or die;\nreturn " . var_export($pairs, true) . ";\n";
file_put_contents(__DIR__ . '/../tests/assets/expectedYoothemePairs.php', $data);

ksort($xmlList);

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<form >
' . join("\n", $xmlList) . '
</form>';
echo __DIR__ . '/forms/fieldsedit.xml';
file_put_contents(__DIR__ . '/forms/fieldsedit.xml', $xml);
