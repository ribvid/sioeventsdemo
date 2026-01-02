<?php
/**
 * Defines the custom field type class.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * sio_acf_field_attendees_list class.
 */
class sio_acf_field_attendees_list extends \acf_field
{
    /**
     * Controls field type visibilty in REST requests.
     *
     * @var bool
     */
    public $show_in_rest = true;

    /**
     * Environment values relating to theme or plugin.
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
        $this->name = 'attendees_list';

        /**
         * Field type label.
         *
         * For public-facing UI. May contain spaces.
         */
        $this->label = __('Seznam udeležencev', 'sage');

        /**
         * The category field appears within in the field type picker.
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
        $this->defaults = array();

        /**
         * Strings used in JavaScript code.
         *
         * Allows JS strings to be translated in PHP and loaded in JS via:
         *
         * ```js
         * const errorMessage = acf._e("attendees_list", "error");
         * ```
         */
        $this->l10n = array();

        $relative_path = str_replace(trailingslashit(ABSPATH), '', trailingslashit(__DIR__));
        $this->env = array(
            'url' => site_url($relative_path), // URL to acf-attendees-list directory.
            'version' => '1.0', // Replace this with your theme or plugin version constant.
        );

        /**
         * Field type preview image.
         *
         * A preview image for the field type in the picker modal.
         */
        $this->preview_image = $this->env['url'] . '/assets/images/field-preview-custom.png';

        parent::__construct();

        add_action('wp_ajax_acf_handle_entry_action', [$this, 'handle_entry_action']);
    }

    /**
     * Settings to display when users configure a field of this type.
     *
     * These settings appear on the ACF "Edit Field Group" admin page when
     * setting up a field.
     *
     * @param array $field
     * @return void
     */
    public function render_field_settings($field)
    {
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

        $post_id = esc_attr(get_the_ID());

        $form_id = esc_attr(get_post_meta($post_id, 'submission_form_id', true));

        if (!$form_id || !GFAPI::form_id_exists($form_id)) {
            return;
        }

        $entries = GFAPI::get_entries($form_id);

        if (empty($entries)) return;

        $first_name_field_id = "1.3";
        $last_name_field_id = "1.6";
        $email_field_id = 2;
        $registered_field_id = 4;

        ?>
        <div class="gf-entries-table-wrap">
            <!-- Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>ID uporabnika</th>
                    <th>Ime</th>
                    <th>Priimek</th>
                    <th>E-pošta</th>
                    <th>Datum prijave</th>
                    <th>Status</th>
                    <th>Dodan v učilnico</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $user_id = isset($entry['created_by']) && $entry['created_by'] > 0 ? $entry['created_by'] : 'Neprijavljen';
                    $first_name = rgar($entry, $first_name_field_id);
                    $last_name = rgar($entry, $last_name_field_id);
                    $email = rgar($entry, $email_field_id);
                    $date = date_i18n('j. n. Y H:i', strtotime($entry['date_created']));
                    $status = rgar($entry, $registered_field_id);
                    $is_enrolled = false; // TODO

                    // Check if ACF fields exist for this entry
                    $has_certificate = !!get_field('certificate', $post_id);
                    $ticket_url = esc_url(gform_get_meta($entry['id'], 'generated_pdf_url'));

                    ?>
                    <tr>
                        <td><?php echo esc_html($user_id); ?></td>
                        <td><?php echo esc_html($first_name); ?></td>
                        <td><?php echo esc_html($last_name); ?></td>
                        <td><?php echo esc_html($email); ?></td>
                        <td><?php echo esc_html($date); ?></td>
                        <td><?php echo esc_html($status); ?></td>
                        <td><?php echo $is_enrolled ? 'Da' : 'Ne'; ?></td>
                        <td>
                            <?php if ($status === 'registered') : ?>
                                <button class="button button-small entry-action submitdelete"
                                        data-action="cancel"
                                        data-entry-id="<?php echo esc_attr($entry['id']); ?>">Odjavi
                                </button>
                                <button class="button button-small entry-action" data-action="attended"
                                        data-entry-id="<?php echo esc_attr($entry['id']); ?>">Potrdi prisotnost
                                </button>
                                <a class="button button-small entry-action <?php echo !$ticket_url ? 'disabled' : ''; ?>"
                                   href="<?php echo $ticket_url; ?>" target="_blank">
                                    Prenesi vstopnico
                                </a>
                            <?php elseif ($status === 'cancelled') : ?>
                                <button class="button button-small entry-action submitdelete"
                                        data-action="register"
                                        data-entry-id="<?php echo esc_attr($entry['id']); ?>">Prijavi
                                </button>
                            <?php elseif ($status === 'attended') : ?>
                                <button class="button button-small entry-action" data-action="issue-certificate"
                                        data-entry-id="<?php echo esc_attr($entry['id']); ?>" <?php echo !$has_certificate ? 'disabled' : ''; ?>>
                                    Izdaj potrdilo
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
            .entry-action + .entry-action {
                margin-top: 0.25rem;
            }
        </style>
        <?php
    }

    /**
     * Enqueues CSS and JavaScript needed by HTML in render_field() method.
     *
     * Callback for admin_enqueue_scripts.
     *
     * @return void
     */
    public function input_admin_enqueue_scripts()
    {
        $url = trailingslashit($this->env['url']);
        $version = $this->env['version'];

        wp_register_script(
            'sio-attendees-list',
            "{$url}assets/js/field.js",
            array('acf-input'),
            $version
        );

        wp_register_style(
            'sio-attendees-list',
            "{$url}assets/css/field.css",
            array('acf-input'),
            $version
        );

        wp_enqueue_script('sio-attendees-list');
        wp_enqueue_style('sio-attendees-list');

        wp_localize_script('sio-button', 'acfAttendeesList', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => 'acf_handle_entry_action',
            'nonce' => wp_create_nonce('acf_attendees_list_nonce'),
        ]);
    }

    public function validate_field($field)
    {
        // Hide the field label/name by setting it programmatically
        // This prevents it from being required or displayed in the field group editor

        // Set a default name if empty to prevent validation errors
        if (empty($field['name'])) {
            $field['name'] = 'field_' . uniqid();
        }

        // Hide the label in frontend
        $field['label'] = '';

        return $field;
    }

    function handle_entry_action()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'acf_attendees_list_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        $action = sanitize_text_field($_POST['entry_action']);
        $entryId = sanitize_text_field($_POST['entry_id']);

        switch ($action) {
            case 'cancel':
                if (!GFAPI::entry_exists($entryId)) {
                    break;
                }

                $entry = GFAPI::get_entry($entryId);
                $entry['4'] = 'cancelled';
                GFAPI::update_entry($entry, $entryId);

                break;
            case 'register':
                if (!GFAPI::entry_exists($entryId)) {
                    break;
                }

                $entry = GFAPI::get_entry($entryId);
                $entry['4'] = 'registered';
                GFAPI::update_entry($entry, $entryId);

                break;
            case 'attended':
                break;
            case 'issue-certificate':
                break;
            default:
                break;
        }

        wp_send_json_success();
    }
}