<?php
/**
 * Registration logic for the new ACF field type.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'sio_include_acf_field_button');
/**
 * Registers the ACF field type.
 */
function sio_include_acf_field_button()
{
    if (!function_exists('acf_register_field_type')) {
        return;
    }

    require_once __DIR__ . '/class-sio-acf-field-button.php';

    acf_register_field_type('sio_acf_field_button');
}
