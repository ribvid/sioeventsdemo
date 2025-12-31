(function() {
    'use strict';

    // Check if TinyMCE is available
    if (typeof tinymce === 'undefined') {
        console.error('AI Engine TinyMCE Translate: TinyMCE not found');
        return;
    }

    // Check if configuration is available
    if (typeof window.AIET_Config === 'undefined') {
        console.error('AI Engine TinyMCE Translate: Configuration not found');
        return;
    }

    /**
     * Register the TinyMCE plugin
     */
    tinymce.PluginManager.add('aiet_translate', function(editor, url) {

        /**
         * Add the translate button to the toolbar
         */
        editor.addButton('aiet_translate', {
            text: window.AIET_Config.i18n.buttonLabel,
            icon: 'language',
            tooltip: window.AIET_Config.i18n.buttonTitle,
            onclick: function() {
                handleTranslateClick(editor);
            }
        });

        /**
         * Add auto-translate button if Polylang is enabled
         */
        if (window.AIET_Config.polylang && window.AIET_Config.polylang.enabled) {
            editor.addButton('aiet_auto_translate', {
                text: window.AIET_Config.i18n.autoTranslateButtonLabel,
                icon: 'translate',
                tooltip: window.AIET_Config.i18n.autoTranslateButtonTitle,
                onclick: function() {
                    handleAutoTranslateClick(editor);
                }
            });
        }

        /**
         * Handle the translate button click
         *
         * @param {Object} editor - TinyMCE editor instance
         */
        function handleTranslateClick(editor) {
            // Get editor content
            var content = editor.getContent({ format: 'html' });

            // Get title field value
            var titleField = document.getElementById('title');
            var title = titleField ? titleField.value.trim() : '';

            // Validate - at least title OR content should have text
            var hasContent = content && content.trim() !== '' && content.trim() !== '<p></p>' && content.trim() !== '<p><br></p>';
            var hasTitle = title && title !== '';

            if (!hasContent && !hasTitle) {
                editor.notificationManager.open({
                    text: window.AIET_Config.i18n.errorNoContent,
                    type: 'warning',
                    timeout: 3000
                });
                return;
            }

            // Show loading notification
            var notification = editor.notificationManager.open({
                text: window.AIET_Config.i18n.processing,
                type: 'info',
                closeButton: false
            });

            // Set editor to readonly during translation
            editor.setMode('readonly');

            // Perform translation (pass both content and title)
            performTranslation(editor, content, title, notification);
        }

        /**
         * Handle the auto-translate from Slovene button click
         *
         * @param {Object} editor - TinyMCE editor instance
         */
        function handleAutoTranslateClick(editor) {
            var config = window.AIET_Config;

            // Validate Polylang is available
            if (!config.polylang || !config.polylang.enabled) {
                editor.notificationManager.open({
                    text: config.i18n.errorPolylangDisabled,
                    type: 'error',
                    timeout: 3000
                });
                return;
            }

            // Check if Slovene version exists
            if (!config.polylang.slovenePostId) {
                editor.notificationManager.open({
                    text: config.i18n.errorNoSlovene,
                    type: 'warning',
                    timeout: 4000
                });
                return;
            }

            // Get current editor content and title
            var currentContent = editor.getContent({ format: 'html' });
            var titleField = document.getElementById('title');
            var currentTitle = titleField ? titleField.value.trim() : '';

            // Check if current post is empty
            var hasContent = currentContent && currentContent.trim() !== '' &&
                             currentContent.trim() !== '<p></p>' &&
                             currentContent.trim() !== '<p><br></p>';
            var hasTitle = currentTitle && currentTitle !== '';

            if (hasContent || hasTitle) {
                editor.notificationManager.open({
                    text: config.i18n.errorEmptyContent,
                    type: 'warning',
                    timeout: 4000
                });
                return;
            }

            // Show loading notification
            var notification = editor.notificationManager.open({
                text: config.i18n.autoTranslateProcessing,
                type: 'info',
                closeButton: false
            });

            // Set editor to readonly
            editor.setMode('readonly');

            // Fetch and translate
            fetchAndTranslateSlovene(editor, config.polylang.slovenePostId, notification);
        }

        /**
         * Fetch Slovene post content and translate it
         *
         * @param {Object} editor - TinyMCE editor instance
         * @param {number} slovenePostId - ID of Slovene post
         * @param {Object} notification - Notification object
         */
        function fetchAndTranslateSlovene(editor, slovenePostId, notification) {
            var config = window.AIET_Config;

            // Fetch Slovene post via WordPress REST API
            fetch(config.restRoot + 'wp/v2/posts/' + slovenePostId, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to fetch Slovene post');
                return response.json();
            })
            .then(function(slovenePost) {
                // Extract title and content
                var sloveneTitle = slovenePost.title && slovenePost.title.rendered ?
                                  slovenePost.title.rendered : '';
                var sloveneContent = slovenePost.content && slovenePost.content.rendered ?
                                    slovenePost.content.rendered : '';

                // Validate Slovene post has content
                var hasSlTitle = sloveneTitle && sloveneTitle.trim() !== '';
                var hasSlContent = sloveneContent && sloveneContent.trim() !== '' &&
                                  sloveneContent.trim() !== '<p></p>';

                if (!hasSlTitle && !hasSlContent) {
                    notification.close();
                    editor.setMode('design');
                    editor.notificationManager.open({
                        text: config.i18n.errorSloveneEmpty,
                        type: 'warning',
                        timeout: 3000
                    });
                    return;
                }

                // Reuse existing performTranslation function
                performTranslation(editor, sloveneContent, sloveneTitle, notification);
            })
            .catch(function(error) {
                handleTranslationError(editor, error, notification);
            });
        }

        /**
         * Perform the translation API calls for both title and content
         *
         * @param {Object} editor - TinyMCE editor instance
         * @param {string} content - Content to translate
         * @param {string} title - Title to translate
         * @param {Object} notification - Notification object
         */
        function performTranslation(editor, content, title, notification) {
            var config = window.AIET_Config;
            var promises = [];

            // Translate title if it exists
            if (title && title.trim() !== '') {
                var titlePayload = {
                    action: 'translateSection',
                    data: {
                        postId: config.postId,
                        text: title,
                        context: content || title  // Use content as context if available
                    }
                };

                promises.push(
                    fetch(config.restUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': config.nonce
                        },
                        body: JSON.stringify(titlePayload)
                    }).then(function(response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                );
            } else {
                promises.push(Promise.resolve(null));  // No title to translate
            }

            // Translate content if it exists
            if (content && content.trim() !== '' && content.trim() !== '<p></p>' && content.trim() !== '<p><br></p>') {
                var contentPayload = {
                    action: 'translateSection',
                    data: {
                        postId: config.postId,
                        text: content,
                        context: content
                    }
                };

                promises.push(
                    fetch(config.restUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': config.nonce
                        },
                        body: JSON.stringify(contentPayload)
                    }).then(function(response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                );
            } else {
                promises.push(Promise.resolve(null));  // No content to translate
            }

            // Wait for both to complete
            Promise.all(promises)
                .then(function(results) {
                    var titleResult = results[0];
                    var contentResult = results[1];
                    handleTranslationSuccess(editor, titleResult, contentResult, notification);
                })
                .catch(function(error) {
                    handleTranslationError(editor, error, notification);
                });
        }

        /**
         * Handle successful translation response
         *
         * @param {Object} editor - TinyMCE editor instance
         * @param {Object} titleResult - API response for title translation
         * @param {Object} contentResult - API response for content translation
         * @param {Object} notification - Notification object
         */
        function handleTranslationSuccess(editor, titleResult, contentResult, notification) {
            // Close loading notification
            notification.close();

            // Re-enable editor
            editor.setMode('design');

            var updated = false;

            // Update title if translated
            if (titleResult && titleResult.success && titleResult.data && titleResult.data.result) {
                var titleField = document.getElementById('title');
                if (titleField) {
                    titleField.value = titleResult.data.result;
                    updated = true;
                }
            }

            // Update content if translated
            if (contentResult && contentResult.success && contentResult.data && contentResult.data.result) {
                editor.setContent(contentResult.data.result);
                updated = true;
            }

            if (!updated) {
                handleTranslationError(editor, new Error('No translations returned'), notification);
                return;
            }

            // Show success message
            editor.notificationManager.open({
                text: window.AIET_Config.i18n.success,
                type: 'success',
                timeout: 3000
            });

            // Mark editor as dirty to enable save button
            editor.setDirty(true);

            // Trigger change event
            editor.fire('change');
        }

        /**
         * Handle translation error
         *
         * @param {Object} editor - TinyMCE editor instance
         * @param {Error} error - Error object
         * @param {Object} notification - Notification object
         */
        function handleTranslationError(editor, error, notification) {
            // Close loading notification if it exists
            if (notification && typeof notification.close === 'function') {
                notification.close();
            }

            // Re-enable editor
            editor.setMode('design');

            // Determine error message
            var errorMessage = window.AIET_Config.i18n.errorGeneric;
            if (error && error.message) {
                errorMessage += ' (' + error.message + ')';
            }

            // Show error notification
            editor.notificationManager.open({
                text: errorMessage,
                type: 'error',
                timeout: 5000
            });

            // Log error to console for debugging
            console.error('AI Engine Translation Error:', error);
        }

        // Return plugin metadata
        return {
            getMetadata: function() {
                return {
                    name: 'AI Engine TinyMCE Translate',
                    url: 'https://github.com/anthropics/claude-code'
                };
            }
        };
    });

})();
