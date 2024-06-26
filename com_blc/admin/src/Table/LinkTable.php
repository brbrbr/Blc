<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Component\Blc\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Blc\BlcTable;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerInterface as HTTPCODES;

/**
 * Link table
 *
 * @since 1.0.0
 */
class LinkTable extends BlcTable implements \Stringable
{
    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    // phpcs:disable PSR2.Classes.PropertyDeclaration
    protected $_supportNullValue = true;

    /**
     *
     * @var    DatabaseDriver
     * @since  4.0.0
     */
    protected $_db            = null;
    protected $_internalHosts = [];


    protected $componentConfig     = null;
    protected string $_splitOption = "#(;|,|\r\n|\n|\r)#";

    /**
     * Full punycodes absloute url to check
     *
     * @var    string
     * @since  24.44.0
     */
    public string $_toCheck;
    // phpcs:enable PSR2.Classes.PropertyDeclaration




    //table columns
    public $id;
    public $url;
    public $internal_url;
    public $final_url;
    public $added;
    public $last_check;
    public $first_failure;
    public $check_count;
    public $http_code;
    public $request_duration;
    public $last_check_attempt;
    public $last_success;
    public $redirect_count;
    public $broken;
    public $working;
    /**
     * @var int
     * @since 24.44.6372
     */
    public $parked =  HTTPCODES::BLC_PARKED_UNCHECKED;
    /**
     * @var int
     */
    public $being_checked = HTTPCODES::BLC_CHECKSTATE_TOCHECK;
    public $mime;
    public $urlid;
    public $data = []; //saved in different table for performance
    public $log  = []; //saved in different table for performance

    /**
     * Constructor
     *
     * @param   DatabaseDriver  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_blc.link';
        parent::__construct('#__blc_links', 'id', $db);
        $this->_db             = $db;
        $this->componentConfig = ComponentHelper::getParams('com_blc');
        $this->internalHosts   = preg_split($this->_splitOption, $this->componentConfig->get('internal_hosts', ''));
        if ($this->internalHosts === false) {
            Factory::getApplication()->enqueueMessage(
                "COM_BLC_INTERNALHOSTS_LIST_INVALID",
                'warning'
            );
            $this->internalHosts = [];
        }
        $this->internalHosts[] = Uri::getInstance()->getHost();
        $this->internalHosts   = array_map('strtolower', array_filter($this->internalHosts));
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function loadStorage()
    {
        if (!$this->id) {
            return;
        }

        $query = $this->_db->getQuery(true);
        $query->select('`log`')
            ->select('`data`')
            ->from('`#__blc_links_storage`')
            ->where('`link_id` = :id')
            ->bind(':id', $this->id);

        $query = $this->_db->setQuery($query);
        $row   = $query = $this->_db->loadObject();
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

        $query = $this->_db->getQuery(true);
        $query->delete('`#__blc_links_storage`')
            ->where('`link_id` = :id')
            ->bind(':id', $this->id);
        $query = $this->_db->setQuery($query)->execute();
        $row   = (object)
        [
            'log'     => $this->maybeEncode($this->log),
            'data'    => $this->maybeEncode($this->data),
            'link_id' => $this->id,
        ];

        $this->_db->insertObject('#__blc_links_storage', $row);
    }

    protected function getPreferedInternal(string $url): string
    {
        $url      = Uri::getInstance($url)->toString(); //removes urlencoding like &amp;
        $sef      = (bool)$this->componentConfig->get('internal_sef', 0);
        $xhtml    = (bool)$this->componentConfig->get('internal_xhtml', 1);
        $absolute =  (bool)$this->componentConfig->get('internal_absolute', 0);
        $url      = $this->route(url: $url, sef: $sef, xhtml: $xhtml, absolute: $absolute);
        return $url;
    }
    protected function setPreferedInternal()
    {
        if (!$this->isInternal()) {
            return;
        }
        $this->internal_url = $this->getPreferedInternal($this->internal_url);
    }

    public function initInternal()
    {
        $this->internal_url = '';
        $parsed             = Uri::getInstance($this->url);
        $scheme             = strtolower($parsed->getScheme() ?? '');
        $host               = strtolower($parsed->getHost() ?? '');
        $host               = preg_replace('#^(www|m)\.#', '', $host);
        if (strpos($this->url, '#') === 0 || \in_array($host, $this->internalHosts) || Uri::isInternal($this->url)) {
            $host   = false;
            $scheme = false;
            $parsed->setHost(null);
            $parsed->setScheme(null);

            $this->internal_url = $parsed->tostring();
        }

        if (!$scheme) {  //most likely a local url
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
            } catch (\Exception $e) {
                //sef failed
                //possible cause: CLI and call like getMenus( com_rsform)
                //go on with the original url.
                //this will give some false results if a seffed url is redirected
            } catch (\Error $e) {
                //sef failed
                //possible cause: CLI and call like getMenus( com_rsform)
                //go on with the original url.
                //this will give some false results if a seffed url is redirected
            }
        } else {
            if ($xhtml && strpos($url, 'index.php') === 0) {
                $url = htmlspecialchars($url, ENT_COMPAT, 'UTF-8');
            }
        }
        $app = Factory::getContainer()->get(SiteApplication::class);
        //do not make absolute when index.php that will break the SEF
        if ($absolute && (!$app->get('sef', 1) || strpos($url, 'index.php') !== 0)) {
            $url =  BlcHelper::root(path: $url);
        }

        return $url;
    }

    public function toString($orig = true, $sef = false, $xhtml = true, $absolute = true)
    {

        if (!$this->isInternal()) {
            return $this->url;
        }

        $url = $orig ? $this->url : $this->internal_url;


        return $this->route($url, $sef, $xhtml, $absolute);
    }

    public function bind($src = [], $ignore = '')
    {
        $ret = parent::bind($src, $ignore);
        return $ret;
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check()
    {
        //ensure bools are stored as int
        $this->broken        = (int)$this->broken;
        $this->working       = (int)$this->working;
        $this->being_checked = (int)$this->being_checked;
        $this->parked = (int)$this->parked;
        if ($this->first_failure == 0) {
            $this->first_failure = $this->_db->getNullDate();
        }
        if ($this->last_success == 0) {
            $this->last_success = $this->_db->getNullDate();
        }
        if ($this->last_check == 0) {
            $this->last_check = $this->_db->getNullDate();
        }
        if ($this->last_check_attempt == 0) {
            $this->last_check_attempt = $this->_db->getNullDate();
        }
        $this->setPreferedInternal();
        return parent::check();
    }
}
