<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

class Base
{
    public static $pdo;
    public static $set;
    public static $twig; // Twig environment instance
    public static $controller = 'site';
    public static $action = 'index';

    public function __construct()
    {
        $this->getRoutes();
        // dbConnect must come before settings if settings are loaded from DB
        $this->dbConnect();
        // settings must come before initTwig if Twig needs access to settings
        $this->settings();
        // session_start before any potential output or Twig rendering
        if (session_status() == PHP_SESSION_NONE) { // Check if session already started
            session_start();
        }
        $this->initTwig();  // Initialize Twig
    }

    protected function initTwig()
    {
        // Composer's autoloader - Twig would typically be loaded via Composer
        // This check might be redundant if already done in a central bootstrap file (like install.php or a future app bootstrap)
        // but good for components to be self-reliant if possible.
        if (file_exists(ROOTPATH . 'vendor/autoload.php')) {
            require_once ROOTPATH . 'vendor/autoload.php';
        } else {
            // Fallback or error if Twig is not available
            // This is a critical dependency for rendering.
            // Consider a more graceful handling or specific error page if Twig is critical
            if (!class_exists('\Twig\Environment')) { // Check if Twig is somehow loaded without composer
                 error_log("Twig library not found. Please install via Composer or include manually. Files dependent on Twig may not work.");
                 self::$twig = null;
                 return;
            }
        }

        try {
            $templateDir = ROOTPATH . 'iwbx-templates';
            if (!is_dir($templateDir)) {
                if (!@mkdir($templateDir, 0755, true) && !is_dir($templateDir)) {
                    error_log("Base template directory 'iwbx-templates' not found and could not be created at: " . $templateDir);
                    self::$twig = null;
                    return;
                }
            }
            // Check for subdirectories and create them if they don't exist
            $subDirs = ['layout', 'auth', 'error', 'admin', 'panel', 'file_manager', 'site']; // Add other main template subdirs as needed
            foreach ($subDirs as $subDir) {
                $fullSubDirPath = $templateDir . DIRECTORY_SEPARATOR . $subDir;
                if (!is_dir($fullSubDirPath)) {
                    if (!@mkdir($fullSubDirPath, 0755, true) && !is_dir($fullSubDirPath)) {
                        error_log("Template subdirectory '$subDir' not found and could not be created at: " . $fullSubDirPath);
                        // Not returning here, as Twig might still function for other directories
                    }
                }
            }

            $loader = new \Twig\Loader\FilesystemLoader($templateDir);
            self::$twig = new \Twig\Environment($loader, [
                // TODO: Make debug and cache conditional based on environment (e.g. a global DEBUG constant)
                'debug' => true,
                'cache' => false, // For development. Set to a path like ROOTPATH . 'cache/twig' for production.
                'auto_reload' => true, // Useful for development
            ]);

            if (self::$set && is_array(self::$set)) {
                 self::$twig->addGlobal('set', self::$set);
            }
            // self::$twig->addGlobal('baseurl', self::$set['baseurl'] ?? ''); // Redundant if 'set' is global
            // User object will be added later if needed, as it's instantiated after Base in index.php

        } catch (\Throwable $e) {
            error_log("Error initializing Twig: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // In a production environment, show a user-friendly error page.
            // For now, dying is acceptable during this refactoring if Twig setup is critical.
            // die("A critical error occurred with the templating engine. Please contact support.");
            self::$twig = null;
        }
    }

    public static function getVersion()
    {
        return '1.0.0';
    }
    public static function checkUpdateUrl()
    {
        return 'http://feed.heck.in/files/indowapbuilder.txt';
    }

    public static function db()
    {
        return self::$pdo;
    }

    public static function dbDisconnect()
    {
        self::$pdo = null;
    }

    protected function dbConnect()
    {
        $db_ini_path = ROOTPATH . "iwbx-includes/db.ini";
        if (!file_exists($db_ini_path) || !is_readable($db_ini_path)) {
            die("Error: Database configuration file (db.ini) is missing or not readable.");
        }
        $dbSettings = parse_ini_file($db_ini_path);

        if ($dbSettings === false) {
            die("Error: Could not parse database configuration file (db.ini). Check its format.");
        }

        // Ensure essential keys exist
        $required_keys = ['database', 'host', 'user', 'password'];
        foreach ($required_keys as $key) {
            if (!isset($dbSettings[$key])) {
                die("Error: Database configuration file (db.ini) is missing required key: '{$key}'.");
            }
        }

        $dsn = 'mysql:dbname=' . $dbSettings["database"] . ';host=' . $dbSettings["host"] .
            ''; // The trailing '' is harmless but unnecessary
        try
        {
            self::$pdo = new PDO($dsn, $dbSettings["user"], $dbSettings["password"], array(PDO::
                    MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        catch (PDOException $e)
        {
            // Log the detailed error for the administrator
            error_log("Database Connection Error: " . $e->getMessage());
            // Provide a generic error message to the user
            die("Error: Could not connect to the database. Please try again later or contact the site administrator if the problem persists.");
        }
    }

    protected function settings()
    {
        $set = array();
        $sets = self::$pdo->query("SELECT * FROM `set`");

        foreach ($sets as $st)
        {
            $set[$st['key']] = $st['val'];
        }

        $set['url'] = $set['siteurl'];
        $set['siteurl'] = parse_url($set['siteurl'], PHP_URL_PATH);
        $set['baseurl'] = $set['siteurl'] . '/index.php';

        self::$set = $set;
    }

    protected function getRoutes()
    {
        if (!empty($_SERVER['PATH_INFO']))
        {
            parse_str($_SERVER['QUERY_STRING'], $query_string);
            $handle_requests = array();

            $route = $_SERVER['PATH_INFO'];
            if (mb_substr($route, 0, 1) != '/')
                $route = '/' . $route;
            $r = explode('/', strtr(trim($route), array('//' => '/', '\\' => '/')));
            $c = 0;
            for ($i = 0; $i < count($r); $i++)
            {
                if ($i % 2)
                {
                    if (!isset($handle_requests[$r[$i]]))
                        $handle_requests[$r[$i]] = isset($r[$i + 1]) ? $r[$i + 1] : '';
                }
                if (isset($r[$i]) && $c == 1 && $r[$i] != '/' && !empty($r[$i]))
                    self::$controller = $r[$i];
                if (isset($r[$i]) && $c == 2 && $r[$i] != '/' && !empty($r[$i]))
                    self::$action = $r[$i];
                ++$c;
            }
            $_REQUEST = $_GET = array_merge($query_string, $handle_requests);
        }
    }
}

?>