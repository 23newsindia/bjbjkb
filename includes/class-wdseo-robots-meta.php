<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Robots_Meta {
    // Cache for robots meta and settings
    private static $robots_cache = array();
    private static $blocked_patterns = null;
    private static $settings_cache = array();

    public static function init() {
        add_action('wp_head', array(__CLASS__, 'output_robots_meta_tag'), 1);
        
        // Only load admin hooks if in admin
        if (is_admin()) {
            add_action('add_meta_boxes', array(__CLASS__, 'add_robots_meta_box'));
            add_action('save_post', array(__CLASS__, 'save_robots_meta'), 10, 2);
            add_action('admin_init', array(__CLASS__, 'register_robots_settings'));
        }
    }

    /**
     * Get cached post meta to avoid repeated database queries
     */
    public static function get_robots_meta($post_id) {
        if (!isset(self::$robots_cache[$post_id])) {
            self::$robots_cache[$post_id] = get_post_meta($post_id, '_wdseo_robots_directive', true);
        }
        return self::$robots_cache[$post_id];
    }

    /**
     * Get cached term meta to avoid repeated database queries
     */
    private static function get_cached_term_meta($term_id, $meta_key) {
        $cache_key = $term_id . '_' . $meta_key;
        
        if (!isset(self::$robots_cache[$cache_key])) {
            self::$robots_cache[$cache_key] = get_term_meta($term_id, $meta_key, true);
        }
        
        return self::$robots_cache[$cache_key];
    }

    /**
     * Get cached settings to avoid repeated database queries
     */
    private static function get_cached_setting($setting_key, $default = '') {
        if (!isset(self::$settings_cache[$setting_key])) {
            self::$settings_cache[$setting_key] = get_option($setting_key, $default);
        }
        
        return self::$settings_cache[$setting_key];
    }

    public static function get_blocked_patterns() {
        if (self::$blocked_patterns === null) {
            $patterns = self::get_cached_setting('wdseo_robots_blocked_urls', '');
            self::$blocked_patterns = array_filter(array_map('trim', explode("\n", $patterns)));
        }
        return self::$blocked_patterns;
    }

    public static function output_robots_meta_tag() {
        // Get current URL efficiently
        $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));

        // Check blocked patterns (cached)
        $patterns = self::get_blocked_patterns();
        if (!empty($patterns)) {
            foreach ($patterns as $pattern) {
                $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match("/^" . $pattern . "/i", $current_url)) {
                    echo '<meta name="robots" content="noindex,nofollow" />';
                    return;
                }
            }
        }

        $robots = '';

        // Efficient conditional checks
        if (is_404() || (!is_singular() && !is_tax() && !is_category() && !is_tag() && 
            !is_front_page() && !is_home() && !is_author() && !is_archive())) {
            echo '<meta name="robots" content="noindex,nofollow" />';
            return;
        }

        if (is_singular()) {
            global $post;
            $directive = self::get_robots_meta($post->ID);
            $robots = $directive !== 'default' ? $directive : 
                     self::get_cached_setting("wdseo_default_robots_" . $post->post_type, 'index,follow');
        } elseif (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();
            $directive = self::get_cached_term_meta($term->term_id, '_wdseo_term_robots_directive');
            $robots = $directive !== '' ? $directive : 
                     self::get_cached_setting("wdseo_default_robots_" . $term->taxonomy, 'index,follow');
        } elseif (is_author()) {
            $robots = self::get_cached_setting('wdseo_default_robots_author', 'noindex,follow');
        } elseif (is_front_page() || is_home()) {
            $robots = self::get_cached_setting('wdseo_default_robots_home', 'index,follow');
        }

        if (!$robots) {
            $robots = 'index,follow';
        }

        echo "<meta name=\"robots\" content=\"" . esc_attr($robots) . "\" />\n";
    }

    /**
     * Register default robots settings in settings page
     */
    public static function register_robots_settings() {
        $post_types = get_post_types(array('public' => true));
        $taxonomies = get_taxonomies(array('public' => true), 'objects');

        // Merge into list
        $types = array_merge($post_types, $taxonomies);

        // Also include author and user profile pages
        $types['author'] = (object) array('label' => 'Author Archives');
        $types['user_profile'] = (object) array('label' => 'User Profile Pages');

        foreach ($types as $key => $type) {
            if (is_object($type)) {
                $name = $type->label;
                $post_type_key = $type->name ?? $key;
            } else {
                $name = ucfirst($key);
                $post_type_key = $key;
            }

            $section_id = "wdseo_robots_section_{$post_type_key}";
            $field_id = "wdseo_default_robots_{$post_type_key}";

            add_settings_section(
                $section_id,
                sprintf('Robots Meta - %s', esc_html($name)),
                null,
                'wild-dragon-seo'
            );

            add_settings_field(
                $field_id,
                'Default Robots Directive',
                array(__CLASS__, 'render_robots_select'),
                'wild-dragon-seo',
                $section_id,
                array('type_key' => $post_type_key)
            );

            register_setting('wdseo_settings_group', $field_id, array(
                'type' => 'string',
                'default' => 'index,follow',
            ));
        }

        // URL pattern blocklist
        add_settings_section(
            'wdseo_robots_section_url_patterns',
            'Block Specific URLs',
            array(__CLASS__, 'url_pattern_description'),
            'wild-dragon-seo'
        );

        add_settings_field(
            'wdseo_robots_blocked_urls',
            'Blocked URL Patterns',
            array(__CLASS__, 'render_blocked_urls_input'),
            'wild-dragon-seo',
            'wdseo_robots_section_url_patterns'
        );

        register_setting('wdseo_settings_group', 'wdseo_robots_blocked_urls', 'sanitize_textarea_input');
    }

    public static function url_pattern_description() {
        echo '<p>Enter one URL pattern per line. Wildcard <code>*</code> is supported.</p>';
    }

    public static function render_blocked_urls_input() {
        $value = self::get_cached_setting('wdseo_robots_blocked_urls', '');
        echo "<textarea name=\"wdseo_robots_blocked_urls\" rows=\"5\" style=\"width: 100%;\">" . esc_textarea($value) . "</textarea>";
    }

    public static function sanitize_textarea_input($input) {
        return sanitize_textarea($input);
    }

    public static function sanitize_textarea($text) {
        $lines = explode("\n", $text);
        $cleaned = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }

    /**
     * Render select dropdown for robots directive
     */
    public static function render_robots_select($args) {
        $type_key = $args['type_key'];
        $field_id = "wdseo_default_robots_{$type_key}";
        $value = self::get_cached_setting($field_id, 'index,follow');

        $options = array(
            'index,follow' => 'Index, Follow',
            'noindex,nofollow' => 'Noindex, Nofollow',
            'index,nofollow' => 'Index, Nofollow',
            'noindex,follow' => 'Noindex, Follow',
        );

        echo '<select name="' . esc_attr($field_id) . '" id="' . esc_attr($field_id) . '">';

        foreach ($options as $val => $label) {
            $selected = selected($value, $val, false);
            echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Add meta box to posts/pages/products
     */
    public static function add_robots_meta_box() {
        $post_types = array('post', 'page', 'product');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'wdseo_robots_meta',
                'Robots Meta Directive',
                array(__CLASS__, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }

        // Add meta box for product categories and tags
        $taxonomies = array('product_cat', 'product_tag', 'post_tag');

        foreach ($taxonomies as $taxonomy) {
            add_action("{$taxonomy}_edit_form_fields", array(__CLASS__, 'edit_term_robots_field'), 10, 2);
            add_action("{$taxonomy}_add_form_fields", array(__CLASS__, 'add_term_robots_field'), 10, 1);
        }

        add_action('created_term', array(__CLASS__, 'save_term_robots'), 10, 3);
        add_action('edit_term', array(__CLASS__, 'save_term_robots'), 10, 3);
    }

    public static function render_meta_box($post) {
        wp_nonce_field('wdseo_save_robots_meta', 'wdseo_robots_meta_nonce');

        $value = self::get_robots_meta($post->ID);
        $value = $value ?: 'default';

        $options = array(
            'default' => 'Use Default Setting',
            'index,follow' => 'Index, Follow',
            'noindex,nofollow' => 'Noindex, Nofollow',
            'index,nofollow' => 'Index, Nofollow',
            'noindex,follow' => 'Noindex, Follow',
        );

        echo '<select name="wdseo_robots_directive">';
        foreach ($options as $val => $label) {
            $selected = selected($val, $value, false);
            echo "<option value=\"$val\"$selected>$label</option>";
        }
        echo '</select>';
    }

    public static function save_robots_meta($post_id, $post) {
        if (!isset($_POST['wdseo_robots_meta_nonce']) || !wp_verify_nonce($_POST['wdseo_robots_meta_nonce'], 'wdseo_save_robots_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $robots = isset($_POST['wdseo_robots_directive']) ? sanitize_text_field($_POST['wdseo_robots_directive']) : 'default';
        update_post_meta($post_id, '_wdseo_robots_directive', $robots);
        
        // Clear cache when saving
        unset(self::$robots_cache[$post_id]);
    }

    public static function add_term_robots_field($term) {
        $options = array(
            'default' => 'Use Default Setting',
            'index,follow' => 'Index, Follow',
            'noindex,nofollow' => 'Noindex, Nofollow',
            'index,nofollow' => 'Index, Nofollow',
            'noindex,follow' => 'Noindex, Follow',
        );

        echo '<div class="form-field">
                <label for="wdseo_term_robots">Robots Directive</label>
                <select name="wdseo_term_robots" id="wdseo_term_robots">';
        foreach ($options as $val => $label) {
            echo "<option value=\"$val\">$label</option>";
        }
        echo '</select></div>';
    }

    public static function edit_term_robots_field($term, $taxonomy) {
        $value = self::get_cached_term_meta($term->term_id, '_wdseo_term_robots_directive');
        $value = $value ?: 'default';

        $options = array(
            'default' => 'Use Default Setting',
            'index,follow' => 'Index, Follow',
            'noindex,nofollow' => 'Noindex, Nofollow',
            'index,nofollow' => 'Index, Nofollow',
            'noindex,follow' => 'Noindex, Follow',
        );

        echo '<tr class="form-field">
                <th scope="row"><label for="wdseo_term_robots">Robots Directive</label></th>
                <td><select name="wdseo_term_robots" id="wdseo_term_robots">';
        foreach ($options as $val => $label) {
            $selected = selected($value, $val, false);
            echo "<option value=\"$val\"$selected>$label</option>";
        }
        echo '</select></td></tr>';
    }

    public static function save_term_robots($term_id, $tt_id, $taxonomy) {
        if (!isset($_POST['wdseo_term_robots'])) return;

        $robots = sanitize_text_field($_POST['wdseo_term_robots']);
        update_term_meta($term_id, '_wdseo_term_robots_directive', $robots);
        
        // Clear cache when saving
        $cache_key = $term_id . '_wdseo_term_robots_directive';
        unset(self::$robots_cache[$cache_key]);
    }

    /**
     * Clear all caches (useful for debugging or when needed)
     */
    public static function clear_cache() {
        self::$robots_cache = array();
        self::$blocked_patterns = null;
        self::$settings_cache = array();
    }
}

add_action('plugins_loaded', array('Wdseo_Robots_Meta', 'init'));