<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

// Language strings are now globally available to Twig as 'lang' via Base.php

// Determine error message and title
// This controller might be called with specific error codes or messages in the future.
// For now, it's a generic 404-like error.
$page_title = Base::$lang_strings['error_page_title'] ?? 'Error';
// error_heading and error_message will be pulled from global 'lang' in Twig template directly.
// For more dynamic error messages based on type, this logic could be expanded.
// $error_code_from_request = $_GET['code'] ?? '404'; // Example
// $error_message_for_template = Base::$lang_strings['error_specific_message_' . $error_code_from_request] ?? Base::$lang_strings['error_page_default_message'];


$view_vars = [
    'page_title' => $page_title,
    // 'lang' key removed, Twig uses global 'lang'
    'user' => $user ?? null,
    'set' => $set ?? [],
    // 'error_heading' and 'error_message' can be set here if dynamic,
    // otherwise template defaults or lang keys will be used.
    // For simplicity, let Twig handle defaults or direct lang key access for now based on error/default.twig.
    'home_url' => $baseurl ?? '/',
    'session_notice' => Func::getNotice(),
    'baseurl_root' => $baseurl ?? '/',
];

if (isset(Base::$twig) && Base::$twig instanceof \Twig\Environment) {
    http_response_code(404);
    echo Base::$twig->render('error/default.twig', $view_vars);
} else {
    // Fallback if Twig is not available (should not happen in normal operation)
    http_response_code(404);
    echo "<h1>Error</h1><p>Page not found and templating system is unavailable.</p>";
}

// No direct include of footer.php as layout.twig handles it.
?>