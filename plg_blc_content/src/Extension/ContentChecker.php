<?php

declare(strict_types=1);

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
use Joomla\Database\DatabaseAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class ContentChecker extends BlcModule implements BlcCheckerInterface
{
    use DatabaseAwareTrait;

    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;

    protected $context     = 'com_content.article';





    public function canCheckLink(LinkTable $linkItem): int
    {

        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_TRUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
       
        $linkItem->log[] = self::class;
        if (!str_starts_with($linkItem->internal_url, 'index.php')) {
            return;
        }
        $parsed = new Uri($linkItem->internal_url);

        $option = $parsed->getVar('option', '');
        $view   = $parsed->getVar('view', '');

        if ($this->context != "{$option}.{$view}") {
            return;
        }

        $origId      = (string)$parsed->getVar('id', '');


        //this would be very wrong
        if (!$origId) {
            $linkItem->http_code = self::BLC_JOOMLA_ITEM_NOT_FOUND;
            $linkItem->broken    = 5000000 + self::BLC_BROKEN_TRUE;
            return;
        }


        $origCatId                        = (string)$parsed->getVar('catid', '');
        [$currentId, $currentAlias]       = explode(':', $origId) + [0, ''];
        [$currentCatid, $currentCatalias] = explode(':', $origCatId) + [0, ''];


        $reprocess                        = false;

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


        ['catid' => $catid, 'alias' => $alias, 'calias' => $calias, 'language' => $language] =  $this->getInfoForId((int)$currentId);
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
            $linkItem->http_code = self::BLC_JOOMLA_ITEM_NOT_FOUND;
            $linkItem->broken    = self::BLC_BROKEN_TRUE;
        }
    }

    /**
     * Helper function to get some meta data from the container
     *
     * @since 25.44.7314
     * @var int $id

     *
     * @return array
     */

    private function getInfoForId(int $id): array
    {
        //caching? Maybe.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quoteName("a.catid", 'catid'))
            ->select($db->quoteName("a.alias", 'alias'))
            ->select($db->quoteName("c.alias", 'calias'))
            ->select($db->quoteName("a.language", 'language'))
            ->from($db->quoteName('#__content', 'a'))
            ->innerJoin($db->quoteName('#__categories', 'c'), $db->quoteName("a.catid") . ' = ' . $db->quoteName("c.id"))
            ->where("{$db->quoteName('a.id')} = :containerId")
            ->bind(':containerId', $id);
        $db->setQuery($query);

        return  $db->loadAssoc() ?? ['catid' => 0, 'alias' => '', 'calias' => '', 'language' => ''];
    }
}
