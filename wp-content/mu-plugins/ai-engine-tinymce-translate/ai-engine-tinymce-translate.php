<?php
/*
Plugin Name: AI Engine TinyMCE Translate
Description: Adds AI Engine translate post functionality to TinyMCE editor
Version: 1.0.0
Author: Custom
*/

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Constants
define('AIET_VERSION', '1.0.3');
define('AIET_PATH', __DIR__);
define('AIET_URL', WPMU_PLUGIN_URL . '/ai-engine-tinymce-translate');

/**
 * Check if AI Engine and Magic Wand module are available
 *
 * @return bool
 */
function aiet_check_dependencies() {
    // Check if AI Engine is active
    if (!class_exists('Meow_MWAI_Core')) {
        return false;
    }

    // Check if Magic Wand module is enabled
    global $mwai_core;
    if (!isset($mwai_core) || !$mwai_core->get_option('module_suggestions')) {
        return false;
    }

    return true;
}

/**
 * Display admin notice if dependencies are not met
 */
function aiet_dependency_notice() {
    if (!aiet_check_dependencies()) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('AI Engine TinyMCE Translate:', 'ai-engine-tinymce-translate'); ?></strong>
                <?php _e('This plugin requires AI Engine with the Magic Wand module enabled.', 'ai-engine-tinymce-translate'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'aiet_dependency_notice');

/**
 * Enqueue scripts for the TinyMCE editor
 */
function aiet_enqueue_scripts() {
    // Get current screen
    $current_screen = get_current_screen();

    // Only load on post editor screens
    if (!$current_screen || !in_array($current_screen->base, ['post'])) {
        return;
    }

    // Check dependencies
    if (!aiet_check_dependencies()) {
        return;
    }

    // Check if we're using the classic editor (not block editor)
    if (method_exists($current_screen, 'is_block_editor') && $current_screen->is_block_editor()) {
        return;
    }

    // Get current post ID
    $post_id = get_the_ID();
    if (!$post_id) {
        global $post;
        $post_id = isset($post->ID) ? $post->ID : 0;
    }

    // Enqueue inline script with configuration
    // This needs to be added before the editor is initialized
    wp_add_inline_script('editor', sprintf(
        'window.AIET_Config = %s;',
        wp_json_encode([
            'restUrl' => rest_url('mwai/v1/ai/magic_wand'),
            'nonce' => wp_create_nonce('wp_rest'),
            'postId' => $post_id,
            'i18n' => [
                'buttonTitle' => __('Translate Post to Site Language', 'ai-engine-tinymce-translate'),
                'buttonLabel' => __('Translate', 'ai-engine-tinymce-translate'),
                'processing' => __('Translating title and content...', 'ai-engine-tinymce-translate'),
                'success' => __('Translation completed successfully!', 'ai-engine-tinymce-translate'),
                'errorGeneric' => __('Translation failed. Please try again.', 'ai-engine-tinymce-translate'),
                'errorNoContent' => __('No title or content to translate. Please add text first.', 'ai-engine-tinymce-translate'),
                'errorAiEngine' => __('AI Engine translation service is not available.', 'ai-engine-tinymce-translate'),
            ]
        ])
    ), 'before');
}
add_action('admin_print_scripts', 'aiet_enqueue_scripts');

/**
 * Register custom TinyMCE button
 *
 * @param array $buttons Array of TinyMCE buttons
 * @param string $editor_id Editor ID
 * @return array Modified buttons array
 */
function aiet_register_button($buttons, $editor_id) {
    // Only add to the main content editor
    if ($editor_id === 'content' && aiet_check_dependencies()) {
        $buttons[] = 'aiet_translate';
    }
    return $buttons;
}
add_filter('mce_buttons', 'aiet_register_button', 10, 2);

/**
 * Register custom TinyMCE plugin
 *
 * @param array $plugin_array Array of TinyMCE plugins
 * @return array Modified plugins array
 */
function aiet_register_plugin($plugin_array) {
    if (aiet_check_dependencies()) {
        $plugin_array['aiet_translate'] = AIET_URL . '/assets/tinymce-translate.js?v=' . AIET_VERSION;
    }
    return $plugin_array;
}
add_filter('mce_external_plugins', 'aiet_register_plugin');
