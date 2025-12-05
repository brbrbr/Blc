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
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    // phpcs:disable PSR2.Classes.PropertyDeclaration


    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    array
     * @since  4.0.0
     */
    protected $internalHosts = [];


    private readonly Registry $componentConfig; //A reference to the plugin's global configuration object.


    /**
     * Full  absolute url to check, might be altered by checkers
     *
     * @var    string
     * @since  24.44.0
     */
    private ?string $toCheck = null;
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
        $this->internalHosts[] = Uri::getInstance()->getHost();
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
    public function __get($name)
    {

        if ($name == 'toCheck') {
            return $this->toCheck ?? $this->toString(
                sef: true,
                xhtml: false,
                absolute: true
            );
        }
    }

    /**
     * @since 25.44.7269
     *
     */
    public function __set($name, $value)
    {
        if ($name == 'toCheck') {
            $this->toCheck = $value;
        }
    }

    /**
     * @since 25.44.7269
     *
     */
    public function __unset($name)
    {
        if ($name == 'toCheck') {
            unset($this->toCheck);
        }
    }
    public function loadStorage()
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
            $registry   = new Registry($row->log);
            $this->log  = $registry->toArray();
            $registry   = new Registry($row->data);
            $this->data = $registry->toArray();
        } else {
            $this->log  = [];
            $this->data = [];
        }
    }
    protected function maybeEncode($v)
    {
        if (\is_array($v) || \is_object($v) || $v === null) {
            return json_encode($v);
        }
        return $v;
    }

    public function saveStorage()
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

    protected function setPreferedInternal()
    {
        if (!$this->isInternal()) {
            return;
        }
        //has we get here the $url is already parsed by initInternal
        //it will  never get here is the  Uri::getInstance failed there since  $this->internal_url is empty
        $url                     = Uri::getInstance($this->internal_url)->toString(); //removes urlencoding like &amp;
        $sef                     = (bool)$this->componentConfig->get('internal_sef', 0);
        $xhtml                   = (bool)$this->componentConfig->get('internal_xhtml', 1);
        $absolute                =  (bool)$this->componentConfig->get('internal_absolute', 0);
        $this->internal_url      = $this->route(url: $url, sef: $sef, xhtml: $xhtml, absolute: $absolute);
    }

    protected function initInternal()
    {
        $this->internal_url = '';
        try {
            $parsed             = Uri::getInstance($this->url);
        } catch (\RuntimeException) {
            $this->internal_url = ''; //sanity set
            return;
        }

        $scheme             = strtolower($parsed->getScheme() ?? '');
        $host               = strtolower($parsed->getHost() ?? '');
        $host               = preg_replace('#^(www|m)\.#', '', $host);

        if (str_starts_with($this->url, '#') || \in_array($host, $this->internalHosts) || Uri::isInternal($this->url)) {
            $host   = false;
            $scheme = false;
            $parsed->setHost(null);
            $parsed->setScheme(null);
            $this->internal_url = $parsed->tostring();
        }

        //url's without scheme like //example.cm will have an host. So need to check both
        if (!$scheme && !$host) {  //most likely a local url
            //do not set the preferred url yet. This is done in the check
            //internal links should all be relative without the rootpath
            //this is tricky, moving sites with absolute url's from a subdir to rootdir install
            //might break things terribly

            $path = $parsed->getPath();

            $rootPath = Uri::root(true);
            if ($rootPath) {
                if (str_starts_with($path, $rootPath)) {
                    $path = substr($path, \strlen($rootPath));
                }
            }
            //all relative
            if ($path) {
                $parsed->setPath(ltrim($path, '/'));
            }
            $this->internal_url = $parsed->tostring();
        }
    }

    public function isInternal()
    {
        return !empty($this->internal_url);
    }

    protected function route($url, $sef = false, $xhtml = true, $absolute = true)
    {

        if ($sef) {
            try {
                $url = Route::link('site', url: $url, xhtml: $xhtml, absolute: false); //absolute does not work with CLI
            } catch (\Exception) {
                //sef failed
                //possible cause: CLI and call like getMenus( com_rsform)
                //go on with the original url.
                //this will give some false results if a seffed url is redirected
            } catch (\Error) {
                //sef failed
                //possible cause: CLI and call like getMenus( com_rsform)
                //go on with the original url.
                //this will give some false results if a seffed url is redirected
            }
        } else {
            if ($xhtml && str_starts_with((string) $url, 'index.php')) {
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
     * NOTE: To build link for active client instead of a specific client, you can use <var>Route::_()</var>
     *

     * @param   bool   $sef       Create SEF link
     * @param   boolean  $xhtml     Replace & by &amp; for XML compliance.
     * @param   boolean  $absolute  Return an absolute URL
     *
     * @return  string  The t URL.
     *
     * @throws  \RuntimeException
     *
     * @since   3.9.0
     */

    public function toString(bool $sef = false, bool $xhtml = true, bool $absolute = true)
    {
        if (\func_num_args() == 4) {
            throw new \RuntimeException(\sprintf('To many arugments for %s in %s', __METHOD__, __CLASS__));
        }
        if (!$this->isInternal()) {
            return $this->url;
        }

        return $this->route($this->url, $sef, $xhtml, $absolute);
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
    public function load($keys = null, $reset = true)
    {

        $keys       = $this->hashURL($keys);
        $loadResult = parent::load($keys, $reset);
        //parent does a bind so no need for initInternal here
        return $loadResult;
    }

    /**
     * @param mixed $src
     *
     * @return mixed
     *
     *  @since   24.44.6473
     */

    private function hashURL($src)
    {
        if (\is_object($src) && empty($src->md5sum) && isset($src->url)) {
            if ($this->md5sum && $src->url !== $this->url) {
                throw new \RuntimeException(Text::_('COM_BLC_CANNOT_MODIFIY_URL'));
            }
            $src->md5sum = md5($src->url);
        } elseif (\is_array($src) && empty($src['md5sum']) && isset($src['url'])) {
            if ($this->md5sum && $src['url'] !== $this->url) {
                throw new \RuntimeException(Text::_('COM_BLC_CANNOT_MODIFIY_URL'));
            }
            $src['md5sum'] = md5((string) $src['url']);
        }

        return $src;
    }

    public function reset()
    {

        $nullDate                 =  $this->getDatabase()->getNullDate();
        $this->id                 = 0;
        $this->url                = '';
        $this->md5sum             = '';
        $this->internal_url       = '';
        $this->final_url          = '';
        $this->toCheck            = null;

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

    private function checkDate(&$date)
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
    public function check()
    {

        $this->md5sum ??= md5($this->url); //should not happen
        //ensure bools are stored as int


        $this->checkDate($this->first_failure);
        $this->checkDate($this->last_success);
        $this->checkDate($this->last_check);
        $this->checkDate($this->last_check_attempt);


        $this->setPreferedInternal();
        return true;
        //  return parent::check();
    }
    /**
     * retrieves a suggested link replacement value
     * so it will return the final_url / prefered internal_url versus the url in toString
     *
     * @since 25.44.7589
     */

    public function getReplaceUrl()
    {
        // phpcs:disable Generic.Files.LineLength
        return $this->internal_url == '' ? ($this->final_url == '' ? $this->url : $this->final_url) : $this->internal_url;
        // phpcs:enable Generic.Files.LineLength
    }
}
