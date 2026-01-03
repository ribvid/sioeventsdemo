<?php

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;


add_action('save_post_email_template', 'generate_html_from_word_template', 10, 3);

function generate_html_from_word_template($post_id, $post, $update)
{
    error_log('=== EMAIL TEMPLATE GENERATION START ===');
    error_log('Post ID: ' . $post_id);
    error_log('Post Status: ' . $post->post_status);

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        error_log('EARLY EXIT: Autosave in progress');
        return;
    }

    if (wp_is_post_revision($post_id)) {
        error_log('EARLY EXIT: Post revision');
        return;
    }

    if ($post->post_status !== 'publish' && $post->post_status !== 'future') {
        error_log('EARLY EXIT: Post not published or scheduled');
        return;
    }

    $template_file = get_field('template', $post_id);
    error_log('Template ACF field: ' . ($template_file ? 'EXISTS' : 'MISSING'));

    if (!$template_file || !isset($template_file['id'])) {
        error_log('EARLY EXIT: Template file is empty or invalid');
        return;
    }

    $template_path = get_attached_file($template_file['id']);
    error_log('Template path: ' . $template_path);
    error_log('Template file exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));

    if (!file_exists($template_path)) {
        error_log('EARLY EXIT: Word template not found: ' . $template_path);
        return;
    }

    try {
        error_log('Starting HTML generation...');

        $upload_dir = wp_upload_dir();
        $html_dir = $upload_dir['basedir'] . '/email-templates/';

        if (!file_exists($html_dir)) {
            wp_mkdir_p($html_dir);
            error_log('Created directory: ' . $html_dir);
        }

        $timestamp = time();
        $slug = sanitize_title($post->post_title);
        $temp_docx = $html_dir . 'temp-' . $timestamp . '.docx';
        $temp_html = $html_dir . 'temp-' . $timestamp . '.html';
        $html_filename = $post_id . '-' . $slug . '.html';
        $html_path = $html_dir . $html_filename;

        $templateProcessor = new TemplateProcessor($template_path);
        $templateProcessor->saveAs($temp_docx);
        error_log('Saved temporary DOCX: ' . $temp_docx);

        $phpWord = \PhpOffice\PhpWord\IOFactory::load($temp_docx);

        $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $htmlWriter->save($temp_html);
        error_log('Saved temporary HTML: ' . $temp_html);

        $html_content = file_get_contents($temp_html);

        $html_content = preg_replace('/<!DOCTYPE[^>]*>/', '', $html_content);
        $html_content = preg_replace('/<html[^>]*>/', '<html>', $html_content);
        $html_content = preg_replace('/<head[^>]*>/', '<head>', $html_content);
        $html_content = preg_replace('/<body[^>]*>/', '<body style="margin: 0; padding: 0;">', $html_content);

        file_put_contents($html_path, $html_content);
        error_log('HTML created at: ' . $html_path);
        error_log('HTML file exists: ' . (file_exists($html_path) ? 'YES - Size: ' . filesize($html_path) . ' bytes' : 'NO'));

        unlink($temp_docx);
        unlink($temp_html);
        error_log('Cleaned up temporary files');

        $html_url = $upload_dir['baseurl'] . '/email-templates/' . $html_filename;
        update_post_meta($post_id, '_email_html_path', $html_path);
        update_post_meta($post_id, '_email_html_url', $html_url);
        update_post_meta($post_id, '_email_html_timestamp', $timestamp);

        error_log('Metadata saved:');
        error_log('  - _email_html_path: ' . $html_path);
        error_log('  - _email_html_url: ' . $html_url);

        generate_email_template_thumbnail($post_id, $upload_dir);

        error_log('=== EMAIL TEMPLATE GENERATION SUCCESSFUL ===');

    } catch (Exception $e) {
        error_log('=== EMAIL TEMPLATE GENERATION FAILED ===');
        error_log('Exception: ' . $e->getMessage());
        error_log('Trace: ' . $e->getTraceAsString());
    }
}


function generate_email_template_thumbnail($post_id, $upload_dir)
{
    error_log('=== EMAIL TEMPLATE THUMBNAIL GENERATION START ===');
    error_log('Post ID: ' . $post_id);

    try {
        $thumb_dir = $upload_dir['basedir'] . '/email-templates/thumbs/';

        if (!file_exists($thumb_dir)) {
            wp_mkdir_p($thumb_dir);
            error_log('Created thumb directory: ' . $thumb_dir);
        }

        $thumb_filename = $post_id . '-thumb.png';
        $thumb_path = $thumb_dir . $thumb_filename;
        $thumb_url = $upload_dir['baseurl'] . '/email-templates/thumbs/' . $thumb_filename;

        $thumbnail_created = create_email_thumbnail_with_gd($thumb_path);

        if ($thumbnail_created) {
            update_post_meta($post_id, '_email_thumbnail_path', $thumb_filename);
            update_post_meta($post_id, '_email_thumbnail_url', $thumb_url);
            error_log('Thumbnail created: ' . $thumb_path);
            error_log('Thumbnail URL: ' . $thumb_url);
            error_log('Thumbnail filename stored: ' . $thumb_filename);
            error_log('=== EMAIL TEMPLATE THUMBNAIL GENERATION SUCCESSFUL ===');
        } else {
            error_log('FAILED: Thumbnail file not created');
            update_post_meta($post_id, '_email_thumbnail_failed', true);
        }

    } catch (Exception $e) {
        error_log('=== EMAIL TEMPLATE THUMBNAIL GENERATION FAILED ===');
        error_log('Exception: ' . $e->getMessage());
        error_log('Trace: ' . $e->getTraceAsString());
        update_post_meta($post_id, '_email_thumbnail_failed', true);
    }
}


function create_email_thumbnail_with_gd($thumb_path)
{
    try {
        $width = 300;
        $height = (int)($width * 1.414);

        $image = imagecreatetruecolor($width, $height);

        $bg_color = imagecolorallocate($image, 248, 249, 250);
        imagefill($image, 0, 0, $bg_color);

        $icon_bg = imagecolorallocate($image, 220, 53, 69);
        $icon_x = ($width - 80) / 2;
        $icon_y = ($height - 100) / 2 - 30;
        imagefilledrectangle($image, $icon_x, $icon_y, $icon_x + 80, $icon_y + 100, $icon_bg);

        $page_color = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, $icon_x + 10, $icon_y + 10, $icon_x + 70, $icon_y + 70, $page_color);

        $pdf_text_color = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 2, $icon_x + 20, $icon_y + 25, 'PDF', $pdf_text_color);

        $lines_color = imagecolorallocate($image, 200, 200, 200);
        for ($i = 0; $i < 5; $i++) {
            $y = $icon_y + 40 + ($i * 6);
            imageline($image, $icon_x + 20, $y, $icon_x + 60, $y, $lines_color);
        }

        $shadow_color = imagecolorallocate($image, 100, 100, 100);
        imagefilledrectangle($image, 4, 4, $width - 4, $height - 4, $shadow_color);

        imagepng($image, $thumb_path, 9);
        imagedestroy($image);

        error_log('GD thumbnail created successfully');
        return true;

    } catch (Exception $e) {
        error_log('GD thumbnail creation failed: ' . $e->getMessage());
        return false;
    }
}


function get_email_html($template_id, $placeholders = [])
{
    error_log('=== GET EMAIL HTML START ===');
    error_log('Template ID: ' . $template_id);

    $html_path = get_post_meta($template_id, '_email_html_path', true);

    if (!$html_path || !file_exists($html_path)) {
        error_log('EARLY EXIT: HTML file not found: ' . $html_path);
        return false;
    }

    $html_content = file_get_contents($html_path);

    if ($html_content === false) {
        error_log('EARLY EXIT: Could not read HTML file');
        return false;
    }

    if (!empty($placeholders)) {
        error_log('Replacing placeholders: ' . count($placeholders));
        foreach ($placeholders as $placeholder => $value) {
            $html_content = str_replace($placeholder, $value, $html_content);
        }
    }

    error_log('=== GET EMAIL HTML SUCCESSFUL ===');

    return $html_content;
}



function delete_email_thumbnail($post_id)
{
    error_log('=== DELETE EMAIL THUMBNAIL START ===');
    error_log('Post ID: ' . $post_id);

    $thumb_path = get_post_meta($post_id, '_email_thumbnail_path', true);

    if ($thumb_path && file_exists($thumb_path)) {
        unlink($thumb_path);
        error_log('Deleted thumbnail: ' . $thumb_path);
    }

    delete_post_meta($post_id, '_email_thumbnail_path');
    delete_post_meta($post_id, '_email_thumbnail_url');
    delete_post_meta($post_id, '_email_thumbnail_failed');

    error_log('=== DELETE EMAIL THUMBNAIL SUCCESSFUL ===');
}


function process_email_placeholders($html, $entry, $course_session_id)
{
    error_log('=== PROCESS EMAIL PLACEHOLDERS START ===');

    $placeholders = [
        '${ime_udeleženca}' => $entry["1.3"] ?? '',
        '${priimek_udeleženca}' => $entry["1.6"] ?? '',
        '${email_udeleženca}' => $entry["2"] ?? '',
        '${id_prijave}' => $entry['id'] ?? '',
        '${datum_prijave}' => $entry['date_created'] ?? '',
        '${ime_dogodka}' => get_the_title($course_session_id),
        '${qr_koda}' => '',
    ];

    if (have_rows('placeholders', $course_session_id)) {
        while (have_rows('placeholders', $course_session_id)) {
            the_row();
            $placeholders[get_sub_field('placeholder')] = get_sub_field('value');
        }
    }

    $start_date = get_field('start_date', $course_session_id);
    if ($start_date) {
        $placeholders['${datum_izvedbe}'] = $start_date;
    }

    $location = get_field('location', $course_session_id);
    if ($location) {
        $placeholders['${kraj}'] = $location;
    }

    $institution = get_field('institution', $course_session_id);
    if ($institution) {
        $placeholders['${institucija}'] = $institution;
    }

    foreach ($placeholders as $placeholder => $value) {
        $html = str_replace($placeholder, $value, $html);
    }

    error_log('=== PROCESS EMAIL PLACEHOLDERS SUCCESSFUL ===');

    return $html;
}


function get_email_template_by_type($course_session_id, $email_type)
{
    error_log('=== GET EMAIL TEMPLATE BY TYPE START ===');
    error_log('Course Session ID: ' . $course_session_id);
    error_log('Email Type: ' . $email_type);

    $field_map = [
        'registration' => 'email_template_registration',
        'registration_cancellation' => 'email_template_cancellation',
        'x_days_before' => 'email_template_reminder',
        'added_to_moodle' => 'email_template_moodle',
        'course_cancellation' => 'email_template_course_cancellation',
        'course_finished' => 'email_template_course_finished',
    ];

    $field_name = $field_map[$email_type] ?? null;

    if (!$field_name) {
        error_log('EARLY EXIT: Unknown email type');
        return null;
    }

    $template = get_field($field_name, $course_session_id);

    if (!$template) {
        error_log('EARLY EXIT: No template selected');
        return null;
    }

    error_log('Template found: ' . $template->ID);
    error_log('=== GET EMAIL TEMPLATE BY TYPE SUCCESSFUL ===');

    return $template;
}


function send_html_email($to, $subject, $html_content, $attachments = [])
{
    error_log('=== SEND HTML EMAIL START ===');
    error_log('To: ' . $to);
    error_log('Subject: ' . $subject);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>',
    ];

    $result = wp_mail($to, $subject, $html_content, $headers, $attachments);

    if ($result) {
        error_log('Email sent successfully');
    } else {
        error_log('Email sending failed');
    }

    error_log('=== SEND HTML EMAIL END ===');

    return $result;
}
