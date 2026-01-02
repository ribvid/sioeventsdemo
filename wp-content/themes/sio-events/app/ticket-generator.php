<?php


/**
 * Gravity Forms - Word Template to PDF Generator
 *
 * This code processes Word templates with placeholders and generates PDFs
 * after form submission.
 */

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use Dompdf\Dompdf;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;

/**
 * Process form submission and generate PDF
 *
 * @param array $entry The form entry data
 * @param array $form The form object
 */
add_action('gform_after_submission', 'gf_generate_pdf_from_word', 10, 2);

function gf_generate_pdf_from_word($entry)
{
    error_log('=== TICKET GENERATION START ===');
    error_log('Entry ID: ' . $entry['id']);
    error_log('Entry Data: ' . print_r($entry, true));
    
    $post_id = $entry['6'];
    error_log('Post ID from field 6: ' . $post_id);
    
    $ticket = get_field('ticket', $post_id);
    error_log('Ticket ACF field: ' . ($ticket ? 'EXISTS' : 'MISSING'));

    if (!$ticket) {
        error_log('EARLY EXIT: Ticket field is empty');
        return;
    }

    $ticket_template = get_field('template', $ticket);
    error_log('Ticket template: ' . ($ticket_template ? 'EXISTS - ID: ' . $ticket_template['id'] : 'MISSING'));

    if (!$ticket_template) {
        error_log('EARLY EXIT: Ticket template is empty');
        return;
    }

    $template_path = get_attached_file($ticket_template['id']);
    error_log('Template path: ' . $template_path);
    error_log('Template file exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));

    // Check if template exists
    if (!file_exists($template_path)) {
        error_log('EARLY EXIT: Word template not found: ' . $template_path);
        return;
    }

    try {
        error_log('Starting PDF generation...');
        // Load the Word template
        $templateProcessor = new TemplateProcessor($template_path);

        // Generate unique filename
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/gravity-forms-pdfs/';

        $qr_temp_path = wp_upload_dir()['basedir'] . '/gravity-forms-pdfs/qr-' . $entry['id'] . '.png';
        $qr_code = get_qr_code($entry['id']);
        $qr_code->saveToFile($qr_temp_path);

        $placeholders = [
            '${ime_udeleženca}' => $entry["1.3"],
            '${priimek_udeleženca}' => $entry["1.6"],
            '${email_udeleženca}' => $entry["2"],
            '${id_prijave}' => $entry['id'],
            '${datum_prijave}' => $entry['date_created'],
        ];

        if (have_rows('placeholders', $post_id)) {
            while (have_rows('placeholders', $post_id)) {
                the_row();
                $placeholders[get_sub_field('placeholder')] = get_sub_field('value');
            }
        }

        $templateProcessor->setValues($placeholders);

        $templateProcessor->setImageValue('qr_koda', [
            'path' => $qr_temp_path,
            'width' => 150,
            'height' => 150,
            'ratio' => true
        ]);

        // Create directory if it doesn't exist
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $timestamp = time();
        $temp_docx = $pdf_dir . 'temp-' . $timestamp . '.docx';
        $pdf_filename = 'form-submission-' . $entry['id'] . '-' . $timestamp . '.pdf';
        $pdf_path = $pdf_dir . $pdf_filename;

        $templateProcessor->saveAs($temp_docx);

        $phpWord = \PhpOffice\PhpWord\IOFactory::load($temp_docx);

        $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $temp_html = $pdf_dir . 'temp-' . $timestamp . '.html';
        $htmlWriter->save($temp_html);

        $html_content = file_get_contents($temp_html);
        $html_content = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('defaultFont', 'DejaVu Sans');
        $dompdf->loadHtml($html_content);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($pdf_path, $dompdf->output());
        error_log('PDF created at: ' . $pdf_path);
        error_log('PDF file exists: ' . (file_exists($pdf_path) ? 'YES - Size: ' . filesize($pdf_path) . ' bytes' : 'NO'));

        unlink($temp_docx);
        unlink($temp_html);
        unlink($qr_temp_path);

        // Store PDF path in entry meta for later use
        $pdf_url = $upload_dir['baseurl'] . '/gravity-forms-pdfs/' . $pdf_filename;
        gform_update_meta($entry['id'], 'generated_pdf_path', $pdf_path);
        gform_update_meta($entry['id'], 'generated_pdf_url', $pdf_url);
        
        error_log('Metadata saved:');
        error_log('  - generated_pdf_path: ' . $pdf_path);
        error_log('  - generated_pdf_url: ' . $pdf_url);

        // Send email with PDF attachment
        gf_send_pdf_email($entry, $pdf_path);
        error_log('Email sent to: ' . rgar($entry, '2'));
        error_log('=== TICKET GENERATION SUCCESSFUL ===');

    } catch (Exception $e) {
        error_log('=== TICKET GENERATION FAILED ===');
        error_log('Exception: ' . $e->getMessage());
        error_log('Trace: ' . $e->getTraceAsString());
    }
}

/**
 * Send email with PDF attachment
 *
 * @param array $entry The form entry
 * @param string $pdf_path Path to generated PDF
 */
function gf_send_pdf_email($entry, $pdf_path)
{
    $to = rgar($entry, '2');
    $subject = 'Vstopnica';
    $message = 'Hvala za prijavo. V priponki se nahaja vaša vstopnica.';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $attachments = array($pdf_path);

    wp_mail($to, $subject, $message, $headers, $attachments);
}

/**
 * Clean up old PDFs (optional - run via cron)
 * Delete PDFs older than 30 days
 */
function gf_cleanup_old_pdfs()
{
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/gravity-forms-pdfs/';

    if (!file_exists($pdf_dir)) {
        return;
    }

    $files = glob($pdf_dir . '*.pdf');
    $now = time();

    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 30 * 24 * 60 * 60) { // 30 days
                unlink($file);
            }
        }
    }
}

// Schedule cleanup (uncomment to enable)
// add_action('wp_scheduled_delete', 'gf_cleanup_old_pdfs');


add_action('gform_after_submission', 'send_registration_email', 10, 2);

function send_registration_email($entry, $form)
{
    error_log('=== REGISTRATION EMAIL START ===');
    error_log('Entry ID: ' . $entry['id']);

    $post_id = $entry['6'];
    $course_session_id = $post_id;

    if (!$course_session_id) {
        error_log('EARLY EXIT: No course session ID found');
        return;
    }

    $template = get_email_template_by_type($course_session_id, 'registration');

    if (!$template) {
        error_log('EARLY EXIT: No registration template found');
        return;
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        error_log('EARLY EXIT: Could not get HTML content');
        return;
    }

    $processed_html = process_email_placeholders($html_content, $entry, $course_session_id);

    $to = rgar($entry, '2');
    $subject = 'Potrditev prijave';

    send_html_email($to, $subject, $processed_html);

    error_log('=== REGISTRATION EMAIL END ===');
}


add_action('gform_after_submission', 'check_cancellation_and_send', 11, 2);

function check_cancellation_and_send($entry, $form)
{
    error_log('=== CHECK CANCELLATION START ===');
    error_log('Entry ID: ' . $entry['id']);

    $status_field_id = 4;
    $previous_status = gform_get_meta($entry['id'], 'previous_status');
    $current_status = rgar($entry, $status_field_id);

    error_log('Previous status: ' . ($previous_status ?? 'none'));
    error_log('Current status: ' . $current_status);

    if ($current_status === 'cancelled' && $previous_status !== 'cancelled') {
        $post_id = $entry['6'];
        $course_session_id = $post_id;

        if (!$course_session_id) {
            error_log('EARLY EXIT: No course session ID found');
            return;
        }

        $template = get_email_template_by_type($course_session_id, 'registration_cancellation');

        if (!$template) {
            error_log('EARLY EXIT: No cancellation template found');
            return;
        }

        $html_content = get_email_html($template->ID);

        if (!$html_content) {
            error_log('EARLY EXIT: Could not get HTML content');
            return;
        }

        $processed_html = process_email_placeholders($html_content, $entry, $course_session_id);

        $to = rgar($entry, '2');
        $subject = 'Umik prijave';

        send_html_email($to, $subject, $processed_html);

        error_log('CANCELLATION EMAIL SENT');
    }

    gform_update_meta($entry['id'], 'previous_status', $current_status);

    error_log('=== CHECK CANCELLATION END ===');
}


add_action('course_enrolled_in_moodle', 'send_moodle_email', 10, 2);

function send_moodle_email($entry, $course_session_id)
{
    error_log('=== MOODLE EMAIL START ===');
    error_log('Entry ID: ' . $entry['id']);

    $template = get_email_template_by_type($course_session_id, 'added_to_moodle');

    if (!$template) {
        error_log('EARLY EXIT: No Moodle template found');
        return;
    }

    $html_content = get_email_html($template->ID);

    if (!$html_content) {
        error_log('EARLY EXIT: Could not get HTML content');
        return;
    }

    $processed_html = process_email_placeholders($html_content, $entry, $course_session_id);

    $to = rgar($entry, '2');
    $subject = 'Vključitev v spletno učilnico';

    send_html_email($to, $subject, $processed_html);

    error_log('=== MOODLE EMAIL END ===');
}


function send_reminder_emails($course_session_id)
{
    error_log('=== REMINDER EMAILS START ===');
    error_log('Course Session ID: ' . $course_session_id);

    $reminder_days = get_field('email_reminder_days', $course_session_id);

    if (!$reminder_days) {
        error_log('EARLY EXIT: No reminder days set');
        return;
    }

    $template = get_email_template_by_type($course_session_id, 'x_days_before');

    if (!$template) {
        error_log('EARLY EXIT: No reminder template found');
        return;
    }

    $start_date = get_field('start_date', $course_session_id);

    if (!$start_date) {
        error_log('EARLY EXIT: No start date');
        return;
    }

    $start_datetime = new \DateTime($start_date);
    $now = new \DateTime();
    $interval = $start_datetime->diff($now);
    $days_until = $interval->days;

    error_log('Days until event: ' . $days_until);
    error_log('Reminder days: ' . $reminder_days);

    if ($days_until != $reminder_days) {
        error_log('EARLY EXIT: Not the right day for reminder');
        return;
    }

    $submission_form_id = get_post_meta($course_session_id, 'submission_form_id', true);

    if (!$submission_form_id) {
        error_log('EARLY EXIT: No submission form found');
        return;
    }

    $search_criteria = [
        'form_id' => $submission_form_id,
        'status' => 'active',
    ];

    $entries = GFAPI::get_entries($submission_form_id, $search_criteria);

    error_log('Found ' . count($entries) . ' active entries');

    foreach ($entries as $entry) {
        $html_content = get_email_html($template->ID);

        if (!$html_content) {
            error_log('EARLY EXIT: Could not get HTML content for entry ' . $entry['id']);
            continue;
        }

        $processed_html = process_email_placeholders($html_content, $entry, $course_session_id);

        $to = rgar($entry, '2');
        $subject = 'Opomnik: ' . $reminder_days . ' dni do dogodka';

        send_html_email($to, $subject, $processed_html);

        error_log('Reminder email sent to entry: ' . $entry['id']);
    }

    error_log('=== REMINDER EMAILS END ===');
}


function send_course_cancellation_email($course_session_id)
{
    error_log('=== COURSE CANCELLATION EMAIL START ===');
    error_log('Course Session ID: ' . $course_session_id);

    $template = get_email_template_by_type($course_session_id, 'course_cancellation');

    if (!$template) {
        error_log('EARLY EXIT: No course cancellation template found');
        return;
    }

    $submission_form_id = get_post_meta($course_session_id, 'submission_form_id', true);

    if (!$submission_form_id) {
        error_log('EARLY EXIT: No submission form found');
        return;
    }

    $search_criteria = [
        'form_id' => $submission_form_id,
        'status' => 'active',
    ];

    $entries = GFAPI::get_entries($submission_form_id, $search_criteria);

    error_log('Found ' . count($entries) . ' active entries');

    foreach ($entries as $entry) {
        $html_content = get_email_html($template->ID);

        if (!$html_content) {
            error_log('EARLY EXIT: Could not get HTML content for entry ' . $entry['id']);
            continue;
        }

        $processed_html = process_email_placeholders($html_content, $entry, $course_session_id);

        $to = rgar($entry, '2');
        $subject = 'Odpoved dogodka';

        send_html_email($to, $subject, $processed_html);

        error_log('Course cancellation email sent to entry: ' . $entry['id']);
    }

    error_log('=== COURSE CANCELLATION EMAIL END ===');
}


function send_course_finished_email($course_session_id)
{
    error_log('=== COURSE FINISHED EMAIL START ===');
    error_log('Course Session ID: ' . $course_session_id);

    $template = get_email_template_by_type($course_session_id, 'course_finished');

    if (!$template) {
        error_log('EARLY EXIT: No course finished template found');
        return;
    }

    $submission_form_id = get_post_meta($course_session_id, 'submission_form_id', true);

    if (!$submission_form_id) {
        error_log('EARLY EXIT: No submission form found');
        return;
    }

    $search_criteria = [
        'form_id' => $submission_form_id,
        'status' => 'active',
    ];

    $entries = GFAPI::get_entries($submission_form_id, $search_criteria);

    error_log('Found ' . count($entries) . ' active entries');

    foreach ($entries as $entry) {
        $html_content = get_email_html($template->ID);

        if (!$html_content) {
            error_log('EARLY EXIT: Could not get HTML content for entry ' . $entry['id']);
            continue;
        }

        $processed_html = process_email_placeholders($html_content, $entry, $course_session_id);

        $to = rgar($entry, '2');
        $subject = 'Končano izobraževanje';

        send_html_email($to, $subject, $processed_html);

        error_log('Course finished email sent to entry: ' . $entry['id']);
    }

    error_log('=== COURSE FINISHED EMAIL END ===');
}


