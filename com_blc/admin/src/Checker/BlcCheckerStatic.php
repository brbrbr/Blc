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

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcSplitOptionTrait;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;

class BlcCheckerStatic extends BlcModule implements BlcCheckerInterface
{
    use BlcSplitOptionTrait;

    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */
    protected static ?BlcModule $instance = null;

    protected $pathPrefixes;
    private string $rootPath;


    protected function init()
    {

        parent::init();


        //  Factory::getApplication()->getDispatcher()->addSubscriber($this);
        $pathPrefixes =  $this->splitOption($this->componentConfig->get('static_paths', 'images,templates'));

        $this->rootPath = Uri::root(pathonly: true);

        $this->pathPrefixes =
            array_map(
                fn ($item) => trim($item, '/') . '/',
                $pathPrefixes
            );
    }


    public function canCheckLink(LinkTable $linkItem): int
    {
        //do not check checked links
        if ($linkItem->http_code !== self::BLC_CHECK_UNSET) {
            return self::BLC_CHECK_FALSE;
        }

        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_TRUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;
        //as we get here the canCheckLink is just executed

        //the url might be in the system.
        $parsed = new Uri($linkItem->url);
        //this will cleanup any leading /'s and queries and fragments
        $urlPath   = $parsed->getPath() ?? '';
        if (!$urlPath) {
            return;
        }
        // Replace %20 and + with spaces in the path
        $urlPath = urldecode($urlPath);


        if ($this->rootPath && str_starts_with($urlPath, $this->rootPath)) {
            $urlPath = substr($urlPath, \strlen($this->rootPath));
        }


        $found   = false;
        $urlPath = ltrim($urlPath, '/');
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
                UrlHelper::urlencodeFixParts($parsed, ['path']);
                $linkItem->final_url      = $parsed->toString();
                if ($linkItem->final_url !== $linkItem->url) {
                    $linkItem->redirect_count = 1;
                }
            }
            /**
             * mime_content_type is just a rought 'estimate'
             * could be improved https://github.com/ralouphie/mimey
             * but not really worth it
             *
             */
            $linkItem->mime            = mime_content_type($filePath);
            $linkItem->http_code       = self::BLC_STATIC_FOUND_HTTP_CODE;

            return;
        }
    }
}
