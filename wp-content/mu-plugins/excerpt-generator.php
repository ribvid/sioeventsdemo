<?php
/*
Plugin Name: AI Excerpt Generator
Description: Adds AI-powered excerpt generation button near excerpt metabox
Version: 1.0.0
Author: Custom
*/

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class AI_Excerpt_Generator {

    const PLUGIN_DIR = __DIR__;
    const PLUGIN_URL = WP_CONTENT_URL . '/mu-plugins/excerpt-generator';

    public function __construct() {
        add_action('edit_form_after_editor', [$this, 'render_button']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_generate_excerpt', [$this, 'ajax_generate_excerpt']);
    }

    /**
     * Check if AI Engine is available
     */
    private function is_ai_engine_available() {
        return class_exists('Meow_MWAI_Core');
    }

    /**
     * Render the Generate Excerpt button
     */
    public function render_button($post) {
        // Only show on post types that support excerpts
        if (!post_type_supports($post->post_type, 'excerpt')) {
            return;
        }

        // Check if AI Engine is available
        if (!$this->is_ai_engine_available()) {
            return;
        }

        // Create nonce for security
        $nonce = wp_create_nonce('generate_excerpt_nonce');
        ?>
        <div id="excerpt-generator-wrapper" style="margin: 20px 0;">
            <button type="button"
                    id="generate-excerpt-btn"
                    class="button button-secondary"
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                <span class="dashicons dashicons-editor-paragraph" style="margin-top: 3px;"></span>
                <?php esc_html_e('Generate Excerpt with AI', 'excerpt-generator'); ?>
            </button>
            <span class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
            <span id="excerpt-generator-message" style="margin-left: 10px;"></span>
        </div>
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        global $post;

        // Only enqueue if post type supports excerpts
        if (!$post || !post_type_supports($post->post_type, 'excerpt')) {
            return;
        }

        // Check if AI Engine is available
        if (!$this->is_ai_engine_available()) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'excerpt-generator',
            self::PLUGIN_URL . '/assets/excerpt-generator.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Enqueue CSS
        wp_enqueue_style(
            'excerpt-generator',
            self::PLUGIN_URL . '/assets/excerpt-generator.css',
            [],
            '1.0.0'
        );

        // Localize script with admin-ajax URL
        wp_localize_script('excerpt-generator', 'excerptGeneratorConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'generating' => __('Generating excerpt...', 'excerpt-generator'),
                'success' => __('Excerpt generated successfully!', 'excerpt-generator'),
                'error' => __('Error generating excerpt. Please try again.', 'excerpt-generator'),
                'noContent' => __('Please add some content before generating an excerpt.', 'excerpt-generator'),
            ]
        ]);
    }

    /**
     * AJAX handler to generate excerpt
     */
    public function ajax_generate_excerpt() {
        // Verify nonce
        check_ajax_referer('generate_excerpt_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'excerpt-generator')]);
            return;
        }

        // Check if AI Engine is available
        if (!$this->is_ai_engine_available()) {
            wp_send_json_error(['message' => __('AI Engine plugin is not available.', 'excerpt-generator')]);
            return;
        }

        // Get and sanitize input
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

        // Validate input
        if (empty($title) && empty($content)) {
            wp_send_json_error(['message' => __('Please provide a title or content.', 'excerpt-generator')]);
            return;
        }

        try {
            global $mwai_core;

            // Get post language for localization
            $language = 'Slovenian'; // Default
            if ($post_id > 0 && method_exists($mwai_core, 'get_post_language')) {
                $detected_language = $mwai_core->get_post_language($post_id);
                if (!empty($detected_language)) {
                    $language = $detected_language;
                }
            }

            // Build the prompt based on AI Engine's excerpt generation pattern
            $prompt = "Craft a clear, SEO-optimized introduction for the following text, using 120 to 170 characters. ";
            $prompt .= "Ensure the introduction is concise and relevant, without including any URLs. ";
            $prompt .= "Ensure the reply is in the same language as the original text ({$language}).\n\n";

            if (!empty($title)) {
                $prompt .= "Title: {$title}\n\n";
            }

            // Strip HTML tags from content for better processing
            $clean_content = wp_strip_all_tags($content);
            $clean_content = substr($clean_content, 0, 4000); // Limit content length

            $prompt .= $clean_content;

            // Create and configure the AI query
            $query = new Meow_MWAI_Query_Text('', 1024);
            $query->set_scope('admin-tools');
            $query->set_message($prompt);

            // Execute the query
            $reply = $mwai_core->run_query($query);

            // Get the result
            $excerpt = $reply->result;

            // Sanitize and return
            $excerpt = sanitize_text_field($excerpt);

            wp_send_json_success([
                'excerpt' => $excerpt,
                'message' => __('Excerpt generated successfully!', 'excerpt-generator')
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error: %s', 'excerpt-generator'),
                    $e->getMessage()
                )
            ]);
        }
    }
}

// Initialize the plugin
new AI_Excerpt_Generator();
