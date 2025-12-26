/**
 * Included when attendees_list fields are rendered for editing by publishers.
 */
(function ($) {
    function initialize_field($field) {
        /**
         * $field is a jQuery object wrapping field elements in the editor.
         */
        console.log('attendees_list field initialized');

        $field.find('button.entry-action').on('click', function (e) {
            e.preventDefault();

            var action = $(this).data('action');
            var entryId = $(this).data('entry-id');

            if ($(this).is(':disabled')) {
                return;
            }

            $.ajax({
                url: acfAttendeesList.ajaxurl,
                type: 'POST',
                data: {
                    action: acfAttendeesList.action,
                    entry_action: action,
                    entry_id: entryId,
                    nonce: acfAttendeesList.nonce,
                },
            }).done(function (response) {
                location.reload()
            });
        });
    }

    if (typeof acf.add_action !== 'undefined') {
        /**
         * Run initialize_field when existing fields of this type load,
         * or when new fields are appended via repeaters or similar.
         */
        acf.add_action('ready_field/type=attendees_list', initialize_field);
        acf.add_action('append_field/type=attendees_list', initialize_field);
    }
})(jQuery);
