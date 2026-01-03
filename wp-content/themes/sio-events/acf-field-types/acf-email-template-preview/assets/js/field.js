/**
 * Email Template Preview Field JavaScript
 */
(function ($) {
    function initialize_field($field) {
        console.log('[Email Template Preview] initialize_field called with:', $field);
        console.log('[Email Template Preview] $field HTML:', $field.html().substring(0, 200));

        // ACF passes the .acf-field wrapper, our custom wrapper is inside it
        var $wrapper = $field.find('.acf-email-template-preview-wrapper');
        console.log('[Email Template Preview] Wrapper found:', $wrapper, 'Length:', $wrapper.length);

        var $grid = $wrapper.find('.acf-email-template-preview-grid');
        console.log('[Email Template Preview] Grid found:', $grid, 'Length:', $grid.length);

        var $loading = $wrapper.find('.acf-email-template-preview-loading');
        console.log('[Email Template Preview] Loading found:', $loading, 'Length:', $loading.length);

        var $valueInput = $wrapper.find('.acf-email-template-preview-value');
        console.log('[Email Template Preview] Value input found:', $valueInput, 'Length:', $valueInput.length);

        var settings = {
            ajaxurl: acfEmailTemplatePreview.ajaxurl,
            action: acfEmailTemplatePreview.action,
            nonce: acfEmailTemplatePreview.nonce,
            maxColumns: $wrapper.data('max-columns') || 3,
            previewHeight: $wrapper.data('preview-height') || 60,
            previewScale: $wrapper.data('preview-scale') || 0.2,
            showEmailType: $wrapper.data('show-email-type') === '1',
            selectedId: $wrapper.data('selected') || '',
            emailTypeLabels: $wrapper.data('email-type-labels') || {},
            emailTypeColors: $wrapper.data('email-type-colors') || {},
            strings: acfEmailTemplatePreview.strings || {}
        };

        loadTemplates($wrapper, $grid, $loading, settings);

        $(document).on('acf_email_template_preview_refresh', function (e, fieldKey) {
            if ($wrapper.data('field-name') === fieldKey) {
                loadTemplates($wrapper, $grid, $loading, settings);
            }
        });
    }

    function loadTemplates($wrapper, $grid, $loading, settings) {
        console.log('[Email Template Preview] Loading templates...', settings);

        // Show loading state
        $grid.empty().append('<div class="acf-email-template-preview-loading"><span class="spinner"></span><span>Loading...</span></div>');

        $.ajax({
            url: settings.ajaxurl,
            type: 'POST',
            data: {
                action: settings.action,
                nonce: settings.nonce
            },
            success: function (response) {
                console.log('[Email Template Preview] AJAX Response:', response);

                if (response.success && response.data && response.data.templates) {
                    console.log('[Email Template Preview] Templates found:', response.data.templates.length);
                    $grid.empty(); // Clear loading state
                    renderTemplateGrid($grid, response.data.templates, settings);
                } else {
                    console.error('[Email Template Preview] No templates or error:', response);
                    $grid.empty(); // Clear loading state
                    showError($grid, settings.strings.no_templates || 'No templates found');
                }
            },
            error: function (xhr, status, error) {
                console.error('[Email Template Preview] AJAX Error:', {xhr: xhr, status: status, error: error});
                $grid.empty(); // Clear loading state
                showError($grid, 'Error loading templates');
            }
        });
    }

    function renderTemplateGrid($grid, templates, settings) {
        console.log('[Email Template Preview] Rendering grid with templates:', templates);
        console.log('[Email Template Preview] Grid element:', $grid);
        console.log('[Email Template Preview] Settings:', settings);

        if (templates.length === 0) {
            console.warn('[Email Template Preview] No templates to render');
            showError($grid, settings.strings.no_templates || 'No templates found');
            return;
        }

        var gridStyle = 'grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));';
        console.log('[Email Template Preview] Setting grid style:', gridStyle);
        $grid.attr('style', gridStyle);

        templates.forEach(function (template, index) {
            console.log('[Email Template Preview] Creating card for template ' + index + ':', template);
            var $card = createTemplateCard(template, $grid, settings);
            console.log('[Email Template Preview] Card created:', $card);
            $grid.append($card);
        });

        console.log('[Email Template Preview] Grid rendering complete. Total cards:', templates.length);
        console.log('[Email Template Preview] Grid HTML:', $grid.html());
        console.log('[Email Template Preview] Grid visible?', $grid.is(':visible'));
        console.log('[Email Template Preview] Grid width/height:', $grid.width(), $grid.height());
        console.log('[Email Template Preview] Grid children count:', $grid.children().length);
    }

    function createTemplateCard(template, $grid, settings) {
        var $card = $('<div class="acf-email-template-card" data-template-id="' + template.id + '">');

        if (settings.selectedId && parseInt(settings.selectedId) === template.id) {
            $card.addClass('is-selected');
        }

        var $thumbnail = $('<div class="acf-email-template-thumbnail">');

        if (template.html_url) {
            var scale = settings.previewScale || 0.4;
            var height = settings.previewHeight || 80;

            var $iframe = $('<iframe class="acf-email-template-iframe">');
            $iframe.attr('src', template.html_url);
            $iframe.attr('loading', 'lazy');
            $iframe.css({
                'width': '1000px',
                'height': '1414px',
                'position': 'absolute',
                'top': '0',
                'left': '0',
                'border': 'none',
                'transform': 'scale(' + scale + ')',
                'transform-origin': 'top left',
                'pointer-events': 'none'
            });
            $thumbnail.append($iframe);
        } else {
            var $placeholder = $('<div class="acf-email-template-thumbnail-placeholder">');
            $placeholder.text(settings.strings.no_preview || 'No preview available');
            $thumbnail.append($placeholder);
        }

        var $info = $('<div class="acf-email-template-info">');

        var $title = $('<div class="acf-email-template-title">');
        $title.text(template.title);
        $info.append($title);

        if (settings.showEmailType && template.email_type) {
            var typeLabel = settings.emailTypeLabels[template.email_type] || template.email_type;
            var typeColor = settings.emailTypeColors[template.email_type] || '#666';
            var $typeBadge = $('<div class="acf-email-template-type-badge">');
            $typeBadge.css('background-color', typeColor);
            $typeBadge.text(typeLabel);
            $info.append($typeBadge);
        }

        var $actions = $('<div class="acf-email-template-actions">');

        if (template.html_url) {
            var $viewButton = $('<button class="button button-small">');
            $viewButton.text(settings.strings.view_full || 'View full');
            $viewButton.on('click', function (e) {
                e.stopPropagation();
                openPreviewModal(template);
            });
            $actions.append($viewButton);
        }

        $card.append($thumbnail);
        $card.append($info);
        $card.append($actions);

        $card.on('click', function () {
            selectTemplate($(this), $grid, settings);
        });

        return $card;
    }

    function selectTemplate($card, $grid, settings) {
        var templateId = $card.data('template-id');
        var $wrapper = $grid.closest('.acf-email-template-preview-wrapper');
        var $valueInput = $wrapper.find('.acf-email-template-preview-value');

        $grid.find('.acf-email-template-card').removeClass('is-selected');
        $card.addClass('is-selected');
        $valueInput.val(templateId);
        $valueInput.trigger('change');
    }

    function openPreviewModal(template) {
        var $modal = $('<div class="acf-email-template-preview-modal">');

        var $overlay = $('<div class="acf-email-template-preview-overlay">');
        $modal.append($overlay);

        var $content = $('<div class="acf-email-template-preview-content">');

        var $header = $('<div class="acf-email-template-preview-header">');

        var $title = $('<h2>');
        $title.text(template.title);
        $header.append($title);

        var $closeButton = $('<button class="button button-close-modal">');
        $closeButton.text('Close');
        $closeButton.on('click', function () {
            $modal.remove();
        });
        $header.append($closeButton);

        var $body = $('<div class="acf-email-template-preview-body">');

        if (template.html_url) {
            var $iframe = $('<iframe class="acf-email-template-preview-iframe">');
            $iframe.attr('src', template.html_url);
            $iframe.on('load', function () {
                $body.find('.acf-email-template-preview-loading').remove();
            });
            $body.append($iframe);
        } else {
            $body.text('Preview not available');
        }

        $content.append($header);
        $content.append($body);
        $modal.append($content);

        $('body').append($modal);

        $modal.on('click', '.acf-email-template-preview-overlay', function () {
            $modal.remove();
        });

        $(document).on('keydown.acfEmailTemplateModal', function (e) {
            if (e.key === 'Escape') {
                $modal.remove();
                $(document).off('keydown.acfEmailTemplateModal');
            }
        });
    }

    function showError($grid, message) {
        var $error = $('<div class="acf-email-template-preview-error">');
        $error.text(message);
        $grid.append($error);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (typeof acf.add_action !== 'undefined') {
        acf.add_action('ready_field/type=email_template_preview', initialize_field);
        acf.add_action('append_field/type=email_template_preview', initialize_field);
    }
})(jQuery);
