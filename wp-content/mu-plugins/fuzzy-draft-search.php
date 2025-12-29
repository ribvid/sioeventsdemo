<?php
/*
Plugin Name: Fuzzy Draft Tag Scanner
Description: Scans both WordPress tags and draft tags for similar/duplicate entries using Levenshtein distance.
*/

add_action('admin_menu', 'register_fuzzy_draft_scanner_menu', 999);

function register_fuzzy_draft_scanner_menu() {
    add_menu_page(
        'Fuzzy Draft Scanner',
        'Fuzzy Draft',
        'manage_options',
        'fuzzy-draft-scanner',
        'render_fuzzy_draft_scanner_page',
        'dashicons-search',
        3  // Position after Fuzzy Scanner (which is at 2)
    );
}

// Helper: Find longest shared text for the search button
function get_smart_search_fragment_draft($str1, $str2) {
    $str1 = strtolower($str1);
    $str2 = strtolower($str2);
    $l1 = strlen($str1);
    $l2 = strlen($str2);
    $longest = '';

    for ($i = 0; $i < $l1; $i++) {
        for ($j = 0; $j < $l2; $j++) {
            $len = 0;
            while (($i + $len < $l1) && ($j + $len < $l2) && ($str1[$i + $len] == $str2[$j + $len])) {
                $len++;
            }
            if ($len > strlen($longest)) {
                $longest = substr($str1, $i, $len);
            }
        }
    }
    return (strlen($longest) >= 3) ? $longest : $str1;
}

function render_fuzzy_draft_scanner_page() {
    @ini_set('memory_limit', '256M');
    set_time_limit(300);

    // 1. Get all WordPress tags
    $tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => 0]);

    echo '<div class="wrap"><h1>Fuzzy Draft Tag Scanner</h1>';

    if (is_wp_error($tags)) {
        $tags = [];
    }

    // 2. Get all draft tags from posts/courses
    $draft_tag_posts = new WP_Query([
        'post_type' => ['post', 'course'],
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'draft_tag',
                'value' => '',
                'compare' => '!='
            ]
        ]
    ]);

    // 3. Build unified items array
    $items = [];

    // Add WordPress tags
    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $items[] = [
                'name' => $tag->name,
                'type' => 'tag',
                'id' => $tag->term_id,
                'post_id' => null,
                'post_title' => null
            ];
        }
    }

    // Add draft tags (split by comma)
    if ($draft_tag_posts->have_posts()) {
        while ($draft_tag_posts->have_posts()) {
            $draft_tag_posts->the_post();
            $draft_tag_value = get_post_meta(get_the_ID(), 'draft_tag', true);

            // Split by comma and trim
            $draft_tag_array = array_map('trim', explode(',', $draft_tag_value));
            $draft_tag_array = array_filter($draft_tag_array);

            foreach ($draft_tag_array as $draft_tag) {
                $items[] = [
                    'name' => $draft_tag,
                    'type' => 'draft_tag',
                    'id' => null,
                    'post_id' => get_the_ID(),
                    'post_title' => get_the_title()
                ];
            }
        }
        wp_reset_postdata();
    }

    echo '<div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">';
    echo '<h3>Scanning ' . count($items) . ' items (' . count($tags) . ' tags + ' . ($draft_tag_posts->found_posts) . ' draft tag sources)...</h3>';
    echo '<p>Sorted by shortest name first. Comparing WordPress tags and draft tags for duplicates.</p><hr>';

    if (empty($items)) {
        echo '<p style="color: green; font-weight: bold;">No tags or draft tags found!</p></div></div>';
        return;
    }

    // 4. Sort by length (shortest first)
    usort($items, function($a, $b) {
        return strlen($a['name']) - strlen($b['name']);
    });

    $processed_ids = [];
    $found_matches = false;

    // 5. Comparison loop
    foreach ($items as $item_a) {
        // Create unique identifier for this item
        $id_a = $item_a['type'] . '_' . ($item_a['type'] === 'tag' ? $item_a['id'] : $item_a['post_id'] . '_' . $item_a['name']);

        if (in_array($id_a, $processed_ids)) continue;

        $group = [];

        foreach ($items as $item_b) {
            // Create unique identifier for comparison item
            $id_b = $item_b['type'] . '_' . ($item_b['type'] === 'tag' ? $item_b['id'] : $item_b['post_id'] . '_' . $item_b['name']);

            if ($id_a === $id_b) continue;
            if (in_array($id_b, $processed_ids)) continue;

            // FORCE LOWERCASE for comparisons
            $name_a = strtolower($item_a['name']);
            $name_b = strtolower($item_b['name']);

            $dist = levenshtein($name_a, $name_b);

            // Logic: strict distance OR contained string (ignoring hyphens)
            $clean_a = str_replace(['-', ' '], '', $name_a);
            $clean_b = str_replace(['-', ' '], '', $name_b);
            $is_similar_clean = ($clean_a == $clean_b);

            // Progressive distance based on tag length
            if (strlen($name_a) <= 3) {
                $allowable_dist = 1;  // Very strict for 3-char tags
            } elseif (strlen($name_a) <= 5) {
                $allowable_dist = 2;  // Moderate for 4-5 char tags
            } else {
                $allowable_dist = 3;  // Looser for 6+ char tags
            }

            if ($dist <= $allowable_dist || $is_similar_clean) {
                $group[] = $item_b;
                $processed_ids[] = $id_b;
            }
        }

        if (!empty($group)) {
            $found_matches = true;
            $processed_ids[] = $id_a;

            // Calculate best search term from the group
            $smart_term = get_smart_search_fragment_draft($item_a['name'], $group[0]['name']);

            // Determine which buttons to show
            $has_tags = ($item_a['type'] === 'tag') || in_array('tag', array_column($group, 'type'));
            $has_drafts = ($item_a['type'] === 'draft_tag') || in_array('draft_tag', array_column($group, 'type'));

            echo '<div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
            echo '<h2 style="margin-top:0; font-size:16px;">Group: <span style="color:#0073aa;">' . esc_html($item_a['name']) . '</span></h2>';

            echo '<ul style="margin: 10px 0 15px 20px; list-style: disc;">';

            // Display main item with type badge
            $type_badge_a = ($item_a['type'] === 'tag')
                ? '<span style="background:#0073aa;color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">TAG</span>'
                : '<span style="background:#f0ad4e;color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">DRAFT</span>';

            $id_display_a = ($item_a['type'] === 'tag')
                ? '<span style="color:#999; font-size: 11px;">(ID: ' . $item_a['id'] . ')</span>'
                : '';

            echo '<li><strong>' . esc_html($item_a['name']) . '</strong> ' . $type_badge_a . ' ' . $id_display_a . '</li>';

            // Display matched items with type badges
            foreach ($group as $match) {
                $type_badge = ($match['type'] === 'tag')
                    ? '<span style="background:#0073aa;color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">TAG</span>'
                    : '<span style="background:#f0ad4e;color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;">DRAFT</span>';

                $id_display = ($match['type'] === 'tag')
                    ? '<span style="color:#999; font-size: 11px;">(ID: ' . $match['id'] . ')</span>'
                    : '';

                echo '<li>' . esc_html($match['name']) . ' ' . $type_badge . ' ' . $id_display . '</li>';
            }
            echo '</ul>';

            // Action buttons
            if ($has_tags) {
                // TaxoPress link for tag management
                $base_url = admin_url('admin.php');
                $params = [
                    'page' => 'st_terms',
                    'terms_filter_post_type' => '',
                    'terms_filter_taxonomy' => 'post_tag',
                    'taxonomy_type' => 'public',
                    's' => $smart_term
                ];
                $taxopress_url = add_query_arg($params, $base_url);

                echo '<a href="' . esc_url($taxopress_url) . '" target="_blank" class="button button-primary">TaxoPress: "' . esc_html($smart_term) . '"</a> ';
            }

            if ($has_drafts) {
                // Link to draft tag review page
                $draft_review_url = admin_url('edit.php?page=pregled-draft-oznak');
                echo '<a href="' . esc_url($draft_review_url) . '" class="button button-secondary" style="margin-left:5px;">Review Draft Tags</a> ';
            }

            echo '<span style="color:#666; font-size:12px; margin-left:10px;">Use these tools to manage similar items.</span>';
            echo '</div>';
        }
    }

    if (!$found_matches) {
        echo '<p style="color: green; font-weight: bold;">No fuzzy duplicates found!</p>';
    }

    echo '</div></div>';
}
