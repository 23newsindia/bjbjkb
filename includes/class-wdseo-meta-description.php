<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Meta_Description {
    // Static cache to avoid repeated database queries
    private static $meta_cache = array();
    private static $term_cache = array();
    private static $option_cache = array();

    public static function init() {
        // Post types
        add_action('add_meta_boxes', array(__CLASS__, 'add_description_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_description_meta'), 10, 2);

        // Term meta (product categories)
        add_action('product_cat_add_form_fields', array(__CLASS__, 'add_term_description_field'));
        add_action('product_cat_edit_form_fields', array(__CLASS__, 'edit_term_description_field'), 10, 2);
        add_action('created_term', array(__CLASS__, 'save_term_description'), 10, 3);
        add_action('edit_term', array(__CLASS__, 'save_term_description'), 10, 3);

        // Front page / home page
        add_action('admin_init', array(__CLASS__, 'add_front_page_description_support'));

        // Output meta description tag FIRST with higher priority
        add_action('wp_head', array(__CLASS__, 'output_meta_description'), 1);
        
        // Then output social meta tags with normal priority
        add_action('wp_head', array('Wdseo_Social_Meta', 'output_og_meta'), 5);
    }

    /**
     * Get cached post meta to avoid repeated database queries
     */
    private static function get_cached_post_meta($post_id, $meta_key) {
        $cache_key = $post_id . '_' . $meta_key;
        
        if (!isset(self::$meta_cache[$cache_key])) {
            self::$meta_cache[$cache_key] = get_post_meta($post_id, $meta_key, true);
        }
        
        return self::$meta_cache[$cache_key];
    }

    /**
     * Get cached term meta to avoid repeated database queries
     */
    private static function get_cached_term_meta($term_id, $meta_key) {
        $cache_key = $term_id . '_' . $meta_key;
        
        if (!isset(self::$term_cache[$cache_key])) {
            self::$term_cache[$cache_key] = get_term_meta($term_id, $meta_key, true);
        }
        
        return self::$term_cache[$cache_key];
    }

    /**
     * Get cached option to avoid repeated database queries
     */
    private static function get_cached_option($option_name, $default = '') {
        if (!isset(self::$option_cache[$option_name])) {
            self::$option_cache[$option_name] = get_option($option_name, $default);
        }
        
        return self::$option_cache[$option_name];
    }

    /**
     * Add meta box to posts/pages/products
     */
    public static function add_description_meta_box() {
        $post_types = array('post', 'page', 'product');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'wdseo_meta_description',
                'Meta Description',
                array(__CLASS__, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public static function render_meta_box($post) {
        wp_nonce_field('wdseo_save_meta', 'wdseo_meta_nonce');
        
        // Use cached meta retrieval
        $desc = self::get_cached_post_meta($post->ID, '_wdseo_meta_description');
        $og_desc = self::get_cached_post_meta($post->ID, '_wdseo_og_description');
        
        echo '<div class="wdseo-meta-box">';
        echo '<h4>Standard Meta Description</h4>';
        echo '<textarea name="wdseo_meta_description" rows="3" style="width:100%;">' . esc_textarea($desc) . '</textarea>';
        
        echo '<h4>Open Graph Description</h4>';
        echo '<textarea name="wdseo_og_description" rows="3" style="width:100%;">' . esc_textarea($og_desc) . '</textarea>';
        echo '<p class="description">Leave blank to use same as standard description</p>';
        echo '</div>';
    }

    public static function save_description_meta($post_id, $post) {
        if (!isset($_POST['wdseo_meta_nonce']) || !wp_verify_nonce($_POST['wdseo_meta_nonce'], 'wdseo_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $description = isset($_POST['wdseo_meta_description']) ? sanitize_text_field($_POST['wdseo_meta_description']) : '';
        $og_description = isset($_POST['wdseo_og_description']) ? sanitize_text_field($_POST['wdseo_og_description']) : '';
        
        update_post_meta($post_id, '_wdseo_meta_description', $description);
        update_post_meta($post_id, '_wdseo_og_description', $og_description);
        
        // Clear cache when saving
        $cache_key_desc = $post_id . '_wdseo_meta_description';
        $cache_key_og = $post_id . '_wdseo_og_description';
        unset(self::$meta_cache[$cache_key_desc]);
        unset(self::$meta_cache[$cache_key_og]);
    }

    /**
     * Add meta field to product categories
     */
    public static function add_term_description_field($term) {
        echo '<div class="form-field">
                <h3>Standard Meta Description</h3>
                <textarea name="wdseo_term_description" id="wdseo_term_description"></textarea>
                
                <h3>Open Graph Description</h3>
                <textarea name="wdseo_term_og_description" id="wdseo_term_og_description"></textarea>
                <p class="description">Leave blank to use same as standard description</p>
              </div>';
    }

    public static function edit_term_description_field($term, $taxonomy) {
        // Use cached term meta retrieval
        $desc = self::get_cached_term_meta($term->term_id, '_wdseo_term_description');
        $og_desc = self::get_cached_term_meta($term->term_id, '_wdseo_term_og_description');
        
        echo '<tr class="form-field">
                <th scope="row"><label for="wdseo_term_description">Standard Meta Description</label></th>
                <td>
                    <textarea name="wdseo_term_description" id="wdseo_term_description">' . esc_textarea($desc) . '</textarea>
                </td>
              </tr>
              <tr class="form-field">
                <th scope="row"><label for="wdseo_term_og_description">Open Graph Description</label></th>
                <td>
                    <textarea name="wdseo_term_og_description" id="wdseo_term_og_description">' . esc_textarea($og_desc) . '</textarea>
                    <p class="description">Leave blank to use same as standard description</p>
                </td>
              </tr>';
    }

    public static function save_term_description($term_id, $tt_id, $taxonomy) {
        if (isset($_POST['wdseo_term_description'])) {
            $desc = sanitize_text_field($_POST['wdseo_term_description']);
            update_term_meta($term_id, '_wdseo_term_description', $desc);
            
            // Clear cache when saving
            $cache_key = $term_id . '_wdseo_term_description';
            unset(self::$term_cache[$cache_key]);
        }
        
        if (isset($_POST['wdseo_term_og_description'])) {
            $og_desc = sanitize_text_field($_POST['wdseo_term_og_description']);
            update_term_meta($term_id, '_wdseo_term_og_description', $og_desc);
            
            // Clear cache when saving
            $cache_key = $term_id . '_wdseo_term_og_description';
            unset(self::$term_cache[$cache_key]);
        }
    }

    /**
     * Support meta description for front page
     */
    public static function add_front_page_description_support() {
        add_settings_field(
            'wdseo_home_meta_description',
            'Home Page Meta Description',
            array(__CLASS__, 'render_home_description_field'),
            'reading',
            'default'
        );
        
        add_settings_field(
            'wdseo_home_og_description',
            'Home Page OG Description',
            array(__CLASS__, 'render_home_og_description_field'),
            'reading',
            'default'
        );

        register_setting('reading', 'wdseo_home_meta_description', 'sanitize_text_field');
        register_setting('reading', 'wdseo_home_og_description', 'sanitize_text_field');
    }

    public static function render_home_description_field() {
        $desc = self::get_cached_option('wdseo_home_meta_description', '');
        echo '<textarea name="wdseo_home_meta_description" rows="3" style="width:100%;">' . esc_textarea($desc) . '</textarea>';
    }

    public static function render_home_og_description_field() {
        $desc = self::get_cached_option('wdseo_home_og_description', '');
        echo '<textarea name="wdseo_home_og_description" rows="3" style="width:100%;">' . esc_textarea($desc) . '</textarea>';
        echo '<p class="description">Leave blank to use same as standard description</p>';
    }

    /**
     * Output meta description tag with optimized database queries
     */
    public static function output_meta_description() {
        // Check if meta descriptions are enabled (cached)
        if (!self::get_cached_option('wdseo_enable_meta_description', 1)) {
            return;
        }

        $desc = '';
        $og_desc = '';

        if (is_singular()) {
            global $post;
            
            // Use cached meta retrieval
            $desc = self::get_cached_post_meta($post->ID, '_wdseo_meta_description');
            $og_desc = self::get_cached_post_meta($post->ID, '_wdseo_og_description');
            
            if (!$desc) {
                $desc = self::generate_description_from_content($post);
            }
        } elseif (is_tax('product_cat')) {
            $term_id = get_queried_object()->term_id;
            
            // Use cached term meta retrieval
            $desc = self::get_cached_term_meta($term_id, '_wdseo_term_description');
            $og_desc = self::get_cached_term_meta($term_id, '_wdseo_term_og_description');
        } elseif (is_front_page() || is_home()) {
            // Use cached option retrieval
            $desc = self::get_cached_option('wdseo_home_meta_description', '');
            $og_desc = self::get_cached_option('wdseo_home_og_description', '');
        }

        // Output standard meta description if exists (FIRST)
        if (!empty($desc)) {
            echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
        }
        
        // Output OG description if exists, otherwise use standard description
        $og_output = !empty($og_desc) ? $og_desc : $desc;
        if (!empty($og_output)) {
            echo '<meta property="og:description" content="' . esc_attr($og_output) . '" />' . "\n";
        }
    }

    /**
     * Generate description from title and content (optimized)
     */
    public static function generate_description_from_content($post) {
        // Cache key for generated descriptions
        $cache_key = $post->ID . '_generated_desc_' . md5($post->post_modified);
        
        if (isset(self::$meta_cache[$cache_key])) {
            return self::$meta_cache[$cache_key];
        }

        $title = get_the_title($post->ID);
        $content = strip_tags(get_the_content(null, false, $post));
        $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace
        $content = trim($content);

        // Start with title
        $desc = '';

        if (strlen($title) <= 60) {
            $desc = $title;
        } else {
            $desc = substr($title, 0, 57) . '...';
        }

        // Add content snippet without going over 160 chars
        $remaining_length = 160 - strlen($desc);
        if ($remaining_length > 20) {
            $desc .= '. ' . wp_trim_words($content, $remaining_length / 8, '...');
        }

        // Cache the generated description
        self::$meta_cache[$cache_key] = $desc;

        return $desc;
    }

    /**
     * Clear all caches (useful for debugging or when needed)
     */
    public static function clear_cache() {
        self::$meta_cache = array();
        self::$term_cache = array();
        self::$option_cache = array();
    }
}

add_action('plugins_loaded', array('Wdseo_Meta_Description', 'init'));