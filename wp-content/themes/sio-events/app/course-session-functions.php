<?php

// Disable Moodle group field
add_filter('acf/load_field/key=field_694d28f95861d', function ($field) {
    $field['disabled'] = true;
    return $field;
});

// Disable enroll participants button if Moodle course is not set
add_filter('acf/load_field/key=field_694d2ffd7acbf', function ($field) {
    $moodle = get_field('moodle');

    if (empty($moodle['course'])) {
        $field['disabled'] = true;
    }

    return $field;
});

// Disable download attendee sheet button if attendee sheet is not available
add_filter('acf/load_field/key=field_694d2cdd84433', function ($field) {
    $attendee_sheet = get_field('attendee_sheet');

    if (empty($attendee_sheet)) {
        $field['disabled'] = true;
    }

    return $field;
});

// Disable issue certificates button if certificate is not set
add_filter('acf/load_field/key=field_694d2fa07acbe', function ($field) {
    $certificate = get_field('certificate');

    if (empty($certificate)) {
        $field['disabled'] = true;
    }

    return $field;
});

// Add default rows to placeholders repeater field
add_filter('acf/load_value/key=field_694d287758617', function ($value, $post_id, $field) {
    // Only set defaults if value is empty
    if (empty($value) && $post_id && get_post_status($post_id) === 'publish') {
        $value = [
            [
                'field_694d288058618' => '${ime_dogodka}',
                'field_694d289858619' => get_the_title($post_id),
            ]
        ];
    }

    return $value;
}, 10, 3);

// Hide create form button if submission form is created
add_filter('acf/load_field/key=field_694d304a7acc0', function ($field) {
    global $post;

    if (!$post) {
        return $field;
    }

    // Get the submission form post ID from post meta
    $submission_form_id = get_post_meta($post->ID, 'submission_form_id', true);

    if ($submission_form_id) {
        // Change field type to message
        $field['type'] = 'message';
        $field['message'] = __('Obrazec je že ustvarjen', 'sage');
        $field['esc_html'] = 0; // Allow HTML in message
        $field['new_lines'] = ''; // No formatting for new lines
        return $field;
    }

    return $field;
});

add_action('admin_footer', function () {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $(document).on('duplicate_form_completed', function () {
                var $field = $('.acf-field[data-key="field_694d304a7acc0"]');

                // Replace button with message
                $field.find('.acf-input').html(
                    '<div><p><?php _e('Obrazec je ustvarjen', 'sage'); ?></p></div>'
                );
            });
        });
    </script>
    <?php
});

add_action('before_delete_post', function ($post_id) {
    if (get_post_type($post_id) !== "course_session") {
        return;
    }

    $submission_form_id = get_post_meta($post_id, 'submission_form_id', true);

    if ($submission_form_id) {
        GFAPI::delete_form($submission_form_id);
    }
});

add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== "course_session") {
        return;
    }

    $submission_form_id = get_post_meta($post_id, 'submission_form_id', true);

    if (!$submission_form_id || !GFAPI::form_id_exists($submission_form_id)) {
        return;
    }

    $form = GFAPI::get_form($submission_form_id);

    $application_start_date = get_field('application_start_date');
    $application_end_date = get_field('application_start_date');
    $max_attendees = get_field('max_attendees');

    if ($max_attendees) {
        $form['limitEntries'] = true;
        $form['limitEntriesCount'] = $max_attendees;
    }

    if ($application_start_date || $application_end_date) {
        $form['scheduleForm'] = true;

        if ($application_start_date) {
            $start_date = Carbon\Carbon::parse($application_start_date);
            $form['scheduleStart'] = $start_date->format('m/d/Y');
            $form['scheduleStartHour'] = $start_date->hour;
            $form['scheduleStartMinute'] = $start_date->minute;
            /* translators: 1: Datum odpiranja prijav, 2: Čas odpiranja prijav */
            $form['schedulePendingMessage'] = sprintf(
                __('Prijave se odprejo %s ob %s.', 'sage'),
                $start_date->format('j. n. Y'),
                $start_date->format('H:i')
            );
        } else {
            $form['scheduleStart'] = "";
        }

        if ($application_end_date) {
            $end_date = Carbon\Carbon::parse($application_end_date);
            $form['scheduleEnd'] = $end_date->format('m/d/Y');
            $form['scheduleEndHour'] = $end_date->hour;
            $form['scheduleEndMinute'] = $end_date->minute;
            $form['scheduleMessage'] = __('Prijave so že zaprte.', 'sage');
        } else {
            $form['scheduleEnd'] = "";
        }
    } else {
        $form['scheduleForm'] = false;
    }

    GFAPI::update_form($form, $submission_form_id);
});

function duplicate_form($post_id)
{
    $form_id = GFAPI::duplicate_form(1);
    $form = GFAPI::get_form($form_id);

    /* translators: ID objave */
    $form['title'] = sprintf(__('Prijavnica na dogodek (objava: #%s)', 'sage'), $post_id);

    foreach ($form['fields'] as &$field) {
        if ($field->type === 'hidden' && $field->label === 'ID objave') {
            $field->defaultValue = $post_id;
            break;
        }
    }

    GFAPI::update_form($form);

    update_post_meta($post_id, 'submission_form_id', $form_id);
}

// Delete post meta on form delete
//    delete_post_meta(115, 'submission_form_id');


function send_test_registration_email($post_id)
{
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Nimate dovoljenja'];
    }

    $current_user = wp_get_current_user();
    $test_email = $current_user->user_email;

    $template = get_field('email_template_registration', $post_id);

    if (!$template) {
        return ['success' => false, 'message' => 'Nobena predloga e-pošte ni izbrana'];
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        return ['success' => false, 'message' => 'HTML vsebina ni na voljo'];
    }

    $test_entry = [
        'id' => 'TEST-' . time(),
        '1.3' => 'Test',
        '1.6' => 'Uporabnik',
        '2' => $test_email,
        'date_created' => date('Y-m-d H:i:s'),
    ];

    $processed_html = process_email_placeholders($html_content, $test_entry, $post_id);

    $subject = 'TEST: Potrditev prijave';

    $result = send_html_email($test_email, $subject, $processed_html);

    return [
        'success' => $result['success'],
        'message' => $result['success']
            ? 'Testno sporočilo je bilo poslano na ' . $test_email
            : 'Napaka pri pošiljanju: ' . ($result['error'] ?? 'Unknown error')
    ];
}


function send_test_cancellation_email($post_id)
{
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Nimate dovoljenja'];
    }

    $current_user = wp_get_current_user();
    $test_email = $current_user->user_email;

    $template = get_field('email_template_registration_cancellation', $post_id);

    if (!$template) {
        return ['success' => false, 'message' => 'Nobena predloga e-pošte ni izbrana'];
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        return ['success' => false, 'message' => 'HTML vsebina ni na voljo'];
    }

    $test_entry = [
        'id' => 'TEST-' . time(),
        '1.3' => 'Test',
        '1.6' => 'Uporabnik',
        '2' => $test_email,
        'date_created' => date('Y-m-d H:i:s'),
    ];

    $processed_html = process_email_placeholders($html_content, $test_entry, $post_id);

    $subject = 'TEST: Umik prijave';

    $result = send_html_email($test_email, $subject, $processed_html);

    return [
        'success' => $result['success'],
        'message' => $result['success']
            ? 'Testno sporočilo je bilo poslano na ' . $test_email
            : 'Napaka pri pošiljanju: ' . ($result['error'] ?? 'Unknown error')
    ];
}


function send_test_reminder_email($post_id)
{
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Nimate dovoljenja'];
    }

    $current_user = wp_get_current_user();
    $test_email = $current_user->user_email;

    $template = get_field('email_template_x_days_before', $post_id);

    if (!$template) {
        return ['success' => false, 'message' => 'Nobena predloga e-pošte ni izbrana'];
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        return ['success' => false, 'message' => 'HTML vsebina ni na voljo'];
    }

    $test_entry = [
        'id' => 'TEST-' . time(),
        '1.3' => 'Test',
        '1.6' => 'Uporabnik',
        '2' => $test_email,
        'date_created' => date('Y-m-d H:i:s'),
    ];

    $processed_html = process_email_placeholders($html_content, $test_entry, $post_id);

    $subject = 'TEST: Opomnik';

    $result = send_html_email($test_email, $subject, $processed_html);

    return [
        'success' => $result['success'],
        'message' => $result['success']
            ? 'Testno sporočilo je bilo poslano na ' . $test_email
            : 'Napaka pri pošiljanju: ' . ($result['error'] ?? 'Unknown error')
    ];
}


function send_test_moodle_email($post_id)
{
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Nimate dovoljenja'];
    }

    $current_user = wp_get_current_user();
    $test_email = $current_user->user_email;

    $template = get_field('email_template_added_to_moodle', $post_id);

    if (!$template) {
        return ['success' => false, 'message' => 'Nobena predloga e-pošte ni izbrana'];
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        return ['success' => false, 'message' => 'HTML vsebina ni na voljo'];
    }

    $test_entry = [
        'id' => 'TEST-' . time(),
        '1.3' => 'Test',
        '1.6' => 'Uporabnik',
        '2' => $test_email,
        'date_created' => date('Y-m-d H:i:s'),
    ];

    $processed_html = process_email_placeholders($html_content, $test_entry, $post_id);

    $subject = 'TEST: Vključitev v spletno učilnico';

    $result = send_html_email($test_email, $subject, $processed_html);

    return [
        'success' => $result['success'],
        'message' => $result['success']
            ? 'Testno sporočilo je bilo poslano na ' . $test_email
            : 'Napaka pri pošiljanju: ' . ($result['error'] ?? 'Unknown error')
    ];
}


function send_test_course_cancellation_email($post_id)
{
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Nimate dovoljenja'];
    }

    $current_user = wp_get_current_user();
    $test_email = $current_user->user_email;

    $template = get_field('email_template_course_cancellation', $post_id);

    if (!$template) {
        return ['success' => false, 'message' => 'Nobena predloga e-pošte ni izbrana'];
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        return ['success' => false, 'message' => 'HTML vsebina ni na voljo'];
    }

    $test_entry = [
        'id' => 'TEST-' . time(),
        '1.3' => 'Test',
        '1.6' => 'Uporabnik',
        '2' => $test_email,
        'date_created' => date('Y-m-d H:i:s'),
    ];

    $processed_html = process_email_placeholders($html_content, $test_entry, $post_id);

    $subject = 'TEST: Odpoved dogodka';

    $result = send_html_email($test_email, $subject, $processed_html);

    return [
        'success' => $result['success'],
        'message' => $result['success']
            ? 'Testno sporočilo je bilo poslano na ' . $test_email
            : 'Napaka pri pošiljanju: ' . ($result['error'] ?? 'Unknown error')
    ];
}


function send_test_course_finished_email($post_id)
{
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'Nimate dovoljenja'];
    }

    $current_user = wp_get_current_user();
    $test_email = $current_user->user_email;

    $template = get_field('email_template_course_finished', $post_id);

    if (!$template) {
        return ['success' => false, 'message' => 'Nobena predloga e-pošte ni izbrana'];
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        return ['success' => false, 'message' => 'HTML vsebina ni na voljo'];
    }

    $test_entry = [
        'id' => 'TEST-' . time(),
        '1.3' => 'Test',
        '1.6' => 'Uporabnik',
        '2' => $test_email,
        'date_created' => date('Y-m-d H:i:s'),
    ];

    $processed_html = process_email_placeholders($html_content, $test_entry, $post_id);

    $subject = 'TEST: Končano izobraževanje';

    $result = send_html_email($test_email, $subject, $processed_html);

    return [
        'success' => $result['success'],
        'message' => $result['success']
            ? 'Testno sporočilo je bilo poslano na ' . $test_email
            : 'Napaka pri pošiljanju: ' . ($result['error'] ?? 'Unknown error')
    ];
}
