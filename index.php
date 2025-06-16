<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

if (!file_exists('iwbx-includes/db.ini'))
{
    die('Silakan install terlebih dahulu!');
}
require ('iwbx-includes/base.php');
$server_host = strtolower($_SERVER['SERVER_NAME']);
$site_host = parse_url($set['url'], PHP_URL_HOST);
$site_domain = mb_substr($server_host, 0, 4) == 'www.' ? mb_substr($server_host,
    4) : $server_host;
if (is_dir(ROOTPATH . 'iwbx-sites/' . $site_domain))
{
    $route = isset($_GET['route']) ? Func::validateRoute(trim($_GET['route'])) :
        'index.html';
    // Use pathinfo to get the extension
    $ext = strtolower(pathinfo($route, PATHINFO_EXTENSION));

    if (is_file(ROOTPATH . 'iwbx-sites/' . $site_domain . '/' . $route) && $ext !=
        'html')
    {
        $mime_types = include_once (ROOTPATH . 'iwbx-includes/mime_types.php');
        if (in_array($ext, array_keys($mime_types)))
            $type = $mime_types[$ext];
        else
            $type = "application/octet-stream";
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename=' . basename(ROOTPATH .
            'iwbx-sites/' . $site_domain . '/' . $route));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize(ROOTPATH . 'iwbx-sites/' . $site_domain .
            '/' . $route));
        ob_clean();
        flush();
        readfile(ROOTPATH . 'iwbx-sites/' . $site_domain . '/' . $route);
        exit();
    }
    if (!is_dir(ROOTPATH . 'iwbx-sites/' . $site_domain . '/' . $route) && !is_file
        (ROOTPATH . 'iwbx-sites/' . $site_domain . '/' . $route))
    {
        $path = ROOTPATH . 'iwbx-sites/' . $site_domain;
        $index = '404.html';
    }
    elseif (is_dir(ROOTPATH . 'iwbx-sites/' . $site_domain . '/' . $route))
    {
        $path = ROOTPATH . 'iwbx-sites/' . $site_domain;
        $index = $route . '/index.html';
    }
    else
    {
        $path = ROOTPATH . 'iwbx-sites/' . $site_domain;
        $index = $route;
    }
    if (!file_exists(ROOTPATH . 'iwbx-sites/' . $site_domain . '/' . $index))
    {
        $path = ROOTPATH . 'iwbx-includes'; // Fallback path for 404.html
        $index = '404.html'; // Fallback template
    }

    // Ensure Twig classes are available (assuming vendor/autoload.php was included in base.php or similar)
    // No need for Template_Autoloader::register();

    try {
        $site_template_loader = new \Twig\Loader\FilesystemLoader($path);
        $site_twig = new \Twig\Environment($site_template_loader, [
            'cache' => false, // Or ROOTPATH . 'iwbx-cache/twig_sites/' (ensure writable)
            'debug' => true,  // Should be configurable for production
            'autoescape' => 'html', // Enable autoescaping for security
        ]);

        if (true) { // Assuming debug is enabled, can be tied to a global $set['debug_mode'] or similar
            $site_twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        // Make $set (global settings) available in site templates
        $site_twig->addGlobal('set', $set);
        // Potentially add other globals like a simplified 'user' object if relevant for sites,
        // or specific site data. For now, only 'set'.

        // Module function for site templates
        $module_instance_for_site = new Module(); // Create a new instance or use a global one if appropriate
        $site_twig->addFunction(new \Twig\TwigFunction('module', function ($name, $options = null) use ($module_instance_for_site) {
            // Consider how module output (which might be HTML) interacts with autoescaping.
            // If getModule returns pre-rendered HTML that should not be escaped,
            // the template using it would need `|raw`.
            return $module_instance_for_site->getModule($name, $options);
        }));

        // Prepare variables for the site's main template
        $template_vars_for_site = [
            // Add any other specific variables needed by all site templates here
            // For example, 'current_route' => $route,
        ];

        echo $site_twig->render($index, $template_vars_for_site);

    } catch (\Twig\Error\LoaderError $e) {
        error_log("Twig LoaderError for site $site_domain: " . $e->getMessage());
        // Display a user-friendly error page for site rendering issues
        // This could be a simple HTML page or use the main app's error controller if accessible
        die("Error rendering site template (LoaderError). Please check site template files. Details: " . htmlspecialchars($e->getMessage()));
    } catch (\Twig\Error\RuntimeError $e) {
        error_log("Twig RuntimeError for site $site_domain: " . $e->getMessage());
        die("Error rendering site template (RuntimeError). Please check site template logic. Details: " . htmlspecialchars($e->getMessage()));
    } catch (\Twig\Error\SyntaxError $e) {
        error_log("Twig SyntaxError for site $site_domain: " . $e->getMessage());
        die("Error rendering site template (SyntaxError). Please check site template syntax. Details: " . htmlspecialchars($e->getMessage()));
    } catch (\Exception $e) {
        error_log("General error rendering site $site_domain: " . $e->getMessage());
        die("An unexpected error occurred while rendering the site.");
    }
}
else
{
    if ($server_host != $site_host)
    {
        header('Location: ' . $set['url']);
        exit();
    }
    $user = new User();

    $controllers = array();
    foreach (glob('iwbx-controllers/*.php') as $controller_file)
        $controllers[] = basename($controller_file, '.php');

    if ($controller && ($key = array_search($controller, $controllers)) !== false &&
        file_exists($file = 'iwbx-controllers/' . $controllers[$key] . '.php'))
    {
        include $file;
    }
    else
    {
        include ('iwbx-controllers/error.php');
    }

}
Base::dbDisconnect();

?>