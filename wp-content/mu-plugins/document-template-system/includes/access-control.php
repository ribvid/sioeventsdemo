<?php
/**
 * Access Control and Multi-Tenancy Logic
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get current user's organization ID
 *
 * @param int|null $user_id User ID (null = current user)
 * @return int|false Organization ID or false for admins
 */
function dts_get_user_organization($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Admins can manage all organizations
    if (current_user_can('manage_options')) {
        return false;
    }

    // Get user's organization from ACF field
    $org_id = get_field('organization', 'user_' . $user_id);

    return $org_id ? (int) $org_id : 0;
}

/**
 * Filter organization queries - users see only their org
 */
add_action('pre_get_posts', 'dts_filter_organizations_query');
function dts_filter_organizations_query($query) {
    // Only apply in admin and for main query
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Only filter organization post type
    if ($query->get('post_type') !== 'organization') {
        return;
    }

    // Admins see all
    if (current_user_can('manage_options')) {
        return;
    }

    // Get user's organization
    $user_org = dts_get_user_organization();

    if ($user_org) {
        $query->set('post__in', [$user_org]);
    } else {
        // User has no organization assigned - show nothing
        $query->set('post__in', [0]);
    }
}

/**
 * Filter template queries - users see only their org's templates
 */
add_action('pre_get_posts', 'dts_filter_templates_query');
function dts_filter_templates_query($query) {
    // Only apply in admin and for main query
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Only filter document_template post type
    if ($query->get('post_type') !== 'document_template') {
        return;
    }

    // Admins see all
    if (current_user_can('manage_options')) {
        return;
    }

    // Get user's organization
    $user_org = dts_get_user_organization();

    if ($user_org) {
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ];
        $query->set('meta_query', $meta_query);
    } else {
        // User has no organization assigned - show nothing
        $query->set('post__in', [0]);
    }
}

/**
 * Filter ticket template selection by organization and type
 */
add_filter('acf/fields/post_object/query/name=ticket_template', 'dts_filter_ticket_template_query', 10, 3);
function dts_filter_ticket_template_query($args, $field, $post_id) {
    $user_org = dts_get_user_organization();

    $meta_query = [
        'relation' => 'AND',
        [
            'key' => 'template_type',
            'value' => 'ticket',
            'compare' => '=',
        ],
    ];

    // Non-admins see only their org's templates
    if ($user_org !== false) {
        $meta_query[] = [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ];
    }

    $args['meta_query'] = $meta_query;

    return $args;
}

/**
 * Filter certificate template selection by organization and type
 */
add_filter('acf/fields/post_object/query/name=certificate_template', 'dts_filter_certificate_template_query', 10, 3);
function dts_filter_certificate_template_query($args, $field, $post_id) {
    $user_org = dts_get_user_organization();

    $meta_query = [
        'relation' => 'AND',
        [
            'key' => 'template_type',
            'value' => 'certificate',
            'compare' => '=',
        ],
    ];

    // Non-admins see only their org's templates
    if ($user_org !== false) {
        $meta_query[] = [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ];
    }

    $args['meta_query'] = $meta_query;

    return $args;
}

/**
 * Filter attendance list template selection by organization and type
 */
add_filter('acf/fields/post_object/query/name=attendance_list_template', 'dts_filter_attendance_list_template_query', 10, 3);
function dts_filter_attendance_list_template_query($args, $field, $post_id) {
    $user_org = dts_get_user_organization();

    $meta_query = [
        'relation' => 'AND',
        [
            'key' => 'template_type',
            'value' => 'attendance_list',
            'compare' => '=',
        ],
    ];

    // Non-admins see only their org's templates
    if ($user_org !== false) {
        $meta_query[] = [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ];
    }

    $args['meta_query'] = $meta_query;

    return $args;
}

/**
 * Add custom columns to Document Template list
 */
add_filter('manage_document_template_posts_columns', 'dts_template_columns');
function dts_template_columns($columns) {
    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        // Add custom columns after title
        if ($key === 'title') {
            $new_columns['template_type'] = 'Vrsta predloge';
            $new_columns['organization'] = 'Organizacija';
            $new_columns['file_size'] = 'Velikost datoteke';
        }
    }

    return $new_columns;
}

/**
 * Populate custom columns for Document Template
 */
add_action('manage_document_template_posts_custom_column', 'dts_template_column_content', 10, 2);
function dts_template_column_content($column, $post_id) {
    switch ($column) {
        case 'template_type':
            $type = get_field('template_type', $post_id);
            $badges = [
                'ticket' => '<span style="background:#0073aa;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">Vstopnica</span>',
                'certificate' => '<span style="background:#46b450;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">Potrdilo</span>',
                'attendance_list' => '<span style="background:#f56e28;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">Podpisni list</span>',
            ];
            echo $badges[$type] ?? '-';
            break;

        case 'organization':
            $org_id = get_field('organization', $post_id);
            if ($org_id) {
                echo get_the_title($org_id);
            } else {
                echo '-';
            }
            break;

        case 'file_size':
            $file = get_field('template_file', $post_id);
            if ($file && isset($file['filesize'])) {
                echo size_format($file['filesize']);
            } else {
                echo '-';
            }
            break;
    }
}

/**
 * Add admin notice if user has no organization assigned
 */
add_action('admin_notices', 'dts_no_organization_notice');
function dts_no_organization_notice() {
    // Only show to non-admins
    if (current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();

    // Only show on relevant pages
    if (!$screen || !in_array($screen->post_type, ['organization', 'document_template', 'course_session'])) {
        return;
    }

    $user_org = get_field('organization', 'user_' . get_current_user_id());

    if (!$user_org) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Opozorilo:</strong> Nimate dodeljene organizacije. ';
        echo 'Kontaktirajte administratorja, da vam dodeli organizacijo.';
        echo '</p></div>';
    }
}

/**
 * File validation removed - ACF's mime_types parameter handles .docx restriction
 * @see acf-fields.php line 95: 'mime_types' => 'docx'
 */

/**
 * Filter media library - users see their org + untagged media
 */
add_action('pre_get_posts', 'dts_filter_media_library_query');
function dts_filter_media_library_query($query) {
    // Only apply in admin
    if (!is_admin()) {
        return;
    }

    // Only filter attachment queries
    if ($query->get('post_type') !== 'attachment') {
        return;
    }

    // Admins see all
    if (current_user_can('manage_options')) {
        return;
    }

    // Get user's organization
    $user_org = dts_get_user_organization();

    if (!$user_org) {
        // User has no organization - show only untagged media
        $query->set('meta_query', [
            [
                'key' => 'organization',
                'compare' => 'NOT EXISTS',
            ],
        ]);
        return;
    }

    // Show user's org media + untagged media
    $query->set('meta_query', [
        'relation' => 'OR',
        [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ],
        [
            'key' => 'organization',
            'compare' => 'NOT EXISTS',
        ],
    ]);
}

/**
 * Filter courses - users see their org + untagged courses
 */
add_action('pre_get_posts', 'dts_filter_courses_query');
function dts_filter_courses_query($query) {
    // Only apply in admin and for main query
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Only filter course post type
    if ($query->get('post_type') !== 'course') {
        return;
    }

    // Admins see all
    if (current_user_can('manage_options')) {
        return;
    }

    // Get user's organization
    $user_org = dts_get_user_organization();

    if (!$user_org) {
        // User has no organization - show only untagged courses
        $query->set('meta_query', [
            [
                'key' => 'organization',
                'compare' => 'NOT EXISTS',
            ],
        ]);
        return;
    }

    // Show user's org courses + untagged courses
    $query->set('meta_query', [
        'relation' => 'OR',
        [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ],
        [
            'key' => 'organization',
            'compare' => 'NOT EXISTS',
        ],
    ]);
}

/**
 * Filter course sessions - users see their org + untagged sessions
 */
add_action('pre_get_posts', 'dts_filter_course_sessions_query');
function dts_filter_course_sessions_query($query) {
    // Only apply in admin and for main query
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Only filter course_session post type
    if ($query->get('post_type') !== 'course_session') {
        return;
    }

    // Admins see all
    if (current_user_can('manage_options')) {
        return;
    }

    // Get user's organization
    $user_org = dts_get_user_organization();

    if (!$user_org) {
        // User has no organization - show only untagged sessions
        $query->set('meta_query', [
            [
                'key' => 'organization',
                'compare' => 'NOT EXISTS',
            ],
        ]);
        return;
    }

    // Show user's org sessions + untagged sessions
    $query->set('meta_query', [
        'relation' => 'OR',
        [
            'key' => 'organization',
            'value' => $user_org,
            'compare' => '=',
        ],
        [
            'key' => 'organization',
            'compare' => 'NOT EXISTS',
        ],
    ]);
}

/**
 * Add organization column to courses
 */
add_filter('manage_course_posts_columns', 'dts_course_organization_column');
function dts_course_organization_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['organization'] = 'Organizacija';
        }
    }
    return $new_columns;
}

add_action('manage_course_posts_custom_column', 'dts_course_organization_column_content', 10, 2);
function dts_course_organization_column_content($column, $post_id) {
    if ($column === 'organization') {
        $org_id = get_field('organization', $post_id);
        if ($org_id) {
            echo get_the_title($org_id);
        } else {
            echo '<span style="color:#999;">Javno</span>';
        }
    }
}

/**
 * Add organization column to course sessions
 */
add_filter('manage_course_session_posts_columns', 'dts_course_session_organization_column');
function dts_course_session_organization_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['organization'] = 'Organizacija';
        }
    }
    return $new_columns;
}

add_action('manage_course_session_posts_custom_column', 'dts_course_session_organization_column_content', 10, 2);
function dts_course_session_organization_column_content($column, $post_id) {
    if ($column === 'organization') {
        $org_id = get_field('organization', $post_id);
        if ($org_id) {
            echo get_the_title($org_id);
        } else {
            echo '<span style="color:#999;">Javno</span>';
        }
    }
}
