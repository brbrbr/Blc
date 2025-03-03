<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * this checker resets redirecting links
 * For example for affiliate links
 *
 */

namespace Blc\Component\Blc\Administrator\Checker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcCheckLink;
use Blc\Component\Blc\Administrator\Blc\BlcModule;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;

class BlcCheckerStatic extends BlcModule implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;

    protected $pathPrefixes;



    public function init()
    {

        parent::init();

        
        //  Factory::getApplication()->getDispatcher()->addSubscriber($this);
        $pathPrefixes = preg_split($this->splitOption, $this->componentConfig->get('static_paths', 'images,templates'));
        if ($pathPrefixes === false) {
            Factory::getApplication()->enqueueMessage(
                "COM_BLC_STATICPATHS_LIST_INVALID",
                'warning'
            );
            $pathPrefixes = [];
        }
        $root = Uri::root(pathonly: true);
        if ($root) {
            $root .=  '/';
        }
        $this->pathPrefixes = array_filter(
            array_map(
                fn($item) => $root . rtrim($item, '/') . '/',
                $pathPrefixes
            )
        );
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


        if (!$this->canCheckLink($linkItem)) {
            return;
        }


        //as we get here the response code is just checked.
        //the url might be in the system.
        $parsed = new Uri($linkItem->url);
        //this will cleanup any leading /'s and queries and fragments
        $urlPath   = ltrim($parsed->getPath() ?? '', '/');
        // Replace %20 and + with spaces in the path
        $urlPath = urldecode($urlPath);

        $found = false;

        foreach ($this->pathPrefixes as $pathPrefix) {
            if (str_starts_with($urlPath, $pathPrefix)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return;
        }
        $filePath = Path::clean(JPATH_ROOT . '/' . $urlPath);

        if (file_exists($filePath)) {
            if ($this->componentConfig->get('urlencodefix', 0) == 1) {
                BlcCheckLink::urlencodeFixParts($parsed, ['path']);
                $linkItem->final_url      = $parsed->toString();
                if ($linkItem->final_url !== $linkItem->url) {
                    $linkItem->redirect_count = 1;
                }
            }
            $linkItem->mime  = mime_content_type($filePath);
            $linkItem->http_code       = self::BLC_STATIC_FOUND_HTTP_CODE;
            $linkItem->log['Checker']  = 'Static Checker';
            return;
        }
    }
}
