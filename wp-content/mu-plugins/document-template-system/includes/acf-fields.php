<?php
/**
 * ACF Field Definitions for Document Template System
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register ACF field groups
 */
add_action('acf/init', 'dts_register_acf_fields');
function dts_register_acf_fields() {
    error_log('DTS: dts_register_acf_fields() called');

    if (!function_exists('acf_add_local_field_group')) {
        error_log('DTS ERROR: ACF Pro is not active!');
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Document Template System:</strong> ACF Pro is required but not active.';
            echo '</p></div>';
        });
        return;
    }

    error_log('DTS: ACF Pro is active, registering field groups');

    // ========================================
    // 1. Organization Fields
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_organization',
        'title' => 'Podatki organizacije',
        'fields' => [
            [
                'key' => 'field_dts_org_contact_email',
                'label' => 'Kontaktni email',
                'name' => 'contact_email',
                'type' => 'email',
                'required' => 1,
            ],
            [
                'key' => 'field_dts_org_contact_phone',
                'label' => 'Kontaktna telefonska številka',
                'name' => 'contact_phone',
                'type' => 'text',
                'required' => 0,
            ],
            [
                'key' => 'field_dts_org_logo',
                'label' => 'Logo organizacije',
                'name' => 'logo',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'required' => 0,
            ],
            [
                'key' => 'field_dts_org_settings',
                'label' => 'Nastavitve',
                'name' => 'settings',
                'type' => 'group',
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_dts_org_default_language',
                        'label' => 'Privzeti jezik',
                        'name' => 'default_language',
                        'type' => 'select',
                        'choices' => [
                            'sl' => 'Slovenščina',
                            'en' => 'Angleščina',
                        ],
                        'default_value' => 'sl',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'organization',
                ],
            ],
        ],
    ]);

    // ========================================
    // 2. Document Template Fields
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_document_template',
        'title' => 'Podrobnosti predloge',
        'fields' => [
            [
                'key' => 'field_dts_template_file',
                'label' => 'Datoteka predloge (.docx)',
                'name' => 'template_file',
                'type' => 'file',
                'required' => 1,
                'return_format' => 'array',
                'mime_types' => 'docx',
                'instructions' => 'Naloži Word dokument (.docx) s spremenljivkami (npr. ${ime_udeleženca})',
            ],
            [
                'key' => 'field_dts_template_type',
                'label' => 'Vrsta predloge',
                'name' => 'template_type',
                'type' => 'select',
                'required' => 1,
                'choices' => [
                    'ticket' => 'Vstopnica',
                    'certificate' => 'Potrdilo',
                    'attendance_list' => 'Podpisni list',
                ],
                'default_value' => 'ticket',
            ],
            [
                'key' => 'field_dts_template_organization',
                'label' => 'Organizacija',
                'name' => 'organization',
                'type' => 'post_object',
                'required' => 1,
                'post_type' => ['organization'],
                'return_format' => 'id',
                'ui' => 1,
                'instructions' => 'Izberi organizacijo, ki ji pripada ta predloga',
            ],
            [
                'key' => 'field_dts_template_description',
                'label' => 'Opis predloge',
                'name' => 'template_description',
                'type' => 'textarea',
                'rows' => 3,
                'instructions' => 'Opis predloge za lažjo identifikacijo',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'document_template',
                ],
            ],
        ],
    ]);

    // ========================================
    // 3. User Organization Field
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_user_organization',
        'title' => 'Organizacija',
        'fields' => [
            [
                'key' => 'field_dts_user_organization',
                'label' => 'Organizacija',
                'name' => 'organization',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['organization'],
                'return_format' => 'id',
                'ui' => 1,
                'instructions' => 'Izberi organizacijo, ki ji pripada ta uporabnik',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'user_form',
                    'operator' => '==',
                    'value' => 'all',
                ],
            ],
        ],
    ]);

    // ========================================
    // 4. Course Session Template Selection
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_course_session_templates',
        'title' => 'Predloge dokumentov',
        'fields' => [
            [
                'key' => 'field_dts_cs_ticket_template',
                'label' => 'Predloga vstopnice',
                'name' => 'ticket_template',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['document_template'],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'instructions' => 'Izberi predlogo za vstopnice (filtrirana po vaši organizaciji)',
            ],
            [
                'key' => 'field_dts_cs_certificate_template',
                'label' => 'Predloga potrdila',
                'name' => 'certificate_template',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['document_template'],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'instructions' => 'Izberi predlogo za potrdila (filtrirana po vaši organizaciji)',
            ],
            [
                'key' => 'field_dts_cs_attendance_list_template',
                'label' => 'Predloga podpisnega lista',
                'name' => 'attendance_list_template',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['document_template'],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'instructions' => 'Izberi predlogo za podpisne liste (filtrirana po vaši organizaciji)',
            ],
            [
                'key' => 'field_dts_cs_download_attendance_btn',
                'label' => 'Prenesi podpisni list',
                'name' => 'download_attendance_list',
                'type' => 'button',
                'button_label' => 'Prenesi podpisni list (PDF)',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'course_session',
                ],
            ],
        ],
        'menu_order' => 100,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
    ]);

    // ========================================
    // 5. Media/Attachment Organization Field
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_attachment_organization',
        'title' => 'Organizacija',
        'fields' => [
            [
                'key' => 'field_dts_attachment_organization',
                'label' => 'Organizacija',
                'name' => 'organization',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['organization'],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'instructions' => 'Izberi organizacijo (pusti prazno za javne datoteke)',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'attachment',
                    'operator' => '==',
                    'value' => 'all',
                ],
            ],
        ],
    ]);

    // ========================================
    // 6. Course Organization Field
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_course_organization',
        'title' => 'Organizacija',
        'fields' => [
            [
                'key' => 'field_dts_course_organization',
                'label' => 'Organizacija',
                'name' => 'organization',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['organization'],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'instructions' => 'Izberi organizacijo (pusti prazno za javna izobraževanja)',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'course',
                ],
            ],
        ],
        'menu_order' => 10,
    ]);

    // ========================================
    // 7. Course Session Organization Field
    // ========================================
    acf_add_local_field_group([
        'key' => 'group_dts_course_session_organization',
        'title' => 'Organizacija',
        'fields' => [
            [
                'key' => 'field_dts_cs_organization',
                'label' => 'Organizacija',
                'name' => 'organization',
                'type' => 'post_object',
                'required' => 0,
                'post_type' => ['organization'],
                'return_format' => 'id',
                'ui' => 1,
                'allow_null' => 1,
                'instructions' => 'Izberi organizacijo (pusti prazno za javne izvedbe)',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'course_session',
                ],
            ],
        ],
        'menu_order' => 5,
    ]);

    error_log('DTS: Successfully registered all 7 field groups');
}
