<?php
/*
Plugin Name: Draft Tag Manager
Description: Admin interface for reviewing and approving/rejecting draft tags suggested by authors.
Version: 1.0
Author: SIO
*/

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
 * Handle approval or rejection of individual draft tags
 */
function obdelaj_potrditev_oznake() {
    // Check for new single-tag action types
    if (!isset($_POST['action']) || !in_array($_POST['action'], ['approve_single_tag', 'reject_single_tag'])) {
        return;
    }

    // Validate inputs
    if (!isset($_POST['tag_name']) || !isset($_POST['post_ids']) || !is_array($_POST['post_ids'])) {
        return;
    }

    $tag_name = sanitize_text_field($_POST['tag_name']);
    $post_ids = array_map('intval', $_POST['post_ids']);

    // Security: Verify nonce
    if (!isset($_POST['draft_tag_nonce']) || !wp_verify_nonce($_POST['draft_tag_nonce'], 'draft_tag_action_' . md5($tag_name))) {
        wp_die('Varnostno preverjanje ni uspelo.');
    }

    $action = $_POST['action'];

    // Process each post that suggested this tag
    foreach ($post_ids as $post_id) {
        // Get current draft tags
        $draft_tags = get_post_meta($post_id, 'draft_tag', true);

        if (!$draft_tags) {
            continue;
        }

        // Split into array
        $tags_array = array_map('trim', explode(',', $draft_tags));
        $tags_array = array_filter($tags_array);

        // Remove the specific tag (case-insensitive)
        $tags_array = array_filter($tags_array, function($tag) use ($tag_name) {
            return strcasecmp($tag, $tag_name) !== 0;
        });

        if ($action === 'approve_single_tag') {
            $post = get_post($post_id);

            // Add to real tags (only for 'post' type)
            if ($post && $post->post_type === 'post') {
                wp_set_post_tags($post_id, [$tag_name], true); // Append mode
            }
        }

        // Update or delete draft_tag field
        if (empty($tags_array)) {
            // No more draft tags, delete the field
            delete_post_meta($post_id, 'draft_tag');
        } else {
            // Still have draft tags, update the field
            $updated_tags = implode(', ', $tags_array);
            update_post_meta($post_id, 'draft_tag', $updated_tags);
        }
    }

    // Success messages
    if ($action === 'approve_single_tag') {
        add_settings_error(
            'draft_tags_messages',
            'draft_tag_approved',
            sprintf('Oznaka "%s" je bila potrjena za %d objav(e).', $tag_name, count($post_ids)),
            'updated'
        );
    } else {
        add_settings_error(
            'draft_tags_messages',
            'draft_tag_rejected',
            sprintf('Oznaka "%s" je bila zavrnjena za %d objav(e).', $tag_name, count($post_ids)),
            'updated'
        );
    }

    set_transient('draft_tags_admin_notice', get_settings_errors('draft_tags_messages'), 30);

    // Redirect back to same page to refresh the list
    wp_redirect(admin_url('edit.php?page=pregled-draft-oznak'));
    exit;
}
add_action('admin_init', 'obdelaj_potrditev_oznake');

// ============================================================================
// 3. PRIKAZ ADMIN STRANI
// ============================================================================

/**
 * Display admin page with table of pending draft tags (tag-centric view)
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

        // Transform post-centric data to tag-centric
        $tag_groups = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $draft_tags = get_post_meta($post_id, 'draft_tag', true);

                // Split comma-separated tags
                $tags_array = array_map('trim', explode(',', $draft_tags));
                $tags_array = array_filter($tags_array);

                // Build post info
                $post_info = [
                    'post_id' => $post_id,
                    'post_title' => get_the_title(),
                    'author' => get_the_author(),
                    'post_type' => get_post_type(),
                    'edit_link' => get_edit_post_link()
                ];

                // Group by tag
                foreach ($tags_array as $tag) {
                    if (!isset($tag_groups[$tag])) {
                        $tag_groups[$tag] = [];
                    }
                    $tag_groups[$tag][] = $post_info;
                }
            }
            wp_reset_postdata();

            // Sort alphabetically
            ksort($tag_groups);
        }

        if (!empty($tag_groups)) : ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 25%;"><strong>Oznaka</strong></th>
                        <th scope="col" style="width: 55%;">Predlagana v objavah</th>
                        <th scope="col" style="width: 20%;">Akcija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tag_groups as $tag_name => $posts) : ?>
                        <tr>
                            <td>
                                <span style="background: #e6f7ff; color: #005a87; padding: 6px 12px; border-radius: 3px; border: 1px solid #1890ff; display: inline-block; font-weight: bold;">
                                    <?php echo esc_html($tag_name); ?>
                                </span>
                            </td>
                            <td>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ($posts as $post_info) : ?>
                                        <li>
                                            <a href="<?php echo esc_url($post_info['edit_link']); ?>">
                                                <?php echo esc_html($post_info['post_title']); ?>
                                            </a>
                                            <span style="color: #666;">(<?php echo esc_html($post_info['author']); ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <!-- Approve Form -->
                                <form method="post" style="display: inline-block; margin-right: 8px;">
                                    <input type="hidden" name="tag_name" value="<?php echo esc_attr($tag_name); ?>">
                                    <?php foreach ($posts as $post_info) : ?>
                                        <input type="hidden" name="post_ids[]" value="<?php echo $post_info['post_id']; ?>">
                                    <?php endforeach; ?>
                                    <input type="hidden" name="action" value="approve_single_tag">
                                    <?php wp_nonce_field('draft_tag_action_' . md5($tag_name), 'draft_tag_nonce'); ?>
                                    <button type="submit" class="button button-primary">
                                        Potrdi (<?php echo count($posts); ?>)
                                    </button>
                                </form>

                                <!-- Reject Form -->
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="tag_name" value="<?php echo esc_attr($tag_name); ?>">
                                    <?php foreach ($posts as $post_info) : ?>
                                        <input type="hidden" name="post_ids[]" value="<?php echo $post_info['post_id']; ?>">
                                    <?php endforeach; ?>
                                    <input type="hidden" name="action" value="reject_single_tag">
                                    <?php wp_nonce_field('draft_tag_action_' . md5($tag_name), 'draft_tag_nonce'); ?>
                                    <button type="submit" class="button button-secondary"
                                            onclick="return confirm('Ali ste prepričani, da želite zavrniti oznako \'<?php echo esc_js($tag_name); ?>\' za <?php echo count($posts); ?> objav(e)?');">
                                        Zavrni
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else : ?>
            <div class="notice notice-success inline" style="padding: 12px;">
                <p style="font-size: 14px; margin: 0;">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 20px; vertical-align: middle;"></span>
                    Trenutno ni nobenih predlaganih oznak v čakalni vrsti. Vse je urejeno!
                </p>
            </div>
        <?php endif; ?>
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
