<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

// Ensure user is logged in
if (!$user->id) {
    $redirect_target = ($set['url'] ?? $baseurl ?? '/') . '/index.php/panel';
    header('Location: ' . $redirect_target);
    exit();
}

// Ensure a site is selected (active in session)
$st = isset($_SESSION['st']) ? abs(intval($_SESSION['st'])) : false;
if (!$st) {
    $_SESSION['notice'] = Base::$lang_strings['panel_dashboard_select_site_notice'] ?? 'Please select a site to manage first.';
    $_SESSION['notice_type'] = 'warning';
    header('Location: ' . ($baseurl ?? '/') . '/panel');
    exit();
}

// Fetch active site details for the logged-in user
$req_site = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
$req_site->execute([$st, $user->id]);
if ($req_site->rowCount() == 0) {
    unset($_SESSION['st']); // Invalid site in session
    $_SESSION['notice'] = Base::$lang_strings['panel_switch_site_error_not_found'] ?? 'Selected site is invalid or not yours.';
    $_SESSION['notice_type'] = 'error';
    header('Location: ' . ($baseurl ?? '/') . '/panel');
    exit();
}
$site = $req_site->fetch(PDO::FETCH_ASSOC);
$site_root_path = ROOTPATH . 'iwbx-sites/' . $site['url'];

// --- Helper Functions (remain the same) ---
function fm_normalize_path($path_input, $site_root_abs_path) {
    $path = trim($path_input ?? '', "/ \t\n\r\0\x0B");
    $path = str_replace(['\\', "\0"], ['/', ''], $path);
    $prefixed_path = $site_root_abs_path . DIRECTORY_SEPARATOR . $path;
    $parts = explode('/', $prefixed_path);
    $absolutes = [];
    foreach ($parts as $part) {
        if ('.' == $part || '' == $part) continue;
        if ('..' == $part) { array_pop($absolutes); }
        else { $absolutes[] = $part; }
    }
    $full_path_normalized = implode(DIRECTORY_SEPARATOR, $absolutes);
    $real_site_root = realpath($site_root_abs_path);
    if ($real_site_root === false || strpos($full_path_normalized, $real_site_root) !== 0) {
        return '';
    }
    $relative_path = substr($full_path_normalized, strlen($real_site_root));
    return trim(str_replace(DIRECTORY_SEPARATOR, '/', $relative_path), '/');
}

function fm_get_breadcrumb_segments($current_path_str, $base_fm_url, $extra_query_params = '') {
    $segments = [];
    if (!empty($current_path_str)) {
        $parts = explode('/', $current_path_str);
        $path_so_far = '';
        foreach ($parts as $part) {
            if (empty($part)) continue;
            $path_so_far .= (empty($path_so_far) ? '' : '/') . $part;
            $segments[] = [ 'name' => $part, 'url' => $base_fm_url . '?path=' . urlencode($path_so_far) . $extra_query_params];
        }
    }
    return $segments;
}

// --- Config & Globals ---
$editable_extensions = ['html', 'txt', 'css', 'js', 'php', 'xml', 'json', 'md', 'ini', 'htaccess', 'log'];
// Local $base_lang_vars removed. All lang strings are now expected to be in Base::$lang_strings (global 'lang' in Twig)

if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    die("Error: Templating engine (Twig) is not available.");
}

$current_rel_path = fm_normalize_path($_GET['path'] ?? $_POST['parent_path'] ?? '', $site_root_path); // Use parent_path from POST for actions
$current_abs_path = $site_root_path . (empty($current_rel_path) ? '' : DIRECTORY_SEPARATOR . $current_rel_path);
$base_fm_action_url = $baseurl . '/' . $controller;
$base_fm_browse_url = $base_fm_action_url . '/index';
$baseurl_root_for_twig = $baseurl;

// --- Main Switch ---
switch ($action) {
    case 'upload_file':
        $target_rel_path = fm_normalize_path($_POST['parent_path'] ?? $current_rel_path, $site_root_path);
        $target_abs_path = $site_root_path . (empty($target_rel_path) ? '' : DIRECTORY_SEPARATOR . $target_rel_path);
        $upload_key_name = 'upload_form_key';
        $upload_key_value_session = $_SESSION[$upload_key_name] ?? '';
        $upload_key_value_form = $_POST[$upload_key_name] ?? '';
        $_SESSION[$upload_key_name] = md5(time().rand());

        if ($upload_key_value_form == $upload_key_value_session && !empty($upload_key_value_session) && isset($_FILES['berkas'])) {
            if (!is_dir($target_abs_path) || !is_writable($target_abs_path)) {
                $_SESSION['notice'] = str_replace('%path%', htmlspecialchars($target_rel_path), Base::$lang_strings['file_manager_invalid_target_dir_write'] ?? 'Target directory is invalid or not writable: %path%');
                $_SESSION['notice_type'] = 'error';
            } else {
                $file_upload = $_FILES['berkas'];
                if ($file_upload['error'] === UPLOAD_ERR_OK) {
                    $file_ext = Func::getExt($file_upload['name']);
                    $clean_basename = Func::permalink(pathinfo($file_upload['name'], PATHINFO_FILENAME));
                    $final_filename = mb_substr($clean_basename, 0, 50) . '.' . $file_ext;
                    if (strlen($file_ext) > 5 || strlen($file_ext) < 1 || empty($clean_basename)) {
                        $_SESSION['notice'] = Base::$lang_strings['file_manager_upload_ext_invalid'] ?? 'Invalid file extension.';
                    } elseif ($file_upload['size'] > ($set['filesize'] * 1024)) {
                        $_SESSION['notice'] = str_replace('%limit%', $set['filesize'], Base::$lang_strings['file_manager_upload_size_exceeded'] ?? 'File size exceeds limit (%limit% KB).');
                    } else {
                        if (move_uploaded_file($file_upload['tmp_name'], $target_abs_path . DIRECTORY_SEPARATOR . $final_filename)) {
                            $_SESSION['notice'] = str_replace(['%filename%', '%path%'], [htmlspecialchars($final_filename), htmlspecialchars($target_rel_path)], Base::$lang_strings['file_manager_upload_success'] ?? 'File "%filename%" uploaded successfully to %path%.');
                            $_SESSION['notice_type'] = 'success';
                        } else {
                            $_SESSION['notice'] = Base::$lang_strings['file_manager_upload_failed'] ?? 'Failed to upload file. Check server permissions.';
                            $_SESSION['notice_type'] = 'error';
                        }
                    }
                    if (!isset($_SESSION['notice'])) $_SESSION['notice_type'] = 'error';
                } else {
                    $_SESSION['notice'] = str_replace('%error_code%', $file_upload['error'], Base::$lang_strings['file_manager_upload_error'] ?? 'File upload error (Code: %error_code%).');
                    $_SESSION['notice_type'] = 'error';
                }
            }
        } else {
            $_SESSION['notice'] = Base::$lang_strings['file_manager_upload_invalid_key'] ?? 'Invalid upload session. Please try again.';
            if (!isset($_FILES['berkas'])) $_SESSION['notice'] = Base::$lang_strings['file_manager_upload_no_file'] ?? 'No file was uploaded.';
            $_SESSION['notice_type'] = 'error';
        }
        header('Location: ' . $base_fm_browse_url . (empty($target_rel_path) ? '' : '?path=' . urlencode($target_rel_path)));
        exit();

    case 'create_directory':
    case 'create_file':
        $is_dir_creation = ($action === 'create_directory');
        $parent_rel_path = fm_normalize_path($_POST['parent_path'] ?? $current_rel_path, $site_root_path);
        $parent_abs_path = $site_root_path . (empty($parent_rel_path) ? '' : DIRECTORY_SEPARATOR . $parent_rel_path);
        $new_item_name = trim($_POST['new_item_name'] ?? '');

        if (!is_dir($parent_abs_path) || !is_writable($parent_abs_path)) {
            $_SESSION['notice'] = Base::$lang_strings['file_manager_create_dir_invalid_base'] ?? 'Base directory is invalid or not writable.'; $_SESSION['notice_type'] = 'error';
        } elseif (empty($new_item_name) || !preg_match('/^[a-zA-Z0-9._-]+$/', $new_item_name)) {
            $_SESSION['notice'] = Base::$lang_strings['file_manager_create_item_invalid_name'] ?? 'Item name is invalid or contains forbidden characters.'; $_SESSION['notice_type'] = 'error';
        } elseif (file_exists($parent_abs_path . DIRECTORY_SEPARATOR . $new_item_name)) {
            $_SESSION['notice'] = str_replace('%item_name%', htmlspecialchars($new_item_name), Base::$lang_strings['file_manager_create_item_exists'] ?? 'An item named "%item_name%" already exists.'); $_SESSION['notice_type'] = 'error';
        } else {
            if ($is_dir_creation) {
                if (mkdir($parent_abs_path . DIRECTORY_SEPARATOR . $new_item_name, 0755)) {
                    $_SESSION['notice'] = str_replace('%dirname%', htmlspecialchars($new_item_name), Base::$lang_strings['file_manager_create_dir_success'] ?? 'Folder "%dirname%" created successfully.'); $_SESSION['notice_type'] = 'success';
                } else { $_SESSION['notice'] = Base::$lang_strings['file_manager_create_dir_failed'] ?? 'Failed to create folder. Check server permissions.'; $_SESSION['notice_type'] = 'error';}
            } else {
                if (file_put_contents($parent_abs_path . DIRECTORY_SEPARATOR . $new_item_name, "\n") !== false) {
                    $_SESSION['notice'] = str_replace('%filename%', htmlspecialchars($new_item_name), Base::$lang_strings['file_manager_create_file_success'] ?? 'File "%filename%" created successfully.'); $_SESSION['notice_type'] = 'success';
                    $new_file_rel_path = (empty($parent_rel_path) ? '' : $parent_rel_path . '/') . $new_item_name;
                    header('Location: ' . $base_fm_action_url . '/edit_file?item=' . urlencode($new_file_rel_path)); exit();
                } else { $_SESSION['notice'] = Base::$lang_strings['file_manager_create_file_failed'] ?? 'Failed to create file. Check server permissions.'; $_SESSION['notice_type'] = 'error';}
            }
        }
        header('Location: ' . $base_fm_browse_url . (empty($parent_rel_path) ? '' : '?path=' . urlencode($parent_rel_path)));
        exit();

    case 'edit_file':
        $item_rel_path = fm_normalize_path($_GET['item'] ?? '', $site_root_path);
        $item_abs_path = $site_root_path . (empty($item_rel_path) ? '' : DIRECTORY_SEPARATOR . $item_rel_path);
        $item_name = basename($item_rel_path);
        $page_title = Base::$lang_strings['edit_file_title'] ?? 'Edit File';
        $errors = [];

        if (empty($item_rel_path) || !is_file($item_abs_path) || !is_readable($item_abs_path)) {
            $_SESSION['notice'] = str_replace('%item%', htmlspecialchars($item_rel_path), Base::$lang_strings['file_manager_item_not_readable'] ?? 'Item not readable: %item%');
            $_SESSION['notice_type'] = 'error';
            $parent_dir = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
            header('Location: ' . $base_fm_browse_url . (empty($parent_dir) ? '' : '?path=' . urlencode($parent_dir))); exit();
        }
        if (!in_array(Func::getExt($item_name), $editable_extensions)) {
             $_SESSION['notice'] = str_replace('%filename%', htmlspecialchars($item_name), Base::$lang_strings['file_manager_item_not_editable'] ?? 'This file type (%filename%) cannot be edited.');
             $_SESSION['notice_type'] = 'warning';
             $parent_dir = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
             header('Location: ' . $base_fm_browse_url . (empty($parent_dir) ? '' : '?path=' . urlencode($parent_dir))); exit();
        }
        $file_content_current = file_get_contents($item_abs_path);
        if (isset($_POST['submit_save_file'])) {
            if (file_put_contents($item_abs_path, $_POST['code']) !== false) {
                $_SESSION['notice'] = str_replace('%filename%', htmlspecialchars($item_name), Base::$lang_strings['file_manager_edit_save_success'] ?? 'File "%filename%" saved successfully.');
                $_SESSION['notice_type'] = 'success';
                header('Location: ' . $base_fm_action_url . '/edit_file?item=' . urlencode($item_rel_path)); exit();
            } else { $errors['save'] = Base::$lang_strings['file_manager_edit_save_failed'] ?? 'Failed to save file. Check file permissions.'; }
        }
        $parent_dir_rel_path = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
        if ($parent_dir_rel_path === '.') $parent_dir_rel_path = '';
        echo Base::$twig->render('file_manager/edit_file.twig', [
            'page_title' => $page_title, 'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url, 'baseurl_root' => $baseurl_root_for_twig,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($parent_dir_rel_path, $base_fm_browse_url),
            'item_name' => $item_name,
            'form_action_url' => $base_fm_action_url . '/edit_file?item=' . urlencode($item_rel_path),
            'file_content' => $file_content_current,
            'preview_url' => ($set['url'] ?? '') . '/' . $item_rel_path,
            'cancel_url' => $base_fm_browse_url . (empty($parent_dir_rel_path) ? '' : '?path=' . urlencode($parent_dir_rel_path)),
            'errors' => $errors, 'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'item_actions':
        $item_rel_path_actions = fm_normalize_path($_GET['item'] ?? '', $site_root_path);
        $item_abs_path_actions = $site_root_path . (empty($item_rel_path_actions) ? '' : DIRECTORY_SEPARATOR . $item_rel_path_actions);
        $item_name_actions = basename($item_rel_path_actions);
        $page_title = (Base::$lang_strings['item_actions_title'] ?? 'Item Actions') . ': ' . $item_name_actions;

        if (empty($item_rel_path_actions) || !file_exists($item_abs_path_actions)) {
            $_SESSION['notice'] = str_replace('%item%', htmlspecialchars($item_rel_path_actions), Base::$lang_strings['file_manager_item_not_found'] ?? 'Item not found: %item%');
            $_SESSION['notice_type'] = 'error';
            header('Location: ' . $base_fm_browse_url); exit();
        }
        $is_dir_actions = is_dir($item_abs_path_actions);
        $item_ext_actions = $is_dir_actions ? null : Func::getExt($item_name_actions);
        $parent_dir_rel_path_actions = ($item_rel_path_actions == $item_name_actions) ? '' : dirname($item_rel_path_actions);
        if ($parent_dir_rel_path_actions === '.') $parent_dir_rel_path_actions = '';

        echo Base::$twig->render('file_manager/item_actions.twig', [
            'page_title' => $page_title, 'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url, 'baseurl_root' => $baseurl_root_for_twig,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($parent_dir_rel_path_actions, $base_fm_browse_url),
            'item' => ['name' => $item_name_actions, 'type' => $is_dir_actions ? 'dir' : 'file', 'is_editable' => !$is_dir_actions && in_array($item_ext_actions, $editable_extensions)],
            'item_type_translated' => $is_dir_actions ? (Base::$lang_strings['global_folder'] ?? 'Folder') : (Base::$lang_strings['global_file'] ?? 'File'),
            'rename_url' => $base_fm_action_url . '/rename_item?item=' . urlencode($item_rel_path_actions),
            'move_url' => $base_fm_browse_url . '?move=' . urlencode($item_rel_path_actions),
            'delete_url' => $base_fm_action_url . '/delete_item?item=' . urlencode($item_rel_path_actions),
            'download_url' => $base_fm_action_url . '/download?item=' . urlencode($item_rel_path_actions),
            'edit_url' => $base_fm_action_url . '/edit_file?item=' . urlencode($item_rel_path_actions),
            'parent_dir_url' => $base_fm_browse_url . (empty($parent_dir_rel_path_actions) ? '' : '?path=' . urlencode($parent_dir_rel_path_actions)),
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'rename_item':
        $item_rel_path_rn = fm_normalize_path($_GET['item'] ?? '', $site_root_path);
        $item_abs_path_rn = $site_root_path . (empty($item_rel_path_rn) ? '' : DIRECTORY_SEPARATOR . $item_rel_path_rn);
        $item_name_old_rn = basename($item_rel_path_rn);
        $page_title = (Base::$lang_strings['rename_title'] ?? 'Rename Item');
        $error_message_rn = null;

        if (empty($item_rel_path_rn) || !file_exists($item_abs_path_rn)) {
            $_SESSION['notice'] = str_replace('%item%', htmlspecialchars($item_rel_path_rn), Base::$lang_strings['file_manager_item_not_found'] ?? 'Item not found: %item%');
            $_SESSION['notice_type'] = 'error';
            header('Location: ' . $base_fm_browse_url); exit();
        }
        $parent_dir_rel_path_rn = ($item_rel_path_rn == $item_name_old_rn) ? '' : dirname($item_rel_path_rn);
        if ($parent_dir_rel_path_rn === '.') $parent_dir_rel_path_rn = '';
        $item_type_rn = is_dir($item_abs_path_rn) ? 'dir' : 'file';

        if (isset($_POST['submit_rename'])) {
            $new_item_name_rn = trim($_POST['new_item_name'] ?? '');
            if (empty($new_item_name_rn) || !preg_match('/^[a-zA-Z0-9._-]+$/', $new_item_name_rn)) {
                $error_message_rn = Base::$lang_strings['file_manager_rename_name_invalid'] ?? 'New name is invalid or contains forbidden characters.';
            } elseif ($new_item_name_rn === $item_name_old_rn) {
                $error_message_rn = Base::$lang_strings['file_manager_rename_name_same'] ?? 'New name is the same as the old name.';
            } else {
                $new_item_abs_path_rn = $site_root_path . (empty($parent_dir_rel_path_rn) ? '' : DIRECTORY_SEPARATOR . $parent_dir_rel_path_rn) . DIRECTORY_SEPARATOR . $new_item_name_rn;
                if (file_exists($new_item_abs_path_rn)) {
                    $error_message_rn = str_replace('%new_name%', htmlspecialchars($new_item_name_rn), Base::$lang_strings['file_manager_rename_name_exists'] ?? 'Name "%new_name%" already exists at this location.');
                } else {
                    if (rename($item_abs_path_rn, $new_item_abs_path_rn)) {
                        $_SESSION['notice'] = str_replace(['%old_name%', '%new_name%'], [htmlspecialchars($item_name_old_rn), htmlspecialchars($new_item_name_rn)], Base::$lang_strings['file_manager_rename_success'] ?? 'Item "%old_name%" renamed to "%new_name%" successfully.');
                        $_SESSION['notice_type'] = 'success';
                        header('Location: ' . $base_fm_browse_url . (empty($parent_dir_rel_path_rn) ? '' : '?path=' . urlencode($parent_dir_rel_path_rn))); exit();
                    } else { $error_message_rn = Base::$lang_strings['file_manager_rename_failed'] ?? 'Failed to rename item. Check permissions.'; }
                }
            }
        }
        echo Base::$twig->render('file_manager/rename_item.twig', [
            'page_title' => $page_title, 'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url, 'baseurl_root' => $baseurl_root_for_twig,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($parent_dir_rel_path_rn, $base_fm_browse_url),
            'item_name' => $item_name_old_rn,
            'current_name_value' => $_POST['new_item_name'] ?? $item_name_old_rn,
            'item_type' => $item_type_rn,
            'form_action_url' => $base_fm_action_url . '/rename_item?item=' . urlencode($item_rel_path_rn),
            'cancel_url' => $base_fm_action_url . '/item_actions?item=' . urlencode($item_rel_path_rn),
            'error_message' => $error_message_rn, 'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'delete_item':
        $item_rel_path_del = fm_normalize_path($_GET['item'] ?? '', $site_root_path);
        $item_abs_path_del = $site_root_path . (empty($item_rel_path_del) ? '' : DIRECTORY_SEPARATOR . $item_rel_path_del);
        $item_name_del = basename($item_rel_path_del);
        $page_title = Base::$lang_strings['delete_title'] ?? 'Delete Item';
        $parent_dir_rel_path_del = ($item_rel_path_del == $item_name_del) ? '' : dirname($item_rel_path_del);
        if ($parent_dir_rel_path_del === '.') $parent_dir_rel_path_del = '';

        if (empty($item_rel_path_del) || !file_exists($item_abs_path_del)) {
            $_SESSION['notice'] = str_replace('%item%', htmlspecialchars($item_rel_path_del), Base::$lang_strings['file_manager_item_not_found'] ?? 'Item not found: %item%');
            $_SESSION['notice_type'] = 'error';
            header('Location: ' . $base_fm_browse_url . (empty($parent_dir_rel_path_del) ? '' : '?path=' . urlencode($parent_dir_rel_path_del))); exit();
        }
        $item_type_del = is_dir($item_abs_path_del) ? 'dir' : 'file';

        if (isset($_POST['submit_delete'])) {
            $deleted = ($item_type_del == 'dir') ? Func::deleteDir($item_abs_path_del) : unlink($item_abs_path_del);
            if ($deleted) {
                $_SESSION['notice'] = str_replace(['%item_type%', '%item_name%'], [ucfirst($item_type_del), htmlspecialchars($item_name_del)], Base::$lang_strings['file_manager_delete_success'] ?? '%item_type% "%item_name%" deleted successfully.');
                $_SESSION['notice_type'] = 'success';
            } else {
                $_SESSION['notice'] = str_replace(['%item_type%', '%item_name%'], [$item_type_del, htmlspecialchars($item_name_del)], Base::$lang_strings['file_manager_delete_failed'] ?? 'Failed to delete %item_type% "%item_name%". Check permissions.');
                $_SESSION['notice_type'] = 'error';
            }
            header('Location: ' . $base_fm_browse_url . (empty($parent_dir_rel_path_del) ? '' : '?path=' . urlencode($parent_dir_rel_path_del))); exit();
        }
        echo Base::$twig->render('file_manager/delete_item.twig', [
            'page_title' => $page_title, 'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url, 'baseurl_root' => $baseurl_root_for_twig,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($parent_dir_rel_path_del, $base_fm_browse_url),
            'item_name' => $item_name_del, 'item_type' => $item_type_del,
            'form_action_url' => $base_fm_action_url . '/delete_item?item=' . urlencode($item_rel_path_del),
            'cancel_url' => $base_fm_action_url . '/item_actions?item=' . urlencode($item_rel_path_del),
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'move_item_confirm':
        $page_title = Base::$lang_strings['move_item_title'] ?? 'Move Item';
        $error_message_mv = null;
        $item_to_move_rel_path_mv = fm_normalize_path($_GET['item_to_move'] ?? '', $site_root_path);
        $target_dir_rel_path_mv = fm_normalize_path($_GET['target_dir'] ?? '', $site_root_path);
        $item_to_move_abs_path_mv = $site_root_path . (empty($item_to_move_rel_path_mv) ? '' : DIRECTORY_SEPARATOR . $item_to_move_rel_path_mv);
        $target_dir_abs_path_mv = $site_root_path . (empty($target_dir_rel_path_mv) ? '' : DIRECTORY_SEPARATOR . $target_dir_rel_path_mv);
        $item_to_move_name_mv = basename($item_to_move_rel_path_mv);
        $parent_of_item_to_move_mv = dirname($item_to_move_rel_path_mv);
        if ($parent_of_item_to_move_mv === '.') $parent_of_item_to_move_mv = '';

        if (empty($item_to_move_rel_path_mv) || !file_exists($item_to_move_abs_path_mv) || !is_dir($target_dir_abs_path_mv)) {
            $_SESSION['notice'] = Base::$lang_strings['file_manager_move_invalid_source_or_target'] ?? 'Source item or target directory is invalid.';
            $_SESSION['notice_type'] = 'error';
            header('Location: ' . $base_fm_browse_url . (empty($parent_of_item_to_move_mv) ? '' : '?path=' . urlencode($parent_of_item_to_move_mv))); exit();
        }
        if (is_dir($item_to_move_abs_path_mv) && strpos(realpath($target_dir_abs_path_mv), realpath($item_to_move_abs_path_mv)) === 0) {
             $_SESSION['notice'] = Base::$lang_strings['file_manager_move_cannot_into_self'] ?? 'Cannot move a folder into itself or its subfolders.';
             $_SESSION['notice_type'] = 'error';
             header('Location: ' . $base_fm_browse_url . '?path=' . urlencode($parent_of_item_to_move_mv ?: '')); exit();
        }

        if (isset($_POST['submit_move_confirm'])) {
            $posted_item_to_move_mv = fm_normalize_path($_POST['item_to_move'] ?? '', $site_root_path);
            $posted_target_dir_mv = fm_normalize_path($_POST['target_dir'] ?? '', $site_root_path);
            if ($posted_item_to_move_mv !== $item_to_move_rel_path_mv || $posted_target_dir_mv !== $target_dir_rel_path_mv) {
                $_SESSION['notice'] = Base::$lang_strings['file_manager_move_validation_error'] ?? 'Move parameter validation error.';
                $_SESSION['notice_type'] = 'error';
                header('Location: ' . $base_fm_browse_url . (empty($parent_of_item_to_move_mv) ? '' : '?path=' . urlencode($parent_of_item_to_move_mv))); exit();
            }
            $new_item_abs_path_mv = $target_dir_abs_path_mv . DIRECTORY_SEPARATOR . $item_to_move_name_mv;
            if (file_exists($new_item_abs_path_mv)) {
                $error_message_mv = str_replace('%item_name%', htmlspecialchars($item_to_move_name_mv), Base::$lang_strings['file_manager_move_name_exists'] ?? 'Item named "%item_name%" already exists in the target directory.');
            } else {
                if (rename($item_to_move_abs_path_mv, $new_item_abs_path_mv)) {
                    $target_display = empty($target_dir_rel_path_mv) ? '/' : '/' .$target_dir_rel_path_mv;
                    $_SESSION['notice'] = str_replace(['%item_name%', '%target_dir%'], [htmlspecialchars($item_to_move_name_mv), htmlspecialchars($target_display)], Base::$lang_strings['file_manager_move_success'] ?? 'Item "%item_name%" successfully moved to "%target_dir%".');
                    $_SESSION['notice_type'] = 'success';
                    header('Location: ' . $base_fm_browse_url . '?path=' . urlencode($target_dir_rel_path_mv)); exit();
                } else { $error_message_mv = Base::$lang_strings['file_manager_move_failed'] ?? 'Failed to move item. Check server permissions.'; }
            }
        }
        echo Base::$twig->render('file_manager/move_item_confirm.twig', [
            'page_title' => $page_title, 'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url, 'baseurl_root' => $baseurl_root_for_twig,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($target_dir_rel_path_mv, $base_fm_browse_url),
            'item_to_move_name' => $item_to_move_name_mv, 'item_to_move_path' => $item_to_move_rel_path_mv,
            'target_dir_path' => $target_dir_rel_path_mv,
            'target_dir_display' => empty($target_dir_rel_path_mv) ? '/' : '/' . $target_dir_rel_path_mv,
            'form_action_url' => $base_fm_action_url . '/move_item_confirm?item_to_move=' . urlencode($item_to_move_rel_path_mv) . '&target_dir=' . urlencode($target_dir_rel_path_mv),
            'cancel_url' => $base_fm_browse_url . '?path=' . urlencode($parent_of_item_to_move_mv ?: ''),
            'error_message' => $error_message_mv, 'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'index':
    default:
        $action = 'index';
        $page_title = Base::$lang_strings['file_manager_title'] ?? 'File Manager';
        $move_item_path_param = isset($_GET['move']) ? fm_normalize_path($_GET['move'], $site_root_path) : null;
        $move_item_name = $move_item_path_param ? basename($move_item_path_param) : null;
        $extra_query_for_breadcrumb = $move_item_path_param ? '&move=' . urlencode($move_item_path_param) : '';
        $items_list = [];

        if (!is_dir($current_abs_path) || !is_readable($current_abs_path)) {
            $_SESSION['notice'] = str_replace('%path%', htmlspecialchars($current_rel_path), Base::$lang_strings['file_manager_invalid_target_dir_write'] ?? 'Path is invalid or not readable: %path%');
            $_SESSION['notice_type'] = 'error';
            if ($current_rel_path !== '') { header('Location: ' . $base_fm_browse_url); exit(); }
        }

        $raw_items = Func::readDir($current_abs_path);
        if ($raw_items !== false) {
            sort($raw_items);
            foreach ($raw_items as $item_name_raw) {
                $item_full_abs_path = $current_abs_path . DIRECTORY_SEPARATOR . $item_name_raw;
                $item_full_rel_path = (empty($current_rel_path) ? '' : $current_rel_path . '/') . $item_name_raw;
                $is_dir = is_dir($item_full_abs_path);
                $item_data = [
                    'name' => $item_name_raw, 'is_dir' => $is_dir, 'full_path' => $item_full_rel_path,
                    'browse_url' => $base_fm_browse_url . '?path=' . urlencode($item_full_rel_path) . $extra_query_for_breadcrumb,
                    'actions_url'=> $base_fm_action_url . '/item_actions?item=' . urlencode($item_full_rel_path),
                    'size_formatted' => $is_dir ? '' : round(@filesize($item_full_abs_path) / 1024, 2) . ' KB',
                    'icon' => $is_dir ? 'fa-folder' : 'fa-file-o',
                ];
                 if ($is_dir && $move_item_path_param && $move_item_path_param != $item_full_rel_path && strpos(realpath($site_root_path . DIRECTORY_SEPARATOR . $item_full_rel_path), realpath($site_root_path . DIRECTORY_SEPARATOR . $move_item_path_param)) !== 0) {
                    $item_data['move_here_url'] = $base_fm_action_url . '/move_item_confirm?target_dir=' . urlencode($item_full_rel_path) . '&item_to_move=' . urlencode($move_item_path_param);
                }
                $items_list[] = $item_data;
            }
        } else {
            $_SESSION['notice'] = str_replace('%path%', htmlspecialchars($current_rel_path), Base::$lang_strings['file_manager_cannot_read_dir'] ?? 'Cannot read directory content: %path%');
            $_SESSION['notice_type'] = 'error';
        }

        $parent_dir_rel_path = ($current_rel_path == '') ? null : dirname($current_rel_path);
        if ($parent_dir_rel_path === '.') $parent_dir_rel_path = '';
        $upload_key_name = 'upload_form_key';
        if (!isset($_SESSION[$upload_key_name])) $_SESSION[$upload_key_name] = md5(time().rand());
        $current_path_query_params_for_forms = (empty($current_rel_path) ? '' : '?parent_path=' . urlencode($current_rel_path)); // Changed to parent_path for clarity in POST

        echo Base::$twig->render('file_manager/index.twig', [
            'page_title' => $page_title,
            'user' => $user, 'set' => $set, 'site' => $site, // 'lang' is global
            'base_fm_url' => $base_fm_browse_url, 'baseurl_root' => $baseurl_root_for_twig,
            'current_path_display' => empty($current_rel_path) ? '/' : '/' . $current_rel_path,
            'current_path' => $current_rel_path,
            'current_path_query_params' => (empty($current_rel_path) ? '' : '?path=' . urlencode($current_rel_path)) . $extra_query_for_breadcrumb,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($current_rel_path, $base_fm_browse_url, $extra_query_for_breadcrumb),
            'parent_dir_url' => ($parent_dir_rel_path !== null) ? ($base_fm_browse_url . ($parent_dir_rel_path === '' ? '' : '?path=' . urlencode($parent_dir_rel_path)) . $extra_query_for_breadcrumb) : null,
            'items' => $items_list, 'pagination_html' => '',
            'create_dir_action_url' => $base_fm_action_url . '/create_directory' . $current_path_query_params_for_forms,
            'create_file_action_url' => $base_fm_action_url . '/create_file' . $current_path_query_params_for_forms,
            'upload_action_url' => $base_fm_action_url . '/upload_file' . $current_path_query_params_for_forms,
            'upload_key_name' => $upload_key_name, 'upload_key_value' => $_SESSION[$upload_key_name],
            'max_upload_size_kb' => $set['filesize'] ?? 1024,
            'session_notice' => Func::getNotice(),
            'move_item_path' => $move_item_path_param, 'move_item_name' => $move_item_name,
        ]);
        break;
}
?>
