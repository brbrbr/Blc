<?php
// phpcs:disable

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}

use Joomla\Database;

//For JED Checker
\defined('_JEXEC') or die('Restricted access');
\defined('JPATH_BASE') || \define('JPATH_BASE', realpath(__DIR__ . '/../'));

require_once('tableDelta.php');


require_once JPATH_BASE . '/configuration.php';
require_once JPATH_BASE . '/libraries/vendor/autoload.php';

$config      = new  JConfig();
$db_host     = $config->host;
$db_user     = $config->user;
$db_password = $config->password;
$db_database = $config->db;
$db_prefix   = $config->dbprefix;

$queries = file_get_contents('com_blc/admin/sql/install.mysql.sql');
$checker = new Brambring\TableDelta(
    $db_host,
    $db_user,
    $db_password,
    $db_database,
    $db_prefix
);
$checker->delta($queries);
