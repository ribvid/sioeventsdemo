<?php
/**
 * Registration logic for the Email Template Preview ACF field type.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'sio_include_acf_field_email_template_preview');

/**
 * Registers the ACF Email Template Preview field type.
 */
function sio_include_acf_field_email_template_preview()
{
    // Check if ACF is active
    if (!function_exists('acf_register_field_type')) {
        return;
    }

    // Include the field class
    require_once __DIR__ . '/class-sio-acf-field-email_template_preview.php';

    // Register the field type with ACF
    acf_register_field_type('sio_acf_field_email_template_preview');
}
