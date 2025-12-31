(function($) {
    'use strict';

    /**
     * Excerpt Generator
     * Handles AJAX calls to generate excerpts using AI Engine
     */
    const ExcerptGenerator = {

        /**
         * Initialize the excerpt generator
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#generate-excerpt-btn').on('click', this.handleGenerateClick.bind(this));
        },

        /**
         * Handle generate button click
         */
        handleGenerateClick: function(e) {
            e.preventDefault();

            const $button = $('#generate-excerpt-btn');
            const $spinner = $('#excerpt-generator-wrapper .spinner');
            const $message = $('#excerpt-generator-message');

            // Get title and content
            const title = $('#title').val() || '';
            let content = '';

            // Try to get content from TinyMCE editor
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                content = tinymce.get('content').getContent();
            } else {
                // Fallback to textarea
                content = $('#content').val() || '';
            }

            // Validate input
            if (!title && !content) {
                this.showMessage($message, excerptGeneratorConfig.i18n.noContent, 'error');
                return;
            }

            // Disable button and show spinner
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.removeClass('error success').text('');

            // Prepare AJAX data
            const data = {
                action: 'generate_excerpt',
                nonce: $button.data('nonce'),
                post_id: $button.data('post-id'),
                title: title,
                content: content
            };

            // Make AJAX request
            $.ajax({
                url: excerptGeneratorConfig.ajaxUrl,
                type: 'POST',
                data: data,
                success: this.handleSuccess.bind(this, $button, $spinner, $message),
                error: this.handleError.bind(this, $button, $spinner, $message)
            });
        },

        /**
         * Handle successful response
         */
        handleSuccess: function($button, $spinner, $message, response) {
            // Re-enable button and hide spinner
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success && response.data.excerpt) {
                // Update excerpt field
                $('#excerpt').val(response.data.excerpt);

                // Show success message
                this.showMessage($message, response.data.message || excerptGeneratorConfig.i18n.success, 'success');

                // Scroll to excerpt field
                this.scrollToExcerpt();
            } else {
                // Show error message
                const errorMsg = response.data && response.data.message ? response.data.message : excerptGeneratorConfig.i18n.error;
                this.showMessage($message, errorMsg, 'error');
            }
        },

        /**
         * Handle error response
         */
        handleError: function($button, $spinner, $message, jqXHR, textStatus, errorThrown) {
            // Re-enable button and hide spinner
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');

            // Show error message
            const errorMsg = excerptGeneratorConfig.i18n.error + ' (' + textStatus + ')';
            this.showMessage($message, errorMsg, 'error');

            // Log error for debugging
            console.error('Excerpt generation error:', errorThrown);
        },

        /**
         * Show message to user
         */
        showMessage: function($message, text, type) {
            $message
                .removeClass('error success')
                .addClass(type)
                .text(text);

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut(300, function() {
                        $(this).text('').show();
                    });
                }, 5000);
            }
        },

        /**
         * Scroll to excerpt field
         */
        scrollToExcerpt: function() {
            const $excerptField = $('#excerpt');
            if ($excerptField.length) {
                $('html, body').animate({
                    scrollTop: $excerptField.offset().top - 100
                }, 500);

                // Focus on excerpt field
                $excerptField.focus();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#generate-excerpt-btn').length) {
            ExcerptGenerator.init();
        }
    });

})(jQuery);
