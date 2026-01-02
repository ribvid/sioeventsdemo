<?php
/**
 * Document Template Custom Post Type Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Document Template CPT
 */
add_action('init', 'dts_register_document_template_cpt');
function dts_register_document_template_cpt() {
    $labels = [
        'name' => 'Predloge dokumentov',
        'singular_name' => 'Predloga dokumenta',
        'menu_name' => 'Predloge dokumentov',
        'add_new' => 'Dodaj predlogo',
        'add_new_item' => 'Dodaj novo predlogo',
        'edit_item' => 'Uredi predlogo',
        'new_item' => 'Nova predloga',
        'view_item' => 'Poglej predlogo',
        'view_items' => 'Poglej predloge',
        'search_items' => 'Poišči predloge',
        'not_found' => 'Ni najdenih predlog',
        'not_found_in_trash' => 'Ni najdenih predlog v smeteh',
        'all_items' => 'Vse predloge',
        'items_list' => 'Seznam predlog',
    ];

    $args = [
        'label' => 'Predloga dokumenta',
        'labels' => $labels,
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 26,
        'menu_icon' => 'dashicons-media-document',
        'capability_type' => 'post',
        'hierarchical' => false,
        'supports' => ['title'],
        'has_archive' => false,
        'rewrite' => false,
    ];

    register_post_type('document_template', $args);
}
