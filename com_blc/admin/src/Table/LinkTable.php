<?php

declare(strict_types=1);

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2025 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface as HTTPCODES;
use Blc\Component\Blc\Administrator\Traits\BlcSplitOptionTrait;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

/**
 * Link table
 *
 * @since 1.0.0
 */
class LinkTable extends BlcTable implements \Stringable
{
    use BlcSplitOptionTrait;

    /**
     * List of hosts that should be considered internal
     * usefull for moved websites or in some proxied environemnts.
     *
     * @var    array
     * @since  4.0.0
     */
    protected array $internalHosts = [];


    private readonly Registry $componentConfig; //A reference to the plugin's global configuration object.


    /**
     * Full  absolute url to check, might be altered by checkers
     *
     * @var    string
     * @since  24.44.0
     */
    private string $toCheck = '';
    // phpcs:enable PSR2.Classes.PropertyDeclaration
    // phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
    protected $_tbl_keys = ['id', 'md5sum'];
    // phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

    //table columns
    public int $id                             = 0;
    public string $url                         = '';
    public string $internal_url                = '';
    public string $final_url                   = '';
    public ?string $added                      = null; //timestamp when inserted
    public string $last_check                  = '0000-00-00 00:00:00'; // nulldate in mysql (no postsql suport fro this component)
    public string $first_failure               = '0000-00-00 00:00:00';
    public string $last_check_attempt          = '0000-00-00 00:00:00';
    public string $last_success                = '0000-00-00 00:00:00';
    public int $check_count                    = 0;
    public int $http_code                      = HTTPCODES::BLC_CHECK_UNSET;
    public float $request_duration             = 0;
    public int $redirect_count                 = 0;
    public int $broken                         = HTTPCODES::BLC_BROKEN_FALSE;
    public int $working                        = HTTPCODES::BLC_WORKING_ACTIVE;
    public int $parked                         =  HTTPCODES::BLC_PARKED_UNCHECKED;
    public int $being_checked                  = HTTPCODES::BLC_CHECKSTATE_TOCHECK;
    public string $mime                        = '';
    public string $md5sum                      = '';
    public $data                               = []; //saved in different table for performance
    public $log                                = []; //saved in different table for performance

    /**
     *
     */


    /**
     * Constructor
     *
     * @param   DatabaseDriver  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {

        $this->typeAlias = 'com_blc.link';
        parent::__construct('#__blc_links', 'id', $db, $dispatcher);
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        $this->internalHosts   = $this->splitOption($this->componentConfig->get('internal_hosts', ''));
        $this->internalHosts[] = Uri::getInstance(BlcHelper::root())->getHost();
        $this->internalHosts   = array_map(strtolower(...), array_filter($this->internalHosts));
    }

    public function __toString(): string
    {
        return (string) $this->toString();
    }
    /**
     * @since 25.44.7269
     *
     */
    public function __get(string $name): mixed
    {
        return  match ($name) {
            default   => throw new \RuntimeException(Text::sprintf('COM_BLC_CANNOT_GET_UNDEFINED_PROPERTY', $name, __METHOD__)),
            'toCheck' => $this->getToCheck(),

            'internalHosts' => $this->internalHosts,
        };
    }

    protected function getToCheck(): string
    {
        if (empty($this->toCheck)) {
            $this->resetInternalUrl();
            $this->toCheck = $this->toString(
                sef: true,
                xhtml: false,
                absolute: true
            );
        }
        return $this->toCheck;
    }

    /**
     * @since 25.44.7269
     *
     */
    public function __set(string $name, mixed $value): void
    {
        $this->{$name} = match ($name) {
            'typeAlias', 'toCheck' => $value,
            default => throw new \RuntimeException(Text::sprintf('COM_BLC_CANNOT_SET_UNDEFINED_PROPERTY', $name, json_encode($value), __METHOD__)),
        };
    }

    /**
     * @since 25.44.7269
     *
     */
    public function __unset($name): void
    {
        switch ($name) {
            default:
                throw new \RuntimeException(Text::sprintf('COM_BLC_CANNOT_UNSET_UNDEFINED_PROPERTY', $name, __METHOD__));
                break;

            case 'toCheck':
                unset($this->{$name});
                break;
        }
    }
    public function loadStorage(): void
    {
        if (!$this->id) {
            return;
        }
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->quotename([
            'log',
            'data',
        ]))
            ->from($db->quotename('#__blc_links_storage'))
            ->where("{$db->quotename('link_id')} = :id")
            ->bind(':id', $this->id, ParameterType::INTEGER);

        $query = $db->setQuery($query);
        $row   = $query = $db->loadObject();
        if ($row) {
            $this->log = (new Registry($row->log))->toArray();
            $this->data = (new Registry($row->data))->toArray();
        } else {
            $this->log  = [];
            $this->data = [];
        }
    }
    protected function maybeEncode(mixed $value): string
    {
        return (\is_array($value) || \is_object($value) || $value === null)
            ? json_encode($value)
            : (string)$value;
    }

    public function saveStorage(): void
    {
        if (!$this->id) {
            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query
            ->select($db->quotename('id'))
            ->from($db->quotename('#__blc_links_storage'))
            ->where("{$db->quotename('link_id')} = :id")
            ->bind(':id', $this->id, ParameterType::INTEGER);
        $lsid     = $db->setQuery($query)->loadResult();
        $queryId  = $this->data['query']['id'] ?? 0;

        if ($queryId) {
            $queryId = \intval($queryId);
            //quick and dirty strip the alias
            $this->data['query']['id'] = $queryId;
        }
        $row   = (object)
        [
            'log'         => $this->maybeEncode($this->log),
            'data'        => $this->maybeEncode($this->data),
            'link_id'     => $this->id,
            'queryId'     => $queryId,
            'queryOption' => mb_substr($this->data['query']['option'] ?? '', 0, 64),
        ];


        if ($lsid) {
            $row->id = $lsid;
            $db->updateObject('#__blc_links_storage', $row, 'id');
        } else {
            $db->insertObject('#__blc_links_storage', $row);
        }
    }

    protected function setPreferedInternal(): void
    {

        if (!$this->isInternal()) {
            return;
        }

        $urlInstance             = new Uri($this->internal_url);
        $url                     = $urlInstance->toString(['user', 'pass', 'port', 'path', 'query', 'fragment']); //removes urlencoding like &amp;
        $sef                     = (bool)$this->componentConfig->get('internal_sef', 0);
        $xhtml                   = (bool)$this->componentConfig->get('internal_xhtml', 1);
        $absolute                = (bool)$this->componentConfig->get('internal_absolute', 0);
        $this->internal_url      = $this->route(url: $url, sef: $sef, xhtml: $xhtml, absolute: $absolute);
    }

    /**
     * @param string $url
     *
     * @return string
     *
     * @since 25.44.8048
     */

    public function resetInternalUrl(): string
    {
        return    $this->setInternalUrl();
    }

    /**
     * @param string $url
     *
     * @return string
     *
     * @since 25.44.8048
     */

    public function setInternalUrl(string $url = ''): string
    {
        $this->internal_url = $url;

        $this->initInternal();
        $this->setPreferedInternal();
        return $this->internal_url;
    }
    protected function isAllowedScheme($scheme): bool
    {
        return \in_array(strtolower((string) $scheme), ['http', 'https', '']);
    }

    protected function initInternal(): void
    {

        if ($this->internal_url) {
            $url = $this->internal_url;
        } else {
            $url = $this->url;
        }


        try {
            $parsed             = new Uri($url);
        } catch (\RuntimeException) {
            $this->internal_url = ''; //sanity set
            return;
        }

        $scheme             = $parsed->getScheme() ?? '';
        //treat links like ftp: javascript: mailto: tel: as external
        if (!$this->isAllowedScheme($scheme)) {
            $this->internal_url = ''; //sanity set
            return;
        }

        $host               = strtolower($parsed->getHost() ?? '');
        $host               = preg_replace('#^(www|m)\.#', '', $host);


        if (
            !$host
            ||
            !$scheme
            ||
            str_starts_with($url, '#')
            ||
            str_starts_with($url, '?')
            ||
            \in_array($host, $this->internalHosts)
            || Uri::isInternal($url)
        ) {
            if (!$this->internal_url) {
                $this->internal_url = $url;
            }


            $path = $parsed->getPath() ?? '';
        }


        if ($this->internal_url && (!$path || !Uri::isInternal($this->url))) {
            //we have a iternal not detected by joomla.
            //so lets assume it is an old url from internnalHosts;
            $parsed->setHost(null);
            $parsed->setScheme(null);


            $path = $parsed->getPath();

            $rootPath = Uri::root(true);
            if ($rootPath) {
                if (str_starts_with($path, $rootPath)) {
                    $path = mb_substr($path, \strlen($rootPath));
                }
            }

            //all relative
            if ($path) {
                $parsed->setPath(ltrim($path, '/'));
            }

            $this->internal_url = $parsed->tostring();

            if ($this->isXhtmlEncoded($this->url)) {
                $this->internal_url = htmlspecialchars($this->internal_url, ENT_COMPAT, 'UTF-8');
            }
        }
        if ($this->internal_url && str_starts_with($this->internal_url, '/index.php')) {
            $this->internal_url = substr($this->internal_url, 1);
        }
    }



    public function isInternal(bool $isIndexPhp  = false): bool
    {

        if ($isIndexPhp) {
            return !empty($this->internal_url) && str_starts_with($this->internal_url, 'index.php');
        }
        return !empty($this->internal_url);
    }
    /**
     * Translates an internal Joomla URL to a humanly readable URL.
     * @param   string   $url       The internal Joomla URL.
     * @param   bool     $sef       Create SEF link
     * @param   boolean  $xhtml     Replace & by &amp; for XML compliance.
     * @param   boolean  $absolute  Return an absolute URL
     *
     * @return  string  The t URL.
     *
     * @since   25.44
     */

    protected function route(string $url, $sef = false, $xhtml = true, $absolute = true): string
    {

        /*
         * If the URL is not an internal Joomla URL, return it as-is.
         */

        if (! $this->isInternal(true)) {
            //make it absolute when requested
            if ($absolute) {
                $url =  BlcHelper::root(path: $url);
            }
            //keep the encoding for the original url
            //$url is decoded
            if ($this->isXhtmlEncoded($this->url)) {
                $url = htmlspecialchars((string) $url, ENT_COMPAT, 'UTF-8');
            }

            return $url;
        }

        if ($sef) {
            try {
                $url = Route::link('site', url: $url, xhtml: $xhtml, absolute: false); //absolute does not work with CLI
            } catch (\Exception | \Error) {
                //sef failed
                //possible cause: CLI and call like getMenus( com_rsform)
                //go on with the original url.
                //this will give some false results if a seffed url is redirected
            }
        } else {
            //only do the xhtml and a 'real' internal link
            if ($xhtml) {
                $url = htmlspecialchars((string) $url, ENT_COMPAT, 'UTF-8');
            }
        }
        $app = Factory::getContainer()->get(SiteApplication::class);
        //do not make absolute when index.php that will break the SEF
        if ($absolute && (!$app->get('sef', 1) || !str_starts_with((string) $url, 'index.php'))) {
            $url =  BlcHelper::root(path: $url);
        }

        return $url;
    }

    /**
     * Translates an internal Joomla URL to a humanly readable URL.
     * NOTE: To build link for active client instead of a specific client,
     *

     * @param   bool     $sef       Create SEF link
     * @param   boolean  $xhtml     Replace & by &amp; for XML compliance.
     * @param   boolean  $absolute  Return an absolute URL
     *
     * @return  string  The t URL.
     *
     * @throws  \RuntimeException
     *
     * @since   3.9.0
     */

    public function toString(bool $sef = false, bool $xhtml = true, bool $absolute = true): string
    {
       
        if (!$this->isInternal()) {
            return $this->url;
        }
        $urlInstance        = new Uri($this->internal_url);

        $url = $urlInstance->toString(['user', 'pass', 'port', 'path', 'query', 'fragment']);

        return $this->route($url, $sef, $xhtml, $absolute);
    }

    public function bind($src = [], $ignore = ''): bool
    {

        $src        = $this->hashURL($src);
        $bindResult = parent::bind($src, $ignore);



        if (!$bindResult) {
            throw new \RuntimeException(Text::_('COM_BLC_LIKNKTABLE_BIND_FAILED'));
        }

        if (empty($this->url)) {
            throw new \RuntimeException(Text::sprintf('COM_BLC_CANNOT_EMPTY_URL', __CLASS__, __METHOD__));
        }



        //reset the internal link in case the configuration changed

        $this->initInternal();
        $this->setPreferedInternal();
        return true;
    }


    /**
     * Method to load a row from the database by primary key and bind the fields to the Table instance properties.
     *
     * @param   mixed    $keys   An optional primary key value to load the row by, or an array of fields to match.
     *                           If not set the instance property value is used.
     * @param   boolean  $reset  True to reset the default values before loading the new row.
     *
     * @return  boolean  True if successful. False if row not found.
     *
     * @since   24.44.6473
     */
    public function load($keys = null, $reset = true): bool
    {

        $keys       = $this->hashURL($keys);
        return parent::load($keys, $reset);
    }

    /**
     * @param mixed $src
     *
     * @return mixed
     *
     *  @since   24.44.6473
     */

    private function hashURL(mixed $src): mixed
    {
        if (\is_object($src) && empty($src->md5sum) && isset($src->url)) {
            if ($this->md5sum && $src->url !== $this->url) {
                throw new \RuntimeException(Text::_('COM_BLC_CANNOT_MODIFIY_URL'));
            }
            $src->md5sum = md5((string)$src->url);
        } elseif (\is_array($src) && empty($src['md5sum']) && isset($src['url'])) {
            if ($this->md5sum && $src['url'] !== $this->url) {
                throw new \RuntimeException(Text::_('COM_BLC_CANNOT_MODIFIY_URL'));
            }
            $src['md5sum'] = md5((string) $src['url']);
        }

        return $src;
    }

    public function reset(): void
    {

        $nullDate                 =  $this->getDatabase()->getNullDate();
        $this->id                 = 0;
        $this->url                = '';
        $this->md5sum             = '';
        $this->internal_url       = '';
        $this->final_url          = '';
        $this->toCheck            = '';

        $this->last_check         = $nullDate;
        $this->first_failure      = $nullDate;
        $this->last_check_attempt = $nullDate;
        $this->last_success       = $nullDate;
        $this->check_count        = 0;

        $this->added            = null;
        $this->http_code        = HTTPCODES::BLC_CHECK_UNSET;
        $this->request_duration = 0;
        $this->redirect_count   = 0;
        $this->broken           = HTTPCODES::BLC_BROKEN_FALSE;
        $this->working          = HTTPCODES::BLC_WORKING_ACTIVE;
        $this->parked           =  HTTPCODES::BLC_PARKED_UNCHECKED;
        $this->being_checked    = HTTPCODES::BLC_CHECKSTATE_TOCHECK;
        $this->mime             = '';


        $this->data  = [];
        $this->log   = [];
        parent::reset();
    }

    private function checkDate(&$date): void
    {

        try {
            $dateSql = new Date($date);
            if ($dateSql->toSql() !== $date) {
                $date = $this->getDatabase()->getNullDate();
            }
        } catch (\Exception) {
            $date = $this->getDatabase()->getNullDate();
        }
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check(): bool
    {

        $this->md5sum ??= md5($this->url); //should not happen
        //ensure bools are stored as int


        $this->checkDate($this->first_failure);
        $this->checkDate($this->last_success);
        $this->checkDate($this->last_check);
        $this->checkDate($this->last_check_attempt);


        $this->setPreferedInternal();
        return true;
    }
    /**
     * retrieves a suggested link replacement value
     * so it will return the final_url / prefered internal_url versus the url in toString
     *
     * @since 25.44.7589
     */

    public function getReplaceUrl(): string
    {
        return $this->internal_url ?: ($this->final_url ?: $this->url);
    }
    /**
     * Check if a url is already xhtml encoded
     * @param string $url
     * @return bool
     *
     * @since 25.44.8048
     */
    private function isXhtmlEncoded(string $url): bool
    {
        // Check for common HTML entities like &amp;, &quot;, or numeric entities &#123;
        return (bool) preg_match('/&[a-z|#|0-9]+;/i', $url);
    }
}
