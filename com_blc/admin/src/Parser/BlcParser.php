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

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;
use  Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Language\Text;

abstract class BlcParser extends BlcModule
{
    ## Pseudo abstract variables
    protected string $parserName = '';
    protected $checkers;
    protected int $synchedId;
    protected string $field;
    protected bool $save = true;

    protected static $instance = null;

    ## Pseudo abstract functions

    /* return the 'text' with the 'oldUrl' replaced by the 'newUrl'

    */
    protected function replaceLink(string $text, string $oldUrl, string $newUrl): string
    {
        throw new \Exception(Text::sprintf("COM_BLC_ERROR_NOT_IMPLEMENTED_YET", __CLASS__ . '->' . __FUNCTION__));
    }

    protected function extractLinks(string $link): array
    {
        throw new \Exception(Text::sprintf("COM_BLC_ERROR_NOT_IMPLEMENTED_YET", __CLASS__ . '->' . __FUNCTION__));
    }

    protected function init()
    {
        parent::init();
        if (empty($this->parserName)) {
            throw new \Exception(Text::sprintf("COM_BLC_ERROR_NOT_MISSING_VALUE", __CLASS__, 'parserName'));
        }

        $this->checkers = BlcCheckLink::getInstance();
    }

    public function setMeta(array|object $meta = []): blcParser
    {
        if (empty($meta)) {
            return $this;
        }
        if (\is_array($meta)) {
            $meta = (object)$meta;
        }
        if (isset($meta->synchId)) {
            $this->synchedId = (int)$meta->synchId;
        }
        if (isset($meta->save)) {
            $this->save = (bool)$meta->save;
        }

        if (isset($meta->field)) {
            $this->field = (string)$meta->field;
        }
        return $this;
    }

    final protected function storeLink($link): int
    {
        $url = trim($link['url'] ?? $link ?? '');

        $pk = [
            'url' => $url,
        ];
        $linkItem = $this->getTable();
        $linkItem->load($pk);
        $linkItem->bind($pk);
        $linkItem->initInternal();

        $storeOrSkip = $this->parseUrl($linkItem);

        if ($storeOrSkip === false) {
            if ($linkItem->id !== null) {
                $linkItem->delete();
            }
            return 0;
        }

        if ($linkItem->id === null) {
            print Text::sprintf("COM_BLC_MSG_NEW_LINK", $url) . "\n";
            try {
                //if there are multiple instances running their might be a collesion of
                //identical links insterted ad the same time
                //ignore these. Will be corrected at the next run.
                $linkItem->save();
            } catch (e) {
                return 0;
            }
        } else {
            print Text::sprintf("COM_BLC_MSG_EXISTING_LINK", $url) . "\n";
        }
        return  $linkItem->id;
    }

    protected function storeLinks(array | string $links): array
    {

        if (\is_string($links)) {
            $links = [$links];
        }

        foreach ($links as $link) {
            try {
                $linkItemId = $this->storeLink($link);
                if ($linkItemId) {
                    $anchor = $this->parseAnchor($link['anchor'] ?? $link['url'] ?? $link);
                    $this->saveInstance($linkItemId, $anchor);
                }
            } catch (Exception $e) {
                //ignore it. most likely this error occurs when there are multiple jobs running
                //will correct itself on a future run.
                echo 'Caught exception: ',  $e->getMessage(), "\n";
            }
        }
        return $links;
    }


    public function extractAndStoreLinks(array|string $input): array
    {



        $links = [];
        if (\is_string($input)) {
            $links = $this->extractLinks($input);
            if ($this->save) {
                $this->storeLinks($links);
            }
        }

        if (\is_array($input)) {
            $keepField = $this->field;
            foreach ($input as $field => $text) {
                $this->field = $field;
                $fieldLinks  = $this->extractLinks($text);
                $this->storeLinks($fieldLinks);
                $links[$field] = $links;
            }
            $this->field = $keepField;
        }

        return $links;
    }

    public function replaceLinks(array|string $input, string $oldUrl, string $newUrl): array | string
    {
        if (\is_string($input)) {
            return $this->replaceLink($input, $oldUrl, $newUrl);
        }

        $results = [];
        if (\is_array($input)) {
            foreach ($input as $id => $text) {
                $results[$id] = $this->replaceLink($text, $oldUrl, $newUrl);
            }
            return $results;
        }
    }

    protected function saveInstance(int $linkId, string $linkText): int
    {
        $instanceTable = $this->getTable('Instance');

        $pk = [
            'link_id'   => $linkId,
            'synch_id'  => $this->synchedId,
            'field'     => $this->field,
            'parser'    => $this->parserName,
            'link_text' => $linkText,
        ];

        $instanceTable->save($pk);
        return $instanceTable->id;
    }

    protected function parseAnchor($anchor)
    {
        return $anchor;
    }


    //clean up of url and  lookup joomla url
    /*
       @returns false if the link should be ignore. Or a string of it's an internal URL.
    */
    protected function parseUrl(LinkTable $linkItem): bool
    {
        $url = $linkItem->url;

        if (!$url) {
            return false;
        }

        if (strpos($url, '#') === 0) {
            return false;
        }


        //this ensures we have a valid parser
        $canCheck = $this->checkers->canCheckLink($linkItem);
        if (HTTPCODES::BLC_CHECK_FALSE === $canCheck) {
            print Text::sprintf('COM_BLC_MSG_CHECK_FALSE', (string)$linkItem) . "\n";
            return false;
        }
        if (HTTPCODES::BLC_CHECK_IGNORE === $canCheck) {
            print Text::sprint('COM_BLC_MSG_CHECK_IGNORE', (string)$linkItem) . "\n";
            return false;
        }
        return true;
    }
}
