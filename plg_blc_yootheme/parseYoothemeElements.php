<?php

$files        = glob(__DIR__ . '/../../templates/yootheme/packages/builder/elements/*/element.json');

$skipTypes   = [];
define("_JEXEC", "1");
$mappedTypes = include(__DIR__ . '/includes/yoothemetree-raw.php');
$defaults = [];
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
$allLinks = [];
$genImage = function ($field, $extra, $type) {
    global $allLinks;
    $allLinks[$extra] ??= [];
    $path = join('/', array_filter([$field, $extra, $type, (string)count($allLinks[$extra])]));
    $link = "https://phpunit.invalid/$path/image.png";
    $allLinks[$extra][] = $link;
    return $link;
};

$genAlt = function ($field, $extra, $type) {
    global $allAnchors;
    $allAnchors[$extra] ??= [];
    $text = join(' ', array_filter([$field, $extra, $type]));
    $anchor = "ALT $text";
    $allAnchors[$extra][] = $anchor;
    return $anchor;
};

$genTitle = function ($field, $extra, $type) {
    global $allAnchors;
    $allAnchors[$extra] ??= [];
    $text = join(' ', array_filter([$field, $extra, $type]));
    $anchor = "Title $text";
    $allAnchors[$extra][] = $anchor;
    return $anchor;
};
$genLink = function ($field, $extra, $type) {
    global $allLinks;
    $allLinks[$extra] ??= [];
    $path = join('/', array_filter([$field, $extra, $type, (string)count($allLinks[$extra])]));
    $link = "https://phpunit.invalid/$path/page.html";
    $allLinks[$extra][] = $link;
    return $link;
};

$genVideo = function ($field, $extra, $type) {
    global $allLinks;
    $allLinks[$extra] ??= [];
    $path = join('/', array_filter([$field, $extra, $type, (string)count($allLinks[$extra])]));
    $link = "https://youtube.com/$path";
    $allLinks[$extra][] = $link;
    return $link;
};

$genAnchor = function ($field, $extra, $type) {
    global $allAnchors;
    $allAnchors[$extra] ??= [];
    $text = join(' ', array_filter([$field, $extra, $type]));
    $anchor = "genAnchor $text";
    $allAnchors[$extra][] = $anchor;
    return $anchor;
};



$genContent = function ($field, $extra, $type) use ($genImage, $genLink, $genAnchor, $genAlt) {
    $image = $genImage($field, $extra, $type);
    $link = $genLink($field, $extra, $type);
    $anchor = $genAnchor($field, $extra, $type);
    $alt = $genAlt($field, $extra, $type);
    $content = "<a href=\"$link\">$anchor</a><img src=\"$image\" alt=\"$alt\"/>";
    return $content;
};



$tree = [];
$moreImages = [];
 $pairs = [];
foreach ($mappedTypes as $type => $mappedType) {
    if (!str_contains($type, 'social')) {
    //      continue;
    }
    if (str_ends_with($type, '_item')) {
        $parent = str_replace('_item', '', $type);
        if (!isset($tree[$parent])) {
            $tree[$parent] =  new stdClass();
            $tree[$parent]->props =  $defaults[$type] ?? new stdClass();
            $tree[$parent]->children =  [];
            $tree[$parent]->type = $type;
        }
        $tree[$parent]->children[] = new stdClass();
        $current = &$tree[$parent]->children[count($tree[$parent]->children) - 1];
    } else {

        if (!isset($tree[$type])) {
            $tree[$type] =  new stdClass();
            $tree[$type]->children =   [];
        }
        $current = &$tree[$type];
    }

    $current->type = $type;
    $current->props = $defaults[$type] ?? new stdClass();
   
    foreach ($mappedType as $field => $function) {
        switch ($function) {
            case 'html':
                $current->props->{$field} = $genContent($type, '', $field);
                break;
            case 'plain':
                $current->props->{$field} = $genAnchor($type, '', $field);
                break;
            case 'image-field-no-alt':
                $current->props->{$field} = $genImage($type, '', $field);
                $current->props->image_alt = $genAlt($type, 'no-match', $field);
                   $pairs[] = [$current->props->{$field}, 'Decorative image (no alt)'];
                break;
            case 'image-field-with-background-image-alt':
                $current->props->background_image = $genImage($type, '', $field);
                $current->props->background_image_alt = $genAlt($type, '', $field);
                   $pairs[] = [$current->props->background_image, $current->props->background_image_alt];
                break;
            case 'image-field-with-label':
                $current->props->{$field} = $genImage($type, '', $field);
                $current->props->label = $genAlt($type, '', $field);
                break;
            //no eentje
            case 'image-with-image-alt':
                if (!isset($current->props->image)) {
                    $current->props->image = $genImage($type, '', $field);
                }
                $current->props->image_alt = $genAlt($type, '', $field);


                break;
            case 'link-with-author':
                $current->props->link = $genLink($type, '', $field);
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
                $pairs[] = [$current->props->link, $current->props->image];
                break;
            case 'link-with-link-text':
                $current->props->link_text = $genAnchor($type, '', $field);
                $current->props->link = $genLink($type, '', $field);
                   $pairs[] = [$current->props->link, $current->props->link_text];
                break;
            case 'link-with-link-title':
                $current->props->link_title = $genAnchor($type, '', $field);
                $current->props->link = $genLink($type, '', $field);
                break;
            case 'link-with-no-anchor':
                $current->props->link = $genLink($type, '', $field);

                break;
            //no eentje
            case 'link-with-title-content':
                $current->props->title = $genAnchor($type, '', $field);
                $current->props->link = $genLink($type, '', $field);
                break;
            case 'video-with-no-title':
                $current->props->{$field} = $genVideo($type, '', $field);
                break;
            case 'video-with-title':
                $current->props->{$field} = $genVideo($type, '', $field);
                $current->props->title = $genAnchor($type, '', $field);
                break;
            case 'video-with-video-title':
                $current->props->{$field} = $genVideo($type, '', $field);
                $current->props->video_title = $genAnchor($type, '', $field);
                break;
        }
    }
/*
    if (!isset($current->props->image)) {
        $current->props->image = $genImage($type, 'no-match', $field);
    }
    if (!isset($current->props->image_alt)) {
        $current->props->image_alt = $genAlt($type, 'no-match', $field);
    }
    if (!isset($current->props->link)) {
        $current->props->link = $genLink($type, 'no-match', $field);
    }
    if (!isset($current->props->link_text)) {
        $current->props->link_text = $genAnchor($type, 'no-match', $field);
    }
    if (!isset($current->props->content)) {
        $current->props->content = $genContent($type, 'no-match', $field);
    }
    if (!isset($current->props->title)) {
        $current->props->title = $genTitle($type, 'no-match', $field);
    }

    */
}
foreach ($moreImages as $k => $moreImage) {
    $next = clone $moreImage;

    $next->props->image = $genImage($next->type, '', 'image/no-alt');
    $next->props->image_alt = '';
    //   $tree[] = $next;
}
$yoothemeTestFile = __DIR__ . '/../tests/assets/yootheme.json';
$yoothemwJson = json_decode(file_get_contents($yoothemeTestFile));


$yoothemwJson->children[0]->children[0]->children[0]->children = array_values($tree);

file_put_contents($yoothemeTestFile, json_encode($yoothemwJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$functionList = [];
foreach ($mappedTypes as &$mappedType) {
    $mappedType = array_filter($mappedType, fn($f) => strtolower($f) != 'skip');
    ksort($mappedType);
    foreach ($mappedType as $field => $function) {
        $functionList[$function] = $field;
    }
}

$mappedTypes = array_filter($mappedTypes);
$data         = "<?php\ndefined('_JEXEC') or die;\nreturn " . var_export($mappedTypes, true) . ";\n";
file_put_contents(__DIR__ . '/includes/yoothemetree.php', $data);

foreach ($allLinks as $k => $v) {

    print "$k: " . count($v) . "\n";
}
$data         = "<?php\ndefined('_JEXEC') or die;\nreturn " . var_export($allLinks[''], true) . ";\n";
file_put_contents(__DIR__ . '/../tests/assets/expectedYoothemeLinks.php', $data);

$data         = "<?php\ndefined('_JEXEC') or die;\nreturn " . var_export($pairs, true) . ";\n";
file_put_contents(__DIR__ . '/../tests/assets/expectedYoothemePairs.php', $data);
