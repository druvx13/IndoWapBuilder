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

        $module_title = 'Panel Modul: ' . self::getName(); // Use static method for name
        $content_html = '<div class="alert alert-warning">Ini adalah modul demo';

        $can_delete_info_path = null;
        if (isset($user) && $user->data['rights'] == 10) { // Check if $user is set
            $content_html .= ', untuk menghapus modul ini silakan hapus file <strong>iwbx-includes/modules/' . __CLASS__ . '.php</strong>';
            $can_delete_info_path = 'iwbx-includes/modules/' . __CLASS__ . '.php';
        } else {
            $content_html .= '.';
        }
        $content_html .= '</div>';

        // Return data for Twig template
        return [
            'title' => $module_title,
            'raw_html' => $content_html, // For direct HTML rendering in module_panel_default.twig
            // Example of more structured data for future:
            // 'message' => 'Ini adalah modul demo.',
            // 'can_delete_info_path' => $can_delete_info_path,
            // 'additional_data' => ['key' => 'value']
        ];
    }
}

?>