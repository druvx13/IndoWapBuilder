<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

<?php // Ensure PHP tag is present if not already

// Language strings for error page
// In a real app, these might come from a global lang system or be specific to this controller
$lang_vars = [
    'error_title' => 'Kesalahan',
    'error_heading' => 'Terjadi Kesalahan',
    'default_error_message' => 'Halaman yang Anda cari tidak ditemukan atau terjadi kesalahan internal.',
    'go_to_homepage' => 'Kembali ke Beranda',
];

// Determine error message and title
// This controller might be called with specific error codes or messages in the future.
// For now, it's a generic 404-like error as per original.
$page_title = $lang_vars['error_title'];
$error_heading = $lang_vars['error_heading'];
$error_message_for_template = $lang_vars['default_error_message'];

// Check if a specific error type is passed, e.g. via a GET param or session
// Example: $error_type = $_GET['type'] ?? '404';
// switch ($error_type) {
//     case '403':
//         $error_message_for_template = "Anda tidak memiliki izin untuk mengakses halaman ini.";
//         break;
//     // Add more cases as needed
// }


$view_vars = [
    'page_title' => $page_title,
    'lang' => $lang_vars,
    'user' => $user ?? null, // Pass user object if available (for layout)
    'set' => $set ?? [],     // Pass settings if available (for layout)
    'error_heading' => $error_heading,
    'error_message' => $error_message_for_template,
    'home_url' => $baseurl ?? '/', // Fallback to root if baseurl not set
    'session_notice' => Func::getNotice(), // Display any pending notices
    'baseurl_root' => $baseurl ?? '/',
    // 'debug_info' => IS_DEV_MODE ? "Error details..." : null, // Example
];

if (isset(Base::$twig) && Base::$twig instanceof \Twig\Environment) {
    http_response_code(404); // Set appropriate HTTP status code
    echo Base::$twig->render('error/default.twig', $view_vars);
} else {
    // Fallback if Twig is not available (should not happen in normal operation)
    http_response_code(404);
    echo "<h1>Error</h1><p>Page not found and templating system is unavailable.</p>";
}

// No direct include of footer.php as layout.twig handles it.
?>