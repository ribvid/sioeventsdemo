<?php
/**
 * Registration logic for the new ACF field type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'sio_include_acf_field_attendees_list' );
/**
 * Registers the ACF field type.
 */
function sio_include_acf_field_attendees_list() {
	if ( ! function_exists( 'acf_register_field_type' ) ) {
		return;
	}

	require_once __DIR__ . '/class-sio-acf-field-attendees-list.php';

	acf_register_field_type( 'sio_acf_field_attendees_list' );
}
