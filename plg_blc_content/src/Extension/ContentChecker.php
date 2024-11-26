<?php

/**
 * @package     BLC
 * @subpackage  blc.yootheme
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Content\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Uri\Uri;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class ContentChecker extends BlcModule implements BlcCheckerInterface
{
    protected $context     = 'com_content.article';
    private $parent;

    public function setParent($parent)
    {
        $this->parent = $parent;
    }


    public function canCheckLink(LinkTable $linkItem): int
    {
     
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_TRUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
      
        if (strpos($linkItem->internal_url, 'index.php') !== 0) {
            return;
        }
        $parsed = new Uri($linkItem->internal_url);

        $option = $parsed->getVar('option', '');
        $view   = $parsed->getVar('view', '');

        if ($this->context != "{$option}.{$view}") {
            return;
        }
      
        $origId      = $parsed->getVar('id', 0);
        //this would be very wrong
        if (!$origId) {
            return;
        }
        $reprocess = false;
        $origCatId                        = $parsed->getVar('catid', 0);
        [$currentId, $currentAlias]       = explode(':', $origId) + [0, ''];
        [$currentCatid, $currentCatalias] = explode(':', $origCatId) + [0, ''];

        if (
            $this->params->get('category_alias', 0) == 2
        ) {
            $currentCatalias = ''; //used as boolean below
        }
        if (
            $this->params->get('article_alias', 0) == 2
        ) {
            $currentAlias = '';  //used as boolean below
        }
        //the unsef or another plugin might have changed the link so check it here and not in canCheckLink

        //be aware that this instance is shared
        //since we change the stored instance we can't use getInstance -- unsef might changed it incorrectly!


        ['catid' => $catid, 'alias' => $alias, 'calias' => $calias, 'language' => $language] =  $this->parent->getInfoForId($currentId, '#__content');

        if ($catid) {
            if ($this->params->get('check_catid', 0)) {
                $currentCatid = $catid;
                //currentCatalias is set when it always be set ( option 1) or the currentCatalias is not empty (if option = 2 cleared above)
                if ($this->params->get('category_alias', 0) == 1 || $currentCatalias) {
                    $currentCatalias = $calias;
                }
            }
            //see comment above
            if ($this->params->get('article_alias', 0) == 1 || $currentAlias) {
                $currentAlias = $alias;
            }


            //
            if ($currentCatalias) {
                $currentCatid .= ':' . $currentCatalias;
            }

            if ($currentAlias) {
                $currentId .= ':' . $currentAlias;
            }



            if ($currentId != $origId || $currentCatid != $origCatId) {
                $parsed->setVar('id', $currentId); //in case it is cleaned from id:alias -> id
                $parsed->setVar('catid', $currentCatid); //in case it is cleaned from catid:alias ->catid
                /* for now we track the query here. As we use it only for internal links and the *content* map */
                $reprocess = true;
            }

            $checkLang = $this->params->get('check_lang', 0);

            switch ($checkLang) {
                case 1:
                    if ($language == '*') {
                        $parsed->delVar('lang');
                    } else {
                        $parsed->setVar('lang', $language);
                    }
                    $reprocess = true;
                    break;
                case 2:
                    $parsed->delVar('lang');
                    $reprocess = true;
                    break;
                case 0:
                default:
                    //do notihng
            }



            if ($reprocess) {
                $linkItem->internal_url = $parsed->toString();
            }


            //used for the link explorer
            $linkItem->data ??= [];
            if (\is_array($linkItem->data)) {
                $linkItem->data['query'] = $parsed->getQuery(true);
            }
        } else {
            //catid not found to item does not exist
            $linkItem->http_code=self::BLC_JOOMLA_ITEM_NOT_FOUND;
            $linkItem->broken=self::BLC_BROKEN_TRUE;

        }
    }
}
