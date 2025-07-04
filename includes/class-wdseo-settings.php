<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Settings {
    private static $tabs = array(
        'general' => 'General',
        'titles' => 'Titles & Meta',
        'robots' => 'Robots Meta',
        'social' => 'Social Meta',
        'sitemap' => 'XML Sitemap'
    );

    private static $frequencies = array(
        'always' => 'Always',
        'hourly' => 'Hourly',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'never' => 'Never'
    );

    // Cache for settings
    private static $settings_cache = array();

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        // Only load admin assets on plugin pages
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        
        // Hook into title generation with higher priority
        add_filter('document_title_parts', array(__CLASS__, 'remove_site_name_from_title_parts'), 10, 1);
        add_filter('wp_title', array(__CLASS__, 'remove_site_name_from_wp_title'), 10, 2);
    }

    public static function enqueue_admin_assets($hook) {
        if ('settings_page_wild-dragon-seo' !== $hook) return;

        // Minified CSS
        wp_enqueue_style('wdseo-admin-style', WDSEO_PLUGIN_URL . 'assets/css/admin-style.min.css', array(), WDSEO_VERSION);
    }

    // Get setting with caching
    public static function get_setting($key, $default = '') {
        if (!isset(self::$settings_cache[$key])) {
            self::$settings_cache[$key] = get_option($key, $default);
        }
        return self::$settings_cache[$key];
    }

    public static function add_settings_page() {
        add_options_page(
            'Wild Dragon SEO Settings',
            'Wild Dragon SEO',
            'manage_options',
            'wild-dragon-seo',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function register_settings() {
    // Add this ▼
    register_setting('wdseo_settings_group', 'wdseo_enable_meta_description', array(
        'type' => 'boolean',
        'default' => 1 // Enabled by default
    ));

        // Batch register settings for post types and taxonomies
        $post_types = get_post_types(array('public' => true));
        $taxonomies = get_taxonomies(array('public' => true));
        $special_pages = array('author_archives', 'user_profiles');

        $items_to_register = array_merge($post_types, $taxonomies, $special_pages);

        foreach ($items_to_register as $item) {
            register_setting('wdseo_settings_group', "wdseo_default_robots_{$item}", array(
                'type' => 'string',
                'default' => 'index,follow',
            ));
        }
 
 
 // Add this inside the register_settings() method
register_setting('wdseo_settings_group', 'wdseo_remove_site_name_from_title', array(
    'type' => 'array', // Stores multiple checkbox values
    'default' => array(), // Default empty array
    'sanitize_callback' => array(__CLASS__, 'sanitize_remove_site_name_from_title') // Optional sanitization
));



        // Register blocked URLs with validation
        register_setting('wdseo_settings_group', 'wdseo_robots_blocked_urls', array(
            'type' => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_textarea_input')
        ));

        // Register social meta with validation
        register_setting('wdseo_settings_group', 'wdseo_twitter_site_handle', array(
            'type' => 'string',
            'default' => '@WildDragonOfficial',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        // Batch register sitemap settings
        $content_types = array(
            'homepage' => array('freq' => 'daily', 'priority' => '1.0'),
            'posts' => array('freq' => 'weekly', 'priority' => '0.8'),
            'pages' => array('freq' => 'monthly', 'priority' => '0.6'),
            'products' => array('freq' => 'daily', 'priority' => '0.8'),
            'product_categories' => array('freq' => 'weekly', 'priority' => '0.7'),
            'post_categories' => array('freq' => 'weekly', 'priority' => '0.7')
        );

        foreach ($content_types as $type => $defaults) {
            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_include", array(
                'type' => 'boolean',
                'default' => true
            ));
            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_frequency", array(
                'type' => 'string',
                'default' => $defaults['freq']
            ));
            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_priority", array(
                'type' => 'float',
                'default' => $defaults['priority']
            ));
        }
    }

    public static function render_settings_page() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap wdseo-settings">
            <h1>Wild Dragon SEO Settings</h1>

            <nav class="nav-tab-wrapper">
                <?php foreach (self::$tabs as $slug => $title): ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=wild-dragon-seo&tab=' . $slug)); ?>"
                       class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($title); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="options.php" method="post" class="wdseo-form">
                <?php
                settings_fields('wdseo_settings_group');
                do_settings_sections('wild-dragon-seo');

                switch ($tab):
                    case 'titles':
                        self::render_titles_section();
                        break;
                    case 'robots':
                        self::render_robots_section();
                        break;
                    case 'social':
                        self::render_social_section();
                        break;
                    case 'sitemap':
                        self::render_sitemap_section();
                        break;
                    default:
                        self::render_general_section();
                endswitch;

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function render_sitemap_section() {
        $content_types = array(
            'homepage' => array(
                'label' => 'Homepage',
                'default_freq' => 'daily',
                'default_priority' => '1.0'
            ),
            'posts' => array(
                'label' => 'Posts',
                'default_freq' => 'weekly',
                'default_priority' => '0.8'
            ),
            'pages' => array(
                'label' => 'Pages',
                'default_freq' => 'monthly',
                'default_priority' => '0.6'
            ),
            'products' => array(
                'label' => 'Products',
                'default_freq' => 'daily',
                'default_priority' => '0.8'
            ),
            'product_categories' => array(
                'label' => 'Product Categories',
                'default_freq' => 'weekly',
                'default_priority' => '0.7'
            ),
            'post_categories' => array(
                'label' => 'Post Categories',
                'default_freq' => 'weekly',
                'default_priority' => '0.7'
            )
        );

        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($content_types as $type => $info) {
            $include = get_option("wdseo_sitemap_{$type}_include", true);
            $frequency = get_option("wdseo_sitemap_{$type}_frequency", $info['default_freq']);
            $priority = get_option("wdseo_sitemap_{$type}_priority", $info['default_priority']);

            echo "<tr>
                    <th scope=\"row\"><label>{$info['label']}</label></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type=\"checkbox\" name=\"wdseo_sitemap_{$type}_include\" value=\"1\" " . checked($include, true, false) . ">
                                Include in Sitemap
                            </label>
                            <br><br>
                            <label>
                                Update Frequency:
                                <select name=\"wdseo_sitemap_{$type}_frequency\" class=\"regular-text\">";
            
            foreach (self::$frequencies as $value => $label) {
                echo "<option value=\"{$value}\"" . selected($frequency, $value, false) . ">{$label}</option>";
            }

            echo "</select>
                            </label>
                            <br><br>
                            <label>
                                Priority:
                                <select name=\"wdseo_sitemap_{$type}_priority\" class=\"regular-text\">";
            
            for ($i = 0.0; $i <= 1.0; $i += 0.1) {
                $value = number_format($i, 1);
                echo "<option value=\"{$value}\"" . selected($priority, $value, false) . ">{$value}</option>";
            }

            echo "</select>
                            </label>
                        </fieldset>
                    </td>
                </tr>";
        }

        echo '</tbody></table>';
    }

    public static function render_general_section() {
        echo '<table class="form-table" role="presentation">
                <tr>
                    <th scope="row">General Settings</th>
                    <td>
                        <p>Configure general SEO settings here.</p>
                    </td>
                
                </tr>
              </table>';
    }

    
    
    
    public static function render_titles_section() {
    // Define $checked FIRST before using it
    // With this (to handle default values):
$checked = get_option('wdseo_remove_site_name_from_title', array());
if (!is_array($checked)) {
    $checked = array(); // Fallback if data is not an array
}
    
    $types = array(
        'post' => 'Posts',
        'page' => 'Pages',
        'product' => 'Products',
        'product_cat' => 'Product Categories',
        'home' => 'Home Page',
    );
    
    $enable_meta_desc = get_option('wdseo_enable_meta_description', 1);

    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row">Remove Site Name From Title On:</th><td>';

    foreach ($types as $key => $label) {
    echo "<label>
            <input type=\"checkbox\" name=\"wdseo_remove_site_name_from_title[]\" value=\"$key\" " . 
            checked(in_array($key, $checked), true, false) . ">
            $label
          </label><br>";
}

    echo '</td></tr>';
    
    // Keep the meta description toggle ▼
    echo '<tr>
            <th scope="row">Meta Descriptions</th>
            <td>
                <label>
                    <input type="checkbox" name="wdseo_enable_meta_description" value="1" ' . 
                    checked($enable_meta_desc, 1, false) . '>
                    Enable auto-generated meta descriptions
                </label>
                <p class="description">Uncheck to disable plugin\'s meta descriptions (use theme defaults)</p>
            </td>
          </tr>';
    
    echo '</table>';
}
    
    
    
    
    

    public static function render_robots_section() {
        $post_types = get_post_types(array('public' => true));
        $taxonomies = get_taxonomies(array('public' => true));
        $special_pages = array(
            'author_archives' => 'Author Archives',
            'user_profiles' => 'User Profile Pages'
        );

        echo '<table class="form-table" role="presentation">';
        
        // Post types
        foreach ($post_types as $post_type) {
            $obj = get_post_type_object($post_type);
            $value = get_option("wdseo_default_robots_{$post_type}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">Robots Meta - {$obj->label}</th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$post_type}", $value);
            echo "</td></tr>";
        }

        // Taxonomies
        foreach ($taxonomies as $taxonomy) {
            $obj = get_taxonomy($taxonomy);
            $value = get_option("wdseo_default_robots_{$taxonomy}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">Robots Meta - {$obj->label}</th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$taxonomy}", $value);
            echo "</td></tr>";
        }

        // Special pages
        foreach ($special_pages as $key => $label) {
            $value = get_option("wdseo_default_robots_{$key}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">Robots Meta - {$key}</th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$key}", $value);
            echo "</td></tr>";
        }

        // Blocked URLs
        echo "<tr>
                <th scope=\"row\">Block Specific URLs</th>
                <td>
                    <textarea name=\"wdseo_robots_blocked_urls\" rows=\"10\" class=\"large-text code\">" . 
                        esc_textarea(get_option('wdseo_robots_blocked_urls', '')) . 
                    "</textarea>
                    <p class=\"description\">Enter one URL pattern per line. Use * as wildcard.</p>
                </td>
              </tr>";

        echo '</table>';
    }

    public static function render_social_section() {
        $handle = get_option('wdseo_twitter_site_handle', '@WildDragonOfficial');

        echo '<table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Twitter Site Handle</th>
                    <td>
                        <input type="text" name="wdseo_twitter_site_handle" value="' . esc_attr($handle) . '" class="regular-text">
                        <p class="description">Used in Twitter Card meta tags.</p>
                    </td>
                </tr>
              </table>';
    }

    public static function render_robots_select($name, $value) {
        $options = array(
            'index,follow' => 'Index, Follow',
            'noindex,nofollow' => 'Noindex, Nofollow',
            'index,nofollow' => 'Index, Nofollow',
            'noindex,follow' => 'Noindex, Follow',
        );

        echo '<select name="' . esc_attr($name) . '">';

        foreach ($options as $val => $label) {
            $selected = selected($value, $val, false);
            echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
    }

    public static function render_sitemap_checkbox($args) {
        $type = $args['type_key'];
        $checked = get_option("wdseo_include_{$type}_sitemap", true);
        echo "<input type=\"checkbox\" name=\"wdseo_include_{$type}_sitemap\" id=\"wdseo_include_{$type}_sitemap\" value=\"1\" " . checked($checked, true, false) . " />";
    }
    
    


    public static function sanitize_textarea_input($input) {
        $lines = explode("\n", $input);
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
     * Remove site name from document title parts (WordPress 4.4+)
     */
    public static function remove_site_name_from_title_parts($title_parts) {
        $remove_from = get_option('wdseo_remove_site_name_from_title', array());
        
        if (empty($remove_from) || !is_array($remove_from)) {
            return $title_parts;
        }

        $current_type = self::get_current_page_type();
        
        if ($current_type && in_array($current_type, $remove_from)) {
            // Remove the site name from title parts
            if (isset($title_parts['site'])) {
                unset($title_parts['site']);
            }
        }

        return $title_parts;
    }

    /**
     * Remove site name from wp_title (fallback for older themes)
     */
    public static function remove_site_name_from_wp_title($title, $sep) {
        $remove_from = get_option('wdseo_remove_site_name_from_title', array());
        
        if (empty($remove_from) || !is_array($remove_from)) {
            return $title;
        }

        $current_type = self::get_current_page_type();
        
        if ($current_type && in_array($current_type, $remove_from)) {
            $site_name = get_bloginfo('name');
            
            // Common separators
            $separators = array(
                ' ' . $sep . ' ',
                ' – ',
                ' - ',
                ' | ',
                ' :: ',
                ' > ',
                ' < '
            );
            
            foreach ($separators as $separator) {
                $pattern = preg_quote($separator . $site_name, '/');
                if (preg_match('/' . $pattern . '$/i', $title)) {
                    return preg_replace('/' . $pattern . '$/i', '', $title);
                }
            }
            
            // Fallback: remove just the site name if found at the end
            $pattern = preg_quote($site_name, '/');
            if (preg_match('/' . $pattern . '$/i', $title)) {
                return trim(preg_replace('/' . $pattern . '$/i', '', $title));
            }
        }

        return $title;
    }

    /**
     * Get current page type for title removal
     */
    private static function get_current_page_type() {
        if (is_front_page() || is_home()) {
            return 'home';
        } elseif (is_singular('post')) {
            return 'post';
        } elseif (is_singular('page')) {
            return 'page';
        } elseif (is_singular('product')) {
            return 'product';
        } elseif (is_tax('product_cat') || is_category()) {
            return 'product_cat';
        }
        
        return false;
    }

    /**
     * Sanitize the "Remove Site Name From Title" checkboxes
     */
    public static function sanitize_remove_site_name_from_title($input) {
        $allowed_types = array('post', 'page', 'product', 'product_cat', 'home');
        $sanitized = array();

        if (is_array($input)) {
            foreach ($input as $value) {
                if (in_array($value, $allowed_types)) {
                    $sanitized[] = sanitize_key($value);
                }
            }
        }

        return $sanitized;
    }
} // <-- End of class Wdseo_Settings

add_action('plugins_loaded', array('Wdseo_Settings', 'init'));