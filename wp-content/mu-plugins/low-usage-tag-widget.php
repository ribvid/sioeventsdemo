<?php
/**
 * Plugin Name: Low Usage Tag Widget
 * Description: Dashboard widget to review and manage low-usage tags
 * Version: 1.0.0
 * Author: SIO Events
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register dashboard widget
 */
add_action('wp_dashboard_setup', 'lut_register_dashboard_widget');
function lut_register_dashboard_widget() {
    if (!current_user_can('manage_categories')) {
        return;
    }

    wp_add_dashboard_widget(
        'low_usage_tags_widget',
        'Pregled nizko uporabljenih oznak',
        'lut_render_widget'
    );
}

/**
 * Get low usage tags based on threshold
 */
function lut_get_low_usage_tags() {
    $threshold = get_option('lut_threshold', 3);
    $limit = get_option('lut_widget_limit', 15);

    $tags = get_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false,
        'number' => 0
    ]);

    if (is_wp_error($tags)) {
        return [];
    }

    // Filter low usage tags that are not dismissed
    $low_usage = array_filter($tags, function($tag) use ($threshold) {
        $dismissed = get_transient('lut_dismissed_' . $tag->term_id);
        return !$dismissed && $tag->count < $threshold;
    });

    // Sort by count ascending (lowest first)
    usort($low_usage, function($a, $b) {
        if ($a->count === $b->count) {
            return strcmp($a->name, $b->name);
        }
        return $a->count - $b->count;
    });

    return array_slice($low_usage, 0, $limit);
}

/**
 * Get all tags for merge dropdown
 */
function lut_get_merge_target_tags() {
    $tags = get_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false,
        'number' => 0
    ]);

    if (is_wp_error($tags)) {
        return [];
    }

    // Sort by count descending (most used first), then by name
    usort($tags, function($a, $b) {
        if ($a->count === $b->count) {
            return strcmp($a->name, $b->name);
        }
        return $b->count - $a->count;
    });

    return $tags;
}

/**
 * Render the dashboard widget
 */
function lut_render_widget() {
    $low_usage_tags = lut_get_low_usage_tags();
    $threshold = get_option('lut_threshold', 3);
    $total_count = count($low_usage_tags);

    ?>
    <div id="lut-widget-container">
        <?php if (empty($low_usage_tags)): ?>
            <p style="text-align: center; color: #46b450; padding: 20px 0;">
                ✓ Vse oznake so dobro uporabljene!
            </p>
        <?php else: ?>
            <div class="lut-header" style="margin-bottom: 15px;">
                <p style="margin: 0 0 10px 0;">
                    Najdeno <strong><?php echo esc_html($total_count); ?></strong>
                    <?php echo $total_count === 1 ? 'oznaka' : 'oznak'; ?>
                    z manj kot <?php echo esc_html($threshold); ?> prispevki.
                </p>
            </div>

            <table class="lut-tags-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <th style="text-align: left; padding: 8px; font-weight: 600;">Oznaka</th>
                        <th style="text-align: center; padding: 8px; font-weight: 600; width: 80px;">Št. prisp.</th>
                        <th style="text-align: right; padding: 8px; font-weight: 600; width: 200px;">Dejanja</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_usage_tags as $tag): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;" data-tag-id="<?php echo esc_attr($tag->term_id); ?>">
                            <td style="padding: 8px;">
                                <span class="lut-tag-name"><?php echo esc_html($tag->name); ?></span>
                            </td>
                            <td style="text-align: center; padding: 8px;">
                                <span class="lut-count-badge lut-count-<?php echo $tag->count === 0 ? 'zero' : 'low'; ?>"
                                      style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 600;">
                                    <?php echo esc_html($tag->count); ?>
                                </span>
                            </td>
                            <td style="text-align: right; padding: 8px;">
                                <button type="button"
                                        class="button button-small lut-delete-btn"
                                        data-tag-id="<?php echo esc_attr($tag->term_id); ?>"
                                        data-tag-name="<?php echo esc_attr($tag->name); ?>"
                                        style="margin-left: 3px;">
                                    Izbriši
                                </button>
                                <button type="button"
                                        class="button button-small lut-merge-btn"
                                        data-tag-id="<?php echo esc_attr($tag->term_id); ?>"
                                        data-tag-name="<?php echo esc_attr($tag->name); ?>"
                                        style="margin-left: 3px;">
                                    Združi
                                </button>
                                <button type="button"
                                        class="button button-small lut-dismiss-btn"
                                        data-tag-id="<?php echo esc_attr($tag->term_id); ?>"
                                        data-tag-name="<?php echo esc_attr($tag->name); ?>"
                                        style="margin-left: 3px;">
                                    Skrij
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="lut-footer" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">
                <p style="margin: 0; font-size: 12px; color: #666;">
                    <a href="<?php echo admin_url('edit-tags.php?taxonomy=post_tag'); ?>">
                        Poglej vse oznake
                    </a>
                    <span style="margin: 0 5px;">•</span>
                    <a href="#" class="lut-settings-link">Nastavitve</a>
                </p>
            </div>
        <?php endif; ?>

        <div id="lut-loading" style="display: none; text-align: center; padding: 20px;">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
        </div>
    </div>

    <style>
        .lut-count-zero {
            background-color: #dc3232;
            color: white;
        }
        .lut-count-low {
            background-color: #f56e28;
            color: white;
        }
        .lut-tags-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        #lut-widget-container .notice {
            margin: 10px 0;
        }
    </style>
    <?php
}

/**
 * Enqueue scripts and styles for the widget
 */
add_action('admin_enqueue_scripts', 'lut_enqueue_scripts');
function lut_enqueue_scripts($hook) {
    // Only load on dashboard
    if ($hook !== 'index.php') {
        return;
    }

    if (!current_user_can('manage_categories')) {
        return;
    }

    // Enqueue WordPress dialog scripts
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');

    // Add inline JavaScript
    wp_add_inline_script('jquery', lut_get_inline_js());
}

/**
 * Get inline JavaScript for widget interactions
 */
function lut_get_inline_js() {
    $merge_targets = lut_get_merge_target_tags();
    $nonce = wp_create_nonce('lut_actions');

    ob_start();
    ?>
    jQuery(document).ready(function($) {
        const lutAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        const lutNonce = '<?php echo $nonce; ?>';

        // Delete tag handler
        $(document).on('click', '.lut-delete-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const tagId = btn.data('tag-id');
            const tagName = btn.data('tag-name');

            if (!confirm('Ali ste prepričani, da želite izbrisati oznako "' + tagName + '"?')) {
                return;
            }

            lutPerformAction('delete', tagId, null, btn);
        });

        // Merge tag handler
        $(document).on('click', '.lut-merge-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const tagId = btn.data('tag-id');
            const tagName = btn.data('tag-name');

            // Create merge dialog
            const allTargets = <?php echo json_encode(array_map(function($tag) {
                return ['id' => $tag->term_id, 'name' => $tag->name, 'count' => $tag->count];
            }, $merge_targets)); ?>;

            // Filter out the source tag itself
            const mergeTargets = allTargets.filter(function(target) {
                return target.id !== tagId;
            });

            if (mergeTargets.length === 0) {
                alert('Ni drugih oznak za združevanje.');
                return;
            }

            let options = '<option value="">-- Izberi ciljno oznako --</option>';
            mergeTargets.forEach(function(target) {
                options += '<option value="' + target.id + '">' + target.name + ' (' + target.count + ' prisp.)</option>';
            });

            const dialogHtml = '<div id="lut-merge-dialog" title="Združi oznako">' +
                '<p>Združi "<strong>' + tagName + '</strong>" v:</p>' +
                '<select id="lut-merge-target" style="width: 100%; padding: 5px;">' + options + '</select>' +
                '</div>';

            $('body').append(dialogHtml);

            $('#lut-merge-dialog').dialog({
                modal: true,
                width: 400,
                buttons: {
                    'Združi': function() {
                        const targetId = $('#lut-merge-target').val();
                        if (!targetId) {
                            alert('Prosim, izberi ciljno oznako.');
                            return;
                        }
                        $(this).dialog('close');
                        lutPerformAction('merge', tagId, targetId, btn);
                    },
                    'Prekliči': function() {
                        $(this).dialog('close');
                    }
                },
                close: function() {
                    $(this).remove();
                }
            });
        });

        // Dismiss tag handler
        $(document).on('click', '.lut-dismiss-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const tagId = btn.data('tag-id');
            const tagName = btn.data('tag-name');

            if (!confirm('Skrij oznako "' + tagName + '" za 30 dni?')) {
                return;
            }

            lutPerformAction('dismiss', tagId, null, btn);
        });

        // Settings link handler
        $(document).on('click', '.lut-settings-link', function(e) {
            e.preventDefault();
            const threshold = prompt('Vnesite prag za nizko uporabljene oznake (število prispevkov):', '3');
            if (threshold !== null && threshold !== '') {
                const thresholdNum = parseInt(threshold);
                if (thresholdNum > 0 && thresholdNum <= 10) {
                    lutSaveSettings(thresholdNum);
                } else {
                    alert('Vnesite število med 1 in 10.');
                }
            }
        });

        // Perform AJAX action
        function lutPerformAction(action, tagId, targetId, btn) {
            const container = $('#lut-widget-container');
            const loading = $('#lut-loading');
            const row = btn.closest('tr');

            // Show loading
            container.find('.lut-tags-table, .lut-header, .lut-footer').hide();
            loading.show();

            $.ajax({
                url: lutAjaxUrl,
                type: 'POST',
                data: {
                    action: 'lut_' + action + '_tag',
                    nonce: lutNonce,
                    tag_id: tagId,
                    target_id: targetId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove row with fade effect
                        row.fadeOut(300, function() {
                            $(this).remove();

                            // Reload widget if no rows left or show success
                            const remainingRows = $('.lut-tags-table tbody tr').length;
                            if (remainingRows === 0) {
                                location.reload();
                            } else {
                                // Update count
                                const newCount = remainingRows;
                                $('.lut-header strong').text(newCount);
                                container.find('.lut-tags-table, .lut-header, .lut-footer').show();
                                loading.hide();
                            }
                        });

                        // Show success notice
                        lutShowNotice(response.data.message, 'success');
                    } else {
                        loading.hide();
                        container.find('.lut-tags-table, .lut-header, .lut-footer').show();
                        lutShowNotice(response.data.message || 'Napaka pri izvajanju dejanja.', 'error');
                    }
                },
                error: function() {
                    loading.hide();
                    container.find('.lut-tags-table, .lut-header, .lut-footer').show();
                    lutShowNotice('Napaka pri povezavi s strežnikom.', 'error');
                }
            });
        }

        // Save settings
        function lutSaveSettings(threshold) {
            $.ajax({
                url: lutAjaxUrl,
                type: 'POST',
                data: {
                    action: 'lut_save_settings',
                    nonce: lutNonce,
                    threshold: threshold
                },
                success: function(response) {
                    if (response.success) {
                        lutShowNotice('Nastavitve shranjene. Stran se bo osvežila.', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        lutShowNotice('Napaka pri shranjevanju nastavitev.', 'error');
                    }
                },
                error: function() {
                    lutShowNotice('Napaka pri povezavi s strežnikom.', 'error');
                }
            });
        }

        // Show notice
        function lutShowNotice(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            $('#lut-widget-container').prepend(notice);

            // Auto dismiss after 3 seconds
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    });
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler: Delete tag
 */
add_action('wp_ajax_lut_delete_tag', 'lut_ajax_delete_tag');
function lut_ajax_delete_tag() {
    check_ajax_referer('lut_actions', 'nonce');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'Nimate dovoljenja za to dejanje.']);
    }

    $tag_id = isset($_POST['tag_id']) ? absint($_POST['tag_id']) : 0;

    if (!$tag_id) {
        wp_send_json_error(['message' => 'Neveljaven ID oznake.']);
    }

    $tag = get_term($tag_id, 'post_tag');
    if (is_wp_error($tag)) {
        wp_send_json_error(['message' => 'Oznaka ne obstaja.']);
    }

    $result = wp_delete_term($tag_id, 'post_tag');

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success(['message' => 'Oznaka "' . $tag->name . '" je bila uspešno izbrisana.']);
}

/**
 * AJAX handler: Merge tag
 */
add_action('wp_ajax_lut_merge_tag', 'lut_ajax_merge_tag');
function lut_ajax_merge_tag() {
    check_ajax_referer('lut_actions', 'nonce');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'Nimate dovoljenja za to dejanje.']);
    }

    $tag_id = isset($_POST['tag_id']) ? absint($_POST['tag_id']) : 0;
    $target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;

    if (!$tag_id || !$target_id) {
        wp_send_json_error(['message' => 'Neveljaven ID oznake.']);
    }

    if ($tag_id === $target_id) {
        wp_send_json_error(['message' => 'Izvorna in ciljna oznaka ne smeta biti enaki.']);
    }

    $source_tag = get_term($tag_id, 'post_tag');
    $target_tag = get_term($target_id, 'post_tag');

    if (is_wp_error($source_tag) || is_wp_error($target_tag)) {
        wp_send_json_error(['message' => 'Ena ali obe oznaki ne obstajata.']);
    }

    // Get all posts with source tag
    $posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tag_id
            ]
        ]
    ]);

    $merged_count = 0;

    // Reassign posts to target tag
    foreach ($posts as $post) {
        // Remove source tag
        wp_remove_object_terms($post->ID, $tag_id, 'post_tag');

        // Add target tag (append mode)
        wp_set_object_terms($post->ID, $target_id, 'post_tag', true);

        $merged_count++;
    }

    // Delete source tag
    wp_delete_term($tag_id, 'post_tag');

    wp_send_json_success([
        'message' => 'Oznaka "' . $source_tag->name . '" je bila združena v "' . $target_tag->name . '" (' . $merged_count . ' prisp.).'
    ]);
}

/**
 * AJAX handler: Dismiss tag
 */
add_action('wp_ajax_lut_dismiss_tag', 'lut_ajax_dismiss_tag');
function lut_ajax_dismiss_tag() {
    check_ajax_referer('lut_actions', 'nonce');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'Nimate dovoljenja za to dejanje.']);
    }

    $tag_id = isset($_POST['tag_id']) ? absint($_POST['tag_id']) : 0;

    if (!$tag_id) {
        wp_send_json_error(['message' => 'Neveljaven ID oznake.']);
    }

    $tag = get_term($tag_id, 'post_tag');
    if (is_wp_error($tag)) {
        wp_send_json_error(['message' => 'Oznaka ne obstaja.']);
    }

    // Set transient for 30 days
    set_transient('lut_dismissed_' . $tag_id, true, 30 * DAY_IN_SECONDS);

    wp_send_json_success(['message' => 'Oznaka "' . $tag->name . '" je bila skrita za 30 dni.']);
}

/**
 * AJAX handler: Save settings
 */
add_action('wp_ajax_lut_save_settings', 'lut_ajax_save_settings');
function lut_ajax_save_settings() {
    check_ajax_referer('lut_actions', 'nonce');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'Nimate dovoljenja za to dejanje.']);
    }

    $threshold = isset($_POST['threshold']) ? absint($_POST['threshold']) : 3;

    if ($threshold < 1 || $threshold > 10) {
        wp_send_json_error(['message' => 'Prag mora biti med 1 in 10.']);
    }

    update_option('lut_threshold', $threshold);

    wp_send_json_success(['message' => 'Nastavitve so bile shranjene.']);
}

/**
 * Set default options on plugin activation
 */
register_activation_hook(__FILE__, 'lut_set_default_options');
function lut_set_default_options() {
    add_option('lut_threshold', 3);
    add_option('lut_widget_limit', 15);
}
