<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema Integration for Wild Dragon SEO
 * Connects with your existing schema plugin without conflicts
 */
class Wdseo_Schema_Integration {
    
    public static function init() {
        // Only hook into schema output if your schema plugin exists
        if (class_exists('Wild_Dragon_Schema_Generator')) {
            add_action('wp_head', array(__CLASS__, 'output_schema_markup'), 1);
        }
        
        // Clear schema cache when content is updated
        add_action('save_post', array(__CLASS__, 'clear_schema_cache'));
        add_action('delete_post', array(__CLASS__, 'clear_schema_cache'));
    }

    /**
     * Output schema markup based on page type
     * This integrates with your existing schema plugin
     */
    public static function output_schema_markup() {
        // Check if your schema plugin class exists
        if (!class_exists('Wild_Dragon_Schema_Generator')) {
            return;
        }

        $schema_generator = new Wild_Dragon_Schema_Generator();
        
        if (is_front_page() || is_home()) {
            echo $schema_generator->get_cached_schema('homepage');
        } elseif (is_category()) {
            echo $schema_generator->get_cached_schema('category_page');
        } elseif (function_exists('is_product_category') && is_product_category()) {
            echo $schema_generator->get_cached_schema('wc_category_page');
        } elseif (function_exists('is_product') && is_product()) {
            echo $schema_generator->get_cached_schema('product_page');
        } elseif (is_single() && get_post_type() === 'post') {
            // Check if this should be a news article
            if (self::is_news_article()) {
                echo $schema_generator->get_cached_schema('news_article');
            } else {
                echo $schema_generator->get_cached_schema('article');
            }
        }
    }

    /**
     * Check if current post should be treated as news article
     */
    private static function is_news_article() {
        // Check if news sitemap is enabled and this post type is included
        if (!get_option('wdseo_news_sitemap_enabled', false)) {
            return false;
        }
        
        $news_post_types = get_option('wdseo_news_post_types', array('post'));
        $current_post_type = get_post_type();
        
        if (!in_array($current_post_type, $news_post_types)) {
            return false;
        }
        
        // Check if post is recent (within 48 hours for news)
        $post_date = get_the_date('U');
        $current_time = current_time('timestamp');
        $hours_diff = ($current_time - $post_date) / 3600;
        
        return $hours_diff <= 48;
    }

    /**
     * Get organization name for use in other parts of the plugin
     */
    public static function get_organization_name() {
        return get_option('wild_dragon_organization_name', get_bloginfo('name'));
    }

    /**
     * Get logo URL for use in other parts of the plugin
     */
    public static function get_logo_url() {
        $logo_url = get_option('wild_dragon_logo_url', '');
        
        if (empty($logo_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }
        
        return $logo_url;
    }

    /**
     * Clear schema cache when content is updated
     */
    public static function clear_schema_cache($post_id = null) {
        if (class_exists('Wild_Dragon_Schema_Generator')) {
            // Clear all schema-related transients
            global $wpdb;
            
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wild_dragon_schema_%' 
                 OR option_name LIKE '_transient_timeout_wild_dragon_schema_%'"
            );
        }
    }

    /**
     * Check if schema plugin is active and working
     */
    public static function is_schema_plugin_active() {
        return class_exists('Wild_Dragon_Schema_Generator');
    }

    /**
     * Get schema settings for display in admin
     */
    public static function get_schema_status() {
        if (!self::is_schema_plugin_active()) {
            return array(
                'status' => 'inactive',
                'message' => 'Schema plugin not detected'
            );
        }

        return array(
            'status' => 'active',
            'message' => 'Schema plugin is active and integrated'
        );
    }
}

// Initialize schema integration
add_action('plugins_loaded', array('Wdseo_Schema_Integration', 'init'));