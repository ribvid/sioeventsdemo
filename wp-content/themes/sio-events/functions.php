<?php

use Roots\Acorn\Application;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our theme. We will simply require it into the script here so that we
| don't have to worry about manually loading any of our classes later on.
|
*/

if (!file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    wp_die(__('Error locating autoloader. Please run <code>composer install</code>.', 'sage'));
}

require $composer;

/*
|--------------------------------------------------------------------------
| Register The Bootloader
|--------------------------------------------------------------------------
|
| The first thing we will do is schedule a new Acorn application container
| to boot when WordPress is finished loading the theme. The application
| serves as the "glue" for all the components of Laravel and is
| the IoC container for the system binding all of the various parts.
|
*/

Application::configure()
    ->withProviders([
        App\Providers\ThemeServiceProvider::class,
    ])
    ->withRouting(web: base_path('routes/web.php'))
    ->boot();

/*
|--------------------------------------------------------------------------
| Register Sage Theme Files
|--------------------------------------------------------------------------
|
| Out of the box, Sage ships with categorically named theme files
| containing common functionality and setup to be bootstrapped with your
| theme. Simply add (or remove) files from the array below to change what
| is registered alongside Sage.
|
*/

collect(['setup', 'filters', 'acf-field-types', 'course-session-functions', 'ticket-generator', 'email-template-generator'])
    ->each(function ($file) {
        if (!locate_template($file = "app/{$file}.php", true, true)) {
            wp_die(
            /* translators: %s is replaced with the relative file path */
                sprintf(__('Error locating <code>%s</code> for inclusion.', 'sage'), $file)
            );
        }
    });

/**
 * Set the page <title> for Laravel routes.
 */
add_filter('pre_get_document_title', function () {
    $routeName = Route::currentRouteName();

    return match ($routeName) {
        'pattern-library' => 'Pattern Library',
        'prose' => 'Global CSS & Prose',
        'disclosure' => 'Disclosure',
        'accordion' => 'Accordion',
        default => $routeName,
    };
});

/**
 * Disable Gutenberg editor.
 */
#add_filter('use_block_editor_for_post', '__return_false', 10);
#add_filter('use_widgets_block_editor', '__return_false');
#add_filter('gutenberg_use_widgets_block_editor', '__return_false', 100);

/**
 * Disable comments on the front-end.
 */
add_filter('comments_open', '__return_false');
add_filter('pings_open', '__return_false');

/**
 * Disable XML-RPC.
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Add Google Analytics, favicons, and Open Graph tags.
 */
add_action('wp_head', function () {
    ?>
    <!-- Google tag (gtag.js) -->

    <!-- Favicons -->
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="<?php echo esc_url(asset('resources/images/icon.svg')) ?>" type="image/svg+xml">

    <?php
    display_og_tags();
});

/**
 * Output Open Graph meta tags.
 */
function display_og_tags(): void
{
    global $wp;

    $title = esc_attr(get_bloginfo("name"));
    $url = home_url($wp->request);
    $image = esc_url(asset('resources/images/og-image.jpg'));
    $description = '';

    if (is_singular() && !is_front_page() && !is_home()) {
        $title = get_the_title();
        $url = get_permalink();
        $image = has_post_thumbnail() ? get_the_post_thumbnail_url() : $image;
        $description = get_the_excerpt();
    }

    $meta_tags = [
        'og:type' => 'website',
        'og:title' => $title,
        'og:url' => $url,
        'og:image' => $image,
        'og:description' => $description,

        'twitter:card' => 'summary_large_image',
        'twitter:title' => $title,
        'twitter:url' => $url,
        'twitter:image' => $image,
        'twitter:description' => $description,
    ];

    foreach ($meta_tags as $property => $content) {
        printf('<meta property="%s" content="%s" />' . "\n", esc_attr($property), esc_attr($content));
    }
}

/**
 * Allow <svg> and <path> tags in ACF fields.
 */
add_filter('wp_kses_allowed_html', function ($tags, $context) {
    if ($context === 'acf') {
        $tags['svg'] = [
            'xmlns' => true,
            'fill' => true,
            'viewbox' => true,
            'role' => true,
            'aria-hidden' => true,
            'aria-label' => true,
            'focusable' => true,
        ];
        $tags['path'] = [
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
        ];
    }

    return $tags;
}, 10, 2);

/**
 * Remove prefix from archive titles.
 */
add_filter('get_the_archive_title_prefix', '__return_empty_string');

/**
 * Replace the default <input type="submit"> with a <button type="submit"> in Contact Form 7.
 */
if (function_exists('wpcf7_add_form_tag')) {
    wpcf7_add_form_tag('submit', function ($tag) {
        $class = wpcf7_form_controls_class($tag->type, 'has-spinner');

        $atts = [];

        $atts['class'] = $tag->get_class_option($class);
        $atts['id'] = $tag->get_id_option();
        $atts['tabindex'] = $tag->get_option('tabindex', 'signed_int', true);

        $value = isset($tag->values[0]) ? $tag->values[0] : '';

        if (empty($value)) {
            $value = __('Send', 'contact-form-7');
        }

        $atts['type'] = 'submit';

        $atts = wpcf7_format_atts($atts);

        $html = sprintf('<button type="submit" %1$s>%2$s</button>', $atts, $value);

        return $html;
    });
}

/**
 * Add Sage's compiled templates directory to Polylang's URL whitelist.
 * This fixes an issue where Polylang doesn't properly translate URLs
 * (home_url, get_post_type_archive_link, etc.) within Blade templates.
 */
add_filter('pll_home_url_white_list', function ($whiteList) {
    $whiteList[] = [
        "file" => config('view.compiled'),
    ];
    return $whiteList;
});

/**
 * Register custom post types.
 */
add_action('init', function () {
    register_post_type('course', [
        'label' => 'Izobraževanje',
        'labels' => [
            'name' => 'Izobraževanja',
            'singular_name' => 'Izobraževanje',
            'add_new' => 'Dodaj izobraževanje',
            'add_new_item' => 'Dodaj izobraževanje',
            'edit_item' => 'Uredi izobraževanje',
            'new_item' => 'Novo izobraževanje',
            'view_item' => 'Poglej izobraževanje',
            'view_items' => 'Poglej izobraževanja',
            'search_items' => 'Poišči izobraževanje',
            'not_found' => 'Ni najdenih izobraževanj',
            'not_found_in_trash' => 'Ni najdenih izobraževanj',
            'all_items' => 'Vsa izobraževanja',
            'items_list' => 'Seznam izobraževanj',
        ],
        'public' => true,
        'supports' => [
            'title',
            'editor',
            'excerpt',
            'thumbnail',
        ],
        'has_archive' => 'izobrazevanja',
        'rewrite' => [
            'slug' => 'izobrazevanje',
        ],
    ]);

    register_post_type('course_session', [
        'label' => 'Izvedbe izobraževanj',
        'labels' => [
            'name' => 'Izvedba izobraževanja',
            'singular_name' => 'Izvedbe izobraževanj',
            'add_new' => 'Dodaj izvedbo',
            'add_new_item' => 'Dodaj izvedbo',
            'edit_item' => 'Uredi izvedbo',
            'new_item' => 'Nova izvedba',
            'view_item' => 'Poglej izvedbo',
            'view_items' => 'Poglej izvedbe',
            'search_items' => 'Poišči izvedbo',
            'not_found' => 'Ni najdenih izvedb',
            'not_found_in_trash' => 'Ni najdenih izvedb',
            'all_items' => 'Vse izvedbe',
            'items_list' => 'Seznam izvedb',
        ],
        'public' => true,
        'supports' => [
            'title',
        ],
        'has_archive' => 'izvedbe-izobrazevanj',
        'rewrite' => [
            'slug' => 'izvedba',
        ],
    ]);

    register_post_type('ticket', [
        'label' => 'Vstopnica',
        'labels' => [
            'name' => 'Vstopnice',
            'singular_name' => 'Vstopnica',
            'add_new' => 'Dodaj vstopnico',
            'add_new_item' => 'Dodaj vstopnico',
            'edit_item' => 'Uredi vstopnico',
            'new_item' => 'Nova vstopnico',
            'view_item' => 'Poglej vstopnico',
            'view_items' => 'Poglej vstopnice',
            'search_items' => 'Poišči vstopnico',
            'not_found' => 'Ni najdenih vstopnic',
            'not_found_in_trash' => 'Ni najdenih vstopnic',
            'all_items' => 'Vstopnice',
            'items_list' => 'Seznam vstopnic',
        ],
        'public' => true,
        'show_in_menu' => 'edit.php?post_type=course_session',
        'supports' => [
            'title',
        ],
        'has_archive' => false,
    ]);

    register_post_type('attendance_sheet', [
        'label' => 'Podpisni list',
        'labels' => [
            'name' => 'Podpisni listi',
            'singular_name' => 'Podpisni list',
            'add_new' => 'Dodaj podpisni list',
            'add_new_item' => 'Dodaj podpisni list',
            'edit_item' => 'Uredi podpisni list',
            'new_item' => 'Nov podpisni list',
            'view_item' => 'Poglej podpisni list',
            'view_items' => 'Poglej podpisne liste',
            'search_items' => 'Poišči podpisni list',
            'not_found' => 'Ni najdenih podpisnih listov',
            'not_found_in_trash' => 'Ni najdenih podpisnih listov',
            'all_items' => 'Podpisni listi',
            'items_list' => 'Seznam podpisnih listov',
        ],
        'public' => true,
        'show_in_menu' => 'edit.php?post_type=course_session',
        'supports' => [
            'title',
        ],
        'has_archive' => false,
    ]);

    register_post_type('certificate', [
        'label' => 'Potrdilo',
        'labels' => [
            'name' => 'Potrdila',
            'singular_name' => 'Potrdilo',
            'add_new' => 'Dodaj potrdilo',
            'add_new_item' => 'Dodaj potrdilo',
            'edit_item' => 'Uredi potrdilo',
            'new_item' => 'Novo potrdilo',
            'view_item' => 'Poglej potrdilo',
            'view_items' => 'Poglej potrdila',
            'search_items' => 'Poišči potrdilo',
            'not_found' => 'Ni najdenih potrdil',
            'not_found_in_trash' => 'Ni najdenih potrdil',
            'all_items' => 'Potrdila',
            'items_list' => 'Seznam potrdil',
        ],
        'public' => true,
        'show_in_menu' => 'edit.php?post_type=course_session',
        'supports' => [
            'title',
        ],
        'has_archive' => false,
    ]);

    register_post_type('email_template', [
        'label' => 'E-poštni vzorec',
        'labels' => [
            'name' => 'E-poštni vzorci',
            'singular_name' => 'E-poštni vzorec',
            'add_new' => 'Dodaj e-poštni vzorec',
            'add_new_item' => 'Dodaj e-poštni vzorec',
            'edit_item' => 'Uredi e-poštni vzorec',
            'new_item' => 'Nov e-poštni vzorec',
            'view_item' => 'Poglej e-poštni vzorec',
            'view_items' => 'Poglej e-poštne vzorce',
            'search_items' => 'Poišči e-poštni vzorec',
            'not_found' => 'Ni najdenih e-poštnih vzorcev',
            'not_found_in_trash' => 'Ni najdenih e-poštnih vzorcev',
            'all_items' => 'E-poštne vzorce',
            'items_list' => 'Seznam e-poštnih vzorcev',
        ],
        'public' => true,
        'show_in_menu' => 'edit.php?post_type=course_session',
        'supports' => [
            'title',
        ],
        'has_archive' => false,
    ]);
});

/**
 * Register custom taxonomies.
 */
add_action('init', function () {
    register_taxonomy('course_category', 'course', [
        'label' => 'Kategorija izobraževanja',
        'labels' => [
            'name' => 'Kategorije izobraževanj',
            'singular_name' => 'Kategorija izobraževanja',
            'menu_name' => 'Kategorije',
            'all_items' => 'Vse kategorije',
            'edit_item' => 'Uredi kategorijo',
            'view_item' => 'Poglej kategorijo',
            'update_item' => 'Posodobi kategorijo',
            'add_new_item' => 'Dodaj novo kategorijo',
            'new_item_name' => 'Novo ime kategorije',
            'parent_item' => 'Nadrejena kategorija',
            'parent_item_colon' => 'Nadrejena kategorija:',
            'search_items' => 'Poišči kategorije',
            'popular_items' => 'Priljubljene kategorije',
            'not_found' => 'Ni najdenih kategorij',
            'back_to_items' => 'Nazaj na kategorije',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'rewrite' => [
            'slug' => 'kategorija-izobrazevanja',
            'with_front' => true,
            'hierarchical' => true,
        ],
    ]);
});

/**
 * Register ACF options pages.
 */
add_action('acf/init', function () {
    if (function_exists('acf_add_options_page')) {
//        acf_add_options_page([
//            'page_title' => 'Primer',
//            'menu_title' => 'Primer',
//            'menu_slug' => 'example',
//            'position' => 30,
//            'capability' => 'edit_posts',
//            'redirect' => false
//        ]);
    }
});

//add_action('gform_entry_detail_sidebar_middle', function ($form, $entry) {
//
//    $status_field_id = 24; // status
//    $current_status = rgar($entry, $status_field_id);
//
//    if ($current_status === 'attended') {
//        $url = add_query_arg([
//            'gf_mark_attended' => 2,
//            'entry_id' => $entry['id'],
//            '_wpnonce' => wp_create_nonce('gf_mark_attended')
//        ]);
//
//        echo '<a class="button button-primary" href="' . esc_url($url) . '">
//        Označi kot preklican
//    </a>';
//    } else {
//        $url = add_query_arg([
//            'gf_mark_attended' => 1,
//            'entry_id' => $entry['id'],
//            '_wpnonce' => wp_create_nonce('gf_mark_attended')
//        ]);
//
//        echo '<a class="button button-primary" href="' . esc_url($url) . '">
//        Označi kot prisoten
//    </a>';
//    }
//
//}, 10, 2);

//add_action('admin_init', function () {
//
//    if (
//        empty($_GET['gf_mark_attended']) ||
//        empty($_GET['entry_id']) ||
//        !wp_verify_nonce($_GET['_wpnonce'], 'gf_mark_attended')
//    ) {
//        return;
//    }
//
//    $entry_id = absint($_GET['entry_id']);
//    $attended = $_GET['gf_mark_attended'] === "1";
//
//    // Update status field (24)
//    GFAPI::update_entry_field(
//        $entry_id,
//        24,
//        $attended ? 'attended' : 'cancelled'
//    );
//
//    // Update timeline field (23)
//    GFAPI::update_entry_field(
//        $entry_id,
//        23,
//        $attended ? current_time('mysql') : ''
//    );
//
//
//    wp_safe_redirect(remove_query_arg([
//        'gf_mark_attended',
//        'entry_id',
//        '_wpnonce'
//    ]));
//    exit;
//});

//add_filter('gform_entries_column_filter', function ($value, $form_id, $field_id, $entry) {
//
//    if ($field_id !== "24") {
//        return $value;
//    }
//
//    if ($value === 'attended') {
//        $url = add_query_arg([
//            'gf_mark_attended' => 2,
//            'entry_id' => $entry['id'],
//            '_wpnonce' => wp_create_nonce('gf_mark_attended')
//        ]);
//
//        return '<a href="' . esc_url($url) . '">✖️ Označi kot odpovedan</a>';
//    }
//
//    $url = add_query_arg([
//        'gf_mark_attended' => 1,
//        'entry_id' => $entry['id'],
//        '_wpnonce' => wp_create_nonce('gf_mark_attended')
//    ]);
//
//    return '<a href="' . esc_url($url) . '">✔️ Označi kot prisoten</a>';
//
//}, 10, 4);

add_filter('query_vars', 'attendance_query_vars');
function attendance_query_vars($vars)
{
    $vars[] = 'attendance_entry';
    return $vars;
}

function get_qr_code($entry_id)
{
    $url = home_url("/belezenje-prisotnosti/?attendance_entry={$entry_id}");

    $result = new Builder(
        writer: new PngWriter(),
        data: $url,
        encoding: new Encoding('UTF-8'),
        size: 200,
        margin: 10,
    );

    // Convert QR code to a base64 string for inline display
    return $result->build();
}

function validate_qr_code($entry_id)
{
    if (!GFAPI::entry_exists($entry_id)) {
        return __('Neveljavna prijava', 'sage');
    }

    if (!current_user_can('manage_options')) {
        return __('Prisotnost lahko zabeleži samo administrator sistema', 'sage');
    }

    $entry = GFAPI::get_entry($entry_id);

    $status_field_id = 4;
    $attendance_time_field_id = 5;

    if ($entry[$status_field_id] === "cancelled") {
        return __('Prijava je umaknjena', 'sage');
    }

    if (!empty($entry[$attendance_time_field_id])) {
        return __('Pristnost je že zabeležena', 'sage');
    }

    $current_time = current_time('Y-m-d H:i:s');

    GFAPI::update_entry_field($entry_id, $attendance_time_field_id, $current_time);
    GFAPI::update_entry_field($entry_id, $status_field_id, 'attended');

    return __('Prisotnost je uspešno zabeležena', 'sage');
}
