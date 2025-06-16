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
    $_SESSION['notice'] = 'Anda tidak memiliki izin untuk mengakses halaman Admin.';
    $_SESSION['notice_type'] = 'error';
    Func::redirect('/account'); // Redirect to user account page
    exit();
}

// Base language strings for all admin actions
$base_lang_vars = [
    'breadcrumb_home' => 'Home', // Though admin usually doesn't link to public home
    'breadcrumb_admin' => 'Admin Panel',
    'baseurl_root' => $baseurl, // For layout links
];

// Ensure Base::$twig is available
if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    die("Error: Templating engine (Twig) is not available. Cannot render admin page.");
}

switch ($action) {
    case 'settings':
        $page_title = 'Pengaturan Situs';
        $errors = [];

        // Populate current_settings for the form from $set global or POST data
        // $set is loaded in base.php
        $current_settings_values = [
            'siteurl' => $_POST['siteurl'] ?? $set['siteurl'], // Use $set['url'] from original if it's the canonical one
            'sitename' => $_POST['sitename'] ?? $set['sitename'],
            'siteemail' => $_POST['siteemail'] ?? $set['siteemail'],
            'timezone' => $_POST['timezone'] ?? $set['timezone'],
            'pageview' => $_POST['pageview'] ?? $set['pageview'],
            'domains_str' => isset($_POST['domains']) ? trim($_POST['domains']) : implode(',', unserialize($set['domains'] ?? 'a:0:{}')),
            'maxsites' => $_POST['maxsites'] ?? $set['maxsites'],
            'filesize' => $_POST['filesize'] ?? $set['filesize'],
        ];

        if (isset($_POST['submit'])) {
            // Re-assign from POST for processing
            $siteurl_input = trim($_POST['siteurl']);
            $sitename_input = mb_substr(trim($_POST['sitename']), 0, 30); // Max length, trim
            $siteemail_input = trim($_POST['siteemail']);
            $timezone_input = trim($_POST['timezone']); // Basic validation for timezone format could be added
            $pageview_input = abs(intval($_POST['pageview']));
            $domains_input_str = strtolower(trim($_POST['domains']));
            $domains_array = preg_split("/[\s,]+/", $domains_input_str, -1, PREG_SPLIT_NO_EMPTY);
            $maxsites_input = abs(intval($_POST['maxsites']));
            $filesize_input = abs(intval($_POST['filesize']));

            if (!filter_var($siteurl_input, FILTER_VALIDATE_URL))
                $errors['siteurl'] = 'URL Situs tidak benar';
            if (!filter_var($siteemail_input, FILTER_VALIDATE_EMAIL))
                $errors['siteemail'] = 'Email Situs tidak benar';
            // Add more validation as needed for other fields (e.g. timezone format, numeric ranges)

            if (empty($errors)) {
                $settings_to_update = [
                    'siteurl' => $siteurl_input,
                    'sitename' => $sitename_input,
                    'siteemail' => $siteemail_input,
                    'timezone' => $timezone_input,
                    'pageview' => $pageview_input,
                    'domains' => serialize($domains_array),
                    'maxsites' => $maxsites_input,
                    'filesize' => $filesize_input,
                ];
                foreach ($settings_to_update as $key => $value) {
                    $upd = Base::db()->prepare("UPDATE `set` SET `val` = ? WHERE `key` = ?");
                    $upd->execute([$value, $key]);
                }
                $_SESSION['notice'] = "Pengaturan berhasil disimpan.";
                $_SESSION['notice_type'] = 'success';
                Func::redirect('/admin/settings'); // Redirect back to settings page to see changes
                exit();
            }
             // If errors, $current_settings_values will be repopulated from POST by its initialization logic
        }

        $lang_vars = array_merge($base_lang_vars, [
            'settings_title' => $page_title,
            'settings_heading' => 'Pengaturan Situs',
            'breadcrumb_settings' => 'Pengaturan',
            'site_url_label' => 'URL Situs',
            'site_url_help' => 'URL Situs tanpa diakhiri garis miring',
            'site_name_label' => 'Nama Situs',
            'site_email_label' => 'Email Situs',
            'timezone_label' => 'Zona Waktu',
            'timezone_help' => '-12 s/d +12 (contoh: Asia/Jakarta atau UTC offset seperti +7)',
            'list_per_page_label' => 'List Per Halaman',
            'site_domains_label' => 'Domain Situs',
            'site_domains_help' => 'Jika lebih dari satu pisahkan dengan tanda , (koma)',
            'max_sites_label' => 'Maks Situs',
            'max_sites_help' => 'Maksimal jumlah situs per user',
            'max_upload_label' => 'Maks Upload',
            'max_upload_help' => 'Besar file maksimal pada yang diupload (dalam kb).',
            'save_button' => 'Simpan',
        ]);

        echo Base::$twig->render('admin/settings.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set, // For layout
            'form_action_url' => $baseurl . '/admin/settings',
            'current_settings' => $current_settings_values,
            'errors' => $errors,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'check_update':
        $page_title = 'Periksa Pembaruan';
        $update_status = 'unknown'; // error, update_available, up_to_date
        $new_version = '';
        $current_version = Func::getVersion();
        $check_update_url = Func::checkUpdateUrl();
        $author_contact_url = 'http://facebook.com/achunks'; // Example, make configurable if needed
        $author_name = 'Achunk JealousMan';

        // Use context for file_get_contents to set a timeout
        $ctx = stream_context_create(['http'=> ['timeout' => 5]]); // 5 seconds timeout
        $remote_version_data = @file_get_contents($check_update_url, false, $ctx);

        if ($remote_version_data !== false) {
            $new_version = trim($remote_version_data); // Assuming the URL just returns the version string
            if (version_compare($current_version, $new_version, '<')) {
                $update_status = 'update_available';
            } else {
                $update_status = 'up_to_date';
            }
        } else {
            $update_status = 'error';
        }

        $lang_vars = array_merge($base_lang_vars, [
            'check_update_title' => $page_title,
            'check_update_heading' => 'Periksa Pembaruan',
            'breadcrumb_check_update' => 'Periksa Pembaruan',
            'error_checking_update' => 'Tidak dapat memeriksa pembaruan, ini terjadi ketika memanggil URL %url%.',
            'update_available_message' => 'Versi baru telah tersedia, yaitu <strong>IndoWapBuilder v%new_version%</strong><p>Untuk info lebih lanjut silakan hubungi <a class="alert-link" href="%author_contact_url%">%author_name%</a></p>',
            'up_to_date_message' => 'Selamat, Kamu menggunakan IndoWapBuilder v.%current_version%, ini adalah versi terbaru.',
            'unknown_update_status' => 'Status pembaruan tidak diketahui.',
            'back_to_admin' => 'Kembali ke Admin Panel',
        ]);

        echo Base::$twig->render('admin/check_update.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'update_status' => $update_status,
            'current_version' => $current_version,
            'new_version' => $new_version, // Only relevant if update_available
            'check_update_url' => $check_update_url, // For error message
            'author_contact_url' => $author_contact_url,
            'author_name' => $author_name,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'index':
    default:
        $page_title = 'Admin Panel';

        $lang_vars = array_merge($base_lang_vars, [
            'admin_panel_title' => $page_title,
            'admin_panel_heading' => 'Admin Panel',
            'version_info' => '<strong>IndoWapBuilder v.%version%</strong>, <a class="alert-link" href="%check_update_url%">Periksa pembaruan &raquo;</a>',
            'settings_link' => 'Pengaturan',
            'users_link' => 'Manajemen Pengguna', // Example for future
        ]);

        echo Base::$twig->render('admin/index.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'app_version' => Base::getVersion(),
            'check_update_url' => $baseurl . '/admin/check_update',
            'settings_url' => $baseurl . '/admin/settings',
            // 'users_list_url' => $baseurl . '/admin/users', // Example
            'session_notice' => Func::getNotice(),
        ]);
        break;
}
// Footer is now part of the Twig layout (app.twig)
?>