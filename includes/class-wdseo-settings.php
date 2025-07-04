<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Settings {
    private static $tabs = array(
        'general' => array(
            'title' => 'General',
            'icon' => '⚙️'
        ),
        'titles' => array(
            'title' => 'Titles & Meta',
            'icon' => '📝'
        ),
        'robots' => array(
            'title' => 'Robots Meta',
            'icon' => '🤖'
        ),
        'social' => array(
            'title' => 'Social Meta',
            'icon' => '📱'
        ),
        'sitemap' => array(
            'title' => 'XML Sitemap',
            'icon' => '🗺️'
        )
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

        // Enhanced CSS
        wp_enqueue_style('wdseo-admin-style', WDSEO_PLUGIN_URL . 'assets/css/admin-style.css', array(), WDSEO_VERSION);
        
        // Add custom admin JavaScript for enhanced interactions
        wp_enqueue_script('wdseo-admin-script', WDSEO_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), WDSEO_VERSION, true);
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
        // Register settings without creating sections (we'll handle display manually)
        
        // Title settings
        register_setting('wdseo_settings_group', 'wdseo_enable_meta_description', array(
            'type' => 'boolean',
            'default' => 1
        ));

        register_setting('wdseo_settings_group', 'wdseo_remove_site_name_from_title', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array(__CLASS__, 'sanitize_remove_site_name_from_title')
        ));

        // Robots settings
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

        register_setting('wdseo_settings_group', 'wdseo_robots_blocked_urls', array(
            'type' => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_textarea_input')
        ));

        // Social settings
        register_setting('wdseo_settings_group', 'wdseo_twitter_site_handle', array(
            'type' => 'string',
            'default' => '@WildDragonOfficial',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        // Standard sitemap settings
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

        // News sitemap settings
        register_setting('wdseo_settings_group', 'wdseo_news_sitemap_enabled', array(
            'type' => 'boolean',
            'default' => false,
        ));

        register_setting('wdseo_settings_group', 'wdseo_news_publication_name', array(
            'type' => 'string',
            'default' => get_bloginfo('name'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('wdseo_settings_group', 'wdseo_news_publication_language', array(
            'type' => 'string',
            'default' => 'en',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('wdseo_settings_group', 'wdseo_news_post_types', array(
            'type' => 'array',
            'default' => array('post'),
            'sanitize_callback' => array(__CLASS__, 'sanitize_post_types_array')
        ));

        // Video sitemap settings
        register_setting('wdseo_settings_group', 'wdseo_video_sitemap_enabled', array(
            'type' => 'boolean',
            'default' => false,
        ));
    }

    public static function render_settings_page() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap wdseo-settings">
            <h1>Wild Dragon SEO</h1>

            <nav class="nav-tab-wrapper">
                <?php foreach (self::$tabs as $slug => $tab_data): ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=wild-dragon-seo&tab=' . $slug)); ?>"
                       class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <span class="wdseo-feature-icon"><?php echo $tab_data['icon']; ?></span>
                        <?php echo esc_html($tab_data['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="options.php" method="post" class="wdseo-form">
                <?php
                settings_fields('wdseo_settings_group');
                
                // Only render the content for the current tab
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

                submit_button('Save Settings', 'primary', 'submit', false);
                ?>
            </form>
        </div>
        <?php
    }

    public static function render_sitemap_section() {
        echo '<div class="wdseo-section-header">🗺️ XML Sitemap Configuration</div>';
        
        // Standard Sitemap Settings
        echo '<h3 style="margin: 24px 32px 16px; color: var(--wdseo-gray-800); display: flex; align-items: center; gap: 8px;">
                <span class="wdseo-feature-icon">📄</span>
                Standard XML Sitemap
              </h3>';
        
        $content_types = array(
            'homepage' => array(
                'label' => 'Homepage',
                'default_freq' => 'daily',
                'default_priority' => '1.0',
                'icon' => '🏠'
            ),
            'posts' => array(
                'label' => 'Blog Posts',
                'default_freq' => 'weekly',
                'default_priority' => '0.8',
                'icon' => '📝'
            ),
            'pages' => array(
                'label' => 'Static Pages',
                'default_freq' => 'monthly',
                'default_priority' => '0.6',
                'icon' => '📄'
            ),
            'products' => array(
                'label' => 'Products',
                'default_freq' => 'daily',
                'default_priority' => '0.8',
                'icon' => '🛍️'
            ),
            'product_categories' => array(
                'label' => 'Product Categories',
                'default_freq' => 'weekly',
                'default_priority' => '0.7',
                'icon' => '📂'
            ),
            'post_categories' => array(
                'label' => 'Post Categories',
                'default_freq' => 'weekly',
                'default_priority' => '0.7',
                'icon' => '🏷️'
            )
        );

        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($content_types as $type => $info) {
            $include = get_option("wdseo_sitemap_{$type}_include", true);
            $frequency = get_option("wdseo_sitemap_{$type}_frequency", $info['default_freq']);
            $priority = get_option("wdseo_sitemap_{$type}_priority", $info['default_priority']);

            echo "<tr>
                    <th scope=\"row\">
                        <div style=\"display: flex; align-items: center; gap: 8px;\">
                            <span class=\"wdseo-feature-icon\">{$info['icon']}</span>
                            {$info['label']}
                        </div>
                    </th>
                    <td>
                        <fieldset>
                            <label style=\"margin-bottom: 16px;\">
                                <input type=\"checkbox\" name=\"wdseo_sitemap_{$type}_include\" value=\"1\" " . checked($include, true, false) . ">
                                <strong>Include in Sitemap</strong>
                            </label>
                            <br>
                            <div style=\"display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;\">
                                <label>
                                    <strong>Update Frequency:</strong><br>
                                    <select name=\"wdseo_sitemap_{$type}_frequency\" class=\"regular-text\" style=\"margin-top: 4px;\">";
            
            foreach (self::$frequencies as $value => $label) {
                echo "<option value=\"{$value}\"" . selected($frequency, $value, false) . ">{$label}</option>";
            }

            echo "</select>
                                </label>
                                <label>
                                    <strong>Priority:</strong><br>
                                    <select name=\"wdseo_sitemap_{$type}_priority\" class=\"regular-text\" style=\"margin-top: 4px;\">";
            
            for ($i = 0.0; $i <= 1.0; $i += 0.1) {
                $value = number_format($i, 1);
                echo "<option value=\"{$value}\"" . selected($priority, $value, false) . ">{$value}</option>";
            }

            echo "</select>
                                </label>
                            </div>
                        </fieldset>
                    </td>
                </tr>";
        }

        echo '</tbody></table>';

        // News Sitemap Settings
        echo '<h3 style="margin: 32px 32px 16px; color: var(--wdseo-gray-800); display: flex; align-items: center; gap: 8px;">
                <span class="wdseo-feature-icon">📰</span>
                Google News Sitemap
              </h3>';

        $news_enabled = get_option('wdseo_news_sitemap_enabled', false);
        $news_publication_name = get_option('wdseo_news_publication_name', get_bloginfo('name'));
        $news_language = get_option('wdseo_news_publication_language', 'en');
        $news_post_types = get_option('wdseo_news_post_types', array('post'));

        echo '<table class="form-table" role="presentation"><tbody>';
        
        echo '<tr>
                <th scope="row">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="wdseo-feature-icon">📰</span>
                        Enable News Sitemap
                    </div>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="wdseo_news_sitemap_enabled" value="1" ' . checked($news_enabled, true, false) . '>
                        <strong>Generate Google News Sitemap</strong>
                    </label>
                    <p class="description">Creates a specialized sitemap for news articles (last 48 hours). Requires Google News approval.</p>
                </td>
              </tr>';

        echo '<tr>
                <th scope="row">Publication Name</th>
                <td>
                    <input type="text" name="wdseo_news_publication_name" value="' . esc_attr($news_publication_name) . '" class="regular-text">
                    <p class="description">The name of your news publication as it appears in Google News.</p>
                </td>
              </tr>';

        echo '<tr>
                <th scope="row">Publication Language</th>
                <td>
                    <select name="wdseo_news_publication_language" class="regular-text">';
        
        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'hi' => 'Hindi'
        );

        foreach ($languages as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($news_language, $code, false) . '>' . esc_html($name) . '</option>';
        }

        echo '</select>
                    <p class="description">ISO 639 language code for your publication.</p>
                </td>
              </tr>';

        echo '<tr>
                <th scope="row">News Post Types</th>
                <td>
                    <fieldset>';

        $available_post_types = get_post_types(array('public' => true), 'objects');
        foreach ($available_post_types as $post_type) {
            $checked = in_array($post_type->name, $news_post_types);
            echo '<label style="display: block; margin-bottom: 8px;">
                    <input type="checkbox" name="wdseo_news_post_types[]" value="' . esc_attr($post_type->name) . '" ' . checked($checked, true, false) . '>
                    ' . esc_html($post_type->label) . '
                  </label>';
        }

        echo '      <p class="description">Select which post types should be included in the news sitemap.</p>
                    </fieldset>
                </td>
              </tr>';

        echo '</tbody></table>';

        // Video Sitemap Settings
        echo '<h3 style="margin: 32px 32px 16px; color: var(--wdseo-gray-800); display: flex; align-items: center; gap: 8px;">
                <span class="wdseo-feature-icon">🎥</span>
                Video Sitemap
              </h3>';

        $video_enabled = get_option('wdseo_video_sitemap_enabled', false);

        echo '<table class="form-table" role="presentation"><tbody>';
        
        echo '<tr>
                <th scope="row">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="wdseo-feature-icon">🎥</span>
                        Enable Video Sitemap
                    </div>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="wdseo_video_sitemap_enabled" value="1" ' . checked($video_enabled, true, false) . '>
                        <strong>Generate Video Sitemap</strong>
                    </label>
                    <p class="description">Creates a specialized sitemap for video content. Requires video metadata to be set on posts/pages.</p>
                </td>
              </tr>';

        echo '</tbody></table>';

        // Sitemap URLs
        echo '<h3 style="margin: 32px 32px 16px; color: var(--wdseo-gray-800); display: flex; align-items: center; gap: 8px;">
                <span class="wdseo-feature-icon">🔗</span>
                Sitemap URLs
              </h3>';

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>
                <th scope="row">Generated Sitemaps</th>
                <td>
                    <div style="display: grid; gap: 12px;">
                        <div style="padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px; border-left: 4px solid var(--wdseo-primary);">
                            <strong>📄 Main Sitemap Index:</strong><br>
                            <a href="' . esc_url(home_url('/sitemap.xml')) . '" target="_blank" style="color: var(--wdseo-primary);">' . esc_url(home_url('/sitemap.xml')) . '</a>
                        </div>';

        if ($news_enabled) {
            echo '<div style="padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px; border-left: 4px solid var(--wdseo-warning);">
                    <strong>📰 News Sitemap:</strong><br>
                    <a href="' . esc_url(home_url('/news-sitemap.xml')) . '" target="_blank" style="color: var(--wdseo-primary);">' . esc_url(home_url('/news-sitemap.xml')) . '</a>
                  </div>';
        }

        if ($video_enabled) {
            echo '<div style="padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px; border-left: 4px solid var(--wdseo-secondary);">
                    <strong>🎥 Video Sitemap:</strong><br>
                    <a href="' . esc_url(home_url('/video-sitemap.xml')) . '" target="_blank" style="color: var(--wdseo-primary);">' . esc_url(home_url('/video-sitemap.xml')) . '</a>
                  </div>';
        }

        echo '      </div>
                    <p class="description">Submit these URLs to Google Search Console for better indexing.</p>
                </td>
              </tr>';

        echo '</tbody></table>';
    }

    public static function render_general_section() {
        echo '<div class="wdseo-section-header">⚙️ General SEO Settings</div>';
        echo '<table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="wdseo-feature-icon">🐉</span>
                            Plugin Status
                        </div>
                    </th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="wdseo-status-indicator wdseo-status-enabled">✓ Active</span>
                            <p style="margin: 0; color: var(--wdseo-gray-600);">Wild Dragon SEO is running and optimizing your site.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="wdseo-feature-icon">📊</span>
                            Quick Stats
                        </div>
                    </th>
                    <td>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                            <div style="text-align: center; padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: var(--wdseo-primary);">' . wp_count_posts('post')->publish . '</div>
                                <div style="font-size: 12px; color: var(--wdseo-gray-600);">Published Posts</div>
                            </div>
                            <div style="text-align: center; padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: var(--wdseo-secondary);">' . wp_count_posts('page')->publish . '</div>
                                <div style="font-size: 12px; color: var(--wdseo-gray-600);">Published Pages</div>
                            </div>';
        
        if (class_exists('WooCommerce')) {
            echo '<div style="text-align: center; padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px;">
                    <div style="font-size: 24px; font-weight: bold; color: var(--wdseo-accent);">' . wp_count_posts('product')->publish . '</div>
                    <div style="font-size: 12px; color: var(--wdseo-gray-600);">Products</div>
                  </div>';
        }
        
        echo '      </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="wdseo-feature-icon">🔗</span>
                            Quick Links
                        </div>
                    </th>
                    <td>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <a href="' . esc_url(home_url('/sitemap.xml')) . '" target="_blank" class="button button-secondary">
                                🗺️ View XML Sitemap
                            </a>
                            <a href="' . esc_url(admin_url('options-general.php?page=wild-dragon-seo&tab=robots')) . '" class="button button-secondary">
                                🤖 Robots Settings
                            </a>
                            <a href="' . esc_url(admin_url('options-general.php?page=wild-dragon-seo&tab=social')) . '" class="button button-secondary">
                                📱 Social Meta
                            </a>
                        </div>
                    </td>
                </tr>
              </table>';
    }

    public static function render_titles_section() {
        echo '<div class="wdseo-section-header">📝 Title & Meta Description Settings</div>';
        
        // Define $checked FIRST before using it
        $checked = get_option('wdseo_remove_site_name_from_title', array());
        if (!is_array($checked)) {
            $checked = array(); // Fallback if data is not an array
        }
        
        $types = array(
            'post' => array('label' => 'Blog Posts', 'icon' => '📝'),
            'page' => array('label' => 'Static Pages', 'icon' => '📄'),
            'product' => array('label' => 'Products', 'icon' => '🛍️'),
            'product_cat' => array('label' => 'Product Categories', 'icon' => '📂'),
            'home' => array('label' => 'Home Page', 'icon' => '🏠'),
        );
        
        $enable_meta_desc = get_option('wdseo_enable_meta_description', 1);

        echo '<table class="form-table" role="presentation">';
        echo '<tr>
                <th scope="row">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="wdseo-feature-icon">🏷️</span>
                        Remove Site Name From Title
                    </div>
                </th>
                <td>
                    <fieldset>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">';

        foreach ($types as $key => $type_data) {
            echo "<label style=\"margin-bottom: 8px;\">
                    <input type=\"checkbox\" name=\"wdseo_remove_site_name_from_title[]\" value=\"$key\" " . 
                    checked(in_array($key, $checked), true, false) . ">
                    <span class=\"wdseo-feature-icon\" style=\"margin-right: 6px;\">{$type_data['icon']}</span>
                    {$type_data['label']}
                  </label>";
        }

        echo '      </div>
                        <p class="description">Choose which page types should have the site name removed from their titles.</p>
                    </fieldset>
                </td>
              </tr>';
        
        // Keep the meta description toggle
        echo '<tr>
                <th scope="row">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="wdseo-feature-icon">📄</span>
                        Meta Descriptions
                    </div>
                </th>
                <td>
                    <label style="margin-bottom: 0;">
                        <input type="checkbox" name="wdseo_enable_meta_description" value="1" ' . 
                        checked($enable_meta_desc, 1, false) . '>
                        <strong>Enable auto-generated meta descriptions</strong>
                    </label>
                    <p class="description">Automatically generate meta descriptions for content without custom descriptions.</p>
                </td>
              </tr>';
        
        echo '</table>';
    }

    public static function render_robots_section() {
        echo '<div class="wdseo-section-header">🤖 Robots Meta & Indexing Control</div>';
        
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
            
            $icon = '📝';
            if ($post_type === 'page') $icon = '📄';
            if ($post_type === 'product') $icon = '🛍️';
            if ($post_type === 'attachment') $icon = '📎';

            echo "<tr>
                    <th scope=\"row\">
                        <div style=\"display: flex; align-items: center; gap: 8px;\">
                            <span class=\"wdseo-feature-icon\">{$icon}</span>
                            {$obj->label}
                        </div>
                    </th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$post_type}", $value);
            echo "</td></tr>";
        }

        // Taxonomies
        foreach ($taxonomies as $taxonomy) {
            $obj = get_taxonomy($taxonomy);
            $value = get_option("wdseo_default_robots_{$taxonomy}", 'index,follow');
            
            $icon = '🏷️';
            if ($taxonomy === 'product_cat') $icon = '📂';

            echo "<tr>
                    <th scope=\"row\">
                        <div style=\"display: flex; align-items: center; gap: 8px;\">
                            <span class=\"wdseo-feature-icon\">{$icon}</span>
                            {$obj->label}
                        </div>
                    </th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$taxonomy}", $value);
            echo "</td></tr>";
        }

        // Special pages
        foreach ($special_pages as $key => $label) {
            $value = get_option("wdseo_default_robots_{$key}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">
                        <div style=\"display: flex; align-items: center; gap: 8px;\">
                            <span class=\"wdseo-feature-icon\">👤</span>
                            {$label}
                        </div>
                    </th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$key}", $value);
            echo "</td></tr>";
        }

        // Blocked URLs
        echo "<tr>
                <th scope=\"row\">
                    <div style=\"display: flex; align-items: center; gap: 8px;\">
                        <span class=\"wdseo-feature-icon\">🚫</span>
                        Block Specific URLs
                    </div>
                </th>
                <td>
                    <textarea name=\"wdseo_robots_blocked_urls\" rows=\"8\" class=\"large-text code\" placeholder=\"Enter one URL pattern per line...&#10;Example:&#10;/private/*&#10;/admin/*&#10;/checkout\">" . 
                        esc_textarea(get_option('wdseo_robots_blocked_urls', '')) . 
                    "</textarea>
                    <p class=\"description\">Enter one URL pattern per line. Use * as wildcard. These URLs will be marked as noindex,nofollow.</p>
                </td>
              </tr>";

        echo '</table>';
    }

    public static function render_social_section() {
        echo '<div class="wdseo-section-header">📱 Social Media & Open Graph Settings</div>';
        
        $handle = get_option('wdseo_twitter_site_handle', '@WildDragonOfficial');

        echo '<table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="wdseo-feature-icon">🐦</span>
                            Twitter Site Handle
                        </div>
                    </th>
                    <td>
                        <input type="text" name="wdseo_twitter_site_handle" value="' . esc_attr($handle) . '" class="regular-text" placeholder="@YourTwitterHandle">
                        <p class="description">Used in Twitter Card meta tags for better social sharing.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class=\"wdseo-feature-icon\">📊</span>
                            Social Features
                        </div>
                    </th>
                    <td>
                        <div style="display: grid; gap: 12px;">
                            <div style="padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px; border-left: 4px solid var(--wdseo-success);">
                                <strong>✓ Open Graph Tags</strong><br>
                                <small style="color: var(--wdseo-gray-600);">Automatically generated for better Facebook sharing</small>
                            </div>
                            <div style="padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px; border-left: 4px solid var(--wdseo-success);">
                                <strong>✓ Twitter Cards</strong><br>
                                <small style="color: var(--wdseo-gray-600);">Enhanced Twitter sharing with rich media</small>
                            </div>
                            <div style="padding: 16px; background: var(--wdseo-gray-50); border-radius: 8px; border-left: 4px solid var(--wdseo-success);">
                                <strong>✓ Schema Markup</strong><br>
                                <small style="color: var(--wdseo-gray-600);">Structured data for better search results</small>
                            </div>
                        </div>
                    </td>
                </tr>
              </table>';
    }

    public static function render_robots_select($name, $value) {
        $options = array(
            'index,follow' => array('label' => 'Index, Follow', 'status' => 'success'),
            'noindex,nofollow' => array('label' => 'Noindex, Nofollow', 'status' => 'danger'),
            'index,nofollow' => array('label' => 'Index, Nofollow', 'status' => 'warning'),
            'noindex,follow' => array('label' => 'Noindex, Follow', 'status' => 'warning'),
        );

        echo '<select name="' . esc_attr($name) . '" style="min-width: 200px;">';

        foreach ($options as $val => $option) {
            $selected = selected($value, $val, false);
            $indicator = '';
            if ($option['status'] === 'success') $indicator = '✓ ';
            if ($option['status'] === 'danger') $indicator = '✗ ';
            if ($option['status'] === 'warning') $indicator = '⚠ ';
            
            echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . $indicator . esc_html($option['label']) . '</option>';
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

    public static function sanitize_post_types_array($input) {
        if (!is_array($input)) {
            return array();
        }

        $available_post_types = get_post_types(array('public' => true));
        $sanitized = array();

        foreach ($input as $post_type) {
            if (in_array($post_type, $available_post_types)) {
                $sanitized[] = sanitize_key($post_type);
            }
        }

        return $sanitized;
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