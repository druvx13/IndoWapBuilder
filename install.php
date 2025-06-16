<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

session_start();

// Define ROOTPATH - assuming install.php is in the project's root directory
if (!defined('ROOTPATH')) {
    define('ROOTPATH', __DIR__ . DIRECTORY_SEPARATOR);
}

// Composer's autoloader - Twig would typically be loaded via Composer
if (file_exists(ROOTPATH . 'vendor/autoload.php')) {
    require_once ROOTPATH . 'vendor/autoload.php';
} else {
    // This is a placeholder. In a real scenario, if Twig isn't found,
    // you'd die here or have a manual include for Twig's autoloader.
    // For the tool environment, we assume Twig classes are available if this script runs.
    // die("Twig library not found. Please run 'composer install'.");
}

// Initialize Twig
$loader = null;
$twig = null;
try {
    // Ensure the template directory exists
    if (!is_dir(ROOTPATH . 'iwbx-templates/install')) {
        die("Template directory not found: " . ROOTPATH . 'iwbx-templates/install' . "<br>Please create it.");
    }
    $loader = new \Twig\Loader\FilesystemLoader(ROOTPATH . 'iwbx-templates/install');
    $twig = new \Twig\Environment($loader, [
        'debug' => true,
        'cache' => false,
    ]);
    // Example: $twig->addExtension(new \Twig\Extension\DebugExtension()); // Uncomment if needed
} catch (\Throwable $e) {
    die("Error initializing Twig: " . $e->getMessage() . "<br>Make sure Twig is installed and iwbx-templates/install directory exists.");
}


function install_indowapbuilder($pdo, $sql_filename = 'indowapbuilder.sql')
{
    $sql_file_path = ROOTPATH . $sql_filename;
    if (!file_exists($sql_file_path) || !is_readable($sql_file_path)) {
        throw new \Exception("SQL installation file not found or not readable: " . htmlspecialchars($sql_file_path));
    }

    $query_string = file_get_contents($sql_file_path);
    if ($query_string === false) {
        throw new \Exception("Could not read SQL installation file: " . htmlspecialchars($sql_file_path));
    }

    $query_string = trim($query_string);
    $query_string = preg_replace("/\n\#[^\n]*/", '', "\n" . $query_string); // Remove SQL comments

    $sql_commands = [];
    $current_command = '';
    $in_string_char = ''; // Basic way to track if we are inside a string

    // Basic SQL splitter (might not handle all edge cases like escaped quotes within strings perfectly)
    // Consider a more robust SQL parser if complex SQL files are used.
    $lines = explode("\n", $query_string);
    foreach ($lines as $line) {
        $line_trimmed = trim($line);
        if (empty($line_trimmed) || strpos($line_trimmed, '--') === 0) { // Skip empty lines and -- comments
            continue;
        }

        $current_command .= $line . "\n";

        // Rudimentary string detection (doesn't handle escaped quotes well)
        // This part needs to be more robust for complex SQL.
        // For simple SQL structure in indowapbuilder.sql, it might suffice.
        $single_quotes = substr_count($line, "'");
        $double_quotes = substr_count($line, '"');

        if ($single_quotes % 2 != 0) {
            $in_string_char = ($in_string_char === "'" ? "" : "'");
        }
        if ($double_quotes % 2 != 0) {
             $in_string_char = ($in_string_char === '"' ? "" : '"');
        }

        if (substr($line_trimmed, -1) === ';' && $in_string_char === '') {
            $sql_commands[] = trim($current_command);
            $current_command = '';
        }
    }
    if (!empty(trim($current_command))) { // Add any trailing command
        $sql_commands[] = trim($current_command);
    }

    foreach ($sql_commands as $command) {
        if (!empty(trim($command))) {
            $pdo->exec($command); // Use exec for DDL/DML without results
        }
    }
}


// --- Language Strings ---
$lang = [
    'install_title' => 'Instalasi IndoWapBuilder',
    'db_setup_title' => 'Pengaturan Database',
    'site_admin_setup_title' => 'Pengaturan Situs & Admin',
    'success_title' => 'Instalasi Berhasil',
    'mysql_host_label' => 'MySQL Host',
    'mysql_user_label' => 'MySQL User',
    'mysql_password_label' => 'MySQL Password',
    'mysql_database_label' => 'MySQL Database',
    'continue_button' => 'Lanjutkan',
    'install_button' => 'Install',
    'site_url_label' => 'URL Situs',
    'site_url_help' => 'URL Situs tanpa diakhiri garis miring',
    'admin_name_label' => 'Nama Admin',
    'admin_name_help' => 'Jika lebih dari satu pisahkan dengan tanda , (koma)',
    'admin_email_label' => 'Email Admin',
    'admin_password_label' => 'Kata sandi Admin',
    'error_db_connect' => 'Tidak dapat terhubung ke database.',
    'error_db_connect_details' => 'Detail:',
    'back_button' => 'Kembali',
    'installation_successful' => 'Instalasi berhasil diselesaikan.',
    'admin_panel_link_text' => 'Admin Panel',
    'delete_install_warning' => 'Demi keamanan harap hapus file <strong>install.php</strong>',
    'form_action_url' => 'install.php',
    'db_details_lost_error' => 'Detail database tidak ditemukan atau sesi berakhir. Harap mulai dari awal.',
    'db_reconnect_error' => 'Gagal menyambung kembali ke database dengan detail yang disimpan. Periksa kembali detail database.',
    'site_url_error' => 'URL Situs wajib diisi dan valid.',
    'admin_name_error' => 'Nama Admin wajib diisi.',
    'admin_email_error' => 'Email Admin tidak valid.',
    'admin_password_error' => 'Kata sandi Admin wajib diisi (minimal 4 karakter).',
    'db_config_write_error' => 'Gagal menulis file konfigurasi db.ini.',
    'sql_install_error' => 'Gagal menjalankan file SQL instalasi: ',
    'db_host_required' => 'Host MySQL wajib diisi.',
    'db_user_required' => 'User MySQL wajib diisi.',
    'db_database_required' => 'Nama Database wajib diisi.',
];

// --- Application Logic ---
$db_input = isset($_POST['db']) ? $_POST['db'] : [];
$data_input = isset($_POST['data']) ? $_POST['data'] : [];
$form_errors = [];
$db_connection_error_details = false;
$db_connected = false; // Is a connection currently active and successful for this request
$pdo = null;
$current_step = 1;

if (isset($_POST['submit_step1'])) {
    if (empty($db_input['host'])) $form_errors['db_host'] = $lang['db_host_required'];
    if (empty($db_input['user'])) $form_errors['db_user'] = $lang['db_user_required'];
    if (empty($db_input['database'])) $form_errors['db_database'] = $lang['db_database_required'];

    if (empty($form_errors)) {
        $dsn = 'mysql:dbname=' . $db_input['database'] . ';host=' . $db_input['host'];
        try {
            $pdo = new PDO($dsn, $db_input["user"], $db_input["password"], [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $_SESSION['install_db_details'] = $db_input; // Save for next step
            $current_step = 2;
            $db_connected = true; // Used to control template display logic
        } catch (PDOException $e) {
            $db_connection_error_details = $e->getMessage();
            $form_errors['db'] = $lang['error_db_connect'];
        }
    }
} elseif (isset($_POST['submit_step2'])) {
    $current_step = 2;
    $db_input_session = isset($_SESSION['install_db_details']) ? $_SESSION['install_db_details'] : null;

    if (!$db_input_session) {
        $form_errors['general'] = $lang['db_details_lost_error'];
        // Force back to step 1 by not setting $db_connected and letting $current_step remain 2, which will render step1_db_form via logic below
    } else {
        if (empty($data_input['siteurl']) || !filter_var($data_input['siteurl'], FILTER_VALIDATE_URL)) $form_errors['siteurl'] = $lang['site_url_error'];
        if (empty($data_input['admname'])) $form_errors['admname'] = $lang['admin_name_error'];
        if (empty($data_input['admemail']) || !filter_var($data_input['admemail'], FILTER_VALIDATE_EMAIL)) $form_errors['admemail'] = $lang['admin_email_error'];
        if (empty($data_input['admpass']) || strlen($data_input['admpass']) < 4) $form_errors['admpass'] = $lang['admin_password_error'];

        if (empty($form_errors)) {
            $dsn = 'mysql:dbname=' . $db_input_session['database'] . ';host=' . $db_input_session['host'];
            try {
                $pdo = new PDO($dsn, $db_input_session["user"], $db_input_session["password"], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                install_indowapbuilder($pdo, 'indowapbuilder.sql');

                $q_set = $pdo->prepare("UPDATE `set` SET `val` = ? WHERE `key` = ?");
                $q_set->execute([rtrim($data_input['siteurl'], '/'), 'siteurl']);

                $password_hash = password_hash($data_input['admpass'], PASSWORD_DEFAULT);

                $user_insert = $pdo->prepare("INSERT INTO `user` SET `name` = ?, `email` = ?, `password` = ?, `rights` = ?, `regtime` = ?");
                $user_insert->execute([$data_input['admname'], $data_input['admemail'], $password_hash, '10', time()]);

                $_SESSION['uid_installed'] = $pdo->lastInsertId(); // Use a different session var to avoid conflict with main app
                $_SESSION['upw_installed'] = $data_input['admpass'];

                $dbconfig_content = "host = \"" . addslashes($db_input_session['host']) . "\"\r\n" .
                                    "database = \"" . addslashes($db_input_session['database']) . "\"\r\n" .
                                    "user = \"" . addslashes($db_input_session['user']) . "\"\r\n" .
                                    "password = \"" . addslashes($db_input_session['password']) . "\";";
                if (@file_put_contents(ROOTPATH . 'iwbx-includes/db.ini', $dbconfig_content) === false) {
                     $form_errors['general_step2'] = $lang['db_config_write_error'];
                } else {
                    unset($_SESSION['install_db_details']);
                    $current_step = 3;
                }
            } catch (PDOException $e) {
                $db_connection_error_details = $e->getMessage();
                $form_errors['db_step2'] = $lang['db_reconnect_error'];
            } catch (\Exception $e) {
                 $form_errors['sql_install'] = $lang['sql_install_error'] . $e->getMessage();
            }
        }
    }
}

// --- Prepare Template Variables ---
$template_vars = [
    'lang' => $lang,
    'page_title' => ($current_step == 3) ? $lang['success_title'] : $lang['install_title'],
    'errors' => $form_errors,
    'db_connection_error_details' => $db_connection_error_details,
    'current_step' => $current_step,

    'db_host_value' => isset($db_input['host']) ? htmlspecialchars($db_input['host']) : 'localhost',
    'db_user_value' => isset($db_input['user']) ? htmlspecialchars($db_input['user']) : 'root',
    'db_password_value' => isset($db_input['password']) ? htmlspecialchars($db_input['password']) : '',
    'db_database_value' => isset($db_input['database']) ? htmlspecialchars($db_input['database']) : 'indowapbuilder',

    'site_url_value' => isset($data_input['siteurl']) ? htmlspecialchars($data_input['siteurl']) : 'http://' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
    'admin_name_value' => isset($data_input['admname']) ? htmlspecialchars($data_input['admname']) : 'admin',
    'admin_email_value' => isset($data_input['admemail']) ? htmlspecialchars($data_input['admemail']) : 'admin@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
    'admin_password_value' => isset($data_input['admpass']) ? htmlspecialchars($data_input['admpass']) : '',

    // These are for repopulating step 2 form's hidden DB fields if it fails & redisplays
    'db_host_hidden' => isset($_SESSION['install_db_details']['host']) ? htmlspecialchars($_SESSION['install_db_details']['host']) : (isset($db_input['host']) ? htmlspecialchars($db_input['host']) : ''),
    'db_user_hidden' => isset($_SESSION['install_db_details']['user']) ? htmlspecialchars($_SESSION['install_db_details']['user']) : (isset($db_input['user']) ? htmlspecialchars($db_input['user']) : ''),
    'db_password_hidden' => '', // Do not persist/re-display password in hidden or any field
    'db_database_hidden' => isset($_SESSION['install_db_details']['database']) ? htmlspecialchars($_SESSION['install_db_details']['database']) : (isset($db_input['database']) ? htmlspecialchars($db_input['database']) : ''),

    'admin_panel_url' => 'index.php', // Default to root, assuming /admin will be routed by main app
];

// --- Determine Main Content Template ---
$main_content_template = 'step1_db_form.twig';
if ($current_step === 1) {
    // Default, already set
} elseif ($current_step === 2) {
    $template_vars['page_title'] = $lang['site_admin_setup_title'];
    // If we are supposed to be on step 2, but lost DB session or form had errors for step 2.
    if (isset($form_errors['general']) && $form_errors['general'] == $lang['db_details_lost_error']) {
        // This error means we can't proceed to step 2, so show step 1 again.
    } elseif (!empty($form_errors)) { // Any other errors on step 2 (validation, db_step2, sql_install)
        $main_content_template = 'step2_site_admin_form.twig';
    } elseif ($db_connected || isset($_SESSION['install_db_details'])) { // Successfully connected in step 1 or session exists
         $main_content_template = 'step2_site_admin_form.twig';
    }
    // If none of the above, it implies we should be on step 1 (e.g. initial load of step 2 without session)

} elseif ($current_step === 3) {
    $main_content_template = 'success_message.twig';
}


// --- Render ---
try {
    if ($twig !== null) { // Ensure twig was initialized
        echo $twig->render('layout.twig', array_merge($template_vars, ['main_content_template' => $main_content_template]));
    } else {
        die("Twig environment not available. Installation cannot proceed.");
    }
} catch (\Throwable $e) {
    die("Error during template rendering: " . $e->getMessage() . "<br>Attempted Template: " . $main_content_template . "<br><pre>" . $e->getTraceAsString() . "</pre>");
}

?>