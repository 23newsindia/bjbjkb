<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Sitemap {
    // Cache for sitemap data
    private static $cache = array();
    private static $news_cache = array();

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
        ));
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
                xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
                xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
                xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
    <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <title>XML Sitemap - Wild Dragon SEO</title>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <style type="text/css">
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; 
                        color: #374151; 
                        background: #f9fafb;
                        margin: 0;
                        padding: 20px;
                    }
                    .container { 
                        max-width: 1200px; 
                        margin: 0 auto; 
                        background: white;
                        border-radius: 12px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                        overflow: hidden;
                    }
                    .header {
                        background: linear-gradient(135deg, #6366f1, #10b981);
                        color: white;
                        padding: 24px 32px;
                        text-align: center;
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 28px;
                        font-weight: 700;
                    }
                    .header p {
                        margin: 8px 0 0 0;
                        opacity: 0.9;
                    }
                    .stats {
                        display: flex;
                        justify-content: center;
                        gap: 32px;
                        padding: 20px 32px;
                        background: #f8f9fa;
                        border-bottom: 1px solid #e5e7eb;
                    }
                    .stat {
                        text-align: center;
                    }
                    .stat-number {
                        font-size: 24px;
                        font-weight: 700;
                        color: #6366f1;
                    }
                    .stat-label {
                        font-size: 12px;
                        color: #6b7280;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    }
                    #sitemap__table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin: 0;
                    }
                    #sitemap__table tr:hover { 
                        background: #f8f9fa; 
                    }
                    #sitemap__table th { 
                        background: #f3f4f6; 
                        padding: 16px 24px; 
                        text-align: left; 
                        font-weight: 600;
                        color: #374151;
                        border-bottom: 2px solid #e5e7eb;
                    }
                    #sitemap__table td { 
                        padding: 16px 24px; 
                        border-bottom: 1px solid #f3f4f6; 
                        vertical-align: top;
                    }
                    .loc { 
                        word-break: break-all; 
                        color: #6366f1;
                        text-decoration: none;
                    }
                    .loc:hover {
                        text-decoration: underline;
                    }
                    .lastmod { 
                        width: 180px; 
                        color: #6b7280;
                        font-size: 14px;
                    }
                    .priority {
                        width: 100px;
                        text-align: center;
                    }
                    .images {
                        width: 80px;
                        text-align: center;
                        color: #10b981;
                        font-weight: 600;
                    }
                    .news-info {
                        font-size: 12px;
                        color: #6b7280;
                        margin-top: 4px;
                    }
                    .badge {
                        display: inline-block;
                        padding: 2px 8px;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                    }
                    .badge-news {
                        background: #fef3c7;
                        color: #92400e;
                    }
                    .badge-video {
                        background: #ddd6fe;
                        color: #5b21b6;
                    }
                    .footer {
                        padding: 24px 32px;
                        text-align: center;
                        background: #f8f9fa;
                        color: #6b7280;
                        font-size: 14px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🐉 XML Sitemap</h1>
                        <p>Generated by Wild Dragon SEO Plugin</p>
                    </div>
                    
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-number"><xsl:value-of select="count(//sitemap:url)"/></div>
                            <div class="stat-label">Total URLs</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><xsl:value-of select="count(//image:image)"/></div>
                            <div class="stat-label">Images</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><xsl:value-of select="count(//news:news)"/></div>
                            <div class="stat-label">News Articles</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><xsl:value-of select="count(//video:video)"/></div>
                            <div class="stat-label">Videos</div>
                        </div>
                    </div>
                    
                    <xsl:choose>
                        <xsl:when test="//sitemap:url">
                            <table id="sitemap__table">
                                <tr>
                                    <th>URL</th>
                                    <th>Type</th>
                                    <th>Images</th>
                                    <th>Last Modified</th>
                                    <th>Priority</th>
                                </tr>
                                <xsl:for-each select="//sitemap:url">
                                    <tr>
                                        <td>
                                            <a href="{sitemap:loc}" class="loc">
                                                <xsl:value-of select="sitemap:loc"/>
                                            </a>
                                            <xsl:if test="news:news">
                                                <div class="news-info">
                                                    <span class="badge badge-news">News</span>
                                                    <xsl:value-of select="news:news/news:title"/>
                                                </div>
                                            </xsl:if>
                                            <xsl:if test="video:video">
                                                <div class="news-info">
                                                    <span class="badge badge-video">Video</span>
                                                    <xsl:value-of select="video:video/video:title"/>
                                                </div>
                                            </xsl:if>
                                        </td>
                                        <td>
                                            <xsl:choose>
                                                <xsl:when test="news:news">📰 News</xsl:when>
                                                <xsl:when test="video:video">🎥 Video</xsl:when>
                                                <xsl:when test="count(image:image) > 0">🖼️ Content</xsl:when>
                                                <xsl:otherwise>📄 Page</xsl:otherwise>
                                            </xsl:choose>
                                        </td>
                                        <td class="images">
                                            <xsl:value-of select="count(image:image)"/>
                                        </td>
                                        <td class="lastmod">
                                            <xsl:value-of select="sitemap:lastmod"/>
                                        </td>
                                        <td class="priority">
                                            <xsl:value-of select="sitemap:priority"/>
                                        </td>
                                    </tr>
                                </xsl:for-each>
                            </table>
                        </xsl:when>
                        <xsl:otherwise>
                            <table id="sitemap__table">
                                <tr>
                                    <th>Sitemap</th>
                                    <th>Type</th>
                                    <th>Last Modified</th>
                                </tr>
                                <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                                    <tr>
                                        <td>
                                            <a href="{sitemap:loc}" class="loc">
                                                <xsl:value-of select="sitemap:loc"/>
                                            </a>
                                        </td>
                                        <td>
                                            <xsl:choose>
                                                <xsl:when test="contains(sitemap:loc, 'news')">📰 News Sitemap</xsl:when>
                                                <xsl:when test="contains(sitemap:loc, 'video')">🎥 Video Sitemap</xsl:when>
                                                <xsl:when test="contains(sitemap:loc, 'product')">🛍️ Product Sitemap</xsl:when>
                                                <xsl:when test="contains(sitemap:loc, 'post')">📝 Post Sitemap</xsl:when>
                                                <xsl:when test="contains(sitemap:loc, 'page')">📄 Page Sitemap</xsl:when>
                                                <xsl:otherwise>🗺️ Standard Sitemap</xsl:otherwise>
                                            </xsl:choose>
                                        </td>
                                        <td class="lastmod">
                                            <xsl:value-of select="sitemap:lastmod"/>
                                        </td>
                                    </tr>
                                </xsl:for-each>
                            </table>
                        </xsl:otherwise>
                    </xsl:choose>
                    
                    <div class="footer">
                        Generated on <xsl:value-of select="current-dateTime()"/> by Wild Dragon SEO Plugin
                    </div>
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
            'news' => array(
                'label' => 'News Articles',
                'callback' => array(__CLASS__, 'generate_news_sitemap'),
            ),
            'video' => array(
                'label' => 'Video Content',
                'callback' => array(__CLASS__, 'generate_video_sitemap'),
            ),
        );
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?wdseo_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?wdseo_sitemap=$matches[1]', 'top');
        add_rewrite_rule('^sitemap\.xsl$', 'index.php?wdseo_sitemap=xsl', 'top');
        add_rewrite_rule('^news-sitemap\.xml$', 'index.php?wdseo_sitemap=news', 'top');
        add_rewrite_rule('^video-sitemap\.xml$', 'index.php?wdseo_sitemap=video', 'top');

        add_rewrite_tag('%wdseo_sitemap%', '([^&]+)');
    }

    public static function render_sitemap() {
        $type = get_query_var('wdseo_sitemap');

        if (!$type) return;

        if ($type === 'xsl') {
            header('Content-Type: text/xsl; charset=utf-8');
            echo self::get_sitemap_xsl();
            exit;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');

        if ($type === 'index') {
            echo self::generate_index();
        } else {
            $types = self::get_supported_types();

            if (isset($types[$type])) {
                // Check if this type should be included
                if ($type === 'news' && !get_option('wdseo_news_sitemap_enabled', false)) {
                    status_header(404);
                    exit;
                }
                
                if ($type !== 'news' && $type !== 'video' && !get_option("wdseo_include_{$type}_sitemap", true)) {
                    status_header(404);
                    exit;
                }

                $callback = $types[$type]['callback'];
                echo call_user_func($callback, $type);
            }
        }

        exit;
    }

    public static function generate_index() {
        $cache_key = 'wdseo_sitemap_index';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        $types = self::get_supported_types();

        foreach ($types as $key => $info) {
            // Special handling for news and video sitemaps
            if ($key === 'news') {
                if (!get_option('wdseo_news_sitemap_enabled', false)) {
                    continue;
                }
                $url = rtrim(home_url("/news-sitemap.xml"), '/');
            } elseif ($key === 'video') {
                if (!get_option('wdseo_video_sitemap_enabled', false)) {
                    continue;
                }
                $url = rtrim(home_url("/video-sitemap.xml"), '/');
            } else {
                $include = get_option("wdseo_include_{$key}_sitemap", true);
                if (!$include) {
                    continue;
                }
                $url = rtrim(home_url("/sitemap-{$key}.xml"), '/');
            }

            $lastmod = date('c');
            $output .= "<sitemap>\n";
            $output .= "  <loc>" . esc_url($url) . "</loc>\n";
            $output .= "  <lastmod>{$lastmod}</lastmod>\n";
            $output .= "</sitemap>\n";
        }

        $output .= "</sitemapindex>";

        // Cache for 1 hour
        set_transient($cache_key, $output, HOUR_IN_SECONDS);

        return $output;
    }

    /**
     * Generate Google News Sitemap - FIXED syntax error
     * Following Google News sitemap specifications
     */
    public static function generate_news_sitemap($type = 'news') {
        if (!get_option('wdseo_news_sitemap_enabled', false)) {
            return '';
        }

        $cache_key = 'wdseo_news_sitemap';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        // Get publication settings - integrate with your schema system
        $publication_name = get_option('wdseo_news_publication_name', get_option('wild_dragon_organization_name', get_bloginfo('name')));
        $publication_language = get_option('wdseo_news_publication_language', 'en');
        $news_post_types = get_option('wdseo_news_post_types', array('post'));

        // Get posts from last 48 hours (Google News requirement)
        $query_args = array(
            'post_type' => $news_post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1000, // Google News allows up to 1000 URLs
            'date_query' => array(
                array(
                    'after' => '48 hours ago',
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($query_args);

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n";
        $output .= "        xmlns:news=\"http://www.google.com/schemas/sitemap-news/0.9\"\n";
        $output .= "        xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        while ($query->have_posts()) {
            $query->the_post();
            
            // Skip if robots meta is noindex
            $robots = get_post_meta(get_the_ID(), '_wdseo_robots_directive', true);
            if ($robots === 'noindex,follow' || $robots === 'noindex,nofollow') {
                continue;
            }

            $permalink = get_permalink();
            $title = get_the_title();
            $publication_date = get_the_date('c'); // W3C format

            $output .= "<url>\n";
            $output .= "  <loc>" . esc_url($permalink) . "</loc>\n";
            $output .= "  <news:news>\n";
            $output .= "    <news:publication>\n";
            $output .= "      <news:name>" . esc_xml($publication_name) . "</news:name>\n";
            $output .= "      <news:language>" . esc_xml($publication_language) . "</news:language>\n";
            $output .= "    </news:publication>\n";
            $output .= "    <news:publication_date>" . esc_xml($publication_date) . "</news:publication_date>\n";
            $output .= "    <news:title>" . esc_xml($title) . "</news:title>\n";
            $output .= "  </news:news>\n";

            // Add featured image if available
            $thumbnail_id = get_post_thumbnail_id();
            if ($thumbnail_id) {
                $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                if ($image_url) {
                    $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                    $output .= "  <image:image>\n";
                    $output .= "    <image:loc>" . esc_url($image_url) . "</image:loc>\n";
                    $output .= "    <image:title>" . esc_xml($title) . "</image:title>\n";
                    if ($alt_text) {
                        $output .= "    <image:caption>" . esc_xml($alt_text) . "</image:caption>\n";
                    }
                    $output .= "  </image:image>\n";
                }
            }

            $output .= "</url>\n";
        }

        wp_reset_postdata();

        $output .= "</urlset>";

        // Cache for 30 minutes (news changes frequently)
        set_transient($cache_key, $output, 30 * MINUTE_IN_SECONDS);

        return $output;
    }

    /**
     * Generate Video Sitemap
     * Following Google Video sitemap specifications
     */
    public static function generate_video_sitemap($type = 'video') {
        if (!get_option('wdseo_video_sitemap_enabled', false)) {
            return '';
        }

        $cache_key = 'wdseo_video_sitemap';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        // Get posts/pages with video content
        $query_args = array(
            'post_type' => array('post', 'page', 'product'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wdseo_video_url',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => '_wdseo_video_embed',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $query = new WP_Query($query_args);

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url(home_url('/sitemap.xsl')) . "\"?>\n";
        $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n";
        $output .= "        xmlns:video=\"http://www.google.com/schemas/sitemap-video/1.1\"\n";
        $output .= "        xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        while ($query->have_posts()) {
            $query->the_post();
            
            // Skip if robots meta is noindex
            $robots = get_post_meta(get_the_ID(), '_wdseo_robots_directive', true);
            if ($robots === 'noindex,follow' || $robots === 'noindex,nofollow') {
                continue;
            }

            $video_url = get_post_meta(get_the_ID(), '_wdseo_video_url', true);
            $video_thumbnail = get_post_meta(get_the_ID(), '_wdseo_video_thumbnail', true);
            $video_title = get_post_meta(get_the_ID(), '_wdseo_video_title', true) ?: get_the_title();
            $video_description = get_post_meta(get_the_ID(), '_wdseo_video_description', true) ?: get_the_excerpt();
            $video_duration = get_post_meta(get_the_ID(), '_wdseo_video_duration', true);
            $video_publication_date = get_post_meta(get_the_ID(), '_wdseo_video_publication_date', true) ?: get_the_date('c');

            if (!$video_url || !$video_thumbnail) {
                continue; // Skip if essential video data is missing
            }

            $permalink = get_permalink();

            $output .= "<url>\n";
            $output .= "  <loc>" . esc_url($permalink) . "</loc>\n";
            $output .= "  <video:video>\n";
            $output .= "    <video:thumbnail_loc>" . esc_url($video_thumbnail) . "</video:thumbnail_loc>\n";
            $output .= "    <video:title>" . esc_xml($video_title) . "</video:title>\n";
            $output .= "    <video:description>" . esc_xml($video_description) . "</video:description>\n";
            $output .= "    <video:content_loc>" . esc_url($video_url) . "</video:content_loc>\n";
            
            if ($video_duration) {
                $output .= "    <video:duration>" . esc_xml($video_duration) . "</video:duration>\n";
            }
            
            $output .= "    <video:publication_date>" . esc_xml($video_publication_date) . "</video:publication_date>\n";
            $output .= "    <video:family_friendly>yes</video:family_friendly>\n";
            $output .= "  </video:video>\n";
            $output .= "</url>\n";
        }

        wp_reset_postdata();

        $output .= "</urlset>";

        // Cache for 1 hour
        set_transient($cache_key, $output, HOUR_IN_SECONDS);

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

        $cache_key = "wdseo_sitemap_{$post_type}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
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
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
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

            // Add images if it's a product or post with featured image
            if ($post_type === 'product' || has_post_thumbnail()) {
                // Featured image
                $thumbnail_id = get_post_thumbnail_id();
                if ($thumbnail_id) {
                    $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                    if ($image_url) {
                        $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                        $output .= "  <image:image>\n";
                        $output .= "    <image:loc>" . esc_url($image_url) . "</image:loc>\n";
                        $output .= "    <image:title>" . esc_xml(get_the_title()) . "</image:title>\n";
                        if ($alt_text) {
                            $output .= "    <image:caption>" . esc_xml($alt_text) . "</image:caption>\n";
                        }
                        $output .= "  </image:image>\n";
                    }
                }

                // Product gallery for WooCommerce products
                if ($post_type === 'product') {
                    $gallery_ids = get_post_meta(get_the_ID(), '_product_image_gallery', true);
                    if ($gallery_ids) {
                        $gallery_ids = explode(',', $gallery_ids);
                        foreach (array_slice($gallery_ids, 0, 5) as $gallery_id) { // Limit to 5 images
                            $image_url = wp_get_attachment_image_url($gallery_id, 'full');
                            if ($image_url) {
                                $alt_text = get_post_meta($gallery_id, '_wp_attachment_image_alt', true);
                                $output .= "  <image:image>\n";
                                $output .= "    <image:loc>" . esc_url($image_url) . "</image:loc>\n";
                                $output .= "    <image:title>" . esc_xml(get_the_title()) . "</image:title>\n";
                                if ($alt_text) {
                                    $output .= "    <image:caption>" . esc_xml($alt_text) . "</image:caption>\n";
                                }
                                $output .= "  </image:image>\n";
                            }
                        }
                    }
                }
            }

            $output .= "</url>\n";
        }

        wp_reset_postdata();

        $output .= "</urlset>";

        // Cache for 6 hours
        set_transient($cache_key, $output, 6 * HOUR_IN_SECONDS);

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

        $cache_key = "wdseo_sitemap_tax_{$type}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
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
            'number' => 0,
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
                    if ($term->description) {
                        $output .= "    <image:caption>" . esc_xml($term->description) . "</image:caption>\n";
                    }
                    $output .= "  </image:image>\n";
                }
            }

            $output .= "</url>\n";
        }

        $output .= "</urlset>";

        // Cache for 12 hours
        set_transient($cache_key, $output, 12 * HOUR_IN_SECONDS);

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
        $output .= "  <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $output .= "  <changefreq>" . esc_html($frequency) . "</changefreq>\n";
        $output .= "  <priority>" . esc_html($priority) . "</priority>\n";

        // Add site logo if available - integrate with your schema system
        $logo_url = get_option('wild_dragon_logo_url', '');
        if (empty($logo_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }
        
        if ($logo_url) {
            $output .= "  <image:image>\n";
            $output .= "    <image:loc>" . esc_url($logo_url) . "</image:loc>\n";
            $output .= "    <image:title>" . esc_xml(get_bloginfo('name')) . "</image:title>\n";
            $output .= "    <image:caption>" . esc_xml(get_bloginfo('description')) . "</image:caption>\n";
            $output .= "  </image:image>\n";
        }

        $output .= "</url>\n";
        $output .= "</urlset>";
        return $output;
    }

    public static function clear_sitemap_cache($post_id, $post) {
        // Clear all sitemap caches
        $cache_keys = array(
            'wdseo_sitemap_index',
            'wdseo_news_sitemap',
            'wdseo_video_sitemap',
            'wdseo_sitemap_post',
            'wdseo_sitemap_page',
            'wdseo_sitemap_product',
            'wdseo_sitemap_tax_product_cat',
            'wdseo_sitemap_tax_category',
        );

        foreach ($cache_keys as $key) {
            delete_transient($key);
        }

        // Don't flush rewrite rules on every save - too expensive
        // flush_rewrite_rules();
    }
}

add_action('plugins_loaded', array('Wdseo_Sitemap', 'init'));