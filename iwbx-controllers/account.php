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
    $redirect_target = ($set['url'] ?? $baseurl ?? '/') . '/index.php/account';
    // $user->redirect uses parent::$set which might not be ideal if $set isn't fully loaded
    // For now, simple header redirect.
    header('Location: ' . $redirect_target);
    exit();
}

// Base language strings for all account actions
$base_lang_vars = [
    'breadcrumb_home' => 'Home',
    'breadcrumb_account' => 'Akun',
    // session_notice is fetched within each action before rendering
    'baseurl_root' => $baseurl, // For layout links like navbar, breadcrumbs
];

// Ensure Base::$twig is available
if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    // Fallback or error if Twig is not available
    die("Error: Templating engine (Twig) is not available. Cannot render account page.");
}

switch ($action) {
    case 'edit_profile': // Renamed from 'edit' for clarity and consistency
        $page_title = 'Edit Profil';
        $errors = [];
        // Initialize form_values with current user data or POST data if available
        $form_values = [
            'author' => $_POST['author'] ?? $user->data['name'],
            'email' => $_POST['email'] ?? $user->data['email'],
            'gender' => $_POST['gender'] ?? $user->data['gender'],
        ];

        if (isset($_POST['submit'])) {
            $form_values['author'] = trim($form_values['author']);
            $form_values['email'] = trim($form_values['email']);
            // Gender is from select, no trim needed.

            // Validation logic (copied from original, can be improved)
            if (mb_strlen($form_values['author']) < 3 || mb_strlen($form_values['author']) > 32)
                $errors['author'] = 'Panjang nama min. 3 s/d 32 karakter.';
            elseif (str_word_count($form_values['author']) > 3)
                $errors['author'] = 'Nama tidak benar (maks 3 kata).';
            elseif (!filter_var($form_values['author'], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9 \'\-\=\@\!\?\_\(\)\[\]]+$/u']]))
                $errors['author'] = 'Nama mengandung karakter tidak valid.';

            if (!filter_var($form_values['email'], FILTER_VALIDATE_EMAIL))
                $errors['email'] = 'Email tidak valid.';
            else {
                $req = Base::db()->prepare("SELECT `user_id` FROM `user` WHERE `user_id` != ? AND `email` = ?");
                $req->execute([$user->id, $form_values['email']]);
                if ($req->rowCount() > 0)
                    $errors['email'] = 'Email sudah terdaftar oleh pengguna lain.';
            }
            if (!in_array($form_values['gender'], ['male', 'female'])) {
                $errors['gender'] = 'Jenis kelamin tidak benar!';
            }

            if (empty($errors)) {
                $us = Base::db()->prepare("UPDATE `user` SET `name` = ?, `email` = ?, `gender` = ? WHERE `user_id` = ?");
                $us->execute([$form_values['author'], $form_values['email'], $form_values['gender'], $user->id]);

                // Update user object data to reflect changes immediately if needed on current page/session
                $user->data['name'] = $form_values['author'];
                $user->data['email'] = $form_values['email'];
                $user->data['gender'] = $form_values['gender'];

                $_SESSION['notice'] = 'Profil berhasil diperbarui.';
                $_SESSION['notice_type'] = 'success';
                header('Location: ' . $baseurl . '/account'); // Redirect to main account page
                exit();
            }
        }

        $lang_vars = array_merge($base_lang_vars, [
            'edit_profile_title' => $page_title,
            'edit_profile_heading' => 'Edit Profil',
            'breadcrumb_edit_profile' => 'Edit Profil',
            'name_label' => 'Nama',
            'email_label' => 'Email',
            'email_help' => 'Harap memasukan alamat email dengan benar, ini digunakan jika Kamu lupa kata sandi',
            'gender_label' => 'Jenis kelamin',
            'gender_male' => 'Laki-laki',
            'gender_female' => 'Perempuan',
            'save_button' => 'Simpan',
            'reset_button' => 'Reset form',
        ]);

        echo Base::$twig->render('account/profile.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/account/edit_profile',
            'form_values' => $form_values,
            'errors' => $errors,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'change_password':
        $page_title = 'Ubah Kata Sandi';
        $errors = [];

        if (isset($_POST['submit'])) {
            $current_pass = $_POST['pass'] ?? '';
            $new_pass1 = $_POST['pass1'] ?? '';
            $new_pass2 = $_POST['pass2'] ?? '';

            if (!password_verify($current_pass, $user->data['password'])) {
                $errors['pass'] = 'Kata sandi sekarang tidak benar!';
            }
            if ($new_pass1 != $new_pass2) {
                $errors['pass2'] = 'Kata sandi baru tidak sama.';
            }
            if (mb_strlen($new_pass1) < 4 || mb_strlen($new_pass1) > 16) { // Password length policy
                $errors['pass1'] = 'Kata sandi baru minimal 4 s/d 16 karakter.';
            }

            if (empty($errors)) {
                $new_password_hash = password_hash($new_pass1, PASSWORD_DEFAULT);
                if (!$new_password_hash) {
                    $errors['general'] = 'Terjadi kesalahan server saat memproses kata sandi baru.';
                } else {
                    $us = Base::db()->prepare("UPDATE `user` SET `password` = ? WHERE `user_id` = ?");
                    $us->execute([$new_password_hash, $user->id]);

                    $_SESSION['upw'] = $new_pass1; // Store raw new password in session
                    $user->data['password'] = $new_password_hash; // Update current user object state

                    $_SESSION['notice'] = 'Kata sandi berhasil diubah.';
                    $_SESSION['notice_type'] = 'success';
                    header('Location: ' . $baseurl . '/account');
                    exit();
                }
            }
        }

        $lang_vars = array_merge($base_lang_vars, [
            'change_password_title' => $page_title,
            'change_password_heading' => 'Ubah Kata Sandi',
            'breadcrumb_change_password' => 'Ubah Kata Sandi',
            'current_password_label' => 'Kata sandi sekarang',
            'new_password_label' => 'Kata sandi baru',
            'repeat_new_password_label' => 'Ulangi Kata sandi baru',
            'save_button' => 'Simpan',
        ]);

        echo Base::$twig->render('account/change_password.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/account/change_password',
            'errors' => $errors,
            'session_notice' => Func::getNotice(),
        ]);
        break;

    case 'index':
    default:
        $page_title = 'Akun Saya';

        $user_display_data = $user->data;
        $user_display_data['regtime_formatted'] = Func::displayDate($user->data['regtime']);
        // Add user_id to display data if not already part of $user->data directly (it is from User class)
        $user_display_data['user_id'] = $user->id;
        $user_display_data['name'] = $user->getName(); // Use getter for consistency if available


        $lang_vars = array_merge($base_lang_vars, [
            'account_title' => $page_title,
            'account_heading' => 'Akun Saya',
            'edit_profile_link' => 'Edit Profile',
            'change_password_link' => 'Ubah Kata Sandi',
            'admin_panel_link' => 'Admin Panel',
            'user_id_label' => 'ID',
            'name_label' => 'Nama',
            'email_label' => 'Email',
            'email_hidden_note' => '(tersembunyi)',
            'gender_label' => 'Jenis Kelamin',
            'gender_male' => 'Laki-laki',
            'gender_female' => 'Perempuan',
            'gender_not_specified' => 'Tidak ditentukan',
            'registered_on_label' => 'Mendaftar',
        ]);

        echo Base::$twig->render('account/index.twig', [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'user_data' => $user_display_data,
            'session_notice' => Func::getNotice(),
        ]);
        break;
}
// Footer is now part of the Twig layout (app.twig)
?>