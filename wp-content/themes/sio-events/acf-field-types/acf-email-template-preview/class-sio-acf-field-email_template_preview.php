<?php

if (!defined('ABSPATH')) {
    exit;
}

class sio_acf_field_email_template_preview extends \acf_field
{
    public $show_in_rest = true;

    private $env;

    public function __construct()
    {
        $this->name = 'email_template_preview';
        $this->label = __('Email Template Preview', 'sage');
        $this->category = 'relational';
        $this->description = __('Email template preview with thumbnails', 'sage');
        $this->doc_url = '';
        $this->tutorial_url = '';

        $this->defaults = array(
            'max_columns' => 3,
            'preview_height' => 60,
            'preview_scale' => 0.2,
            'show_email_type' => true,
        );

        $this->l10n = array(
            'select_template' => __('Izberite vzorec e-pošte', 'sage'),
            'no_templates' => __('Ni e-poštnih vzorcev', 'sage'),
            'loading' => __('Nalagam...', 'sage'),
            'view_full' => __('Ogled v polni velikosti', 'sage'),
        );

        $theme_uri = get_template_directory_uri();
        $this->env = array(
            'url' => $theme_uri . '/acf-field-types/acf-email-template-preview',
            'version' => '1.0',
        );

        $this->preview_image = $this->env['url'] . '/assets/images/field-preview-custom.png';

        parent::__construct();

        add_action('wp_ajax_acf_email_template_preview_load', array($this, 'ajax_load_templates'));
    }

    public function ajax_load_templates()
    {
        error_log('=== EMAIL TEMPLATE PREVIEW AJAX START ===');

        // Verify nonce (dies with -1 on failure by default)
        check_ajax_referer('acf_nonce', 'nonce', true);

        error_log('Nonce verified successfully');

        $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';

        $field_key_map = [
            'field_6952b75bregistration_template' => 'registration',
            'field_6952b75dcancellation_template' => 'registration_cancellation',
            'field_6952b760reminder_template' => 'x_days_before',
            'field_6952b762moodle_template' => 'added_to_moodle',
            'field_6952b764course_cancellation_template' => 'course_cancellation',
            'field_6952b766course_finished_template' => 'course_finished',
        ];

        $email_type = '';

        if (strpos($field_name, 'acf[') === 0) {
            $field_key = str_replace('acf[', '', str_replace(']', '', $field_name));
            $email_type = $field_key_map[$field_key] ?? '';
        }

        error_log('Field name: ' . $field_name);
        error_log('Email type filter: ' . $email_type);

        $args = array(
            'post_type' => 'email_template',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        if ($email_type) {
            $args['meta_query'] = array(
                array(
                    'key' => 'email_type',
                    'value' => $email_type,
                    'compare' => '='
                )
            );
        }

        $templates = get_posts($args);

        error_log('Found ' . count($templates) . ' published email templates');

        $template_data = array();

        foreach ($templates as $template) {
            $thumbnail_url = get_post_meta($template->ID, '_email_thumbnail_url', true);
            $html_url = get_post_meta($template->ID, '_email_html_url', true);
            $email_type = get_field('email_type', $template->ID);

            error_log('Template ID ' . $template->ID . ': thumbnail=' . ($thumbnail_url ? 'YES' : 'NO') . ', email_type=' . $email_type);

            if (!$thumbnail_url) {
                $upload_dir = wp_upload_dir();
                $thumbnail_url = $upload_dir['baseurl'] . '/email-templates/thumbs/placeholder.png';
            }

            $template_data[] = array(
                'id' => $template->ID,
                'title' => $template->post_title,
                'email_type' => $email_type,
                'thumbnail' => $thumbnail_url,
                'html_url' => $html_url,
            );
        }

        error_log('Sending ' . count($template_data) . ' templates to frontend');
        error_log('=== EMAIL TEMPLATE PREVIEW AJAX END ===');

        wp_send_json_success(array('templates' => $template_data));
    }

    public function render_field_settings($field)
    {
        acf_render_field_setting(
            $field,
            array(
                'label' => __('Št. stolpcev', 'sage'),
                'type' => 'number',
                'name' => 'max_columns',
                'instructions' => __('Največje število stolpcev v mreži', 'sage'),
                'default' => 3,
            )
        );

        acf_render_field_setting(
            $field,
            array(
                'label' => __('Višina predogleda (px)', 'sage'),
                'type' => 'number',
                'name' => 'preview_height',
                'instructions' => __('Višina predogleda v pikslih', 'sage'),
                'default' => 60,
            )
        );

        acf_render_field_setting(
            $field,
            array(
                'label' => __('Velikost predogleda', 'sage'),
                'type' => 'number',
                'name' => 'preview_scale',
                'instructions' => __('Velikost predogleda (0.1-0.5)', 'sage'),
                'default' => 0.2,
            )
        );

        acf_render_field_setting(
            $field,
            array(
                'label' => __('Pokaži vrsto e-pošte', 'sage'),
                'type' => 'true_false',
                'name' => 'show_email_type',
                'instructions' => __('Pokaži oznako vrste e-pošte na kartici', 'sage'),
                'default' => true,
            )
        );
    }

    public function render_field($field)
    {
        $field_value = $field['value'];
        $max_columns = $field['max_columns'] ?? 3;
        $preview_height = $field['preview_height'] ?? 60;
        $preview_scale = $field['preview_scale'] ?? 0.2;
        $show_email_type = $field['show_email_type'] ?? true;
        $field_name = esc_attr($field['name']);

        $email_type_labels = array(
            'registration' => __('Ob prijavi', 'sage'),
            'registration_cancellation' => __('Ob umiku prijave', 'sage'),
            'x_days_before' => __('X dni pred', 'sage'),
            'added_to_moodle' => __('Moodle', 'sage'),
            'course_cancellation' => __('Odpoved dogodka', 'sage'),
            'course_finished' => __('Končano', 'sage'),
        );

        $email_type_colors = array(
            'registration' => '#28a745',
            'registration_cancellation' => '#dc3545',
            'x_days_before' => '#ffc107',
            'added_to_moodle' => '#6610f2',
            'course_cancellation' => '#dc3545',
            'course_finished' => '#17a2b8',
        );
        ?>

        <div class="acf-email-template-preview-wrapper"
             data-field-name="<?php echo $field_name; ?>"
             data-max-columns="<?php echo $max_columns; ?>"
             data-preview-height="<?php echo $preview_height; ?>"
             data-preview-scale="<?php echo $preview_scale; ?>"
             data-show-email-type="<?php echo $show_email_type ? '1' : '0'; ?>"
             data-selected="<?php echo $field_value ? $field_value : ''; ?>"
             data-nonce="<?php echo wp_create_nonce('acf_nonce'); ?>"
             data-email-type-labels='<?php echo wp_json_encode($email_type_labels); ?>'
             data-email-type-colors='<?php echo wp_json_encode($email_type_colors); ?>'>

            <input type="hidden"
                   name="<?php echo $field_name; ?>"
                   class="acf-email-template-preview-value"
                   value="<?php echo $field_value ? esc_attr($field_value) : ''; ?>">

            <div class="acf-email-template-preview-grid">
                <div class="acf-email-template-preview-loading">
                    <span class="spinner"></span>
                    <span><?php _e('Nalagam...', 'sage'); ?></span>
                </div>
            </div>

            <?php if (!empty($field['description'])): ?>
                <p class="description"><?php echo esc_html($field['description']); ?></p>
            <?php endif; ?>
        </div>

        <?php
    }

    public function input_admin_enqueue_scripts()
    {
        $url = trailingslashit($this->env['url']);
        $version = $this->env['version'];

        wp_register_script(
            'sio-email-template-preview',
            "{$url}/assets/js/field.js",
            array('acf-input'),
            $version
        );

        wp_register_style(
            'sio-email-template-preview',
            "{$url}/assets/css/field.css",
            array('acf-input'),
            $version
        );

        wp_enqueue_script('sio-email-template-preview');
        wp_enqueue_style('sio-email-template-preview');

        wp_localize_script('sio-email-template-preview', 'acfEmailTemplatePreview', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => 'acf_email_template_preview_load',
            'nonce' => wp_create_nonce('acf_nonce'),
            'strings' => array(
                'select_template' => __('Izberite vzorec e-pošte', 'sage'),
                'no_templates' => __('Ni e-poštnih vzorcev', 'sage'),
                'loading' => __('Nalagam...', 'sage'),
                'view_full' => __('Ogled v polni velikosti', 'sage'),
                'close' => __('Zapri', 'sage'),
                'no_preview' => __('Predogled ni na voljo', 'sage'),
            ),
        ));
    }
}
