<?php
/**
 * Organization Custom Post Type Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Organization CPT
 */
add_action('init', 'dts_register_organization_cpt');
function dts_register_organization_cpt() {
    $labels = [
        'name' => 'Organizacije',
        'singular_name' => 'Organizacija',
        'menu_name' => 'Organizacije',
        'add_new' => 'Dodaj organizacijo',
        'add_new_item' => 'Dodaj novo organizacijo',
        'edit_item' => 'Uredi organizacijo',
        'new_item' => 'Nova organizacija',
        'view_item' => 'Poglej organizacijo',
        'view_items' => 'Poglej organizacije',
        'search_items' => 'Poišči organizacije',
        'not_found' => 'Ni najdenih organizacij',
        'not_found_in_trash' => 'Ni najdenih organizacij v smeteh',
        'all_items' => 'Vse organizacije',
        'items_list' => 'Seznam organizacij',
    ];

    $args = [
        'label' => 'Organizacija',
        'labels' => $labels,
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-building',
        'capability_type' => 'post',
        'hierarchical' => false,
        'supports' => ['title', 'editor'],
        'has_archive' => false,
        'rewrite' => false,
    ];

    register_post_type('organization', $args);
}
