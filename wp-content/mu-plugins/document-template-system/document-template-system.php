<?php
/**
 * Plugin Name: Document Template System
 * Description: Multi-tenant document generation with organization-scoped templates
 * Version: 1.0.0
 * Author: SIO Events Team
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DTS_PATH', __DIR__);
define('DTS_URL', plugin_dir_url(__FILE__));
define('DTS_VERSION', '1.0.0');

// Load dependencies
require_once DTS_PATH . '/includes/cpt-organization.php';
require_once DTS_PATH . '/includes/cpt-document-template.php';
require_once DTS_PATH . '/includes/access-control.php';
require_once DTS_PATH . '/includes/template-generator.php';
require_once DTS_PATH . '/includes/acf-fields.php';

/**
 * Configure ACF JSON save path for field group portability
 */
add_filter('acf/settings/save_json', function ($path) {
    return DTS_PATH . '/acf-json';
});

/**
 * Configure ACF JSON load paths
 */
add_filter('acf/settings/load_json', function ($paths) {
    // Remove original path
    unset($paths[0]);

    // Add our custom path
    $paths[] = DTS_PATH . '/acf-json';

    return $paths;
});

/**
 * Activation hook - flush rewrite rules
 */
register_activation_hook(__FILE__, 'dts_activate');
function dts_activate() {
    // Register post types
    require_once DTS_PATH . '/includes/cpt-organization.php';
    require_once DTS_PATH . '/includes/cpt-document-template.php';

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation hook - cleanup
 */
register_deactivation_hook(__FILE__, 'dts_deactivate');
function dts_deactivate() {
    flush_rewrite_rules();
}
