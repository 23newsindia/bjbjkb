<?php
/*
Plugin Name: Wild Dragon SEO
Description: A powerful all-in-one SEO plugin for WordPress & WooCommerce with advanced sitemap support.
Version: 1.0.0
Author: Wild Dragon Dev Team
Text Domain: wild-dragon-seo
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WDSEO_PLUGIN_FILE', __FILE__);
define('WDSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WDSEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WDSEO_VERSION', '1.0.0');
define('WDSEO_TEXT_DOMAIN', 'wild-dragon-seo');

// Disable WordPress default sitemaps early and with multiple methods
add_filter('wp_sitemaps_enabled', '__return_false', 1);
add_action('init', function() {
    remove_action('init', 'wp_sitemaps_get_server');
}, 1);

// Load translations
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wild-dragon-seo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Activation hook
register_activation_hook(__FILE__, array('Wdseo_Activator', 'activate'));

// Plugin activator class
class Wdseo_Activator {
    public static function activate() {
        // Disable WordPress default sitemaps
        add_filter('wp_sitemaps_enabled', '__return_false', 1);
        
        // Flush rewrite rules to register sitemap endpoint
        flush_rewrite_rules();
    }
}

// Load required files
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-image-alt-title.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-sitemap.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-social-meta.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-meta-description.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-settings.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-robots-meta.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-rules-engine.php';
require_once WDSEO_PLUGIN_DIR . 'includes/class-wdseo-video-meta.php';

// Initialize main functionality
add_action('plugins_loaded', array('Wdseo_Image_Alt_Title', 'init'));