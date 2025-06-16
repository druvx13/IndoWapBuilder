<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

//@ini_set('display_errors', false);
//@error_reporting(7);
@ini_set('session.use_trans_sid', 0);
// magic_quotes_sybase and magic_quotes_runtime were removed in PHP 5.4. These lines are no longer needed.
// @ini_set('magic_quotes_sybase', 0);
// @ini_set('magic_quotes_runtime', 0);
@ini_set('arg_separator.output', '&amp;');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

// Define ROOTPATH using __DIR__ for better readability and consistency.
// __DIR__ gives the directory of the current file (iwbx-includes).
// dirname(__DIR__) gives its parent directory (the project root).
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

spl_autoload_register('loadcomponents');
function loadcomponents($name)
{
    // Sanitize $name to prevent directory traversal if it could ever be manipulated.
    // Basic basename sanitization, assuming class names don't contain special chars.
    $name = basename($name);

    $component_file = ROOTPATH . 'iwbx-includes/components/' . $name . '.php';
    if (file_exists($component_file)) {
        include $component_file;
        return;
    }

    $module_file = ROOTPATH . 'iwbx-includes/modules/' . $name . '.php';
    if (file_exists($module_file)) {
        include $module_file;
        return;
    }
}

new Base();

$pdo = Base::$pdo;
$set = Base::$set;
$baseurl = $set['baseurl'];
$controller = Base::$controller;
$action = Base::$action;
$kmess = $set['pageview'] > 4 && $set['pageview'] < 100 ? $set['pageview'] : 10;
$id = isset($_REQUEST['id']) ? abs(intval($_REQUEST['id'])) : false;
$is_modal = isset($_GET['__modal']) ? true : false;
$act = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : '';
$mod = isset($_REQUEST['mod']) ? trim($_REQUEST['mod']) : '';
$page = isset($_REQUEST['page']) && (ctype_digit($_REQUEST['page'])) && ($_REQUEST['page'] >
    0) ? intval($_REQUEST['page']) : 1;
$start = isset($_REQUEST['page']) ? $page * $kmess - $kmess : (isset($_GET['start']) ?
    abs(intval($_GET['start'])) : 0);

?>