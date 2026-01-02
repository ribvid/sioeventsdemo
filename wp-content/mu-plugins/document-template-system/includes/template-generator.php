<?php
/**
 * Document Generation Logic
 * Extends existing ticket-generator.php pattern with organization-scoped templates
 */

if (!defined('ABSPATH')) {
    exit;
}

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use Dompdf\Dompdf;

/**
 * Hook into Gravity Forms submission - Priority 5 (runs before theme's priority 10)
 */
add_action('gform_after_submission', 'dts_generate_ticket_from_template', 5, 2);

/**
 * Generate ticket PDF from organization template
 *
 * @param array $entry The form entry data
 * @param array $form The form object
 */
function dts_generate_ticket_from_template($entry, $form) {
    // Get course_session ID from entry field 6
    $post_id = rgar($entry, '6');
    if (!$post_id) {
        return;
    }

    // Check if new template system is used
    $template_id = get_field('ticket_template', $post_id);
    if (!$template_id) {
        // Fallback to existing system (ACF file fields on course_session)
        return;
    }

    try {
        // Get template file path
        $template_path = dts_get_template_file_path($template_id);
        if (!$template_path) {
            error_log('DTS: Template file not found for template ID: ' . $template_id);
            return;
        }

        // Load the Word template
        $templateProcessor = new TemplateProcessor($template_path);

        // Setup directories
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/gravity-forms-pdfs/';
        wp_mkdir_p($pdf_dir);

        // Generate QR code
        $qr_temp_path = $pdf_dir . 'qr-' . $entry['id'] . '.png';
        $qr_code = get_qr_code($entry['id']);
        $qr_code->saveToFile($qr_temp_path);

        // Build placeholders
        $placeholders = dts_build_placeholders($post_id, $entry);

        // Apply placeholders
        $templateProcessor->setValues($placeholders);

        // Set QR code image
        $templateProcessor->setImageValue('qr_koda', [
            'path' => $qr_temp_path,
            'width' => 150,
            'height' => 150,
            'ratio' => true,
        ]);

        // Generate PDF
        $timestamp = time();
        $temp_docx = $pdf_dir . 'temp-' . $timestamp . '.docx';
        $pdf_filename = 'ticket-' . $entry['id'] . '-' . $timestamp . '.pdf';
        $pdf_path = $pdf_dir . $pdf_filename;

        $templateProcessor->saveAs($temp_docx);

        // Convert DOCX to PDF
        dts_convert_docx_to_pdf($temp_docx, $pdf_path);

        // Cleanup temp files
        unlink($temp_docx);
        unlink($qr_temp_path);

        // Store PDF metadata in entry
        gform_update_meta($entry['id'], 'generated_pdf_path', $pdf_path);
        gform_update_meta($entry['id'], 'generated_pdf_url', $upload_dir['baseurl'] . '/gravity-forms-pdfs/' . $pdf_filename);

        // Send email with PDF
        dts_send_ticket_email($entry, $pdf_path);

    } catch (Exception $e) {
        error_log('DTS: PDF Generation Error: ' . $e->getMessage());
    }
}

/**
 * Get template file path from template ID
 *
 * @param int $template_id Document template post ID
 * @return string|false File path or false if not found
 */
function dts_get_template_file_path($template_id) {
    $template_file = get_field('template_file', $template_id);

    if (!$template_file || !isset($template_file['ID'])) {
        return false;
    }

    $file_path = get_attached_file($template_file['ID']);

    if (!file_exists($file_path)) {
        return false;
    }

    return $file_path;
}

/**
 * Build placeholders array from entry and course_session
 *
 * @param int $post_id Course session post ID
 * @param array $entry Gravity Forms entry
 * @return array Placeholders array
 */
function dts_build_placeholders($post_id, $entry) {
    $placeholders = [
        'ime_udeleženca' => rgar($entry, '1.3', ''),
        'priimek_udeleženca' => rgar($entry, '1.6', ''),
        'email_udeleženca' => rgar($entry, '2', ''),
        'id_prijave' => $entry['id'],
        'datum_prijave' => $entry['date_created'],
    ];

    // Add course_session specific placeholders from ACF repeater
    if (have_rows('placeholders', $post_id)) {
        while (have_rows('placeholders', $post_id)) {
            the_row();
            $placeholder_key = get_sub_field('placeholder');
            $placeholder_value = get_sub_field('value');

            if ($placeholder_key && $placeholder_value) {
                // Remove ${} if present
                $placeholder_key = str_replace(['${', '}'], '', $placeholder_key);
                $placeholders[$placeholder_key] = $placeholder_value;
            }
        }
    }

    return $placeholders;
}

/**
 * Convert DOCX to PDF using PhpWord and Dompdf
 *
 * @param string $docx_path Path to DOCX file
 * @param string $pdf_path Path to save PDF
 */
function dts_convert_docx_to_pdf($docx_path, $pdf_path) {
    // Load DOCX
    $phpWord = IOFactory::load($docx_path);

    // Convert to HTML
    $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
    $temp_html = str_replace('.docx', '.html', $docx_path);
    $htmlWriter->save($temp_html);

    // Load HTML content
    $html_content = file_get_contents($temp_html);
    $html_content = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');

    // Convert HTML to PDF
    $dompdf = new Dompdf();
    $dompdf->set_option('defaultFont', 'DejaVu Sans');
    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save PDF
    file_put_contents($pdf_path, $dompdf->output());

    // Cleanup temp HTML
    unlink($temp_html);
}

/**
 * Send email with ticket PDF
 *
 * @param array $entry Gravity Forms entry
 * @param string $pdf_path Path to PDF file
 */
function dts_send_ticket_email($entry, $pdf_path) {
    $to = rgar($entry, '2');
    $subject = 'Vstopnica';
    $message = 'Hvala za prijavo. V priponki se nahaja vaša vstopnica.';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $attachments = [$pdf_path];

    wp_mail($to, $subject, $message, $headers, $attachments);
}

/**
 * Generate attendance list PDF from multiple entries
 *
 * @param int $course_session_id Course session post ID
 * @return string|false PDF path or false on error
 */
function dts_generate_attendance_list($course_session_id) {
    try {
        // Get attendance list template
        $template_id = get_field('attendance_list_template', $course_session_id);
        if (!$template_id) {
            return false;
        }

        // Get template file path
        $template_path = dts_get_template_file_path($template_id);
        if (!$template_path) {
            error_log('DTS: Attendance list template file not found for template ID: ' . $template_id);
            return false;
        }

        // Get form ID
        $form_id = get_post_meta($course_session_id, 'submission_form_id', true);
        if (!$form_id) {
            error_log('DTS: No form ID found for course_session: ' . $course_session_id);
            return false;
        }

        // Get entries with status 'registered'
        $entries = GFAPI::get_entries($form_id, [
            'field_filters' => [
                [
                    'key' => '4',
                    'value' => 'registered',
                ],
            ],
        ]);

        if (empty($entries) || is_wp_error($entries)) {
            error_log('DTS: No registered entries found for form: ' . $form_id);
            return false;
        }

        // Load template processor
        $templateProcessor = new TemplateProcessor($template_path);

        // Clone row for each attendee
        $count = count($entries);
        $templateProcessor->cloneRow('row', $count);

        // Fill in attendee data
        foreach ($entries as $index => $entry) {
            $row = $index + 1;
            $templateProcessor->setValue("row#{$row}", $row);
            $templateProcessor->setValue("ime#{$row}", rgar($entry, '1.3', ''));
            $templateProcessor->setValue("priimek#{$row}", rgar($entry, '1.6', ''));
            $templateProcessor->setValue("email#{$row}", rgar($entry, '2', ''));
        }

        // Setup output directory
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/attendance-lists/';
        wp_mkdir_p($pdf_dir);

        // Generate filenames
        $timestamp = time();
        $filename_base = 'attendance-' . $course_session_id . '-' . $timestamp;
        $temp_docx = $pdf_dir . $filename_base . '.docx';
        $pdf_path = $pdf_dir . $filename_base . '.pdf';

        // Save DOCX
        $templateProcessor->saveAs($temp_docx);

        // Convert to PDF
        dts_convert_docx_to_pdf($temp_docx, $pdf_path);

        // Cleanup temp file
        unlink($temp_docx);

        return $pdf_path;

    } catch (Exception $e) {
        error_log('DTS: Attendance List Generation Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Handle attendance list download button
 */
add_action('acf/save_post', 'dts_handle_attendance_download', 20);
function dts_handle_attendance_download($post_id) {
    // Only for course_session post type
    if (get_post_type($post_id) !== 'course_session') {
        return;
    }

    // Check if download button was clicked
    if (isset($_POST['acf']) && isset($_POST['acf']['field_dts_cs_download_attendance_btn'])) {
        // Generate attendance list
        $pdf_path = dts_generate_attendance_list($post_id);

        if ($pdf_path && file_exists($pdf_path)) {
            // Force download
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($pdf_path) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($pdf_path));

            ob_clean();
            flush();
            readfile($pdf_path);
            exit;
        } else {
            // Show error notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Napaka:</strong> Podpisnega lista ni bilo mogoče generirati. ';
                echo 'Preverite, ali ste izbrali predlogo in ali obstajajo prijave.';
                echo '</p></div>';
            });
        }
    }
}
