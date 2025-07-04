<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Video Meta Management for Video Sitemaps
 * Handles video metadata for posts/pages to support video sitemaps
 */
class Wdseo_Video_Meta {
    
    public static function init() {
        // Add meta boxes for video information
        add_action('add_meta_boxes', array(__CLASS__, 'add_video_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_video_meta'), 10, 2);
        
        // Add video fields to product edit screen (WooCommerce)
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_product_options_general_product_data', array(__CLASS__, 'add_product_video_fields'));
            add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_video_meta'));
        }
    }

    /**
     * Add video meta boxes to posts, pages, and products
     */
    public static function add_video_meta_boxes() {
        $post_types = array('post', 'page', 'product');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'wdseo_video_meta',
                'Video Information (for Video Sitemap)',
                array(__CLASS__, 'render_video_meta_box'),
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render video meta box
     */
    public static function render_video_meta_box($post) {
        wp_nonce_field('wdseo_save_video_meta', 'wdseo_video_meta_nonce');
        
        $video_url = get_post_meta($post->ID, '_wdseo_video_url', true);
        $video_thumbnail = get_post_meta($post->ID, '_wdseo_video_thumbnail', true);
        $video_title = get_post_meta($post->ID, '_wdseo_video_title', true);
        $video_description = get_post_meta($post->ID, '_wdseo_video_description', true);
        $video_duration = get_post_meta($post->ID, '_wdseo_video_duration', true);
        $video_publication_date = get_post_meta($post->ID, '_wdseo_video_publication_date', true);

        echo '<div class="wdseo-video-meta-box" style="display: grid; gap: 16px;">';
        
        echo '<p style="background: #e7f3ff; padding: 12px; border-radius: 4px; margin: 0; border-left: 4px solid #0073aa;">
                <strong>📹 Video Sitemap Information</strong><br>
                Fill out these fields if this content contains a video that should be included in your video sitemap.
              </p>';

        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">';
        
        // Video URL
        echo '<div>
                <label for="wdseo_video_url"><strong>Video URL</strong></label><br>
                <input type="url" id="wdseo_video_url" name="wdseo_video_url" value="' . esc_attr($video_url) . '" 
                       style="width: 100%; margin-top: 4px;" placeholder="https://example.com/video.mp4">
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">Direct URL to the video file (.mp4, .mov, etc.)</p>
              </div>';

        // Video Thumbnail
        echo '<div>
                <label for="wdseo_video_thumbnail"><strong>Video Thumbnail URL</strong></label><br>
                <input type="url" id="wdseo_video_thumbnail" name="wdseo_video_thumbnail" value="' . esc_attr($video_thumbnail) . '" 
                       style="width: 100%; margin-top: 4px;" placeholder="https://example.com/thumbnail.jpg">
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">High-quality thumbnail image (JPG, PNG)</p>
              </div>';

        echo '</div>';

        // Video Title
        echo '<div>
                <label for="wdseo_video_title"><strong>Video Title</strong></label><br>
                <input type="text" id="wdseo_video_title" name="wdseo_video_title" value="' . esc_attr($video_title) . '" 
                       style="width: 100%; margin-top: 4px;" placeholder="Leave blank to use post title">
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">Title of the video (leave blank to use post title)</p>
              </div>';

        // Video Description
        echo '<div>
                <label for="wdseo_video_description"><strong>Video Description</strong></label><br>
                <textarea id="wdseo_video_description" name="wdseo_video_description" rows="3" 
                          style="width: 100%; margin-top: 4px;" placeholder="Brief description of the video content">' . esc_textarea($video_description) . '</textarea>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">Brief description (max 2,048 characters)</p>
              </div>';

        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">';

        // Video Duration
        echo '<div>
                <label for="wdseo_video_duration"><strong>Duration (seconds)</strong></label><br>
                <input type="number" id="wdseo_video_duration" name="wdseo_video_duration" value="' . esc_attr($video_duration) . '" 
                       style="width: 100%; margin-top: 4px;" min="1" max="28800" placeholder="635">
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">Video duration in seconds (1-28800)</p>
              </div>';

        // Publication Date
        echo '<div>
                <label for="wdseo_video_publication_date"><strong>Publication Date</strong></label><br>
                <input type="datetime-local" id="wdseo_video_publication_date" name="wdseo_video_publication_date" 
                       value="' . esc_attr($video_publication_date) . '" style="width: 100%; margin-top: 4px;">
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">When the video was published (leave blank for post date)</p>
              </div>';

        echo '</div>';
        echo '</div>';
    }

    /**
     * Save video meta data
     */
    public static function save_video_meta($post_id, $post) {
        if (!isset($_POST['wdseo_video_meta_nonce']) || !wp_verify_nonce($_POST['wdseo_video_meta_nonce'], 'wdseo_save_video_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $video_fields = array(
            '_wdseo_video_url' => 'esc_url_raw',
            '_wdseo_video_thumbnail' => 'esc_url_raw',
            '_wdseo_video_title' => 'sanitize_text_field',
            '_wdseo_video_description' => 'sanitize_textarea_field',
            '_wdseo_video_duration' => 'absint',
            '_wdseo_video_publication_date' => 'sanitize_text_field',
        );

        foreach ($video_fields as $field => $sanitize_function) {
            $form_field = str_replace('_wdseo_', 'wdseo_', $field);
            
            if (isset($_POST[$form_field])) {
                $value = call_user_func($sanitize_function, $_POST[$form_field]);
                
                // Special validation for duration
                if ($field === '_wdseo_video_duration' && $value > 28800) {
                    $value = 28800; // Max 8 hours
                }
                
                update_post_meta($post_id, $field, $value);
            }
        }
    }

    /**
     * Add video fields to WooCommerce product data
     */
    public static function add_product_video_fields() {
        echo '<div class="options_group">';
        
        echo '<h4 style="padding: 12px; margin: 0; background: #f0f0f1; border-left: 4px solid #0073aa;">📹 Video Information</h4>';

        woocommerce_wp_text_input(array(
            'id' => '_wdseo_video_url',
            'label' => 'Video URL',
            'placeholder' => 'https://example.com/video.mp4',
            'description' => 'Direct URL to the product video file',
            'type' => 'url',
        ));

        woocommerce_wp_text_input(array(
            'id' => '_wdseo_video_thumbnail',
            'label' => 'Video Thumbnail URL',
            'placeholder' => 'https://example.com/thumbnail.jpg',
            'description' => 'High-quality thumbnail image for the video',
            'type' => 'url',
        ));

        woocommerce_wp_text_input(array(
            'id' => '_wdseo_video_title',
            'label' => 'Video Title',
            'placeholder' => 'Leave blank to use product title',
            'description' => 'Title of the video (optional)',
        ));

        woocommerce_wp_textarea_input(array(
            'id' => '_wdseo_video_description',
            'label' => 'Video Description',
            'placeholder' => 'Brief description of the video content',
            'description' => 'Brief description of the video (max 2,048 characters)',
            'rows' => 3,
        ));

        woocommerce_wp_text_input(array(
            'id' => '_wdseo_video_duration',
            'label' => 'Duration (seconds)',
            'placeholder' => '635',
            'description' => 'Video duration in seconds (1-28800)',
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '1',
                'max' => '28800',
            ),
        ));

        echo '</div>';
    }

    /**
     * Save WooCommerce product video meta
     */
    public static function save_product_video_meta($post_id) {
        $video_fields = array(
            '_wdseo_video_url' => 'esc_url_raw',
            '_wdseo_video_thumbnail' => 'esc_url_raw',
            '_wdseo_video_title' => 'sanitize_text_field',
            '_wdseo_video_description' => 'sanitize_textarea_field',
            '_wdseo_video_duration' => 'absint',
        );

        foreach ($video_fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_function, $_POST[$field]);
                
                // Special validation for duration
                if ($field === '_wdseo_video_duration' && $value > 28800) {
                    $value = 28800; // Max 8 hours
                }
                
                update_post_meta($post_id, $field, $value);
            }
        }
    }
}

// Initialize the video meta functionality
add_action('plugins_loaded', array('Wdseo_Video_Meta', 'init'));