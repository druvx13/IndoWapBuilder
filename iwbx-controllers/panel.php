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

// Base language strings for all panel actions
$base_lang_vars = [
    'breadcrumb_home' => 'Home',
    'breadcrumb_panel' => 'Panel',
    'baseurl_root' => $baseurl, // For layout links
];

// Ensure Base::$twig is available
if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    die("Error: Templating engine (Twig) is not available. Cannot render panel page.");
}

// Fetch current site from session if set (used by dashboard and modules page)
$current_site_in_session = null;
$stid_session = isset($_SESSION['st']) ? abs(intval($_SESSION['st'])) : false;
if ($stid_session) {
    $q_current_site = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
    $q_current_site->execute([$stid_session, $user->id]);
    if ($q_current_site->rowCount() > 0) {
        $current_site_in_session = $q_current_site->fetch(PDO::FETCH_ASSOC);
    } else {
        unset($_SESSION['st']); // Invalid site ID in session
    }
}


switch ($action) {
    case 'modules':
        $page_title = 'Modul Panel';
        $lang_vars = array_merge($base_lang_vars, [
            'modules_title' => $page_title,
            'modules_heading' => 'Modul Panel',
            'breadcrumb_modules' => 'Modul',
            'available_modules_heading' => 'Modul Tersedia',
            'no_modules_available' => 'Tidak ada modul yang tersedia saat ini.',
        ]);

        if (isset($_GET['module'])) {
            $module_id = $_GET['module'];
            if (!filter_var($module_id, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9\_]+$/']])) {
                Func::redirect('/panel/modules?error=invalid_module_name'); // Or show error
                exit;
            }
            // The module file itself should be included by the autoloader if named correctly (e.g. Module_Blog)
            // Or by a specific loader if modules are not PSR-4 classes.
            // The original code uses `class_exists` and `file_exists` with `module_` prefix implicitly.
            // This part requires the module to handle its own rendering, possibly using Base::$twig.
            // For now, this specific part of rendering a module's panel is complex to fully Twigify
            // without knowing the module's structure. We'll focus on listing modules.
            // If a module's panel() method echoes HTML, it will break the Twig layout.
            // This needs a strategy: either modules use Twig, or their output is captured.

            $module_class_name = $module_id;
            // Autoloader should handle including the module file based on class name
            // e.g. if $module_id is 'module_test', class 'module_test' should be loadable.

            if (class_exists($module_class_name)) {
                $module_instance = new $module_class_name();
                if (method_exists($module_instance, 'panel')) {
                    $module_output_data = $module_instance->panel(); // Expected to return an array

                    // Ensure $module_output_data is an array, default if not
                    if (!is_array($module_output_data)) {
                        $module_output_data = [
                            'title' => $module_class_name, // Fallback title
                            'raw_html' => 'Error: Module panel() method did not return an array.',
                        ];
                         error_log("Module '$module_class_name' panel() method did not return an array.");
                    }

                    // Update lang vars for the module panel default template
                    $lang_vars_module_panel = array_merge($lang_vars, [
                        'module_panel_title' => $module_output_data['title'] ?? $module_class_name,
                        'module_panel_default_heading' => $module_output_data['title'] ?? $module_class_name,
                        'module_no_content' => 'Modul tidak menghasilkan konten atau formatnya salah.',
                        'back_to_modules_list' => 'Kembali ke Daftar Modul',
                    ]);

                    echo Base::$twig->render('panel/module_panel_default.twig', [
                        'page_title' => $page_title, // Main page title "Modul Panel"
                        'lang' => $lang_vars_module_panel,
                        'user' => $user,
                        'set' => $set,
                        'module_id' => $module_id, // Pass the module ID for context
                        'module_output' => $module_output_data, // Data from module's panel() method
                        'session_notice' => Func::getNotice(),
                        'current_site_url' => $current_site_in_session['url'] ?? null,
                    ]);
                } else {
                    $_SESSION['notice'] = "Modul '$module_class_name' tidak memiliki metode panel().";
                    $_SESSION['notice_type'] = 'error';
                    Func::redirect('/panel/modules'); exit;
                }
            } else {
                 $_SESSION['notice'] = "Modul '$module_class_name' tidak ditemukan (class error).";
                     $_SESSION['notice_type'] = 'error';
                     Func::redirect('/panel/modules'); exit;
                }
            } else {
                 $_SESSION['notice'] = "File modul '$module_class_name' tidak ditemukan.";
                 $_SESSION['notice_type'] = 'error';
                 Func::redirect('/panel/modules'); exit;
            }
        } else {
            // List available modules
            $modules_glob = glob(ROOTPATH . 'iwbx-includes/modules/module_*.php');
            $modules_list_for_template = [];
            if (count($modules_glob)) {
                foreach ($modules_glob as $module_path) {
                    $module_id = basename($module_path, '.php');
                    // include_once $module_path; // Autoloader should handle if class name matches
                    if (class_exists($module_id) && method_exists($module_id, 'getName')) {
                         $modules_list_for_template[] = ['id' => $module_id, 'name' => $module_id::getName(), 'description' => method_exists($module_id, 'getDescription') ? $module_id::getDescription() : ''];
                    }
                }
            }
            echo Base::$twig->render('panel/modules.twig', [
                'page_title' => $page_title,
                'lang' => $lang_vars,
                'user' => $user,
                'set' => $set,
                'modules_list' => $modules_list_for_template,
                'session_notice' => Func::getNotice(),
                'current_site_url' => $current_site_in_session['url'] ?? null,
            ]);
        }
        break;

    case 'dashboard':
        if (!$current_site_in_session) {
            $_SESSION['notice'] = 'Silakan pilih situs untuk dikelola terlebih dahulu.';
            $_SESSION['notice_type'] = 'warning';
            Func::redirect('/panel'); // Redirect to site selection
            exit();
        }
        $site = $current_site_in_session;
        $page_title = 'Dashboard: ' . $site['url'];

        $modules_glob = glob(ROOTPATH . 'iwbx-includes/modules/module_*.php');
        $modules_list_for_template = [];
        if (count($modules_glob)) {
            foreach ($modules_glob as $module_path) {
                 $module_id = basename($module_path, '.php');
                // include_once $module_path; // Autoloader should handle
                if (class_exists($module_id) && method_exists($module_id, 'getName')) {
                    $modules_list_for_template[] = ['id' => $module_id, 'name' => $module_id::getName()];
                }
            }
        }

        $lang_vars = array_merge($base_lang_vars, [
            'dashboard_title' => 'Dashboard',
            'dashboard_heading' => 'Dashboard Situs',
            'modules_heading' => 'Modul Terpasang',
            'no_modules_installed' => 'Tidak ada modul yang terpasang atau tersedia.',
            'file_manager_link' => 'File Manager',
            'site_details_heading' => 'Detail Situs',
            'site_url_label' => 'URL',
            'created_on_label' => 'Dibuat',
            'site_status_label' => 'Status',
            'site_status_active' => 'Aktif',
            'disk_usage_label' => 'Penggunaan Disk',
        ]);

        $site_display_data = $site;
        $site_display_data['time_formatted'] = Func::displayDate($site['time']);

        echo Base::$twig->render('panel/dashboard.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'site' => $site_display_data,
            'modules' => $modules_list_for_template,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'delete_site':
        $page_title = 'Hapus Situs';
        $site_to_delete = null;
        $form_action_url = '';

        if ($id) { // $id comes from base.php, parsed from request
            $q = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
            $q->execute([$id, $user->id]);
            if ($q->rowCount() > 0) {
                $site_to_delete = $q->fetch(PDO::FETCH_ASSOC);
                $form_action_url = $baseurl . '/panel/delete_site/id/' . $id;
            }
        }

        if (isset($_POST['submit']) && isset($_POST['site_id'])) {
            $site_id_to_delete = abs(intval($_POST['site_id']));
            // Re-fetch to ensure user still owns it, even if $id from GET was different
            $q_check = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
            $q_check->execute([$site_id_to_delete, $user->id]);
            if ($q_check->rowCount() > 0) {
                $site_data = $q_check->fetch(PDO::FETCH_ASSOC);
                Func::deleteSite($site_data); // deleteSite handles file deletion and DB record
                $_SESSION['notice'] = "Situs '" . htmlspecialchars($site_data['url']) . "' berhasil dihapus.";
                $_SESSION['notice_type'] = 'success';
                 if (isset($_SESSION['st']) && $_SESSION['st'] == $site_id_to_delete) {
                    unset($_SESSION['st']); // Clear active site if it was the one deleted
                }
            } else {
                $_SESSION['notice'] = 'Gagal menghapus situs: Situs tidak ditemukan atau bukan milik Anda.';
                $_SESSION['notice_type'] = 'error';
            }
            Func::redirect('/panel'); // Redirect to main panel page
            exit();
        }

        $lang_vars = array_merge($base_lang_vars, [
            'delete_site_title' => $page_title,
            'delete_site_heading' => 'Hapus Situs',
            'breadcrumb_delete_site' => 'Hapus Situs',
            'confirm_delete_message' => 'Kamu yakin akan menghapus situs %site_url%?',
            'yes_delete_button' => 'Ya, Hapus',
            'cancel_button' => 'Batal',
            'site_not_found_error' => 'Situs yang akan dihapus tidak ditemukan atau Anda tidak memiliki izin.',
            'back_to_panel_button' => 'Kembali ke Panel',
        ]);

        echo Base::$twig->render('panel/delete_site.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'site_to_delete' => $site_to_delete,
            'form_action_url' => $form_action_url,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'switch':
        if ($id) { // $id comes from base.php
            // Verify user owns this site before switching
            $q_check = Base::db()->prepare("SELECT `site_id` FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
            $q_check->execute([$id, $user->id]);
            if ($q_check->rowCount() > 0) {
                $_SESSION['st'] = $id; // Set active site ID in session
                 $_SESSION['notice'] = 'Berpindah ke dashboard situs yang dipilih.';
                 $_SESSION['notice_type'] = 'info';
                header('Location: ' . $baseurl . '/panel/dashboard');
            } else {
                 $_SESSION['notice'] = 'Gagal berpindah: Situs tidak ditemukan atau bukan milik Anda.';
                 $_SESSION['notice_type'] = 'error';
                header('Location: ' . $baseurl . '/panel');
            }
            exit();
        }
        // If no ID, redirect to panel index
        header('Location: ' . $baseurl . '/panel');
        exit();
        break;

    case 'create_site':
        $page_title = 'Buat Situs Baru';
        $errors = [];
        $max_sites_reached = false;
        $max_sites_limit = $set['maxsites'] ?? 1;

        $req_count = Base::db()->prepare("SELECT COUNT(*) FROM `site` WHERE `user_id` = ?");
        $req_count->execute([$user->id]);
        if ($req_count->fetchColumn() >= $max_sites_limit) {
            $max_sites_reached = true;
        }

        $available_domains = [];
        if (isset($set['domains'])) {
            $parsed_domains = unserialize($set['domains']);
            if ($parsed_domains !== false || $set['domains'] === serialize(false)) {
                $available_domains = $parsed_domains;
            } else {
                $errors['general'] = 'Error membaca konfigurasi domain.';
                error_log("Error unserializing domains from settings for panel/create_site.");
            }
        }

        $form_values = [
            'subdomain' => $_POST['subdomain'] ?? '',
            'domain' => $_POST['domain'] ?? ($available_domains[0] ?? ''),
        ];

        if (isset($_POST['submit']) && !$max_sites_reached) {
            $form_values['subdomain'] = strtolower(trim($form_values['subdomain']));
            $form_values['domain'] = strtolower(trim($form_values['domain']));

            if (mb_strlen($form_values['subdomain']) < 4 || mb_strlen($form_values['subdomain']) > 16)
                $errors['subdomain'] = 'Panjang subdomain min. 4 s/d 16 karakter.';
            elseif (!filter_var($form_values['subdomain'], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?$/']])) // RFC compliant-like
                $errors['subdomain'] = 'Subdomain hanya boleh berisi a-z, 0-9 dan tanda hubung (-), tidak boleh diawali/diakhiri tanda hubung.';

            if (empty($available_domains)) {
                 $errors['domain'] = 'Tidak ada domain yang tersedia untuk pembuatan situs.';
            } elseif (!in_array($form_values['domain'], $available_domains)) {
                $errors['domain'] = 'Domain yang dipilih tidak valid.';
            }

            if (empty($errors)) { // Only proceed if basic validation passes
                $full_url = $form_values['subdomain'] . '.' . $form_values['domain'];
                $req_check_url = Base::db()->prepare("SELECT `site_id` FROM `site` WHERE `url` = ?");
                $req_check_url->execute([$full_url]);
                if ($req_check_url->rowCount() > 0) {
                    $errors['subdomain'] = 'Alamat situs <strong>' . htmlspecialchars($full_url) . '</strong> sudah terdaftar.';
                } else {
                    $site_path = ROOTPATH . 'iwbx-sites/' . $full_url;
                    if (is_dir($site_path) || file_exists($site_path)) {
                        $errors['subdomain'] = 'Direktori untuk situs ini sudah ada di server. Pilih nama lain.';
                    } elseif (mkdir($site_path, 0755, true)) { // Recursive creation
                        $st_insert = Base::db()->prepare("INSERT INTO `site` SET `user_id` = ?, `url` = ?, `time` = ?");
                        $st_insert->execute([$user->id, $full_url, time()]);
                        $_SESSION['notice'] = 'Situs ' . htmlspecialchars($full_url) . ' berhasil dibuat!';
                        $_SESSION['notice_type'] = 'success';
                        header('Location: ' . $baseurl . '/panel/');
                        exit();
                    } else {
                        $errors['general'] = 'Pembuatan direktori situs gagal. Silakan hubungi Administrator.';
                        error_log("Failed to create directory: " . $site_path);
                    }
                }
            }
        }

        $lang_vars = array_merge($base_lang_vars, [
            'create_site_title' => $page_title,
            'create_site_heading' => 'Buat Situs Baru',
            'breadcrumb_create_site' => 'Buat Situs',
            'max_sites_error_message' => 'Anda sudah tidak diijinkan lagi membuat situs baru, Maksimal jumlah situs per user adalah %maxsites%.',
            'subdomain_label' => 'Subdomain',
            'domain_label' => 'Domain',
            'no_domains_available' => 'Tidak ada domain tersedia',
            'create_button' => 'Membuat',
        ]);

        echo Base::$twig->render('panel/create_site.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/panel/create_site',
            'form_values' => $form_values,
            'errors' => $errors,
            'max_sites_reached' => $max_sites_reached,
            'max_sites_limit' => $max_sites_limit,
            'available_domains' => $available_domains,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'index':
    default:
        $page_title = 'Panel Pengguna';
        $sites = [];
        $res = Base::db()->prepare("SELECT * FROM `site` WHERE `user_id` = ? ORDER BY `time` DESC");
        $res->execute([$user->id]);
        if ($res->rowCount() > 0) {
            foreach ($res->fetchAll(PDO::FETCH_ASSOC) as $site_item) {
                $site_item['time_formatted'] = Func::displayDate($site_item['time']);
                $sites[] = $site_item;
            }
        }

        $lang_vars = array_merge($base_lang_vars, [
            'panel_title' => $page_title,
            'panel_heading' => 'Panel Pengguna',
            'create_new_site_button' => 'Membuat situs baru',
            'site_created_label' => 'Dibuat',
            'manage_site_button' => 'Kelola',
            'delete_site_button' => 'Hapus',
            'confirm_delete_site' => 'Yakin ingin menghapus situs ini?', // For JS confirm, if used
            'no_sites_message' => 'Kamu belum memiliki situs.',
        ]);

        echo Base::$twig->render('panel/index.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'sites' => $sites,
            'session_notice' => Func::getNotice(),
        ]);
        break;
}
// Footer is now part of the Twig layout (app.twig)
?>