<?php

/**
 * @package IndoWapBuilder
 * @version VERSION (see attached file)
 * @author Achunk JealousMan
 * @link http://facebook.com/achunks
 * @copyright 2014 - 2015
 * @license LICENSE (see attached file)
 */

class module_test extends Module
{
    protected $options = array();

    protected function load($options = null)
    {
        $this->options = is_null($options) ? array() : json_decode($options);
        return parent::$set['sitename'];
    }
    public static function getName()
    {
        return 'Module Test';
    }
    public function panel()
    {
        global $user; // $user should be available from the controller scope

        // Use lang strings from Base::$lang_strings (globally available in Twig, but access here if needed)
        $lang = Base::$lang_strings;

        $module_title = $lang['module_test_panel_title'] ?? 'Test Module Panel';

        $content_html = '<div class="alert alert-warning">' . ($lang['module_test_panel_content_demo'] ?? 'This is a demo module panel.');

        $can_delete_info_path = null;
        if (isset($user) && $user->data['rights'] == 10) { // Check if $user is set
            $filepath_to_delete = 'iwbx-includes/modules/' . __CLASS__ . '.php';
            $delete_info_str = $lang['module_test_panel_delete_info'] ?? 'To remove this module, please delete the file: <strong>%filepath%</strong>';
            $content_html .= ' ' . str_replace('%filepath%', $filepath_to_delete, $delete_info_str);
            $can_delete_info_path = $filepath_to_delete;
        } else {
            $content_html .= '.';
        }
        $content_html .= '</div>';

        // Return data for Twig template
        return [
            'title' => $module_title,
            'raw_html' => $content_html,
            // For more structured data (if module_panel_default.twig is adapted):
            // 'message' => $lang['module_test_panel_content_demo'] ?? 'This is a demo module panel.',
            // 'can_delete_info_path' => $can_delete_info_path,
        ];
    }
}

?>