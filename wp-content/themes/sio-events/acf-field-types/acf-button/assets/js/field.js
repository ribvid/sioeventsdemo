/**
 * Included when button fields are rendered for editing by publishers.
 */
(function ($) {
    function initialize_field($field) {
        /**
         * $field is a jQuery object wrapping field elements in the editor.
         */
        var $button = $field.find('[data-element="acf-button-field"]');
        var $spinner = $field.find('[data-element="acf-button-spinner"]');

        $button.on('click', function (e) {
            e.preventDefault();

            var callback = $(this).data('callback');
            var nonce = $(this).data('nonce');
            var postId = $(this).data('post-id');

            if (!callback) {
                alert('No callback function specified');
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            $spinner.show();

            // Make AJAX request
            $.ajax({
                url: acfButton.ajaxurl,
                type: 'POST',
                data: {
                    action: acfButton.action,
                    callback: callback,
                    nonce: nonce,
                    postId: postId,
                },
                success: function (response) {
                    if (response.success) {
                        // console.log('Action completed successfully!');
                        // console.log('Result:', response.data);
                        $(document).trigger(`${callback}_completed`, [postId]);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function () {
                    alert('AJAX request failed');
                },
                complete: function () {
                    $button.prop('disabled', false);
                    $spinner.hide();
                }
            });
        });
    }

    if (typeof acf.add_action !== 'undefined') {
        /**
         * Run initialize_field when existing fields of this type load,
         * or when new fields are appended via repeaters or similar.
         */
        acf.add_action('ready_field/type=button', initialize_field);
        acf.add_action('append_field/type=button', initialize_field);
    }
})(jQuery);
