<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wdseo_Rules_Engine
 *
 * A modular system for loading SEO rule files from the /rules/ directory.
 */
class Wdseo_Rules_Engine {

    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'load_rules'), 11);
    }

    /**
     * Load all rule files in the /rules/ directory
     */
    public static function load_rules() {
        $rule_files = glob(WDSEO_PLUGIN_DIR . 'rules/*.php');

        if ($rule_files && is_array($rule_files)) {
            foreach ($rule_files as $file) {
                require_once $file;
            }
        }
    }
}

Wdseo_Rules_Engine::init();