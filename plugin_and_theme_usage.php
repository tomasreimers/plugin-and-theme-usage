<?php 
/*
Plugin Name: Plugin and Theme Usage
Plugin URI: https://github.com/berkmancenter/plugin-and-theme-usage
Author: Tomas Reimers
Author URI: http://tomasreimers.com
Description: Export usage of plugins and themes on a Wordpress Network install.
Version: 0.1
*/

require_once(ABSPATH . 'wp-includes/pluggable.php');

// need get_plugins function 
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

class Plugin_and_theme_usage {

    private function analyze_themes($blogs, $output_stream){
        global $wpdb;
        $keys = array_keys(wp_get_themes());
        // render header
        $header_array = array_merge(array("BLOG ID", "BLOG NAME", "BLOG URL"), $keys);
        fputcsv($output_stream, $header_array);
        // render main body
        foreach ($blogs as $blog){
            set_time_limit(30);
            $name = get_blog_option($blog, "blogname");
            $url = get_blog_option($blog, "siteurl");
            $theme = $wpdb->get_var(
                $wpdb->prepare("SELECT option_value FROM " . $wpdb->get_blog_prefix($blog) . "options WHERE option_name = 'stylesheet'", array())
            );
            $row = array($blog, $name, $url);
            foreach ($keys as $key){
                $to_push = "0";
                if ($key == $theme){
                    $to_push = "1";
                }
                array_push($row, $to_push);
            }
            fputcsv($output_stream, $row);
        }
    }
    private function analyze_plugins($blogs, $output_stream){
        global $wpdb;
        // get keys
        $keys_rough = array_keys(get_plugins());
        // remove sitewide active plugins
        $keys = array();
        $sitewide_plugins_serialized = $wpdb->get_var(
            $wpdb->prepare("SELECT meta_value FROM " . $wpdb->base_prefix . "sitemeta WHERE meta_key = 'active_sitewide_plugins'", array())
        );
        $sitewide_plugins = array_keys(maybe_unserialize($sitewide_plugins_serialized));
        foreach ($keys_rough as $key){
            if (!in_array($key, $sitewide_plugins)){
                array_push($keys, $key);
            }
        }
        // render header
        $header_array = array_merge(array("BLOG ID", "BLOG NAME", "BLOG URL"), $keys);
        fputcsv($output_stream, $header_array);
        // render main body
        foreach ($blogs as $blog){
            set_time_limit(30);
            $name = get_blog_option($blog, "blogname");
            $url = get_blog_option($blog, "siteurl");
            $plugins_serialized = $wpdb->get_var(
                $wpdb->prepare("SELECT option_value FROM " . $wpdb->get_blog_prefix($blog) . "options WHERE option_name = 'active_plugins'", array())
            );
            $plugins = maybe_unserialize($plugins_serialized);
            $row = array($blog, $name, $url);
            foreach ($keys as $key){
                $to_push = "0";
                if (in_array($key, $plugins)){
                    $to_push = "1";
                }
                array_push($row, $to_push);
            }
            fputcsv($output_stream, $row);
        }
    }

    private function get_all_blogs(){
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare("SELECT blog_id FROM " . $wpdb->base_prefix . "blogs", array())
        );
    }
    private function get_live_blogs(){
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare("SELECT blog_id FROM " . $wpdb->base_prefix . "blogs WHERE NOT ( spam = 1 OR deleted = 1 OR archived = '1' OR (TIMESTAMPDIFF(DAY, registered, NOW()) > 365 AND last_updated = 0) )", array())
        );
    }
    private function get_dead_blogs(){
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare("SELECT blog_id FROM " . $wpdb->base_prefix . "blogs WHERE spam = 1 OR deleted = 1 OR archived = '1' OR (TIMESTAMPDIFF(DAY, registered, NOW()) > 365 AND last_updated = 0)", array())
        );
    }

    public function render_csv(){
        $valid_blog_type = array("dead", "live", "all");
        $valid_content_type = array("plugins", "themes");
        // validate page
        if ($_GET['page'] === "plugin-and-theme-usage-csv" && strstr($_SERVER['REQUEST_URI'], "settings.php") !== FALSE){
            // validate request 
            if (in_array($_GET["blog_type"], $valid_blog_type) && in_array($_GET["content_type"], $valid_content_type)){
                // validate permissions
                if (current_user_can('edit_themes')){
                    // open stream
                    $output = fopen("php://output", "w");
                    // send headers
                    header("Content-type: application/csv");
                    header("Content-Disposition: attachment; filename=usage.csv");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    // get blogs
                    $blogs = NULL;
                    switch ($_GET["blog_type"]){
                        case "dead":
                            $blogs = $this->get_dead_blogs();
                            break;
                        case "live":
                            $blogs = $this->get_live_blogs();
                            break;
                        case "all":
                            $blogs = $this->get_all_blogs();
                            break;
                    }
                    // render body of CSV
                     switch ($_GET["content_type"]){
                        case "plugins":
                            $blogs = $this->analyze_plugins($blogs, $output);
                            break;
                        case "themes":
                            $blogs = $this->analyze_themes($blogs, $output);
                            break;
                    }
                    // close stream
                    fclose($output);
                    // prevent further output
                    exit();
                }
            }
        }
    }
    public function render_page(){

        // START HTML DOCUMENT
        ?>

        <h2><?php _e('Plugins and Themes Usage'); ?></h2>

        <h3><?php _e('All Blogs'); ?></h3>
        <a href="settings.php?page=plugin-and-theme-usage-csv&blog_type=all&content_type=themes" class="button-secondary"><?php _e('Themes'); ?></a>
        <a href="settings.php?page=plugin-and-theme-usage-csv&blog_type=all&content_type=plugins" class="button-secondary"><?php _e('Plugins'); ?></a>

        <h3><?php _e('Live Blogs'); ?></h3>
        <a href="settings.php?page=plugin-and-theme-usage-csv&blog_type=live&content_type=themes" class="button-secondary"><?php _e('Themes'); ?></a>
        <a href="settings.php?page=plugin-and-theme-usage-csv&blog_type=live&content_type=plugins" class="button-secondary"><?php _e('Plugins'); ?></a>

        <h3><?php _e('Dead Blogs (marked as spam, archived, deactivated, OR registered over a year ago and never updated)'); ?></h3>
        <a href="settings.php?page=plugin-and-theme-usage-csv&blog_type=dead&content_type=themes" class="button-secondary"><?php _e('Themes'); ?></a>
        <a href="settings.php?page=plugin-and-theme-usage-csv&blog_type=dead&content_type=plugins" class="button-secondary"><?php _e('Plugins'); ?></a>

        <?php
        // END HTML DOCUMENT
    }

    public function hook_in(){
        add_submenu_page(
            'settings.php',
            __('Plugin and Theme Usage'),
            __('Plugin and Theme Usage'), 
            'manage_network', 
            'plugin-and-theme-usage', 
            array($this, 'render_page')
        );
    }

}

$plugin_and_theme_usage = new Plugin_and_theme_usage();

// hook into menu - admin page
add_action('network_admin_menu', array($plugin_and_theme_usage, 'hook_in'));

// create new page
add_action('init', array($plugin_and_theme_usage, 'render_csv'));

?>