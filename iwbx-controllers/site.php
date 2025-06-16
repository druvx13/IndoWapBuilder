<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

switch ($action)
{
    case 'logout':
        $user->logOut();
        header('Location: ' . $baseurl . '/site/index');
        exit();
        break;

    case 'whois':
        $page_title = 'Whois';
        $domain_value = isset($_GET['domain']) ? strtolower(trim($_GET['domain'])) : '';
        $site_info = null;

        if ($domain_value) {
            $search_domain = $domain_value;
            if (strpos($search_domain, 'www.') === 0) {
                $search_domain = substr($search_domain, 4);
            }

            $br = Base::db()->prepare("SELECT s.url, s.time, u.name as owner_name FROM `site` s JOIN `user` u ON s.user_id = u.user_id WHERE s.url = ?");
            $br->execute([$search_domain]);

            if ($br->rowCount() > 0) {
                $row = $br->fetch(PDO::FETCH_ASSOC);
                $site_info = [
                    'url' => $row['url'],
                    'owner_name' => $row['owner_name'],
                    'registered_date' => Func::displayDate($row['time']),
                ];
            }
        }

        $lang_vars = [
            'whois_title' => 'WHOIS Lookup',
            'whois_heading' => 'WHOIS Lookup',
            'domain_placeholder' => 'Contoh: site.' . ($_SERVER['SERVER_NAME'] ?? 'example.com'),
            'whois_button' => 'WHOIS',
            'site_url_label' => 'URL Situs',
            'owner_label' => 'Pemilik',
            'registered_on_label' => 'Mendaftar',
            'data_not_found' => 'Data tidak ditemukan.',
        ];

        $view_vars = [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/site/whois',
            'domain_value' => htmlspecialchars($domain_value),
            'site_info' => $site_info,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl,
        ];
        echo Base::$twig->render('site/whois_results.twig', $view_vars);
        break;

    case 'domain_check':
    case 'domain_check':
        $page_title = 'Domain Checker';
        $submitted_subdomain = isset($_POST['subdomain']) ? Func::permalink(trim($_POST['subdomain'])) : null;
        $submitted_domains_for_check = isset($_POST['domains']) && is_array($_POST['domains']) ? $_POST['domains'] : [];

        if ($submitted_subdomain !== null && (mb_strlen($submitted_subdomain) < 4 || mb_strlen($submitted_subdomain) > 16)) {
            $submitted_subdomain = null; // Invalidate if length criteria not met
        }

        $all_system_domains = [];
        if (isset($set['domains'])) {
            $parsed_system_domains = unserialize($set['domains']);
            if ($parsed_system_domains !== false || $set['domains'] === serialize(false)) {
                $all_system_domains = $parsed_system_domains;
            } else {
                error_log("Error unserializing domains from settings for site/domain_check.");
            }
        }

        $results = [];
        if ($submitted_subdomain && !empty($submitted_domains_for_check)) {
            foreach ($submitted_domains_for_check as $domain_to_check) {
                if (in_array($domain_to_check, $all_system_domains)) {
                    $url_to_check = $submitted_subdomain . '.' . $domain_to_check;
                    $req = Base::db()->prepare("SELECT COUNT(*) FROM `site` WHERE `url` = ?");
                    $req->execute([$url_to_check]);
                    $count = $req->fetchColumn();

                    $results[] = [
                        'url' => $url_to_check,
                        'available' => $count == 0,
                        'register_url' => $baseurl . '/panel/create_site/domain/' . $url_to_check,
                        'whois_url' => $baseurl . '/site/whois/domain/' . $url_to_check,
                    ];
                }
            }
        }

        $lang_vars = [
            'domain_check_title' => 'Domain Checker',
            'domain_check_results_heading' => 'Hasil Pengecekan Domain',
            'perform_another_check' => 'Periksa ketersediaan subdomain lainnya',
            'subdomain_placeholder' => 'Masukan subdomain',
            'check_button' => 'Check',
            'no_domains_configured' => 'Tidak ada domain yang dikonfigurasi untuk pengecekan.',
            'results_for_subdomain' => 'Hasil untuk subdomain',
            'domain_available' => 'Domain masih tersedia!',
            'register_domain_button' => 'Pendaftaran',
            'domain_unavailable' => 'Domain tidak tersedia!',
            'whois_button' => 'WHOIS',
            'no_domains_selected_for_check' => 'Tidak ada domain yang dipilih untuk dicek, atau subdomain tidak valid.',
        ];

        $view_vars = [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user,
            'set' => $set,
            'form_action_url' => $baseurl . '/site/domain_check',
            'subdomain_value' => htmlspecialchars($submitted_subdomain ?? ''),
            'all_available_domains_for_form' => $all_system_domains,
            'checked_domains' => $submitted_domains_for_check, // For repopulating checkboxes
            'results' => $results,
            'submitted_subdomain' => $submitted_subdomain, // To know if a check was performed
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl,
        ];
        echo Base::$twig->render('site/domain_check_results.twig', $view_vars);
        break;

    case 'reset_password':
        $code = isset($_GET['code']) ? $_GET['code'] : '';
        $pageTitle = 'Setel ulang kata sandi';
        include_once (ROOTPATH . 'iwbx-includes/header.php');
        echo '<h4 class="head-title">Setel ulang kata sandi</h4>';
        if (mb_strlen($code) != 20)
        {
            echo Func::displayError('Kode konfirmasi tidak benar!');
            include_once (ROOTPATH . 'iwbx-includes/footer.php');
            exit();
        }
        if (abs(intval(mb_substr($code, 0, 10))) < (time() - 3600))
        {
            echo Func::displayError('Kode konfirmasi sudah tidak berlaku lagi!');
            include_once (ROOTPATH . 'iwbx-includes/footer.php');
            exit();
        }
        $req = Base::db()->prepare("SELECT * FROM `user` WHERE `code` = ?");
        $req->execute(array($code));
        if ($req->rowCount() != 1)
        {
            echo Func::displayError('Kode konfirmasi tidak benar!');
            include_once (ROOTPATH . 'iwbx-includes/footer.php');
            exit();
        }
        $usr = $req->fetch();
        $error = false;
        $pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        $pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';

        if (isset($_POST['submit']))
        {
            if ($pass1 != $pass2)
                $errors['pass2'] = 'Kata sandi tidak sama.';
            if (mb_strlen($pass1) < 4 || mb_strlen($pass1) > 16 || mb_strlen($pass2) < 4 ||
                mb_strlen($pass2) > 16)
                $errors['pass1'] = 'Kata sandi minimal 4 s/d 16 karakter.';
            if (empty($errors))
            {
                $new_password_hash = password_hash($pass1, PASSWORD_DEFAULT);
                if (!$new_password_hash) {
                    $errors['pass1'] = 'Terjadi kesalahan saat memproses kata sandi baru.';
                } else {
                    $us = Base::db()->prepare("UPDATE `user` SET `password` = ?, `code` = ? WHERE `user_id` = ?");
                    $us->execute(array(
                        $new_password_hash,
                        '', // Clear the reset code
                        $usr['user_id'],
                        ));

                    // Set session for login
                    $_SESSION['uid'] = $usr['user_id'];
                    // Store the raw password in session, consistent with User::auth() and other updates
                    $_SESSION['upw'] = $pass1;

                    $_SESSION['notice'] = 'Kata sandi Anda telah berhasil direset. Anda sekarang login.';
                    header('Location: ' . $baseurl . '/account'); // Redirect to account page
                    exit();
                }
            }
        }
        // $errors array is used here, not single $error string for form field errors
        // $error_message_top is for errors displayed before the form (code validation)

        $lang_vars = [
            'reset_password_title' => 'Setel ulang kata sandi',
            'reset_password_heading' => 'Setel ulang kata sandi',
            'new_password_label' => 'Kata sandi baru',
            'repeat_new_password_label' => 'Ulangi Kata sandi baru',
            'save_button' => 'Simpan',
        ];

        $view_vars = [
            'page_title' => $pageTitle, // Already set
            'lang' => $lang_vars,
            'user' => $user, // For layout
            'form_action_url' => $baseurl . '/site/reset_password/code/' . $code,
            'errors' => $errors ?? [], // Pass form field errors
            'error_message' => $error_message_top ?? null, // For errors displayed above the form
            'session_notice' => Func::getNotice(),
             // pass1 and pass2 values are not typically repopulated for password fields
        ];
        echo Base::$twig->render('auth/reset_password.twig', $view_vars);
        break;

    case 'forgot_password':
        $error_message_top = false; // Renamed from $error to avoid conflict with $errors array for form fields
        $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
        if (isset($_POST['submit']))
        {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                $error = 'Email tidak valid.';
            else
            {
                $req = Base::db()->prepare("SELECT * FROM `user` WHERE `email` = ?");
                $req->execute(array($email));
                if ($req->rowCount() == 0)
                    $error = 'Email tidak terdaftar.';
                else
                {
                    $usr = $req->fetch();
                    if (!empty($usr['code']))
                    {
                        if (($tm = abs(intval(mb_substr($usr['code'], 0, 10)))) > (time() - 600))
                            $error = 'Sebelumnya kode konfirmasi telah dikirim pada <strong>' . Func::
                                displayDate($tm) . '</strong>, Untuk mengirim ulang kode konfirmasi silakan tunggu minimal 10 ' .
                                'menit dari permintaan sebelumnya!';
                    }
                }
            }
            if (!$error)
            {
                $code = time() . Func::generatePassword();
                $subject = 'Setel Ulang Kata Sandi';
                $mail = "Hai, {$usr['name']}\r\n" .
                    "Baru-baru ini Anda telah meminta menyetel ulang kata sandi pada situs {$set['url']},\r\n" .
                    "untuk melanjutkan silakan klik link berikut ini\r\n" . "{$set['url']}/index.php/site/" .
                    "reset_password/code/$code\r\n" . "\r\nJika bukan Anda yang mengirimkan permintaan tersebut abaikan pesan ini.";
                $adds = "From: <" . $set['siteemail'] . ">\r\n";
                $adds .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
                if (mail($email, $subject, $mail, $adds))
                {
                    $uss = Base::db()->prepare("UPDATE `user` SET `code` = ? WHERE `user_id` = ?");
                    $uss->execute(array(
                        $code,
                        $usr['user_id'],
                        ));

                    $_SESSION['notice'] = 'Kode konfirmasi telah dikirim ke alamat email Kamu.';
                    header('Location: ' . $baseurl . '/site/login');
                    exit();

                }
                else
                {
                    $error = 'Gagal mengirim email';
                }
            }

        }

        $pageTitle = 'Lupa Kata sandi';
        // $error variable holds a single error string or false.

        // Prepare lang strings for Twig
        $lang_vars = [
            'forgot_password_title' => 'Lupa Kata sandi',
            'forgot_password_heading' => 'Lupa Kata sandi',
            'email_label' => 'Email',
            'send_button' => 'Kirim',
        ];

        $view_vars = [
            'page_title' => $pageTitle,
            'lang' => $lang_vars,
            'user' => $user,
            'form_action_url' => $baseurl . '/site/forgot_password',
            'email_value' => htmlspecialchars($email), // $email is already defined in this case
            'error_message' => $error ? $error : null, // Pass single error message if it exists
            'session_notice' => Func::getNotice(),
        ];
        echo Base::$twig->render('auth/forgot_password.twig', $view_vars);
        break;

    case 'register':
        if ($user->id)
        {
            header('Location: ' . $baseurl . '/panel');
            exit();
        }
        $pageTitle = 'Pendaftaran';
        $errors = array();
        $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
        $author = isset($_POST['author']) ? trim($_POST['author']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $repeat_password = isset($_POST['repeat_password']) ? $_POST['repeat_password'] :
            '';

        if (isset($_POST['submit']))
        {
            if (mb_strlen($author) < 3 || mb_strlen($author) > 32)
                $errors['author'] = 'Panjang nama min. 3 s/d 32 karakter.';
            elseif (str_word_count($author) > 3)
                $errors['author'] = 'Nama tidak benar.';
            elseif (!filter_var($author, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' =>
                        '/[a-zA-Z0-9 \-\=\@\!\?\_\(\)\[\]]+$/'))))
                $errors['author'] = 'Nama tidak benar.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                $errors['email'] = 'Email tidak valid.';
            else
            {
                $req = Base::db()->prepare("SELECT * FROM `user` WHERE `email` = ?");
                $req->execute(array($email));
                if ($req->rowCount() > 0)
                    $errors['email'] = 'Email sudah terdaftar.';
            }
            if ($password != $repeat_password)
                $errors['password'] = 'Kata sandi tidak sama.';
            if (mb_strlen($password) < 4 || mb_strlen($password) > 16)
                $errors['password'] = 'Kata sandi minimal 4 s/d 16 karakter.';
            if (empty($errors))
            {
                $new_password_hash = password_hash($password, PASSWORD_DEFAULT);
                if (!$new_password_hash) {
                    // This should ideally not happen with valid algo and input
                    $errors[] = 'Terjadi kesalahan server saat membuat akun (hash error).';
                } else {
                    $us = Base::db()->prepare("INSERT INTO `user` SET `name` = ?, `email` = ?, `password` = ?, `regtime` = ?");
                    $us->execute(array(
                        $author,
                        $email,
                        $new_password_hash,
                        time(),
                        ));
                    $uid = Base::db()->lastInsertId();

                    // Set session for auto-login
                    $_SESSION['uid'] = $uid;
                    // Store the raw password in session, consistent with User::auth() and other updates
                    $_SESSION['upw'] = $password;

                    $_SESSION['notice'] = 'Pendaftaran berhasil! Anda sekarang login.';
                    header('Location: ' . $baseurl . '/panel/'); // Redirect to panel
                    exit();
                }

            }
            else
            {
                $errors[] = 'Pendaftaran gagal, silakan hubungi Administrator.';
            }
            // This 'else' implies that if $errors was not empty initially, it might add this generic error.
            // It might be better to only add this if NO other specific errors were set.
            // However, keeping original logic for now.
            // else
            // {
            //     $errors['general'] = 'Pendaftaran gagal, silakan hubungi Administrator.';
            // }
        }

        // Prepare lang strings for Twig
        $lang_vars = [
            'register_title' => 'Pendaftaran',
            'register_heading' => 'Pendaftaran',
            'name_label' => 'Nama Anda',
            'email_label' => 'Email',
            'password_label' => 'Kata sandi',
            'repeat_password_label' => 'Ulangi kata sandi',
            'register_button' => 'Mendaftar',
        ];

        // Ensure all potential error keys are passed to Twig, even if empty
        $form_errors_twig = [
            'author' => $errors['author'] ?? null,
            'email' => $errors['email'] ?? null,
            'password' => $errors['password'] ?? null,
            'repeat_password' => $errors['repeat_password'] ?? null, // Though original code doesn't set this key
            'general' => $errors['general'] ?? ($errors[0] ?? null) // Handle the generic error added by original code
        ];


        $view_vars = [
            'page_title' => $page_title, // Already set to 'Pendaftaran'
            'lang' => $lang_vars,
            'user' => $user,
            'form_action_url' => $baseurl . '/site/register',
            'author_value' => htmlspecialchars($author),
            'email_value' => htmlspecialchars($email),
            'errors' => $form_errors_twig,
            'session_notice' => Func::getNotice(),
        ];
        echo Base::$twig->render('auth/register.twig', $view_vars);
        break;

    case 'login':
        if ($user->id) { // User already logged in
            header('Location: ' . $baseurl . '/panel');
            exit();
        }

        $page_title = 'Masuk';
        $form_error_general = false; // For general error message like "Email or password incorrect"
        $email_value = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
        $form_action_url = $baseurl . '/site/login';

        $redirect_url_param = isset($_GET['redirect']) ? filter_var(urldecode($_GET['redirect']), FILTER_SANITIZE_URL) : '';
        if ($redirect_url_param) {
            // Basic validation for redirect URL (must be relative or same host)
            $parsed_redirect = parse_url($redirect_url_param);
            if (isset($parsed_redirect['host']) && $parsed_redirect['host'] != $_SERVER['SERVER_NAME']) {
                $redirect_url_param = ''; // Discard external redirects
            } elseif (isset($parsed_redirect['scheme'])) {
                 // Allow only http/https for safety
                if (!in_array(strtolower($parsed_redirect['scheme']), ['http', 'https'])) {
                    $redirect_url_param = '';
                }
            }
            if ($redirect_url_param) {
                 $form_action_url .= '?redirect=' . urlencode($redirect_url_param);
            }
        }


        if (isset($_POST['submit'])) {
            $password_input = $_POST['password']; // Raw password from form

            if ($email_value && $password_input) {
                $req = Base::db()->prepare("SELECT * FROM `user` WHERE `email` = ?");
                $req->execute([$email_value]);

                if ($req->rowCount() == 0) {
                    $form_error_general = 'Email atau Kata sandi tidak benar';
                } else {
                    $user_data = $req->fetch();
                    $stored_hash = $user_data['password'];
                    $login_successful = false;

                    if (password_verify($password_input, $stored_hash)) {
                        $login_successful = true;
                        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
                            $new_hash = password_hash($password_input, PASSWORD_DEFAULT);
                            if ($new_hash) {
                                $upd = Base::db()->prepare("UPDATE `user` SET `password` = ? WHERE `user_id` = ?");
                                $upd->execute([$new_hash, $user_data['user_id']]);
                            }
                        }
                    } elseif ($stored_hash == md5(md5($password_input))) { // Check old MD5
                        $login_successful = true;
                        $new_hash = password_hash($password_input, PASSWORD_DEFAULT); // Upgrade hash
                        if ($new_hash) {
                            $upd = Base::db()->prepare("UPDATE `user` SET `password` = ? WHERE `user_id` = ?");
                            $upd->execute([$new_hash, $user_data['user_id']]);
                        }
                    }

                    if ($login_successful) {
                        $_SESSION['uid'] = $user_data['user_id'];
                        $_SESSION['upw'] = $password_input; // Store raw password

                        $target_redirect = $redirect_url_param ?: $baseurl . '/panel';
                        header('Location: ' . $target_redirect);
                        exit();
                    } else {
                        $form_error_general = 'Email atau Kata sandi tidak benar';
                    }
                }
            } else {
                $form_error_general = 'Email dan Kata sandi wajib diisi.';
            }
        }

        // Prepare lang strings for Twig
        $lang_vars = [
            'login_title' => 'Login',
            'login_heading' => 'Masuk',
            'email_label' => 'Email',
            'password_label' => 'Kata sandi',
            'login_button' => 'Masuk',
            'forgot_password_link' => 'Lupa kata sandi?',
            'register_link' => 'Pendaftaran',
        ];

        $view_vars = [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user, // Pass the $user object for layout/app.twig
            'form_action_url' => $form_action_url,
            'email_value' => htmlspecialchars($email_value),
            'form_error_general' => $form_error_general,
            'forgot_password_url' => $baseurl . '/site/forgot_password',
            'register_url' => $baseurl . '/site/register',
            'session_notice' => Func::getNotice(), // Get and clear session notice
        ];
        echo Base::$twig->render('auth/login.twig', $view_vars);
        break;

    case 'index':
        $page_title = $set['sitename'] ?? 'Welcome'; // Default page title

        $available_domains = [];
        if (isset($set['domains'])) {
            $parsed_domains = unserialize($set['domains']);
            if ($parsed_domains !== false || $set['domains'] === serialize(false)) {
                $available_domains = $parsed_domains;
            } else {
                error_log("Error unserializing domains from settings for site/index.");
            }
        }

        $lang_vars = [
            'tagline' => 'Free Wap Site Builder',
            'main_heading' => 'Buat wap site gratis',
            'main_tagline' => 'adalah tempat terbaik untuk situs pribadi ataupun situs usaha.',
            'domain_check_heading' => 'Periksa ketersediaan subdomain',
            'subdomain_placeholder' => 'Masukan subdomain',
            'check_button' => 'Check',
            'no_domains_configured' => 'Tidak ada domain yang dikonfigurasi untuk pengecekan.',
            'feature_multisite_title' => 'Multi Site',
            'feature_multisite_desc' => 'Gak perlu bikin banyak akun kalo cuma mau bikin beberapa situs, karena dalam satu akun Kamu bisa bikin lebih dari satu situs.',
            'feature_template_title' => 'Template Sistem',
            'feature_template_desc' => 'Sistem template yang memiliki banyak fungsi dan modul-modul canggih seperti: Blog, Chat, dll.',
        ];

        $view_vars = [
            'page_title' => $page_title,
            'lang' => $lang_vars,
            'user' => $user, // Pass $user for layout
            'set' => $set,   // Pass $set for things like sitename, and for layout
            'domain_check_url' => $baseurl . '/site/domain_check',
            'available_domains' => $available_domains,
            'session_notice' => Func::getNotice(),
            'baseurl_root' => $baseurl, // For layout links
        ];
        echo Base::$twig->render('site/index.twig', $view_vars);
        break;

    default:
        header('Location: ' . $baseurl . '/error/404');
        exit();
        break;
}
include_once (ROOTPATH . 'iwbx-includes/footer.php');

?>