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
    header('Location: ' . $redirect_target);
    exit();
}

// Base lang_strings are globally available to Twig as 'lang' via Base.php

// Ensure Base::$twig is available
if (!isset(Base::$twig) || !(Base::$twig instanceof \Twig\Environment)) {
    die("Error: Templating engine (Twig) is not available. Cannot render account page.");
}

// The $baseurl_root variable for Twig templates, if not already a global Twig var
$baseurl_root_for_twig = $baseurl; // $baseurl is from iwbx-includes/base.php

switch ($action) {
    case 'edit_profile':
        $page_title = Base::$lang_strings['account_edit_profile_title'] ?? 'Edit Profile';
        $errors = [];
        $form_values = [
            'author' => $_POST['author'] ?? $user->data['name'],
            'email' => $_POST['email'] ?? $user->data['email'],
            'gender' => $_POST['gender'] ?? $user->data['gender'],
        ];

        if (isset($_POST['submit'])) {
            $form_values['author'] = trim($form_values['author']);
            $form_values['email'] = trim($form_values['email']);

            if (mb_strlen($form_values['author']) < 3 || mb_strlen($form_values['author']) > 32)
                $errors['author'] = Base::$lang_strings['account_profile_error_name_length'] ?? 'Name must be between 3 and 32 characters.';
            elseif (str_word_count($form_values['author']) > 3)
                $errors['author'] = Base::$lang_strings['account_profile_error_name_words'] ?? 'Name is not valid (max 3 words).';
            elseif (!filter_var($form_values['author'], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[a-zA-Z0-9 \'\-\=\@\!\?\_\(\)\[\]]+$/u']]))
                $errors['author'] = Base::$lang_strings['account_profile_error_name_invalid_chars'] ?? 'Name contains invalid characters.';

            if (!filter_var($form_values['email'], FILTER_VALIDATE_EMAIL))
                $errors['email'] = Base::$lang_strings['account_profile_error_email_invalid'] ?? 'Email is not valid.';
            else {
                $req = Base::db()->prepare("SELECT `user_id` FROM `user` WHERE `user_id` != ? AND `email` = ?");
                $req->execute([$user->id, $form_values['email']]);
                if ($req->rowCount() > 0)
                    $errors['email'] = Base::$lang_strings['account_profile_error_email_taken'] ?? 'Email is already registered by another user.';
            }
            if (!in_array($form_values['gender'], ['male', 'female'])) {
                $errors['gender'] = Base::$lang_strings['account_profile_error_gender_invalid'] ?? 'Gender is not valid.';
            }

            if (empty($errors)) {
                $us = Base::db()->prepare("UPDATE `user` SET `name` = ?, `email` = ?, `gender` = ? WHERE `user_id` = ?");
                $us->execute([$form_values['author'], $form_values['email'], $form_values['gender'], $user->id]);

                $user->data['name'] = $form_values['author'];
                $user->data['email'] = $form_values['email'];
                $user->data['gender'] = $form_values['gender'];

                $_SESSION['notice'] = Base::$lang_strings['account_profile_update_success'] ?? 'Profile updated successfully.';
                $_SESSION['notice_type'] = 'success';
                header('Location: ' . $baseurl . '/account');
                exit();
            }
        }

        echo Base::$twig->render('account/profile.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/account/edit_profile',
            'form_values' => $form_values,
            'errors' => $errors,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;

    case 'change_password':
        $page_title = Base::$lang_strings['account_change_password_title'] ?? 'Change Password';
        $errors = [];

        if (isset($_POST['submit'])) {
            $current_pass = $_POST['pass'] ?? '';
            $new_pass1 = $_POST['pass1'] ?? '';
            $new_pass2 = $_POST['pass2'] ?? '';

            if (!password_verify($current_pass, $user->data['password'])) {
                $errors['pass'] = Base::$lang_strings['account_password_error_current_incorrect'] ?? 'Current password is not correct.';
            }
            if ($new_pass1 != $new_pass2) {
                $errors['pass2'] = Base::$lang_strings['account_password_error_new_mismatch'] ?? 'New passwords do not match.';
            }
            if (mb_strlen($new_pass1) < 4 || mb_strlen($new_pass1) > 16) {
                $errors['pass1'] = Base::$lang_strings['account_password_error_new_length'] ?? 'New password must be between 4 and 16 characters.';
            }

            if (empty($errors)) {
                $new_password_hash = password_hash($new_pass1, PASSWORD_DEFAULT);
                if (!$new_password_hash) {
                    $errors['general'] = Base::$lang_strings['account_password_error_server'] ?? 'Server error processing new password.';
                } else {
                    $us = Base::db()->prepare("UPDATE `user` SET `password` = ? WHERE `user_id` = ?");
                    $us->execute([$new_password_hash, $user->id]);

                    $_SESSION['upw'] = $new_pass1;
                    $user->data['password'] = $new_password_hash;

                    $_SESSION['notice'] = Base::$lang_strings['account_password_update_success'] ?? 'Password changed successfully.';
                    $_SESSION['notice_type'] = 'success';
                    header('Location: ' . $baseurl . '/account');
                    exit();
                }
            }
        }

        echo Base::$twig->render('account/change_password.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/account/change_password',
            'errors' => $errors,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;

    case 'index':
    default:
        $page_title = Base::$lang_strings['account_page_title'] ?? 'My Account';

        $user_display_data = $user->data;
        $user_display_data['regtime_formatted'] = Func::displayDate($user->data['regtime']);
        $user_display_data['user_id'] = $user->id;
        $user_display_data['name'] = $user->getName();

        echo Base::$twig->render('account/index.twig', [
            'page_title' => $page_title,
            'user' => $user,
            'set' => $set,
            'user_data' => $user_display_data,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl_root_for_twig,
        ]);
        break;
}
?>
