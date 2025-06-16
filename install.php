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


// --- Language Strings (English) ---
$lang = [
    'install_title' => 'IndoWapBuilder Installation',
    'db_setup_title' => 'Database Setup',
    'site_admin_setup_title' => 'Site & Admin Setup',
    'success_title' => 'Installation Successful',
    'installation_successful_heading' => 'Installation Successful!', // For success template
    'proceed_to_admin_panel_html' => 'Please <a href="%admin_url%" class="alert-link">click here</a> to go to the Admin Panel.', // For success template
    'mysql_host_label' => 'MySQL Host',
    'mysql_user_label' => 'MySQL User',
    'mysql_password_label' => 'MySQL Password',
    'mysql_database_label' => 'MySQL Database',
    'continue_button' => 'Continue',
    'install_button' => 'Install Now',
    'site_url_label' => 'Site URL',
    'site_url_help' => 'Your site\'s main URL, without a trailing slash.',
    'admin_name_label' => 'Admin Name',
    'admin_name_help' => 'If more than one, separate with a comma.',
    'admin_email_label' => 'Admin Email',
    'admin_password_label' => 'Admin Password',
    'error_db_connect' => 'Could not connect to the database.',
    'error_db_connect_details' => 'Details:',
    'back_button' => 'Back', // For step2_site_admin_form.twig to go back to DB setup
    'installation_successful' => 'Installation completed successfully.',
    'admin_panel_link_text' => 'Admin Panel',
    'delete_install_warning' => 'For security reasons, please delete the <strong>install.php</strong> file now.',
    'form_action_url' => 'install.php', // Self-posting form
    'db_details_lost_error' => 'Database details not found or session expired. Please start over.',
    'db_reconnect_error' => 'Failed to reconnect to the database with the saved details. Please check the database details again.',
    'site_url_error' => 'Site URL is required and must be a valid URL.',
    'admin_name_error' => 'Admin Name is required.',
    'admin_email_error' => 'Admin Email is not valid.',
    'admin_password_error' => 'Admin Password is required (minimum 4 characters).',
    'db_config_write_error' => 'Failed to write db.ini configuration file. Check file permissions.',
    'sql_install_error' => 'Failed to execute installation SQL: ',
    'db_host_required' => 'MySQL Host is required.',
    'db_user_required' => 'MySQL User is required.',
    'db_database_required' => 'MySQL Database name is required.',
    'fill_all_required_fields' => 'Please fill in all required fields.',
    'site_details_legend' => 'Site Details', // For step2_site_admin_form.twig
    'admin_details_legend' => 'Admin Account Details', // For step2_site_admin_form.twig
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
// Default page_title, will be overridden based on step
$page_title_for_template = $lang['install_title'];

$template_vars = [
    'lang' => $lang,
    // page_title will be set specifically per step
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

// --- Determine Main Content Template & Specific Page Title ---
if ($current_step === 1) {
    $main_content_template = 'step1_db_form.twig';
    $page_title_for_template = $lang['db_setup_title'];
} elseif ($current_step === 2) {
    // Check if we should even be on step 2 (i.e., DB details are in session)
    if (!isset($_SESSION['install_db_details']) && !isset($_POST['submit_step1'])) { // Added !isset($_POST['submit_step1']) to allow step1 processing to set step=2
        $form_errors['general'] = $lang['db_details_lost_error'];
        $current_step = 1; // Force back to step 1
        $main_content_template = 'step1_db_form.twig';
        $page_title_for_template = $lang['db_setup_title'];
    } else {
        $main_content_template = 'step2_site_admin_form.twig';
        $page_title_for_template = $lang['site_admin_setup_title'];
    }
} elseif ($current_step === 3) {
    $main_content_template = 'success_message.twig';
    $page_title_for_template = $lang['success_title'];
} else { // Should not happen, default to step 1
    $main_content_template = 'step1_db_form.twig';
    $page_title_for_template = $lang['db_setup_title'];
    $current_step = 1; // Ensure current_step var is accurate
}

// Update current_step in template_vars if it was changed by logic above
$template_vars['current_step'] = $current_step;
$template_vars['page_title'] = $page_title_for_template;


// --- Render ---
try {
    if ($twig !== null) { // Ensure twig was initialized
        // Render the specific step template, which extends layout.twig
        echo $twig->render($main_content_template, $template_vars);
    } else {
        die("Twig environment not available. Installation cannot proceed.");
    }
} catch (\Throwable $e) {
    die("Error during template rendering: " . $e->getMessage() . "<br>Attempted Template: " . $main_content_template . "<br><pre>" . $e->getTraceAsString() . "</pre>");
}

?>