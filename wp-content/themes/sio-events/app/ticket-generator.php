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
    $post_id = $entry['6'];
    $ticket = get_field('ticket', $post_id);

    if (!$ticket) return;

    $ticket_template = get_field('template', $ticket);

    if (!$ticket_template) return;

    $template_path = get_attached_file($ticket_template['id']);

    // Check if template exists
    if (!file_exists($template_path)) {
        error_log('Word template not found: ' . $template_path);
        return;
    }

    try {
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

        unlink($temp_docx);
        unlink($temp_html);
        unlink($qr_temp_path);

        // Store PDF path in entry meta for later use
        gform_update_meta($entry['id'], 'generated_pdf_path', $pdf_path);
        gform_update_meta($entry['id'], 'generated_pdf_url', $upload_dir['baseurl'] . '/gravity-forms-pdfs/' . $pdf_filename);

        // Send email with PDF attachment
        gf_send_pdf_email($entry, $pdf_path);

    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
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

