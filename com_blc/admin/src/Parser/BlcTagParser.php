<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * Based on Wordpress Broken Link Checker by WPMU DEV https://wpmudev.com/
 *
 */

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects



abstract class BlcTagParser extends BlcParser
{
    protected static $instance = null;
    protected string $attribute;
    protected string $element;

    protected function replaceLink(string $text, string $oldUrl, string $newUrl): string
    {
        $offset  = 0;
        $results = $this->extractTags($text, $this->element, return_the_entire_tag: true);
        foreach ($results as $result) {
            $url = $result['attributes'][$this->attribute] ?? false;
            if ($url === $oldUrl) {
                //since we checked on url === $oldUrl we could alomst do a str_replace
                //however the full_tag might contain a partial link
                //href=https://example.com/ data-lang=https://example.com/lang
                // or is this not a real world prolbem?
                $urlPreg    = preg_quote($oldUrl);
                $oldFullTag = $result['full_tag'];
                /**
                 * attribute="value"
                 * attribute='value'
                 * attribute=value<space>
                 * attribute=<value>
                 * extractTags will not return results with unmatching quotes
                 * ignore :attribute=value/> when is it attribute=(value/)> or attribute=(value)/>
                 */
                $regex      = "#({$this->attribute}\s*=\s*[\"\']?){$urlPreg}([\"'\s>])#i";

                //respect the incoming structure as much as possible:
                $newFullTag =  preg_replace($regex, "$1$newUrl$2", $oldFullTag);

                if ($newFullTag !== $oldFullTag) {
                    $text       = substr_replace($text, $newFullTag, $result['offset'] + $offset, \strlen($oldFullTag));
                    $offset += (\strlen($newFullTag) - \strlen($oldFullTag));
                }
            }
        }

        return $text;
    }


    abstract protected function getAnchor(array $result): string;

    public function extractLinks(string $text): array
    {

        $parsed  = [];
        $results = $this->extractTags($text, $this->element);
        foreach ($results as $result) {
            $url      = $result['attributes'][$this->attribute] ?? '';
            $parsed[] = [
                'url'    => $url,
                'anchor' => $this->getAnchor($result),
            ];
        }
        return $parsed;
    }

    /*
        extractTags is about 5 -10 faster compared to simple_html_dom
        so even if we run it twice ( a and img) it's still faster.
        we can't run a and img in the same run since img is selfclosing and a not

    */
    /**
     * extractTags()
     * Extract specific HTML tags and their attributes from a string.
     *
     * You can either specify one tag, an array of tag names, or a regular expression that matches the tag name(s).
     * If multiple tags are specified you must also set the $selfclosing parameter and it must be the same for
     * all specified tags (so you can't extract both normal and self-closing tags in one go).
     *
     * The function returns a numerically indexed array of extracted tags. Each entry is an associative array
     * with these keys :
     *   tag_name    - the name of the extracted tag, e.g. "a" or "img".
     *   offset      - the numberic offset of the first character of the tag within the HTML source.
     *   contents    - the inner HTML of the tag. This is always empty for self-closing tags.
     *   attributes  - a name -> value array of the tag's attributes, or an empty array if the tag has none.
     *   full_tag    - the entire matched tag, e.g. '<a href="http://example.com">example.com</a>'. This key
     *                 will only be present if you set $return_the_entire_tag to true.
     *
     * @param string $html The HTML code to search for tags.
     * @param string|array $tag The tag(s) to extract.
     * @param bool $selfclosing  Whether the tag is self-closing or not.
     * Setting it to null will force the script to try and make an educated guess.
     * @param bool $return_the_entire_tag Return the entire matched tag in 'full_tag' key of the results array.
     * @param string $charset The character set of the HTML code. Defaults to ISO-8859-1.
     *
     * @return array An array of extracted tags, or an empty array if no matching tags were found.
     */
    protected function extractTags($html, $tag, $selfclosing = null, $return_the_entire_tag = false, $charset = 'UTF-8')
    {

        if (\is_array($tag)) {
            $tag = implode('|', $tag);
        }

        //If the user didn't specify if $tag is a self-closing tag we try to auto-detect it
        //by checking against a list of known self-closing tags.
        $selfclosing_tags = ['area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta', 'col', 'param'];
        if (\is_null($selfclosing)) {
            $selfclosing = \in_array($tag, $selfclosing_tags);
        }

        //The regexp is different for normal and self-closing tags because I can't figure out
        //how to make a sufficiently robust unified one.
        if ($selfclosing) {
            $tag_pattern =
                '@<(?P<tag>' . $tag . ')
					(?P<attributes>\s[^>]+)?
					\s*/?>
					@xsi';
        } else {
            $tag_pattern =
                '@<(?P<tag>' . $tag . ')
					(?P<attributes>\s[^>]+)?
					\s*>
					(?P<contents>.*?)
					</(?P=tag)>
					@xsi';
        }

        $attribute_pattern =
            '@
				(?P<name>[\-\w]+)
				\s*=\s*
				(
					(?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)
					|
					(?P<value_unquoted>[^\s"\']+?)(?:>|\s+|$)
				)
				@xsi';

        //Find all tags
        if (!preg_match_all($tag_pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            //Return an empty array if we didn't find anything
            return [];
        }

        $tags = [];
        foreach ($matches as $match) {
            // Parse tag attributes, if any.
            $attributes = [];
            if (!empty($match['attributes'][0])) {
                if (preg_match_all($attribute_pattern, $match['attributes'][0], $attribute_data, PREG_SET_ORDER)) {
                    //Turn the attribute data into a name->value array
                    foreach ($attribute_data as $attr) {
                        //if (!empty($attr['value_quoted'])) {
                        //      $value = $attr['value_quoted']??$attr['value_unquoted']??'';
                        //  } elseif (!empty($attr['value_unquoted'])) {
                        //      $value = $attr['value_unquoted'];
                        //  } else {
                        //      $value = '';
                        //  }
                        // Passing the value through html_entity_decode is handy when you want
                        // to extract link URLs or something like that. You might want to remove
                        // or modify this call if it doesn't fit your situation.
                        //  $value = html_entity_decode($value, ENT_QUOTES, $charset);

                        //$attributes[$attr['name']] = $value;

                        $attributes[$attr['name']] = $attr['value_quoted'] ?: $attr['value_unquoted'] ?? '' ;
                    }
                }
            }

            $tag = [
                'tag_name'   => $match['tag'][0],
                'offset'     => $match[0][1],
                'contents'   => !empty($match['contents']) ? $match['contents'][0] : '', // Empty for self-closing tags.
                'attributes' => array_change_key_case($attributes, CASE_LOWER),
            ];

            if ($return_the_entire_tag) {
                $tag['full_tag'] = $match[0][0];
            }

            $tags[] = $tag;
        }
        return $tags;
    }
}
