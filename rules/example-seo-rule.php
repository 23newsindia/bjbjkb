<?php
if (!defined('ABSPATH')) {
    exit;
}

// Example: Inject a canonical URL tag into <head>
add_action('wp_head', function () {
    if (is_singular()) {
        global $post;
        echo '<link rel="canonical" href="' . esc_url(get_permalink($post->ID)) . '" />' . "\n";
    }
}, 2);

