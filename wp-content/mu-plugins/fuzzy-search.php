<?php
/*
Plugin Name: Force Fuzzy Scanner
Description: Adds a fuzzy tag search to the admin menu with Smart Sorting (Shortest First).
*/

add_action('admin_menu', 'register_fuzzy_scanner_menu', 999);

function register_fuzzy_scanner_menu() {
    add_menu_page(
        'Fuzzy Tag Scanner',
        'Fuzzy Scanner',
        'manage_options',
        'fuzzy-tag-scanner',
        'render_fuzzy_scanner_page',
        'dashicons-search',
        2
    );
}

// Helper: Find longest shared text for the search button
function get_smart_search_fragment($str1, $str2) {
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

function render_fuzzy_scanner_page() {
    @ini_set('memory_limit', '256M');
    set_time_limit(300);

    // 1. Get all tags
    $tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => 0]);

    echo '<div class="wrap"><h1>Fuzzy Tag Scanner (Optimized)</h1>';

    if ( is_wp_error( $tags ) || empty( $tags ) ) {
        echo '<div class="notice notice-error"><p>No tags found.</p></div></div>';
        return;
    }

    // 2. CRITICAL FIX: Sort tags by length (Shortest word becomes the "Parent" Scanner)
    // This ensures "brin" runs before "b-rin", allowing it to capture "brina" too.
    usort($tags, function($a, $b) {
        return strlen($a->name) - strlen($b->name);
    });

    echo '<div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">';
    echo '<h3>Scanning ' . count($tags) . ' tags...</h3>';
    echo '<p>Sorted by shortest name first to catch variations like <em>brina</em> and <em>b-rin</em> in one group.</p><hr>';

    $processed_ids = [];
    $found_matches = false;

    foreach ($tags as $tag_a) {
        if (in_array($tag_a->term_id, $processed_ids)) continue;

        $group = [];

        foreach ($tags as $tag_b) {
            if ($tag_a->term_id === $tag_b->term_id) continue;
            if (in_array($tag_b->term_id, $processed_ids)) continue;

            // 3. FORCE LOWERCASE for comparisons to fix case-sensitivity issues
            $name_a = strtolower($tag_a->name);
            $name_b = strtolower($tag_b->name);

            $dist = levenshtein($name_a, $name_b);

            // Logic: strict distance OR contained string (ignoring hyphens)
            $clean_a = str_replace(['-', ' '], '', $name_a);
            $clean_b = str_replace(['-', ' '], '', $name_b);
            $is_similar_clean = ($clean_a == $clean_b);

            // Progressive distance based on tag length for better precision
            if (strlen($name_a) <= 3) {
                $allowable_dist = 1;  // Very strict for 3-char tags
            } elseif (strlen($name_a) <= 5) {
                $allowable_dist = 2;  // Moderate for 4-5 char tags
            } else {
                $allowable_dist = 2;  // Stricter for 6+ char tags to prevent false matches
            }

            if ($dist <= $allowable_dist || $is_similar_clean) {
                $group[] = $tag_b;
                $processed_ids[] = $tag_b->term_id;
            }
        }

        if (!empty($group)) {
            $found_matches = true;
            $processed_ids[] = $tag_a->term_id;

            // Calculate best search term from the group
            $smart_term = get_smart_search_fragment($tag_a->name, $group[0]->name);

            // TaxoPress Link
            $base_url = admin_url('admin.php');
            $params = [
                'page' => 'st_terms',
                'terms_filter_post_type' => '',
                'terms_filter_taxonomy' => 'post_tag',
                'taxonomy_type' => 'public',
                's' => $smart_term
            ];
            $taxopress_url = add_query_arg($params, $base_url);

            echo '<div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
            echo '<h2 style="margin-top:0; font-size:16px;">Group: <span style="color:#0073aa;">' . esc_html($tag_a->name) . '</span></h2>';

            echo '<ul style="margin: 10px 0 15px 20px; list-style: disc;">';
            echo '<li><strong>' . esc_html($tag_a->name) . '</strong> <span style="color:#999; font-size: 11px;">(ID: ' . $tag_a->term_id . ')</span></li>';
            foreach ($group as $match) {
                echo '<li>' . esc_html($match->name) . ' <span style="color:#999; font-size: 11px;">(ID: ' . $match->term_id . ')</span></li>';
            }
            echo '</ul>';

            echo '<a href="' . esc_url($taxopress_url) . '" target="_blank" class="button button-primary">Smart Search: "' . esc_html($smart_term) . '"</a> ';
            echo '<span style="color:#666; font-size:12px; margin-left:10px;">Opens TaxoPress filtered by shared text.</span>';
            echo '</div>';
        }
    }

    if (!$found_matches) {
        echo '<p style="color: green; font-weight: bold;">No fuzzy duplicates found!</p>';
    }

    echo '</div></div>';
}