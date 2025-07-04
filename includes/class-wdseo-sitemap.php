<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Sitemap {

    public static function init() {
        // Disable WordPress default sitemaps with multiple methods
        add_filter('wp_sitemaps_enabled', '__return_false', 1);
        add_action('init', array(__CLASS__, 'disable_wp_sitemaps'), 1);
        
        add_action('init', array(__CLASS__, 'register_rewrite_rules'));
        add_action('template_redirect', array(__CLASS__, 'render_sitemap'));
        add_action('save_post', array(__CLASS__, 'clear_sitemap_cache'), 10, 2);
        add_filter('redirect_canonical', array(__CLASS__, 'prevent_sitemap_trailing_slash'), 10, 2);

        // Add admin settings only in admin context
        if (is_admin()) {
            add_action('admin_init', array(__CLASS__, 'register_settings'));
        }
    }

    /**
     * Additional method to disable WordPress sitemaps
     */
    public static function disable_wp_sitemaps() {
        // Remove the sitemap server initialization
        remove_action('init', 'wp_sitemaps_get_server');
        
        // Remove sitemap rewrite rules
        global $wp_rewrite;
        if ($wp_rewrite) {
            remove_filter('rewrite_rules_array', 'wp_sitemaps_add_rewrite_rules');
        }
    }

    // New method for admin settings registration
    public static function register_settings() {
        add_settings_section(
            'wdseo_sitemap_section',
            'XML Sitemap Settings',
            null,
            'wild-dragon-seo'
        );

        $types = self::get_supported_types();

        foreach ($types as $type => $info) {
            add_settings_field(
                "wdseo_include_{$type}_sitemap",
                "Include {$info['label']} in Sitemap",
                array('Wdseo_Settings', 'render_sitemap_checkbox'),
                'wild-dragon-seo',
                'wdseo_sitemap_section',
                array('type_key' => $type)
            );

            register_setting('wdseo_settings_group', "wdseo_include_{$type}_sitemap", array(
                'type' => 'boolean',
                'default' => true,
            ));

            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_frequency", array(
                'type' => 'string',
                'default' => 'weekly',
            ));

            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_priority", array(
                'type' => 'string',
                'default' => '0.9',
            ));
        }
    }

    public static function prevent_sitemap_trailing_slash($redirect_url, $requested_url) {
        if (preg_match('/sitemap(-[a-z0-9_-]+)?\.xml$/', $requested_url)) {
            return false;
        }
        return $redirect_url;
    }

    private static function get_sitemap_xsl() {
        return '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" 
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
                xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <title>XML Sitemap</title>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <style type="text/css">
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; color: #444; }
                    #sitemap { max-width: 980px; margin: 0 auto; }
                    #sitemap__table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    #sitemap__table tr:hover { background: #f6f6f6; }
                    #sitemap__table th { background: #f8f9fa; padding: 12px; text-align: left; }
                    #sitemap__table td { padding: 12px; border-bottom: 1px solid #eee; }
                    .loc { word-break: break-all; }
                    .lastmod { width: 200px; }
                </style>
            </head>
            <body>
                <div id="sitemap">
                    <h1>XML Sitemap</h1>
                    <xsl:choose>
                        <xsl:when test="//sitemap:url">
                            <table id="sitemap__table">
                                <tr>
                                    <th>URL</th>
                                    <th>Images</th>
                                    <th>Last Modified</th>
                                    <th>Priority</th>
                                </tr>
                                <xsl:for-each select="//sitemap:url">
                                    <tr>
                                        <td class="loc"><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td>
                                        <td><xsl:value-of select="count(image:image)"/></td>
                                        <td class="lastmod"><xsl:value-of select="sitemap:lastmod"/></td>
                                        <td><xsl:value-of select="sitemap:priority"/></td>
                                    </tr>
                                </xsl:for-each>
                            </table>
                        </xsl:when>
                        <xsl:otherwise>
                            <table id="sitemap__table">
                                <tr>
                                    <th>Sitemap</th>
                                    <th>Last Modified</th>
                                </tr>
                                <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                                    <tr>
                                        <td class="loc"><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td>
                                        <td class="lastmod"><xsl:value-of select="sitemap:lastmod"/></td>
                                    </tr>
                                </xsl:for-each>
                            </table>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>';
    }

    public static function get_supported_types() {
        return array(
            'post' => array(
                'label' => 'Posts',
                'callback' => array(__CLASS__, 'generate_post_sitemap'),
            ),
            'page' => array(
                'label' => 'Pages',
                'callback' => array(__CLASS__, 'generate_page_sitemap'),
            ),
            'product' => array(
                'label' => 'Products',
                'callback' => array(__CLASS__, 'generate_product_sitemap'),
            ),
            'product_cat' => array(
                'label' => 'Product Categories',
                'callback' => array(__CLASS__, 'generate_taxonomy_sitemap'),
            ),
            'category' => array(
                'label' => 'Post Categories',
                'callback' => array(__CLASS__, 'generate_taxonomy_sitemap'),
            ),
            'homepage' => array(
                'label' => 'Homepage',
                'callback' => array(__CLASS__, 'generate_homepage_sitemap'),
            ),
        );
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?wdseo_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?wdseo_sitemap=$matches[1]', 'top');
        add_rewrite_rule('^sitemap\.xsl$', 'index.php?wdseo_sitemap=xsl', 'top');

        add_rewrite_tag('%wdseo_sitemap%', '([^&]+)');
    }

    public static function render_sitemap() {
        $type = get_query_var('wdseo_sitemap');

        if (!$type) return;

        if ($type === 'xsl') {
            header('Content-Type: text/xsl');
            echo self::get_sitemap_xsl();
            exit;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=utf-8');

        if ($type === 'index') {
            echo self::generate_index();
        } else {
            $types = self::get_supported_types();

            if (isset($types[$type]) && get_option("wdseo_include_{$type}_sitemap", true)) {
                $callback = $types[$type]['callback'];
                echo call_user_func($callback, $type);
            }
        }

        exit;
    }

    public static function generate_index() {
        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        $types = self::get_supported_types();

        foreach ($types as $key => $info) {
            $include = get_option("wdseo_include_{$key}_sitemap", true);

            if ($include) {
                $url = rtrim(home_url("/sitemap-{$key}.xml"), '/');
                $lastmod = date('c');
                $output .= "<sitemap>\n";
                $output .= "  <loc>" . esc_url($url) . "</loc>\n";
                $output .= "  <lastmod>{$lastmod}</lastmod>\n";
                $output .= "</sitemap>\n";
            }
        }

        $output .= "</sitemapindex>";

        return $output;
    }

    public static function generate_generic_sitemap($post_type, $label = '') {
        if (!post_type_exists($post_type)) {
            return '';
        }

        // Check if this post type should be included
        if (!get_option("wdseo_include_{$post_type}_sitemap", true)) {
            return '';
        }

        // Get sitemap settings for this post type
        $frequency = get_option("wdseo_sitemap_{$post_type}_frequency", 'weekly');
        $priority = get_option("wdseo_sitemap_{$post_type}_priority", '0.9');

        // Ensure frequency has a value
        if (empty($frequency)) {
            $frequency = 'weekly';
        }

        // Ensure priority has a value and is properly formatted
        if (empty($priority) || !is_numeric($priority)) {
            $priority = '0.9';
        }

        $query_args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'modified',
        );

        // Exclude system pages and private content
        if ($post_type === 'page') {
            $excluded_slugs = apply_filters('wdseo_excluded_pages_from_sitemap', array(
                'checkout',
                'cart',
                'my-account',
                'wishlist',
                'order-received',
                'order-pay',
                'lost-password',
                'view-order',
                'add-payment-method'
            ));

            $query_args['post__not_in'] = array();

            foreach ($excluded_slugs as $slug) {
                $page = get_page_by_path($slug);
                if ($page) {
                    $query_args['post__not_in'][] = $page->ID;
                }
            }
        }

        $query = new WP_Query($query_args);

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        while ($query->have_posts()) {
            $query->the_post();
            
            // Skip if robots meta is noindex
            $robots = get_post_meta(get_the_ID(), '_wdseo_robots_directive', true);
            if ($robots === 'noindex,follow' || $robots === 'noindex,nofollow') {
                continue;
            }

            $permalink = get_permalink();
            $modified = get_the_modified_time('c');
            $modified_date = mysql2date('Y-m-d', $modified);

            $output .= "<url>\n";
            $output .= "  <loc>" . esc_url($permalink) . "</loc>\n";
            $output .= "  <lastmod>{$modified_date}</lastmod>\n";
            $output .= "  <changefreq>" . esc_html($frequency) . "</changefreq>\n";
            $output .= "  <priority>" . esc_html($priority) . "</priority>\n";

            // Add images if it's a product
            if ($post_type === 'product') {
                // Featured image
                $thumbnail_id = get_post_thumbnail_id();
                if ($thumbnail_id) {
                    $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                    if ($image_url) {
                        $output .= "  <image:image>\n";
                        $output .= "    <image:loc>" . esc_url($image_url) . "</image:loc>\n";
                        $output .= "    <image:title>" . esc_xml(get_the_title()) . "</image:title>\n";
                        $output .= "    <image:caption>" . esc_xml(get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)) . "</image:caption>\n";
                        $output .= "  </image:image>\n";
                    }
                }

                // Product gallery
                $gallery_ids = get_post_meta(get_the_ID(), '_product_image_gallery', true);
                if ($gallery_ids) {
                    $gallery_ids = explode(',', $gallery_ids);
                    foreach ($gallery_ids as $gallery_id) {
                        $image_url = wp_get_attachment_image_url($gallery_id, 'full');
                        if ($image_url) {
                            $output .= "  <image:image>\n";
                            $output .= "    <image:loc>" . esc_url($image_url) . "</image:loc>\n";
                            $output .= "    <image:title>" . esc_xml(get_the_title()) . "</image:title>\n";
                            $output .= "    <image:caption>" . esc_xml(get_post_meta($gallery_id, '_wp_attachment_image_alt', true)) . "</image:caption>\n";
                            $output .= "  </image:image>\n";
                        }
                    }
                }
            }

            $output .= "</url>\n";
        }

        wp_reset_postdata();

        $output .= "</urlset>";
        return $output;
    }

    public static function generate_post_sitemap($type = 'post') {
        return self::generate_generic_sitemap('post');
    }

    public static function generate_page_sitemap($type = 'page') {
        return self::generate_generic_sitemap('page');
    }

    public static function generate_product_sitemap($type = 'product') {
        return self::generate_generic_sitemap('product');
    }

    public static function generate_taxonomy_sitemap($type = 'product_cat') {
        if (!get_option("wdseo_include_{$type}_sitemap", true)) {
            return '';
        }

        // Get sitemap settings for this taxonomy
        $frequency = get_option("wdseo_sitemap_{$type}_frequency", 'weekly');
        $priority = get_option("wdseo_sitemap_{$type}_priority", '0.9');

        // Ensure frequency has a value
        if (empty($frequency)) {
            $frequency = 'weekly';
        }

        // Ensure priority has a value and is properly formatted
        if (empty($priority) || !is_numeric($priority)) {
            $priority = '0.9';
        }

        $terms = get_terms(array(
            'taxonomy' => $type,
            'hide_empty' => true,
        ));

        if (is_wp_error($terms)) {
            return '';
        }

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        foreach ($terms as $term) {
            // Skip if robots meta is noindex
            $robots = get_term_meta($term->term_id, '_wdseo_term_robots_directive', true);
            if ($robots === 'noindex,follow' || $robots === 'noindex,nofollow') {
                continue;
            }

            $url = get_term_link($term);
            if (is_wp_error($url)) {
                continue;
            }

            $output .= "<url>\n";
            $output .= "  <loc>" . esc_url($url) . "</loc>\n";
            $output .= "  <changefreq>" . esc_html($frequency) . "</changefreq>\n";
            $output .= "  <priority>" . esc_html($priority) . "</priority>\n";

            // Add category image if available
            $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                if ($image_url) {
                    $output .= "  <image:image>\n";
                    $output .= "    <image:loc>" . esc_url($image_url) . "</image:loc>\n";
                    $output .= "    <image:title>" . esc_xml($term->name) . "</image:title>\n";
                    $output .= "    <image:caption>" . esc_xml($term->description) . "</image:caption>\n";
                    $output .= "  </image:image>\n";
                }
            }

            $output .= "</url>\n";
        }

        $output .= "</urlset>";
        return $output;
    }

    public static function generate_homepage_sitemap() {
        if (!get_option('wdseo_include_homepage_sitemap', true)) {
            return '';
        }

        // Get homepage sitemap settings
        $frequency = get_option('wdseo_sitemap_homepage_frequency', 'daily');
        $priority = get_option('wdseo_sitemap_homepage_priority', '1.0');

        // Ensure frequency has a value
        if (empty($frequency)) {
            $frequency = 'daily';
        }

        // Ensure priority has a value and is properly formatted
        if (empty($priority) || !is_numeric($priority)) {
            $priority = '1.0';
        }

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";
        $output .= "<url>\n";
        $output .= "  <loc>" . esc_url(home_url('/')) . "</loc>\n";
        $output .= "  <changefreq>" . esc_html($frequency) . "</changefreq>\n";
        $output .= "  <priority>" . esc_html($priority) . "</priority>\n";

        // Add site logo if available
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $output .= "  <image:image>\n";
                $output .= "    <image:loc>" . esc_url($logo_url) . "</image:loc>\n";
                $output .= "    <image:title>" . esc_xml(get_bloginfo('name')) . "</image:title>\n";
                $output .= "  </image:image>\n";
            }
        }

        $output .= "</url>\n";
        $output .= "</urlset>";
        return $output;
    }

    public static function clear_sitemap_cache($post_id, $post) {
        flush_rewrite_rules();
    }
}

add_action('plugins_loaded', array('Wdseo_Sitemap', 'init'));