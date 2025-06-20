<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\Blc\Unsef\Extension;

use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
\defined('JPATH_PUBLIC') || \define('JPATH_PUBLIC', JPATH_ROOT); //J4
// phpcs:enable PSR1.Files.SideEffects



final class BlcPluginActor extends CMSPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use BlcHelpTrait;
    use DatabaseAwareTrait;

    private $oldStyleRegex  = '#(?:^|/)([0-9]+)\-(.+)#i';
    protected $context      = 'unsef';
    private $siteRouter     = null;
    private const  HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-blc-unsef';




    /**
     *
     * @since 25.44.7314
     *
     */


    public function __get($name)
    {
        return match ($name) {
            'context' => $this->context,
            'name'    => $this->_name,
            default   => null
        };
    }

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

    # from libraries/src/Router/SiteRouter.php
    # use root since it's a site route!!!!!
    # and the original parseInit expect absolute URL to remove the index.php.
    # so we have to add/remove some /'s
    protected function parseInit(&$parsed)
    {

        $path =  urldecode($parsed->getPath());

        try {
            $baseUri = Uri::root(true);
        } catch (\RuntimeException) {
            $baseUri = '';
        }

        $path = substr_replace($path, '', 0, \strlen($baseUri));

        if (preg_match("#.*?\.php#u", $path, $matches)) {
            // Get the current entry point path relative to the site path.
            $scriptPath = realpath(
                $_SERVER['SCRIPT_FILENAME'] ?: str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED'])
            );
            $relativeScriptPath = ltrim(str_replace('\\', '/', str_replace(JPATH_PUBLIC, '', $scriptPath)), '/');


            if (is_file(JPATH_PUBLIC . '/' . $matches[0]) && ($matches[0] === $relativeScriptPath)) {
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
            return self::BLC_CHECK_TRUE;
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

        //get the site container (migh be in admin)
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

    public function checkLink(LinkTable &$linkItem): void
    {
        $linkItem->log[] = self::class;
        $app             = Factory::getContainer()->get(SiteApplication::class);
        if (!$app->get('sef', 1)) {
            return;
        }

        if (!$linkItem->isInternal()) {
            return;
        }

        //be aware that this instance is shared
        //since we change the stored instance we can't use getInstance
        $parsed = new Uri($linkItem->internal_url);

        $path = $parsed->getPath();
        if ($path === null) {
            return;
        }

        //skip if it's already a query link with index.php or if the link it to a location with assets
        if (
            str_ends_with($path, 'index.php')
            || rtrim($path, '/\\') == Uri::root(true)
            // phpcs:disable Generic.Files.LineLength
            || preg_match('#^/?(plugins|cache|images|media|modules|templates|administrator|api|cli|includes|language|layouts|logs|tmp)#', $path)
            // phpcs:enable Generic.Files.LineLength
        ) {
            return;
        }

        $this->getRouter();
        //we can not re-order the rules. This one has to come first
        //this parseInit uses root() instead of base()
        //to get the site's  base url, not the admin.
        $this->parseInit($parsed);

        //resolve/fix .html links
        $this->siteRouter->attachParseRule([$this->siteRouter, 'parseFormat'], SiteRouter::PROCESS_BEFORE);


        //now we can parse the url iwth what's left over from the SiteRouter
        try {
            $this->siteRouter->parse($parsed, false);
        } catch (RouteNotFoundException) {
            //The router will throw this exeptioon if the routing failed
            //aka page not found. Lets try to resolve the link if configured
            if ((bool)$this->params->get('resolveid', 0)) {
                $this->resolveOldStyle($parsed);
            }
        }
        //convert to pure link for known component
        if (
            $parsed->getVar('option', null)
            &&
            $parsed->getVar('view', null)
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

        if ($parsed->getVar('format', '') === 'html') {
            $parsed->setVar('format', null);
        }



        $linkItem->internal_url = $parsed->toString();
    }


    private function resolveOldStyle(Uri $parsed)
    {

        $path = $parsed->getPath();
        if (preg_match($this->oldStyleRegex, $path, $m)) {
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
                $parsed->setVar('option', 'com_content');
                $parsed->setVar('view', 'article');
                $parsed->setVar('id', $article->id);
                $parsed->setVar('catid', $article->catid);
            }
        }

        // return $parsed;
    }
}
