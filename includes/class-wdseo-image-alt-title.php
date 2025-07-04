<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Image_Alt_Title {
    private static $product_cache = array();
    private static $alt_cache = array();

    public static function init() {
        if (is_admin()) {
            add_action('woocommerce_process_product_meta', array(__CLASS__, 'auto_set_image_alt_and_title'), 10, 2);
        }
        add_filter('wp_get_attachment_image_attributes', array(__CLASS__, 'set_missing_image_attributes'), 10, 2);
    }

    public static function get_cached_product($product_id) {
        if (!isset(self::$product_cache[$product_id])) {
            self::$product_cache[$product_id] = wc_get_product($product_id);
        }
        return self::$product_cache[$product_id];
    }

    public static function get_cached_alt($attachment_id) {
        if (!isset(self::$alt_cache[$attachment_id])) {
            self::$alt_cache[$attachment_id] = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        }
        return self::$alt_cache[$attachment_id];
    }

    public static function set_missing_image_attributes($attr, $attachment) {
        if (isset($attr['alt']) && !empty($attr['alt'])) {
            return $attr;
        }

        $attachment_id = is_numeric($attachment) ? $attachment : $attachment->ID;
        $parent_id = get_post($attachment_id)->post_parent;

        if (!$parent_id || 'product' !== get_post_type($parent_id)) {
            return $attr;
        }

        $product = self::get_cached_product($parent_id);
        if ($product) {
            $product_name = $product->get_name();
            
            if (!isset($attr['alt']) || empty($attr['alt'])) {
                $attr['alt'] = $product_name;
            }
            
            if (!isset($attr['title']) || empty($attr['title'])) {
                $attr['title'] = $product_name;
            }
        }

        return $attr;
    }

    /**
     * Set alt and title attributes during save
     */
    public function auto_set_image_attributes($post_id) {
        $attachment_id = $post_id;

        // Skip if it's not an attachment
        if ('attachment' !== get_post_type($attachment_id)) {
            return;
        }

        $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $attachment = get_post($attachment_id);

        if (!$image_alt && $attachment && $attachment->post_parent && 'product' === get_post_type($attachment->post_parent)) {
            $product = wc_get_product($attachment->post_parent);
            if ($product) {
                $product_name = $product->get_name();

                // Update image alt
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $product_name);
            }
        }

        // Set title if empty
        if ($attachment && empty($attachment->post_title)) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_title' => $product_name ?? 'Product Image',
            ));
        }
    }

    /**
     * Handle meta processing for product edit screen save
     */
    public function auto_set_image_alt_and_title($post_id, $post) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $this->auto_set_image_attributes($thumbnail_id);
        }

        $attachment_ids = wc_get_product($post_id)->get_gallery_image_ids();
        foreach ($attachment_ids as $attachment_id) {
            $this->auto_set_image_attributes($attachment_id);
        }
    }
}