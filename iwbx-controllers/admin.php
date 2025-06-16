<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

if (!$user->id) {
    $redirect_target = ($set['url'] ?? $baseurl ?? '/') . '/index.php/panel';
    header('Location: ' . $redirect_target);
    exit();
}
if ($user->data['rights'] != 10) { // Admin rights check
    // Use lang string for error message
    $_SESSION['notice'] = Base::$lang_strings['admin_error_permission'] ?? 'You do not have permission to access the Admin Panel.';
    $_SESSION['notice_type'] = 'error';
    Func::redirect('/account'); // Redirect to user account page
    exit();
}

// Base language strings are now globally available to Twig as 'lang' via Base.php initTwig()

// Ensure Base::$twig is available
if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    // This should ideally not happen if Base.php constructor ran successfully
    die("Error: Templating engine (Twig) is not available. Cannot render admin page.");
}

switch ($action) {
    case 'settings':
        $page_title = Base::$lang_strings['admin_settings_page_title'] ?? 'Site Settings';
        $errors = [];

        $current_settings_values = [
            'siteurl' => $_POST['siteurl'] ?? $set['siteurl'],
            'sitename' => $_POST['sitename'] ?? $set['sitename'],
            'siteemail' => $_POST['siteemail'] ?? $set['siteemail'],
            'timezone' => $_POST['timezone'] ?? $set['timezone'],
            'pageview' => $_POST['pageview'] ?? $set['pageview'],
            'domains_str' => isset($_POST['domains']) ? trim($_POST['domains']) : implode(',', unserialize($set['domains'] ?? 'a:0:{}')),
            'maxsites' => $_POST['maxsites'] ?? $set['maxsites'],
            'filesize' => $_POST['filesize'] ?? $set['filesize'],
        ];

        if (isset($_POST['submit'])) {
            $siteurl_input = trim($_POST['siteurl']);
            $sitename_input = mb_substr(trim($_POST['sitename']), 0, 30);
            $siteemail_input = trim($_POST['siteemail']);
            $timezone_input = trim($_POST['timezone']);
            $pageview_input = abs(intval($_POST['pageview']));
            $domains_input_str = strtolower(trim($_POST['domains']));
            $domains_array = preg_split("/[\s,]+/", $domains_input_str, -1, PREG_SPLIT_NO_EMPTY);
            $maxsites_input = abs(intval($_POST['maxsites']));
            $filesize_input = abs(intval($_POST['filesize']));

            if (!filter_var($siteurl_input, FILTER_VALIDATE_URL))
                $errors['siteurl'] = Base::$lang_strings['admin_settings_error_siteurl_invalid'] ?? 'Site URL is not valid.';
            if (!filter_var($siteemail_input, FILTER_VALIDATE_EMAIL))
                $errors['siteemail'] = Base::$lang_strings['admin_settings_error_siteemail_invalid'] ?? 'Site Email is not valid.';

            if (empty($errors)) {
                $settings_to_update = [
                    'siteurl' => $siteurl_input, 'sitename' => $sitename_input, 'siteemail' => $siteemail_input,
                    'timezone' => $timezone_input, 'pageview' => $pageview_input,
                    'domains' => serialize($domains_array), 'maxsites' => $maxsites_input, 'filesize' => $filesize_input,
                ];
                foreach ($settings_to_update as $key => $value) {
                    $upd = Base::db()->prepare("UPDATE `set` SET `val` = ? WHERE `key` = ?");
                    $upd->execute([$value, $key]);
                }
                $_SESSION['notice'] = Base::$lang_strings['admin_settings_success_notice'] ?? "Settings saved successfully.";
                $_SESSION['notice_type'] = 'success';
                Func::redirect('/admin/settings');
                exit();
            }
        }

        // Specific lang keys for this template are expected in global 'lang' from en.php
        echo Base::$twig->render('admin/settings.twig', [
            'page_title' => $page_title,
            'user' => $user, // For layout
            'set' => $set,   // For layout
            'form_action_url' => $baseurl . '/admin/settings',
            'current_settings' => $current_settings_values,
            'errors' => $errors,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl, // For breadcrumbs in template
        ]);
        break;

    case 'check_update':
        $page_title = Base::$lang_strings['admin_check_update_page_title'] ?? 'Check for Updates';
        $update_status = 'unknown';
        $new_version = '';
        $current_version = Func::getVersion();
        $check_update_url = Func::checkUpdateUrl();
        $author_contact_url = 'http://facebook.com/achunks';
        $author_name = 'Achunk JealousMan';

        $ctx = stream_context_create(['http'=> ['timeout' => 5]]);
        $remote_version_data = @file_get_contents($check_update_url, false, $ctx);

        if ($remote_version_data !== false) {
            $new_version = trim($remote_version_data);
            if (version_compare($current_version, $new_version, '<')) {
                $update_status = 'update_available';
            } else {
                $update_status = 'up_to_date';
            }
        } else {
            $update_status = 'error';
        }

        echo Base::$twig->render('admin/check_update.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'update_status' => $update_status,
            'current_version' => $current_version,
            'new_version' => $new_version,
            'check_update_url' => $check_update_url,
            'author_contact_url' => $author_contact_url,
            'author_name' => $author_name,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl,
        ]);
        break;

    case 'index':
    default:
        $page_title = Base::$lang_strings['admin_index_page_title'] ?? 'Admin Panel';

        echo Base::$twig->render('admin/index.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'app_version' => Base::getVersion(),
            'check_update_url' => $baseurl . '/admin/check_update',
            'settings_url' => $baseurl . '/admin/settings',
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl,
        ]);
        break;
}
// Footer is now part of the Twig layout (app.twig)
?>
