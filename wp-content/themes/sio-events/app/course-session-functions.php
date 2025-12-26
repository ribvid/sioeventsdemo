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