<?php
/**
 * Defines the custom field type class.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * sio_acf_field_button class.
 */
class sio_acf_field_button extends \acf_field
{
    /**
     * Controls field type visibilty in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to the theme or plugin.
     *
     * @var array $env Plugin or theme context such as 'url' and 'version'.
     */
    private $env;

    /**
     * Constructor.
     */
    public function __construct()
    {
        /**
         * Field type reference used in PHP and JS code.
         *
         * No spaces. Underscores allowed.
         */
        $this->name = 'button';

        /**
         * Field type label.
         *
         * For public-facing UI. May contain spaces.
         */
        $this->label = __('Button', 'sage');

        /**
         * The category the field appears within in the field type picker.
         */
        $this->category = 'basic'; // basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME

        /**
         * Field type Description.
         *
         * For field descriptions. May contain spaces.
         */
        $this->description = __('', 'sage');

        /**
         * Field type Doc URL.
         *
         * For linking to a documentation page. Displayed in the field picker modal.
         */
        $this->doc_url = '';

        /**
         * Field type Tutorial URL.
         *
         * For linking to a tutorial resource. Displayed in the field picker modal.
         */
        $this->tutorial_url = '';

        /**
         * Defaults for your custom user-facing settings for this field type.
         */
        $this->defaults = array(
            'callback' => '',
        );

        /**
         * Strings used in JavaScript code.
         *
         * Allows JS strings to be translated in PHP and loaded in JS via:
         *
         * ```js
         * const errorMessage = acf._e("button", "error");
         * ```
         */
        $this->l10n = array(
            'error' => __('Error! Please enter a higher value', 'sage'),
        );

        $this->env = array(
            'url' => site_url(str_replace(ABSPATH, '', __DIR__)), // URL to the acf-button directory.
            'version' => '1.0', // Replace this with your theme or plugin version constant.
        );

        /**
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        $this->preview_image = $this->env['url'] . '/assets/images/field-preview-custom.png';

        parent::__construct();

        // Register AJAX handler
        add_action('wp_ajax_acf_button_action', [$this, 'ajax_handler']);
    }

    public function ajax_handler()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'acf_button_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $callback = sanitize_text_field($_POST['callback']);
        $post_id = sanitize_text_field($_POST['postId']);

        if (function_exists($callback) && is_callable($callback)) {
            $result = call_user_func($callback, $post_id);
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Callback function not found');
        }
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF “Edit Field Group” admin page when
     * setting up the field.
     *
     * @param array $field
     * @return void
     */
    public function render_field_settings($field)
    {
        /*
         * Repeat for each setting you wish to display for this field type.
         */
        acf_render_field_setting(
            $field,
            array(
                'label' => __('Callback', 'sage'),
                'type' => 'text',
                'name' => 'callback',
            )
        );

        // To render field settings on other tabs in ACF 6.0+:
        // https://www.advancedcustomfields.com/resources/adding-custom-settings-fields/#moving-field-setting
    }

    /**
     * HTML content to show when a publisher edits the field on the edit screen.
     *
     * @param array $field The field settings and values.
     * @return void
     */
    public function render_field($field)
    {
        // Debug output to show what field data is available.
//        echo '<pre>';
//        print_r($field);
//        echo '</pre>';

        $callback_name = esc_attr($field['callback']);
        $button_text = !empty($field['label']) ? esc_html($field['label']) : 'Execute Action';
        ?>
        <button
                type="button"
                class="button button-primary"
                data-callback="<?php echo $callback_name; ?>"
                data-nonce="<?php echo wp_create_nonce('acf_button_nonce'); ?>"
                data-post-id="<?php echo esc_attr(get_the_ID()); ?>"
                data-element="acf-button-field"
                <?php if ($field['disabled'] ?? false): ?>disabled<?php endif; ?>>
            <?php echo $button_text; ?>
        </button>
        <span style="display:none;" data-element="acf-button-spinner">Nalagam...</span>
        <?php if (!empty($field['description'])): ?>
        <p class="description"><?php echo esc_html($field['description']); ?></p>
    <?php endif; ?>
        <?php
    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in the render_field() method.
     *
     * Callback for admin_enqueue_script.
     *
     * @return void
     */
    public function input_admin_enqueue_scripts()
    {
        $url = trailingslashit($this->env['url']);
        $version = $this->env['version'];

        wp_register_script(
            'sio-button',
            "{$url}assets/js/field.js",
            array('acf-input'),
            $version
        );

        wp_register_style(
            'sio-button',
            "{$url}assets/css/field.css",
            array('acf-input'),
            $version
        );

        wp_enqueue_script('sio-button');
        wp_enqueue_style('sio-button');

        wp_localize_script('sio-button', 'acfButton', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => 'acf_button_action'
        ]);
    }
}
