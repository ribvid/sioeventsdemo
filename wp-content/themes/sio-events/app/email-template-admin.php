<?php

/**
 * Email Template Admin Customizations
 *
 * Adds thumbnail display to admin list table and edit screen
 * for the email_template custom post type.
 */

// ============================================================================
// 1. ADMIN LIST TABLE COLUMN
// ============================================================================

/**
 * Add thumbnail column to email_template admin list
 *
 * @param array $columns Existing columns
 * @return array Modified columns with thumbnail inserted after title
 */
function sio_email_template_add_thumbnail_column($columns)
{
    $new_columns = array();

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['thumbnail'] = __('Predogled', 'sage');
        }
    }

    return $new_columns;
}
add_filter('manage_email_template_columns', 'sio_email_template_add_thumbnail_column');


/**
 * Display thumbnail content in admin list column
 *
 * @param string $column  Column name
 * @param int    $post_id Post ID
 */
function sio_email_template_display_thumbnail_column($column, $post_id)
{
    if ($column !== 'thumbnail') {
        return;
    }

    $html_editor_mode = get_field('html_editor_mode', $post_id);
    $html_url = get_post_meta($post_id, '_email_html_url', true);
    $post_status = get_post_status($post_id);

    if ($post_status !== 'publish' && $post_status !== 'future') {
        echo '<span style="color: #6c757d; font-size: 12px;">';
        echo '<span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px;"></span> ';
        echo esc_html__('V čakanju', 'sage');
        echo '</span>';
        return;
    }

    $scale = 0.2;
    $iframe_style = 'width: 1000px; height: 1414px; position: absolute; top: 0; left: 0; border: none; transform: scale(' . $scale . '); transform-origin: top left; pointer-events: none;';

    printf(
        '<div style="width: 60px; height: 60px; overflow: hidden; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative;">
            <iframe style="%s" loading="lazy" %s></iframe>
        </div>',
        $iframe_style,
        $html_editor_mode === 'custom' 
            ? 'srcdoc="' . esc_attr(get_field('custom_html', $post_id)) . '"' 
            : 'src="' . esc_url($html_url) . '"'
    );
}
add_action('manage_email_template_posts_custom_column', 'sio_email_template_display_thumbnail_column', 10, 2);


/**
 * Add custom CSS for thumbnail column width and styling
 */
function sio_email_template_admin_column_styles()
{
    global $post_type;

    if ($post_type !== 'email_template') {
        return;
    }
    ?>
    <style>
        /* Thumbnail column width */
        .wp-list-table .column-thumbnail {
            width: 80px;
            text-align: center;
            vertical-align: middle;
        }

        /* Hover effect for thumbnail images */
        .wp-list-table .column-thumbnail img:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
        }

        /* Status icons alignment */
        .wp-list-table .column-thumbnail .dashicons {
            vertical-align: middle;
        }
    </style>
    <?php
}
add_action('admin_head', 'sio_email_template_admin_column_styles');


// ============================================================================
// 2. EDIT SCREEN META BOX
// ============================================================================

/**
 * Register thumbnail preview meta box on email_template edit screen
 */
function sio_email_template_add_thumbnail_metabox()
{
    add_meta_box(
        'email_template_thumbnail_preview',
        __('Predogled e-poštnega vzorca', 'sage'),
        'sio_email_template_render_thumbnail_metabox',
        'email_template',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'sio_email_template_add_thumbnail_metabox');


/**
 * Render thumbnail preview meta box content
 *
 * @param WP_Post $post Current post object
 */
function sio_email_template_render_thumbnail_metabox($post)
{
    $html_editor_mode = get_field('html_editor_mode', $post->ID);
    $html_url = get_post_meta($post->ID, '_email_html_url', true);
    $post_status = get_post_status($post->ID);

    if ($post_status !== 'publish' && $post_status !== 'future') {
        echo '<div style="padding: 15px; text-align: center; background: #f0f6fc; border-radius: 4px; border-left: 4px solid #0073aa;">';
        echo '<p style="margin: 0; color: #0073aa; font-size: 13px;">';
        echo '<span class="dashicons dashicons-info" style="font-size: 18px; vertical-align: middle; margin-right: 5px;"></span>';
        echo esc_html__('Predogled bo ustvarjen po objavi vzorca.', 'sage');
        echo '</p>';
        echo '</div>';
        return;
    }

    $scale = 0.2;
    $iframe_style = 'width: 1000px; height: 1414px; position: absolute; top: 0; left: 0; border: none; transform: scale(' . $scale . '); transform-origin: top left; pointer-events: none;';

    echo '<div class="email-template-thumbnail-wrapper">';
    echo '<div style="margin-bottom: 12px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); height: 60px; position: relative;">';
    printf(
        '<iframe style="%s" loading="lazy" %s></iframe>',
        $iframe_style,
        $html_editor_mode === 'custom' 
            ? 'srcdoc="' . esc_attr(get_field('custom_html', $post->ID)) . '"' 
            : 'src="' . esc_url($html_url) . '"'
    );
    echo '</div>';

    echo '<div style="display: flex; flex-direction: column; gap: 8px;">';

    if ($html_editor_mode === 'custom') {
        echo '<button type="button" class="button button-secondary" id="email-template-full-preview" style="text-align: center;">';
        echo '<span class="dashicons dashicons-visibility" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span>';
        echo esc_html__('Ogled v polni velikosti', 'sage');
        echo '</button>';
        echo '<script type="text/javascript">';
        echo '(function($) {';
        echo '  $(document).ready(function() {';
        echo '    $("#email-template-full-preview").on("click", function() {';
        echo '      var html = ' . json_encode(get_field('custom_html', $post->ID)) . ';';
        echo '      var win = window.open("", "_blank");';
        echo '      win.document.write(html);';
        echo '      win.document.close();';
        echo '    });';
        echo '  });';
        echo '})(jQuery);';
        echo '</script>';
    } else {
        printf(
            '<a href="%s" target="_blank" class="button button-secondary" style="text-align: center;">
                <span class="dashicons dashicons-visibility" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span>
                %s
            </a>',
            esc_url($html_url),
            esc_html__('Ogled v polni velikosti', 'sage')
        );
    }

    echo '</div>';
    echo '</div>';
}


/**
 * Add custom CSS for meta box styling
 */
function sio_email_template_metabox_styles()
{
    global $post_type;

    if ($post_type !== 'email_template') {
        return;
    }
    ?>
    <style>
        /* Meta box thumbnail wrapper */
        #email_template_thumbnail_preview .inside {
            padding: 12px;
            margin: 0;
        }

        /* Ensure full width in sidebar */
        .email-template-thumbnail-wrapper {
            width: 100%;
        }

        /* Button group styling */
        .email-template-thumbnail-wrapper .button {
            width: 100%;
            justify-content: center;
            display: flex;
            align-items: center;
        }

        /* Dashicons in buttons */
        .email-template-thumbnail-wrapper .button .dashicons {
            margin-top: -2px;
        }
    </style>
    <?php
}
add_action('admin_head', 'sio_email_template_metabox_styles');
