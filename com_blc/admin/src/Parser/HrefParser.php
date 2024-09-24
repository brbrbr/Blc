<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


class HrefParser extends BlcTagParser
{
    protected string $parserName = 'href';
    protected string $attribute  = 'href';
    protected string $element    = 'a';

    protected static $instance = null;

    protected function getAnchor(array $result): string
    {
        return $result['contents'] ?? 'empty \'a\' tag';
    }

    public function testParser()
    {
        $oldUrl                                                  = "http://example.com";
        $tests["<a href= $oldUrl data-value=\"1\">replaced</a>"] = true;
        $tests["<a href='$oldUrl'>replaced</a>"]                 = true;
        $tests["<a href=\"$oldUrl\">replaced</a>"]               = true;

        //   $tests["<a href=\"$oldUrl'>replaced</a>"] = true;

        $tests["<a href =$oldUrl data-value=\"1\">replaced</a>"] = true;

        $oldUrlHttps                                            = "https://example.com";
        $tests["<a href=$oldUrlHttps data-value=\"0\">not</a>"] = false;

        $tests["<a href='$oldUrl/'>not</a>"]       = false;
        $tests["<a href=$oldUrl/>not</a>"]         = false;
        $tests["<a href=$oldUrl+>not</a>"]         = false;
        $tests["<a href=\"$oldUrl/page\">not</a>"] = false;


        $newUrl = "https://EXAMPLE.DEV";
        foreach ($tests as $oldFullTag => $wanted) {
            ob_start();
            $out     = $this->replaceLink($oldFullTag, $oldUrl, $newUrl);
            $results = ob_get_clean();
            $result  = ($out !== $oldFullTag);

            if ($wanted !== $result) {
                print $results;
                print "IN $oldFullTag -> OUT: $out\n";
            }
        }
    }
}
