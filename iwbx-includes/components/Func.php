<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

class Func extends Base
{
    public static function getNotice($clear_notice = true)
    {
        $notice_data = null;
        if (isset($_SESSION['notice'])) {
            $notice_data = [
                'message' => $_SESSION['notice'],
                'type' => $_SESSION['notice_type'] ?? 'info', // Default to 'info' if not set
            ];
            if ($clear_notice) {
                unset($_SESSION['notice']);
                if (isset($_SESSION['notice_type'])) {
                    unset($_SESSION['notice_type']);
                }
            }
        }
        return $notice_data; // Returns an array ['message' => ..., 'type' => ...] or null
    }

    /**
     * Generates a password-like string.
     * !!! WARNING: This function is cryptographically weak and should NOT be used
     * for generating secure passwords for users or sensitive data.
     * It uses predictable patterns and a weak random number generator (rand()).
     * Consider using a library like random_compat or PHP's built-in random_bytes() / random_int()
     * for secure token/password generation if needed, or preferably rely on user-defined passwords
     * that are then securely hashed. This function may be removed or replaced in the future.
     */
    public static function generatePassword($length = 10)
    {
        $vowels = 'aeuy';
        $consonants = 'bdghjmnpqrstvzBDGHJLMNPQRSTVWXZ23456789';
        $password = '';
        $alt = time() % 2;
        for ($i = 0; $i < $length; $i++)
        {
            if ($alt == 1)
            {
                $password .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            }
            else
            {
                $password .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
        }
        return $password;
    }

    public static function validateRoute($route)
    {
        // Normalize route: remove leading slash, replace multiple slashes and backslashes
        $route = ltrim($route, '/');
        $route = str_replace(array('//', '\\'), '/', $route); // Also handles backslashes consistently

        // Remove any characters not in the allowed set
        $route = preg_replace_callback('/[^a-zA-Z0-9\_\-\.\/]/', function ($match)
        {
            return ''; }
        , $route);
        $ro = array();
        $routes = explode('/', $route);
        foreach ($routes as $r)
        {
            if ($r != '.' && $r != '..' && $r != '')
                $ro[] = $r;
        }
        return implode('/', $ro);
    }
    public static function deleteSite($site)
    {
        // Use prepared statements to prevent SQL injection
        $stmt = Base::db()->prepare("DELETE FROM `site` WHERE `site_id` = ?");
        $stmt->execute([$site['site_id']]);
        self::deleteDir(ROOTPATH . 'iwbx-sites/' . $site['url']);

    }

    public static function redirect($url)
    {
        header('Location: ' . parent::$set['baseurl'] . $url);
        exit();
    }
    public static function displayDate($var)
    {
        $shift = (self::$set['timezone']) * 3600;
        if (date('Y', $var) == date('Y', time()))
        {
            if (date('z', $var + $shift) == date('z', time() + $shift)) {
                // Use translated string for "Today"
                return (Base::$lang_strings['func_date_today'] ?? 'Today') . ', ' . date("H:i", $var + $shift);
            }
            if (date('z', $var + $shift) == date('z', time() + $shift) - 1) {
                // Use translated string for "Yesterday"
                return (Base::$lang_strings['func_date_yesterday'] ?? 'Yesterday') . ', ' . date("H:i", $var + $shift);
            }
        }

        return date("d/m/Y H:i", $var + $shift);
    }

    public static function deleteDir($directory, $empty = false)
    {
        if (substr($directory, -1) == "/")
        {
            $directory = substr($directory, 0, -1);
        }

        if (!file_exists($directory) || !is_dir($directory))
        {
            return false;
        }
        elseif (!is_readable($directory))
        {
            return false;
        }
        else
        {
            $directoryHandle = opendir($directory);
            if (!$directoryHandle) { // Check if opendir failed
                // Optionally log an error here: error_log("Failed to open directory: $directory");
                return false;
            }

            while (false !== ($contents = readdir($directoryHandle))) // Explicitly check for false
            {
                if ($contents != '.' && $contents != '..')
                {
                    $path = $directory . "/" . $contents;

                    if (is_dir($path))
                    {
                        self::deleteDir($path);
                    }
                    else
                    {
                        unlink($path);
                    }
                }
            }

            closedir($directoryHandle);

            if ($empty == false)
            {
                if (!rmdir($directory))
                {
                    return false;
                }
            }

            return true;
        }
    }

    public static function getExt($file)
    {
        // Using pathinfo for a more robust way to get the file extension
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return strtolower($ext);
    }

    public static function displayPagination($url, $start, $total, $kmess, $query_string = false)
    {
        $page_str = $query_string == false ? 'page/%d' : $query_string;
        $url = substr($url, -1) == '?' ? substr($url, 0, -1) . '/' : $url;

        $url = substr($url, -1) == '/' ? $url : $url . '/';

        $ssid = rand(1111, 9999);
        $neighbors = 5;
        if ($start >= $total)
            $start = max(0, $total - (($total % $kmess) == 0 ? $kmess : ($total % $kmess)));
        else
            $start = max(0, (int)$start - ((int)$start % (int)$kmess));
        $base_link = '<li><a href="' . strtr($url, array('%' => '%%')) . $page_str .
            '">%s</a></li>';
        $out[] = $start == 0 ? '<li class="disabled"><span>&laquo;</span></li>' :
            sprintf('<li><a href="' . strtr($url, array('%' => '%%')) . $page_str .
            '">%s</a></li>', $start / $kmess, '&laquo;');
        if ($start > $kmess * $neighbors)
            $out[] = sprintf($base_link, 1, '1');
        if ($start > $kmess * ($neighbors + 1))
        {
            $out[] = '<li class="disable"><span>...</span></li>';
        }
        for ($nCont = $neighbors; $nCont >= 1; $nCont--)
            if ($start >= $kmess * $nCont)
            {
                $tmpStart = $start - $kmess * $nCont;
                $out[] = sprintf($base_link, $tmpStart / $kmess + 1, $tmpStart / $kmess + 1);
            }
        $out[] = '<li class="active"><span>' . ($start / $kmess + 1) . '</span></li>';
        $tmpMaxPages = (int)(($total - 1) / $kmess) * $kmess;
        for ($nCont = 1; $nCont <= $neighbors; $nCont++)
            if ($start + $kmess * $nCont <= $tmpMaxPages)
            {
                $tmpStart = $start + $kmess * $nCont;
                $out[] = sprintf($base_link, $tmpStart / $kmess + 1, $tmpStart / $kmess + 1);
            }
        if ($start + $kmess * ($neighbors + 1) < $tmpMaxPages)
        {
            $out[] = '<li class="disable"><span>...</span></li>';
        }
        if ($start + $kmess * $neighbors < $tmpMaxPages)
            $out[] = sprintf($base_link, $tmpMaxPages / $kmess + 1, $tmpMaxPages / $kmess +
                1);
        if ($start + $kmess < $total)
        {
            $display_page = ($start + $kmess) > $total ? $total : ($start / $kmess + 2);
            $out[] = sprintf('<li><a href="' . strtr($url, array('%' => '%%')) . $page_str .
                '">%s</a></li>', $display_page, '&raquo;');
        }
        else
        {
            $out[] = '<li class="disabled"><span>&raquo;</span></li>';
        }

        $html = '<div class="paging"><ul class="pagination pagination-sm">' . implode('',
            $out) . '</ul></div>';

        return $html;
    }

    public static function displayError($error = '', $link = '')
    {
        if (!empty($error))
        {
            // Use translated string for "Error!" title
            $error_title = Base::$lang_strings['func_error_generic_title'] ?? 'Error!';
            $out = '<div class="alert alert-danger"><strong>' . htmlspecialchars($error_title) . '</strong>:';
            if (is_array($error))
            {
                $out .= '<ol>';
                foreach ($error as $err)
                {
                    $out .= '<li>' . $err . '</li>';
                }
                $out .= '</ol>';
            }
            else
            {
                $out .= ' ' . $error;
            }
            if (!empty($link))
                $out .= '<br />' . $link;
            $out .= '</div>';
            return $out;
        }
        else
        {
            return false;
        }
    }

    public static function permalink($str)
    {
        setlocale(LC_ALL, 'en_US.UTF8');
        $plink = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $plink = preg_replace("/[^a-zA-Z0-9\/_| -]/", '', $plink);
        $plink = strtolower(trim($plink, '-'));
        $plink = preg_replace("/[\/_| -]+/", '-', $plink);

        return $plink;
    }

    public static function readDir($dir, $recurse = false)
    {
        $files = array();
        $folders = array();
        // Removed error suppression operator @ from opendir
        $dh = opendir($dir);
        if (false === $dh) { // Explicitly check if opendir failed
            // Optionally log an error here: error_log("Failed to open directory: $dir");
            return false;
        }

        while (false !== ($el = readdir($dh))) // Explicitly check for false
        {
            $path = $dir . '/' . $el;

            if (is_dir($path) && $el != '.' && $el != '..')
            {
                $folders[] = $el;
                if ($recurse)
                {
                    // Corrected recursive call to self::readDir (was self::read_dir)
                    // Note: The return value of the recursive call is not used here.
                    // This means $files and $folders will only contain items from the top-level $dir.
                    // If the intention was to collect all files/folders recursively into the top-level arrays,
                    // this function's logic needs further adjustment.
                    // For now, fixing the incorrect method name.
                    self::readDir($path);
                }
            }
            elseif (is_file($path))
            {
                $files[] = $el;
            }
        }
        closedir($dh);

        return array_merge_recursive($folders, $files);
    }
}

?>