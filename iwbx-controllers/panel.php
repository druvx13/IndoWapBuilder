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

// Base language strings are now globally available to Twig as 'lang' via Base.php

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
        unset($_SESSION['st']);
    }
}

$baseurl_root_for_twig = $baseurl; // For layout links

switch ($action) {
    case 'modules':
        $page_title = Base::$lang_strings['panel_page_title_modules'] ?? 'Modules Panel';
        // Local $lang_vars removed, specific keys for this template are expected in global 'lang'

        if (isset($_GET['module'])) {
            $module_id = $_GET['module'];
            if (!filter_var($module_id, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9\_]+$/']])) {
                $_SESSION['notice'] = Base::$lang_strings['panel_module_error_invalid_name'] ?? 'Invalid module name.';
                $_SESSION['notice_type'] = 'error';
                Func::redirect('/panel/modules');
                exit;
            }

            $module_class_name = $module_id;
            // $module_file_path = ROOTPATH . 'iwbx-includes/modules/' . $module_class_name . '.php'; // Autoloader handles this

            if (class_exists($module_class_name)) {
                $module_instance = new $module_class_name();
                if (method_exists($module_instance, 'panel')) {
                    $module_output_data = $module_instance->panel();

                    if (!is_array($module_output_data)) {
                        $module_output_data = [
                            'title' => $module_class_name,
                            'raw_html' => 'Error: Module panel() method did not return an array.',
                        ];
                         error_log("Module '$module_class_name' panel() method did not return an array.");
                    }

                    echo Base::$twig->render('panel/module_panel_default.twig', [
                        'page_title' => $page_title,
                        'user' => $user,
                        'set' => $set,
                        'module_id' => $module_id,
                        'module_output' => $module_output_data,
                        'session_notice' => Func::getNotice(),
                        'current_site_url' => $current_site_in_session['url'] ?? null,
                        'baseurl_root' => $baseurl_root_for_twig,
                    ]);
                } else {
                    $_SESSION['notice'] = str_replace('%module_name%', $module_class_name, Base::$lang_strings['panel_module_error_no_panel_method'] ?? "Module '%module_name%' does not have a panel() method.");
                    $_SESSION['notice_type'] = 'error';
                    Func::redirect('/panel/modules'); exit;
                }
            } else {
                 $_SESSION['notice'] = str_replace('%module_name%', $module_class_name, Base::$lang_strings['panel_module_error_class_not_found'] ?? "Module '%module_name%' not found (class error).");
                 $_SESSION['notice_type'] = 'error';
                 Func::redirect('/panel/modules'); exit;
            }
        } else {
            $modules_glob = glob(ROOTPATH . 'iwbx-includes/modules/module_*.php');
            $modules_list_for_template = [];
            if (count($modules_glob)) {
                foreach ($modules_glob as $module_path) {
                    $module_id_from_file = basename($module_path, '.php');
                    if (class_exists($module_id_from_file) && method_exists($module_id_from_file, 'getName')) {
                         $modules_list_for_template[] = ['id' => $module_id_from_file, 'name' => $module_id_from_file::getName(), 'description' => method_exists($module_id_from_file, 'getDescription') ? $module_id_from_file::getDescription() : ''];
                    }
                }
            }
            echo Base::$twig->render('panel/modules.twig', [
                'page_title' => $page_title,
                'user' => $user,
                'set' => $set,
                'modules_list' => $modules_list_for_template,
                'session_notice' => Func::getNotice(),
                'current_site_url' => $current_site_in_session['url'] ?? null,
                'baseurl_root' => $baseurl_root_for_twig,
            ]);
        }
        break;

    case 'dashboard':
        if (!$current_site_in_session) {
            $_SESSION['notice'] = Base::$lang_strings['panel_dashboard_select_site_notice'] ?? 'Please select a site to manage first.';
            $_SESSION['notice_type'] = 'warning';
            Func::redirect('/panel');
            exit();
        }
        $site_for_dashboard = $current_site_in_session;
        $page_title = str_replace('%site_url%', $site_for_dashboard['url'], Base::$lang_strings['panel_page_title_dashboard'] ?? 'Site Dashboard: %site_url%');

        $modules_glob_dash = glob(ROOTPATH . 'iwbx-includes/modules/module_*.php');
        $modules_list_dash = [];
        if (count($modules_glob_dash)) {
            foreach ($modules_glob_dash as $module_path_dash) {
                 $module_id_dash = basename($module_path_dash, '.php');
                if (class_exists($module_id_dash) && method_exists($module_id_dash, 'getName')) {
                    $modules_list_dash[] = ['id' => $module_id_dash, 'name' => $module_id_dash::getName()];
                }
            }
        }

        $site_display_data_dash = $site_for_dashboard;
        $site_display_data_dash['time_formatted'] = Func::displayDate($site_for_dashboard['time']);

        echo Base::$twig->render('panel/dashboard.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'site' => $site_display_data_dash,
            'modules' => $modules_list_dash,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;

    case 'delete_site':
        $page_title = Base::$lang_strings['panel_page_title_delete_site'] ?? 'Delete Site';
        $site_to_delete = null;
        $form_action_url = '';

        if ($id) {
            $q_del = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
            $q_del->execute([$id, $user->id]);
            if ($q_del->rowCount() > 0) {
                $site_to_delete = $q_del->fetch(PDO::FETCH_ASSOC);
                $form_action_url = $baseurl . '/panel/delete_site/id/' . $id;
            }
        }

        if (isset($_POST['submit']) && isset($_POST['site_id'])) {
            $site_id_to_delete = abs(intval($_POST['site_id']));
            $q_check_del = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
            $q_check_del->execute([$site_id_to_delete, $user->id]);
            if ($q_check_del->rowCount() > 0) {
                $site_data_del = $q_check_del->fetch(PDO::FETCH_ASSOC);
                Func::deleteSite($site_data_del);
                $_SESSION['notice'] = str_replace('%site_url%', htmlspecialchars($site_data_del['url']), Base::$lang_strings['panel_delete_site_success_notice'] ?? "Site '%site_url%' deleted successfully.");
                $_SESSION['notice_type'] = 'success';
                 if (isset($_SESSION['st']) && $_SESSION['st'] == $site_id_to_delete) {
                    unset($_SESSION['st']);
                }
            } else {
                $_SESSION['notice'] = Base::$lang_strings['panel_delete_site_error_not_found'] ?? 'Failed to delete site: Site not found or not owned by you.';
                $_SESSION['notice_type'] = 'error';
            }
            Func::redirect('/panel');
            exit();
        }

        echo Base::$twig->render('panel/delete_site.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'site_to_delete' => $site_to_delete,
            'form_action_url' => $form_action_url,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;

    case 'switch':
        if ($id) {
            $q_check_sw = Base::db()->prepare("SELECT `site_id` FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
            $q_check_sw->execute([$id, $user->id]);
            if ($q_check_sw->rowCount() > 0) {
                $_SESSION['st'] = $id;
                 $_SESSION['notice'] = Base::$lang_strings['panel_switch_site_success_notice'] ?? 'Switched to dashboard for selected site.';
                 $_SESSION['notice_type'] = 'info';
                header('Location: ' . $baseurl . '/panel/dashboard');
            } else {
                 $_SESSION['notice'] = Base::$lang_strings['panel_switch_site_error_not_found'] ?? 'Failed to switch: Site not found or not owned by you.';
                 $_SESSION['notice_type'] = 'error';
                header('Location: ' . $baseurl . '/panel');
            }
            exit();
        }
        header('Location: ' . $baseurl . '/panel');
        exit();
        break;

    case 'create_site':
        $page_title = Base::$lang_strings['panel_page_title_create_site'] ?? 'Create New Site';
        $errors = [];
        $max_sites_reached = false;
        $max_sites_limit = $set['maxsites'] ?? 1;

        $req_count_sites = Base::db()->prepare("SELECT COUNT(*) FROM `site` WHERE `user_id` = ?");
        $req_count_sites->execute([$user->id]);
        if ($req_count_sites->fetchColumn() >= $max_sites_limit) {
            $max_sites_reached = true;
            $errors['general'] = str_replace('%maxsites%', $max_sites_limit, Base::$lang_strings['panel_max_sites_reached_error'] ?? 'You have reached the maximum number of sites allowed (%maxsites%).');
        }

        $available_domains_cs = [];
        if (isset($set['domains'])) {
            $parsed_domains_cs = unserialize($set['domains']);
            if ($parsed_domains_cs !== false || $set['domains'] === serialize(false)) {
                $available_domains_cs = $parsed_domains_cs;
            } else {
                $errors['general'] = Base::$lang_strings['panel_create_site_error_domains_config'] ?? 'Error reading domain configuration.';
                error_log("Error unserializing domains from settings for panel/create_site.");
            }
        }

        $form_values_cs = ['subdomain' => $_POST['data']['subdomain'] ?? '', 'domain' => $_POST['data']['domain'] ?? ($available_domains_cs[0] ?? '')];

        if (isset($_POST['submit']) && !$max_sites_reached && !isset($errors['general'])) {
            $form_values_cs['subdomain'] = strtolower(trim($form_values_cs['subdomain']));
            $form_values_cs['domain'] = strtolower(trim($form_values_cs['domain']));

            if (mb_strlen($form_values_cs['subdomain']) < 4 || mb_strlen($form_values_cs['subdomain']) > 16)
                $errors['subdomain'] = Base::$lang_strings['panel_create_site_error_subdomain_length'] ?? 'Subdomain length must be between 4 and 16 characters.';
            elseif (!filter_var($form_values_cs['subdomain'], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?$/']]))
                $errors['subdomain'] = Base::$lang_strings['panel_create_site_error_subdomain_invalid_chars'] ?? 'Subdomain can only contain a-z, 0-9, and hyphen (-), and cannot start or end with a hyphen.';

            if (empty($available_domains_cs)) $errors['domain'] = Base::$lang_strings['panel_create_site_error_domain_invalid'] ?? 'Selected domain is not valid.';
            elseif (!in_array($form_values_cs['domain'], $available_domains_cs)) $errors['domain'] = Base::$lang_strings['panel_create_site_error_domain_invalid'] ?? 'Selected domain is not valid.';

            if (empty($errors)) {
                $full_url_cs = $form_values_cs['subdomain'] . '.' . $form_values_cs['domain'];
                $req_check_url_cs = Base::db()->prepare("SELECT `site_id` FROM `site` WHERE `url` = ?");
                $req_check_url_cs->execute([$full_url_cs]);
                if ($req_check_url_cs->rowCount() > 0) {
                    $errors['subdomain'] = str_replace('%full_url%', htmlspecialchars($full_url_cs), Base::$lang_strings['panel_create_site_error_url_taken'] ?? 'Site address <strong>%full_url%</strong> is already registered.');
                } else {
                    $site_path_cs = ROOTPATH . 'iwbx-sites/' . $full_url_cs;
                    if (is_dir($site_path_cs) || file_exists($site_path_cs)) {
                        $errors['subdomain'] = Base::$lang_strings['panel_create_site_error_dir_exists'] ?? 'Directory for this site already exists on the server. Choose a different name.';
                    } elseif (@mkdir($site_path_cs, 0755, true)) {
                        $st_insert_cs = Base::db()->prepare("INSERT INTO `site` SET `user_id` = ?, `url` = ?, `time` = ?");
                        $st_insert_cs->execute([$user->id, $full_url_cs, time()]);
                        $_SESSION['notice'] = str_replace('%full_url%', htmlspecialchars($full_url_cs), Base::$lang_strings['panel_create_site_success_notice'] ?? 'Site %full_url% created successfully!');
                        $_SESSION['notice_type'] = 'success';
                        header('Location: ' . $baseurl . '/panel/'); exit();
                    } else {
                        $errors['general'] = Base::$lang_strings['panel_create_site_error_mkdir_failed'] ?? 'Failed to create site directory. Please contact an administrator.';
                        error_log("Failed to create directory: " . $site_path_cs . " for user " . $user->id);
                    }
                }
            }
        }

        echo Base::$twig->render('panel/create_site.twig', [
            'page_title' => $page_title,
            'user' => $user, 'set' => $set,
            'form_action_url' => $baseurl . '/panel/create_site',
            'form_values' => $form_values_cs,
            'errors' => $errors,
            'max_sites_reached' => $max_sites_reached,
            'max_sites_limit' => $max_sites_limit,
            'available_domains' => $available_domains_cs,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;

    case 'index':
    default:
        $page_title = Base::$lang_strings['panel_page_title_index'] ?? 'User Panel';
        $sites_list = [];
        $res_sites = Base::db()->prepare("SELECT * FROM `site` WHERE `user_id` = ? ORDER BY `time` DESC");
        $res_sites->execute([$user->id]);
        if ($res_sites->rowCount() > 0) {
            foreach ($res_sites->fetchAll(PDO::FETCH_ASSOC) as $site_item_idx) {
                $site_item_idx['time_formatted'] = Func::displayDate($site_item_idx['time']);
                $sites_list[] = $site_item_idx;
            }
        }

        echo Base::$twig->render('panel/index.twig', [
            'page_title' => $page_title,
            'user' => $user, 'set' => $set,
            'sites' => $sites_list,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;
}
?>
