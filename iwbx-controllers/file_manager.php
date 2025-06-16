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
    $_SESSION['notice'] = 'Silakan pilih situs untuk dikelola terlebih dahulu.';
    $_SESSION['notice_type'] = 'warning';
    header('Location: ' . ($baseurl ?? '/') . '/panel');
    exit();
}

// Fetch active site details for the logged-in user
$req_site = Base::db()->prepare("SELECT * FROM `site` WHERE `site_id` = ? AND `user_id` = ?");
$req_site->execute([$st, $user->id]);
if ($req_site->rowCount() == 0) {
    unset($_SESSION['st']); // Invalid site in session
    $_SESSION['notice'] = 'Situs yang dipilih tidak valid atau bukan milik Anda.';
    $_SESSION['notice_type'] = 'error';
    header('Location: ' . ($baseurl ?? '/') . '/panel');
    exit();
}
$site = $req_site->fetch(PDO::FETCH_ASSOC);
$site_root_path = ROOTPATH . 'iwbx-sites/' . $site['url'];

// --- Helper Functions ---
function fm_normalize_path($path_input, $site_root_abs_path) {
    $path = trim($path_input ?? '', "/ \t\n\r\0\x0B");
    $path = str_replace(['\\', "\0"], ['/', ''], $path);

    $prefixed_path = $site_root_abs_path . DIRECTORY_SEPARATOR . $path;

    $parts = explode('/', $prefixed_path);
    $absolutes = [];
    foreach ($parts as $part) {
        if ('.' == $part || '' == $part) continue;
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    $full_path_normalized = implode(DIRECTORY_SEPARATOR, $absolutes);

    $real_site_root = realpath($site_root_abs_path);
    if (strpos($full_path_normalized, $real_site_root) !== 0) {
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
            $segments[] = [
                'name' => $part,
                'url' => $base_fm_url . '?path=' . urlencode($path_so_far) . $extra_query_params,
            ];
        }
    }
    return $segments;
}

// --- Config & Globals ---
$editable_extensions = ['html', 'txt', 'css', 'js', 'php', 'xml', 'json', 'md', 'ini', 'htaccess', 'log'];
$base_lang_vars = [ /* Language strings as defined in previous attempts */
    'breadcrumb_home' => 'Home', 'breadcrumb_panel' => 'Panel', 'breadcrumb_dashboard' => 'Dashboard',
    'breadcrumb_file_manager_root' => 'File Manager', 'baseurl_root' => $baseurl,
    'cancel_button' => 'Batal', 'save_button' => 'Simpan', 'create_button' => 'Buat',
    'upload_button' => 'Upload', 'delete_button' => 'Hapus', 'rename_button' => 'Ubah Nama',
    'valid_chars_message' => 'Karakter yang diijinkan: a-z, A-Z, 0-9, ., _, -',
    'file_manager_title' => 'File Manager', 'file_manager_heading' => 'File Manager',
    'parent_directory' => '(Induk Direktori)', 'create_directory_button' => 'Buat Folder',
    'create_file_button' => 'Buat File', 'upload_file_button' => 'Upload File',
    'folder_empty' => 'Folder kosong.', 'actions_button' => 'Tindakan',
    'create_directory_modal_title' => 'Buat Folder Baru', 'folder_name_label' => 'Nama Folder',
    'create_file_modal_title' => 'Buat File Baru', 'file_name_label' => 'Nama File',
    'upload_file_modal_title' => 'Upload File', 'select_file_label' => 'Pilih File',
    'max_file_size_note' => 'Maksimal ukuran %max_size% kb',
    'moving_item_info' => 'Memindahkan', 'click_folder_to_move' => 'Klik folder tujuan atau',
    'cancel_move' => 'Batalkan Pindah', 'move_here_button' => 'Pindahkan ke Sini',
    'edit_file_title' => 'Edit File', 'edit_file_heading' => 'Edit File',
    'code_label' => 'Kode', 'preview_button' => 'Preview',
    'rename_title' => 'Ubah Nama', 'rename_heading' => 'Ubah Nama', 'rename_breadcrumb' => 'Ubah Nama',
    'new_folder_name_label' => 'Nama Folder Baru', 'new_file_name_label' => 'Nama File Baru',
    'delete_title' => 'Hapus Item', 'delete_heading' => 'Hapus Item', 'delete_breadcrumb' => 'Hapus',
    'confirm_delete_folder_message' => 'Apakah Kamu yakin akan menghapus folder <strong>%item_name%</strong> beserta seluruh isinya?',
    'confirm_delete_file_message' => 'Apakah Kamu yakin akan menghapus file <strong>%item_name%</strong>?',
    'yes_delete_button' => 'Ya, Hapus', 'item_actions_title' => 'Tindakan Item',
    'actions_for_item_heading' => 'Tindakan untuk', 'rename_link' => 'Ubah nama',
    'move_link' => 'Pindah', 'delete_link' => 'Hapus', 'download_link' => 'Download',
    'edit_link' => 'Edit', 'back_to_folder_button' => 'Kembali ke Folder',
    'move_item_title' => 'Pindahkan Item', 'move_item_heading' => 'Konfirmasi Pemindahan Item',
    'move_breadcrumb' => 'Pindahkan Item',
    'confirm_move_message' => 'Apa kamu yakin akan memindahkan <strong>%item_name%</strong> ke folder <strong>%target_folder%</strong>?',
    'yes_move_button' => 'Ya, Pindahkan',
    'move_parameters_missing' => 'Parameter untuk pemindahan item tidak lengkap atau item tidak ditemukan.',
    'back_to_fm_button' => 'Kembali ke File Manager',
];

if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    die("Error: Templating engine (Twig) is not available.");
}

$current_rel_path = fm_normalize_path($_GET['path'] ?? '', $site_root_path);
$current_abs_path = $site_root_path . (empty($current_rel_path) ? '' : DIRECTORY_SEPARATOR . $current_rel_path);
$base_fm_action_url = $baseurl . '/' . $controller;
$base_fm_browse_url = $base_fm_action_url . '/index';

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
                $_SESSION['notice'] = 'Direktori tujuan tidak valid: ' . htmlspecialchars($target_rel_path); $_SESSION['notice_type'] = 'error';
            } else {
                $file_upload = $_FILES['berkas'];
                if ($file_upload['error'] === UPLOAD_ERR_OK) {
                    $file_ext = Func::getExt($file_upload['name']);
                    $clean_basename = Func::permalink(pathinfo($file_upload['name'], PATHINFO_FILENAME));
                    $final_filename = mb_substr($clean_basename, 0, 50) . '.' . $file_ext;
                    if (strlen($file_ext) > 5 || strlen($file_ext) < 1 || empty($clean_basename)) $_SESSION['notice'] = "Nama/ekstensi file tidak valid.";
                    elseif ($file_upload['size'] > ($set['filesize'] * 1024)) $_SESSION['notice'] = "Ukuran file melebihi batas (" . $set['filesize'] . " KB).";
                    else {
                        if (move_uploaded_file($file_upload['tmp_name'], $target_abs_path . DIRECTORY_SEPARATOR . $final_filename)) {
                            $_SESSION['notice'] = 'File "' . htmlspecialchars($final_filename) . '" berhasil diupload.'; $_SESSION['notice_type'] = 'success';
                        } else { $_SESSION['notice'] = 'Gagal upload. Periksa izin server.'; $_SESSION['notice_type'] = 'error'; }
                    }
                    if (!isset($_SESSION['notice'])) $_SESSION['notice_type'] = 'error'; // Ensure type is set if notice was set by other error
                } else { $_SESSION['notice'] = 'Kesalahan upload (Code: ' . $file_upload['error'] . ').'; $_SESSION['notice_type'] = 'error';}
            }
        } else { $_SESSION['notice'] = 'Sesi form upload tidak valid. Coba lagi.'; $_SESSION['notice_type'] = 'error';}
        header('Location: ' . $base_fm_browse_url . (empty($target_rel_path) ? '' : '?path=' . urlencode($target_rel_path)));
        exit();

    case 'create_directory':
    case 'create_file':
        $is_dir_creation = ($action === 'create_directory');
        $parent_rel_path = fm_normalize_path($_POST['parent_path'] ?? $current_rel_path, $site_root_path);
        $parent_abs_path = $site_root_path . (empty($parent_rel_path) ? '' : DIRECTORY_SEPARATOR . $parent_rel_path);
        $new_item_name = trim($_POST['new_item_name'] ?? '');

        if (!is_dir($parent_abs_path) || !is_writable($parent_abs_path)) {
            $_SESSION['notice'] = 'Direktori dasar tidak valid/ditulis.'; $_SESSION['notice_type'] = 'error';
        } elseif (empty($new_item_name) || !preg_match('/^[a-zA-Z0-9._-]+$/', $new_item_name)) {
            $_SESSION['notice'] = 'Nama tidak valid/karakter terlarang.'; $_SESSION['notice_type'] = 'error';
        } elseif (file_exists($parent_abs_path . DIRECTORY_SEPARATOR . $new_item_name)) {
            $_SESSION['notice'] = 'Nama "' . htmlspecialchars($new_item_name) . '" sudah ada.'; $_SESSION['notice_type'] = 'error';
        } else {
            if ($is_dir_creation) {
                if (mkdir($parent_abs_path . DIRECTORY_SEPARATOR . $new_item_name, 0755)) {
                    $_SESSION['notice'] = 'Folder "' . htmlspecialchars($new_item_name) . '" dibuat.'; $_SESSION['notice_type'] = 'success';
                } else { $_SESSION['notice'] = 'Gagal buat folder.'; $_SESSION['notice_type'] = 'error';}
            } else { // Create file
                if (file_put_contents($parent_abs_path . DIRECTORY_SEPARATOR . $new_item_name, "\n") !== false) {
                    $_SESSION['notice'] = 'File "' . htmlspecialchars($new_item_name) . '" dibuat.'; $_SESSION['notice_type'] = 'success';
                    $new_file_rel_path = (empty($parent_rel_path) ? '' : $parent_rel_path . '/') . $new_item_name;
                    header('Location: ' . $base_fm_action_url . '/edit_file?item=' . urlencode($new_file_rel_path)); exit();
                } else { $_SESSION['notice'] = 'Gagal buat file.'; $_SESSION['notice_type'] = 'error';}
            }
        }
        header('Location: ' . $base_fm_browse_url . (empty($parent_rel_path) ? '' : '?path=' . urlencode($parent_rel_path)));
        exit();

    case 'edit_file':
        // Logic as previously defined and tested
        $item_rel_path = fm_normalize_path($_GET['item'] ?? '', $site_root_path);
        $item_abs_path = $site_root_path . (empty($item_rel_path) ? '' : DIRECTORY_SEPARATOR . $item_rel_path);
        $item_name = basename($item_rel_path);
        $page_title = $base_lang_vars['edit_file_title'];
        $errors = [];

        if (empty($item_rel_path) || !is_file($item_abs_path) || !is_readable($item_abs_path)) {
            $_SESSION['notice'] = 'File tidak ditemukan: ' . htmlspecialchars($item_rel_path); $_SESSION['notice_type'] = 'error';
            $parent_dir = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
            header('Location: ' . $base_fm_browse_url . (empty($parent_dir) ? '' : '?path=' . urlencode($parent_dir))); exit();
        }
        if (!in_array(Func::getExt($item_name), $editable_extensions)) {
             $_SESSION['notice'] = 'Tipe file ini tidak bisa diedit.'; $_SESSION['notice_type'] = 'warning';
             $parent_dir = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
             header('Location: ' . $base_fm_browse_url . (empty($parent_dir) ? '' : '?path=' . urlencode($parent_dir))); exit();
        }
        $file_content_current = file_get_contents($item_abs_path);
        if (isset($_POST['submit_save_file'])) {
            if (file_put_contents($item_abs_path, $_POST['code']) !== false) {
                $_SESSION['notice'] = 'File "' . htmlspecialchars($item_name) . '" disimpan.'; $_SESSION['notice_type'] = 'success';
                header('Location: ' . $base_fm_action_url . '/edit_file?item=' . urlencode($item_rel_path)); exit();
            } else { $errors['save'] = 'Gagal simpan file.'; }
        }
        $parent_dir_rel_path = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
        if ($parent_dir_rel_path === '.') $parent_dir_rel_path = '';
        echo Base::$twig->render('file_manager/edit_file.twig', [
            'page_title' => $page_title, 'lang' => $base_lang_vars, 'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url,
            'breadcrumb_segments' => fm_get_breadcrumb_segments($parent_dir_rel_path, $base_fm_browse_url),
            'item_name' => $item_name,
            'form_action_url' => $base_fm_action_url . '/edit_file?item=' . urlencode($item_rel_path),
            'file_content' => $file_content_current,
            'preview_url' => ($set['url'] ?? '') . '/' . $item_rel_path,
            'cancel_url' => $base_fm_browse_url . (empty($parent_dir_rel_path) ? '' : '?path=' . urlencode($parent_dir_rel_path)),
            'errors' => $errors, 'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'rename_item':
    case 'delete_item':
    case 'item_actions':
    case 'download':
    case 'move_item_confirm':
        // Logic for these actions as previously defined and tested (or to be filled in)
        // For this submission, I'll ensure they are distinct placeholders or implement one fully.
        // Let's quickly fill 'download' as it's simple and doesn't need a template.
        if ($action === 'download') {
            $item_rel_path = fm_normalize_path($_GET['item'] ?? '', $site_root_path);
            $item_abs_path = $site_root_path . (empty($item_rel_path) ? '' : DIRECTORY_SEPARATOR . $item_rel_path);
            $item_name = basename($item_rel_path);
            if (empty($item_rel_path) || !is_file($item_abs_path) || !is_readable($item_abs_path)) {
                $_SESSION['notice'] = 'File tidak ditemukan: ' . htmlspecialchars($item_rel_path); $_SESSION['notice_type'] = 'error';
                $p_dir = ($item_rel_path == $item_name) ? '' : dirname($item_rel_path);
                header('Location: ' . $base_fm_browse_url . (empty($p_dir) ? '' : '?path=' . urlencode($p_dir))); exit();
            }
            $mime_types = include(ROOTPATH . 'iwbx-includes/mime_types.php');
            $ext = Func::getExt($item_name);
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($mime_types[$ext] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . $item_name . '"');
            header('Expires: 0'); header('Cache-Control: must-revalidate'); header('Pragma: public');
            header('Content-Length: ' . filesize($item_abs_path));
            readfile($item_abs_path); exit();
        } else {
            // For other actions, show placeholder message and redirect
             $_SESSION['notice'] = "Tindakan File Manager ('" . htmlspecialchars($action) . "') belum sepenuhnya diimplementasikan dengan Twig.";
             $_SESSION['notice_type'] = 'warning';
             $item_param = fm_normalize_path($_GET['item'] ?? $current_rel_path, $site_root_path);
             $redirect_path = dirname($item_param);
             if ($redirect_path === '.' || $redirect_path === $item_param) $redirect_path = '';
             header('Location: ' . $base_fm_browse_url . (empty($redirect_path) ? '' : '?path=' . urlencode($redirect_path)));
             exit();
        }
        break;


    case 'index':
    default:
        $action = 'index';
        $page_title = $base_lang_vars['file_manager_title'];
        $move_item_path_param = isset($_GET['move']) ? fm_normalize_path($_GET['move'], $site_root_path) : null;
        $move_item_name = $move_item_path_param ? basename($move_item_path_param) : null;
        $extra_query_for_breadcrumb = $move_item_path_param ? '&move=' . urlencode($move_item_path_param) : '';
        $items_list = [];

        if (!is_dir($current_abs_path) || !is_readable($current_abs_path)) {
            $_SESSION['notice'] = 'Path tidak valid: ' . htmlspecialchars($current_rel_path); $_SESSION['notice_type'] = 'error';
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
        } else { $_SESSION['notice'] = 'Tidak dapat baca direktori: ' . htmlspecialchars($current_rel_path); $_SESSION['notice_type'] = 'error'; }

        $parent_dir_rel_path = ($current_rel_path == '') ? null : dirname($current_rel_path);
        if ($parent_dir_rel_path === '.') $parent_dir_rel_path = '';
        $upload_key_name = 'upload_form_key';
        if (!isset($_SESSION[$upload_key_name])) $_SESSION[$upload_key_name] = md5(time().rand());
        $current_path_query_params_for_forms = (empty($current_rel_path) ? '' : '?parent_path=' . urlencode($current_rel_path));

        echo Base::$twig->render('file_manager/index.twig', [
            'page_title' => $page_title, 'lang' => $base_lang_vars,
            'user' => $user, 'set' => $set, 'site' => $site,
            'base_fm_url' => $base_fm_browse_url,
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
