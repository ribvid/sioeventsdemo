<?php

/**
 * Draft Tag Admin Review Interface
 *
 * Allows Authors to suggest tags via the ACF 'draft_tag' field.
 * Editors review and manually approve/reject suggestions through a dedicated admin interface.
 * For Posts: approved draft tags are converted to real WordPress tags.
 * For Courses: approved draft tags indicate editorial review (courses don't have WP tags).
 */

// ============================================================================
// 1. REGISTRACIJA ADMIN STRANI
// ============================================================================

/**
 * Register admin submenu page under "Posts"
 */
function registracija_strani_draft_oznake() {
    add_submenu_page(
        'edit.php',                     // Parent slug (Posts menu)
        'Pregled predlaganih oznak',    // Page title
        'Predlagane oznake',            // Menu title
        'manage_categories',            // Required capability (Editor/Admin)
        'pregled-draft-oznak',          // Page slug
        'prikazi_stran_draft_oznake'    // Callback function
    );
}
add_action('admin_menu', 'registracija_strani_draft_oznake');

// ============================================================================
// 2. OBDELAVA POTRDITVE/ZAVRNITVE
// ============================================================================

/**
 * Handle approval or rejection of draft tags
 */
function obdelaj_potrditev_oznake() {
    // Check if approval or rejection action was submitted
    if (!isset($_POST['action']) || !in_array($_POST['action'], ['approve_tag', 'reject_tag'])) {
        return;
    }

    if (!isset($_POST['post_id'])) {
        return;
    }

    // Security: Verify nonce
    $post_id = intval($_POST['post_id']);
    if (!isset($_POST['draft_tag_nonce']) || !wp_verify_nonce($_POST['draft_tag_nonce'], 'draft_tag_action_' . $post_id)) {
        wp_die('Varnostno preverjanje ni uspelo.');
    }

    // Get draft tags
    $draft_tags = get_post_meta($post_id, 'draft_tag', true);

    if (!$draft_tags) {
        return;
    }

    $action = $_POST['action'];

    // APPROVAL: Convert to real tags (for posts only) and delete draft field
    if ($action === 'approve_tag') {
        $post = get_post($post_id);

        // Only create actual tags for 'post' post type
        if ($post && $post->post_type === 'post') {
            // Split comma-separated tags into individual tags
            $tags_array = array_map('trim', explode(',', $draft_tags));
            // Remove empty values
            $tags_array = array_filter($tags_array);

            // Add tags (append mode - doesn't replace existing tags)
            wp_set_post_tags($post_id, $tags_array, true);
        }

        // Delete draft_tag field
        delete_post_meta($post_id, 'draft_tag');

        // Success message
        add_settings_error(
            'draft_tags_messages',
            'draft_tags_approved',
            'Oznake so bile uspešno potrjene in dodane.',
            'updated'
        );
        set_transient('draft_tags_admin_notice', get_settings_errors('draft_tags_messages'), 30);
    }

    // REJECTION: Delete draft field without converting
    elseif ($action === 'reject_tag') {
        delete_post_meta($post_id, 'draft_tag');

        // Success message
        add_settings_error(
            'draft_tags_messages',
            'draft_tags_rejected',
            'Predlagane oznake so bile zavrnjene.',
            'updated'
        );
        set_transient('draft_tags_admin_notice', get_settings_errors('draft_tags_messages'), 30);
    }

    // Redirect back to same page to refresh the list
    wp_redirect(admin_url('edit.php?page=pregled-draft-oznak'));
    exit;
}
add_action('admin_init', 'obdelaj_potrditev_oznake');

// ============================================================================
// 3. PRIKAZ ADMIN STRANI
// ============================================================================

/**
 * Display admin page with table of pending draft tags
 */
function prikazi_stran_draft_oznake() {
    ?>
    <div class="wrap">
        <h1>Predlagane oznake v čakalni vrsti</h1>

        <?php
        // Display success/error messages
        if ($notices = get_transient('draft_tags_admin_notice')) {
            delete_transient('draft_tags_admin_notice');
            foreach ($notices as $notice) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($notice['type']),
                    esc_html($notice['message'])
                );
            }
        }
        ?>

        <?php
        // Query for posts/courses with non-empty draft_tag field
        $args = array(
            'post_type' => array('post', 'course'),
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'draft_tag',
                    'value' => '',
                    'compare' => '!='
                )
            ),
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) : ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 25%;">Naslov</th>
                        <th scope="col" style="width: 12%;">Avtor</th>
                        <th scope="col" style="width: 10%;">Tip</th>
                        <th scope="col" style="width: 10%;">Status</th>
                        <th scope="col" style="width: 25%;"><strong>Predlagane oznake</strong></th>
                        <th scope="col" style="width: 18%;">Akcija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()) : $query->the_post();
                        $draft_tags = get_post_meta(get_the_ID(), 'draft_tag', true);
                        $post_type_obj = get_post_type_object(get_post_type());
                        $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type();
                        $status_obj = get_post_status_object(get_post_status());
                        $status_label = $status_obj ? $status_obj->label : get_post_status();
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link()); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php the_author(); ?></td>
                            <td><?php echo esc_html($post_type_label); ?></td>
                            <td><?php echo esc_html($status_label); ?></td>
                            <td>
                                <span style="background: #e6f7ff; color: #005a87; padding: 4px 10px; border-radius: 3px; border: 1px solid #1890ff; display: inline-block;">
                                    <?php echo esc_html($draft_tags); ?>
                                </span>
                            </td>
                            <td>
                                <!-- Approve Form -->
                                <form method="post" style="display: inline-block; margin-right: 8px;">
                                    <input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>">
                                    <input type="hidden" name="action" value="approve_tag">
                                    <?php wp_nonce_field('draft_tag_action_' . get_the_ID(), 'draft_tag_nonce'); ?>
                                    <button type="submit" class="button button-primary">Potrdi</button>
                                </form>

                                <!-- Reject Form -->
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>">
                                    <input type="hidden" name="action" value="reject_tag">
                                    <?php wp_nonce_field('draft_tag_action_' . get_the_ID(), 'draft_tag_nonce'); ?>
                                    <button type="submit" class="button button-secondary" onclick="return confirm('Ali ste prepričani, da želite zavrniti te oznake?');">Zavrni</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        <?php else : ?>
            <div class="notice notice-success inline" style="padding: 12px;">
                <p style="font-size: 14px; margin: 0;">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px; vertical-align: middle;"></span>
                    Trenutno ni nobenih predlaganih oznak v čakalni vrsti. Vse je urejeno!
                </p>
            </div>
        <?php endif;
        wp_reset_postdata();
        ?>
    </div>
    <?php
}

// ============================================================================
// 4. ADMIN STOLPEC (za hitro prepoznavo v seznamu objav)
// ============================================================================

/**
 * Add "Predlagane oznake" column to admin post list
 *
 * @param array $columns Existing columns
 * @return array Modified columns
 */
function dodaj_stolpec_draft_tag($columns) {
    $columns['draft_tag_col'] = 'Predlagane oznake';
    return $columns;
}

// Add column for Posts
add_filter('manage_posts_columns', 'dodaj_stolpec_draft_tag');
// Add column for Courses
add_filter('manage_course_columns', 'dodaj_stolpec_draft_tag');

/**
 * Display draft tag content in admin column
 *
 * @param string $column  Column name
 * @param int    $post_id Post ID
 */
function prikazi_draft_tag_vsebino($column, $post_id) {
    if ($column === 'draft_tag_col') {
        $draft = get_post_meta($post_id, 'draft_tag', true);
        if ($draft) {
            echo '<span style="color:red; font-weight:bold;">' . esc_html($draft) . '</span>';
        } else {
            echo '—';
        }
    }
}

// Display column content for Posts
add_action('manage_posts_custom_column', 'prikazi_draft_tag_vsebino', 10, 2);
// Display column content for Courses
add_action('manage_course_posts_custom_column', 'prikazi_draft_tag_vsebino', 10, 2);
