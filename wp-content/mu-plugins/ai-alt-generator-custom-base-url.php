<?php
/*
Plugin Name: AI Alt Generator - Custom OpenAI Base URL
Description: Adds ability to configure custom OpenAI base URL for the AI Image Alt Text Generator plugin
Version: 1.0.0
Author: Custom
*/

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Option name constant
define('ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION', 'acpl_ai_alt_generator_custom_base_url');

/**
 * Register the custom base URL setting
 */
function acpl_ai_alt_register_custom_base_url_setting() {
    register_setting(
        'media',
        ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION,
        [
            'type' => 'string',
            'sanitize_callback' => 'acpl_ai_alt_sanitize_base_url',
            'default' => '',
            'show_in_rest' => false,
        ]
    );
}
add_action('admin_init', 'acpl_ai_alt_register_custom_base_url_setting', 20);

/**
 * Add settings field to existing AI Alt Generator section
 */
function acpl_ai_alt_add_custom_base_url_field() {
    // Ensure the main plugin's section exists
    if (!class_exists('ACPL\AIAltGenerator\Admin')) {
        return;
    }

    add_settings_field(
        'acpl_ai_alt_generator_custom_base_url_field',
        __('Custom OpenAI Base URL', 'ai-alt-generator-custom-base-url'),
        'acpl_ai_alt_render_custom_base_url_field',
        'media',
        'acpl_ai_alt_generator_section',
        ['label_for' => 'custom_base_url']
    );
}
add_action('admin_init', 'acpl_ai_alt_add_custom_base_url_field', 25);

/**
 * Render the custom base URL settings field
 */
function acpl_ai_alt_render_custom_base_url_field() {
    $value = get_option(ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION, '');
    ?>
    <input
        type="url"
        id="custom_base_url"
        name="<?php echo esc_attr(ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION); ?>"
        value="<?php echo esc_attr($value); ?>"
        class="regular-text code"
        placeholder="https://api.openai.com/v1"
    />
    <p class="description">
        <?php
        echo esc_html__(
            'Leave empty to use the default OpenAI endpoint. Set a custom base URL for proxies, Azure OpenAI, or custom endpoints. The "/responses" path will be appended automatically.',
            'ai-alt-generator-custom-base-url'
        );
        ?>
        <br>
        <strong><?php echo esc_html__('Example:', 'ai-alt-generator-custom-base-url'); ?></strong>
        <code>https://my-proxy.com/openai/v1</code>
        <?php echo esc_html__('will become', 'ai-alt-generator-custom-base-url'); ?>
        <code>https://my-proxy.com/openai/v1/responses</code>
    </p>
    <?php
}

/**
 * Sanitize and validate the base URL
 *
 * @param string $input The user-provided URL
 * @return string The sanitized URL or empty string
 */
function acpl_ai_alt_sanitize_base_url($input) {
    // If empty, allow it (uses default)
    if (empty(trim($input))) {
        return '';
    }

    $input = trim($input);

    // Validate URL format
    if (!filter_var($input, FILTER_VALIDATE_URL)) {
        add_settings_error(
            ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION,
            'invalid_url',
            __('Invalid URL format. Please enter a valid URL starting with http:// or https://', 'ai-alt-generator-custom-base-url')
        );
        return get_option(ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION, '');
    }

    // Parse URL to validate components
    $parsed = parse_url($input);

    // Must have http or https scheme
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
        add_settings_error(
            ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION,
            'invalid_scheme',
            __('URL must use http:// or https:// scheme.', 'ai-alt-generator-custom-base-url')
        );
        return get_option(ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION, '');
    }

    // Must have host
    if (!isset($parsed['host'])) {
        add_settings_error(
            ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION,
            'invalid_host',
            __('URL must include a valid hostname.', 'ai-alt-generator-custom-base-url')
        );
        return get_option(ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION, '');
    }

    // Warn if query string is present
    if (isset($parsed['query'])) {
        add_settings_error(
            ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION,
            'has_query_string',
            __('Base URL should not contain query parameters. They will be removed.', 'ai-alt-generator-custom-base-url'),
            'warning'
        );
    }

    // Reconstruct clean URL without query/fragment
    $clean_url = $parsed['scheme'] . '://' . $parsed['host'];

    if (isset($parsed['port'])) {
        $clean_url .= ':' . $parsed['port'];
    }

    if (isset($parsed['path'])) {
        $clean_url .= rtrim($parsed['path'], '/');
    }

    return $clean_url;
}

/**
 * Filter the API URL to use custom base URL
 *
 * @param string $default_url The default OpenAI API URL
 * @return string The modified API URL or original if no custom URL is set
 */
function acpl_ai_alt_filter_api_url($default_url) {
    $custom_base_url = get_option(ACPL_AI_ALT_CUSTOM_BASE_URL_OPTION, '');

    // If no custom URL is set, use default
    if (empty($custom_base_url)) {
        return $default_url;
    }

    // Construct URL with custom base + /responses endpoint
    $custom_url = rtrim($custom_base_url, '/') . '/responses';

    // Log the URL change if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[AI Alt Generator Custom Base URL] Using custom endpoint: %s (default was: %s)',
            $custom_url,
            $default_url
        ));
    }

    return $custom_url;
}
add_filter('acpl/ai_alt_generator/api_url', 'acpl_ai_alt_filter_api_url', 10);
