<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Unsef\Extension;

use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
\defined('JPATH_PUBLIC') || \define('JPATH_PUBLIC', JPATH_ROOT); //J4
// phpcs:enable PSR1.Files.SideEffects



final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use BlcHelpTrait;


    #private $oldStyleRegex = '#([0-9]+)\-([a-z0-9\-]+)$#i';
    private $oldStyleRegex  = '#(?:^|/)([0-9]+)\-(.+)$#i';
    protected $context      = 'joomla';
    private $siteRouter     = null;
    private const  HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-unsef';


    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBlcCheckerRequest' => 'onBlcCheckerRequest',
        ];
    }
    //FROM parseformat
    protected function parseFormat(&$parsed)
    {
        $path = urldecode($parsed->getPath());
        if ($suffix = pathinfo($path, PATHINFO_EXTENSION)) {
            $parsed->setVar('format', $suffix);
            $path = str_replace('.' . $suffix, '', $path);
            $parsed->setPath($path);
        }
    }
    # from libraries/src/Router/SiteRouter.php
    # use root since it's a site route!!!!!
    protected function parseInit(&$parsed)
    {
        $path = urldecode($parsed->getPath());
        try {
            $baseUri = Uri::root(true);
        } catch (\RuntimeException $e) {
            $baseUri = '';
        }

        $path = substr_replace($path, '', 0, \strlen($baseUri));
        if (preg_match("#.*?\.php#u", $path, $matches)) {
            // Get the current entry point path relative to the site path.
            $scriptPath = realpath(
                $_SERVER['SCRIPT_FILENAME'] ?: str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED'])
            );
            $relativeScriptPath = str_replace('\\', '/', str_replace(JPATH_PUBLIC, '', $scriptPath));
            if (is_file(JPATH_PUBLIC . $matches[0]) && ($matches[0] === $relativeScriptPath)) {
                // Remove the entry point segments from the request path for proper routing.
                $path = str_replace($matches[0], '', $path);
            }
        }
        // Set the route
        $parsed->setPath(trim($path, '/'));
    }


    public function onBlcCheckerRequest($event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker($this, 15); //before the content plugin
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_CONTINUE;
        }
        return self::BLC_CHECK_FALSE;
    }


    private function getRouter()
    {
        if ($this->siteRouter !== null) {
            return;
        }
        //Joomla doesn't use the parse part any more. If there are in conflicts in the future a clone is needed
        $this->siteRouter = Factory::getContainer()->get(SiteRouter::class);

        //get the site container (migh be in  admin)
        $app = Factory::getContainer()->get(SiteApplication::class);
        //load the language for the 'site'
        $app->loadLanguage($app->getLanguage());
        if ((int) $app->get('force_ssl') === 2) {
            //the Siterouter parsers will redirect if the url has no https scheme.
            $yep =  $this->siteRouter->detachRule(
                'parse',
                [$this->siteRouter, 'parseCheckSSL'],
                SiteRouter::PROCESS_BEFORE
            );
            if (!$yep) {
                //let's sillently fail
            }
        }
        //this one will fuck up because we are in the backend
        $yep = $this->siteRouter->detachRule(
            'parse',
            [$this->siteRouter, 'parseInit'],
            SiteRouter::PROCESS_BEFORE
        );
        if (!$yep) {
            //this is serious.
            //can the detach fail??
        }
    }

    public function checkLink(LinkTable &$linkItem, $results = []): array
    {
        $app = Factory::getContainer()->get(SiteApplication::class);
        if (!$app->get('sef', 1)) {
            return  $results;
        }

        if (!$linkItem->isInternal()) {
            return  $results;
        }

        //be aware that this instance is shared
        //since we change the stored instance we can't use getInstance
        $parsed = new Uri($linkItem->internal_url);

        $path = $parsed->getPath();
        //skip if it's already a query link with index.php or if the link it to a location with assets
        if (
            substr($path, -9) === 'index.php'
            || rtrim($path, '/\\') == Uri::root(true)
            // phpcs:disable Generic.Files.LineLength
            || preg_match('#^/?(plugins|cache|images|media|modules|templates|administrator|api|cli|includes|language|layouts|logs|tmp)#', $path)
            // phpcs:enable Generic.Files.LineLength
        ) {
            return  $results;
        }
        $this->getRouter();
        //we can not re-order the rules. This one has to come first
        //this parseInit uses root() instead of base()
        //to get the site's  base url, not the admin.
        $this->parseInit($parsed);

        //now we can parse the url iwth what's left over from the SiteRouter
        try {
            $this->siteRouter->parse($parsed, false);
            if (
                $parsed->getVar('view') == 'article' &&
                $parsed->getVar('option') == 'com_content'
            ) {
                $parsed->setVar('Itemid', null);
                $parsed->setVar('layout', null);
                $parsed->setPath('index.php');
                $parsed->setHost(null);
                $parsed->setScheme(null);
            } elseif ((int)$parsed->getVar('Itemid') > 0) {
                $parsed->setPath('index.php');
                $parsed->setHost(null);
                $parsed->setScheme(null);
            }
            $linkItem->internal_url = $parsed->toString();
        } catch (RouteNotFoundException $e) {
            //The router will throw this exeptioon if the routing failed
            //aka page not found. Lets try to resolve the link if configured
            if ((bool)$this->params->get('resolveid', 0)) {
                $resolved = $this->resolveOldStyle($linkItem->internal_url);
                if ($resolved) {
                    $linkItem->internal_url = $resolved;
                }
            }
        }
        return  $results;
    }


    private function resolveOldStyle($parsed): string|bool
    {
        $resolved = false;
        if (preg_match($this->oldStyleRegex, $parsed, $m)) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select($db->quoteName("a.id", 'id'))
                ->select($db->quoteName("a.catid", 'catid'))
                ->from('`#__content` `a`')
                ->where('`a`.`id` = :matchId')
                ->bind(':matchId', $m[1], ParameterType::INTEGER)
                ->where('`a`.`alias` = :matchAlias')
                ->bind(':matchAlias', $m[2], ParameterType::STRING);
            $db->setQuery($query);
            $article = $db->loadObject();
            if ($article) {
                $query = [
                    'catid'  => $article->catid,
                    'id'     => $article->id,
                    'view'   => 'article',
                    'option' => 'com_content',

                ];


                $resolved = 'index.php?' .  Uri::buildQuery($query);
            }
        }

        return $resolved;
    }
}
