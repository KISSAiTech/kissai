/**
 * kissai-admin-elements.js
 *
 * This script handles row highlighting and optionally performs an AJAX call
 * for any link/button with the .assistant-action-button class.
 *
 * Requires jQuery. Make sure to enqueue this script in your plugin's admin code:
 *
 *   wp_enqueue_script(
 *     'kissai-admin-elements',
 *     plugin_dir_url(__FILE__) . 'js/kissai-admin-elements.js',
 *     ['jquery'],
 *     '1.0',
 *     true
 *   );
 *
 */
function convert_string_to_function(str) {
    if (str !== 'undefined' && str !== '' && str !== null) {
        return new Function(str);
    }
    return null;
}

function run_script(str, responseObject) {
    if (!str) return;

    // Create a new function with one parameter: response
    var func = new Function('response', str);

    // Call the function, passing in the response
    func(responseObject);
}


(function($) {
    $(document).ready(function() {

        // Attach one click handler for all elements with .assistant-action-button
        $(document).on('click', '.assistant-action-button', function(e) {
            e.preventDefault();

            // 1) Highlight the row and remove highlighting from others
            var $currentTr = $(this).closest('tr');
            var $otherTrs  = $('tr').not($currentTr);

            $currentTr.addClass('selected');
            $otherTrs.removeClass('selected');

            // 2) Fetch assistant ID from data attributes
            var assistantId = $(this).data('assistant-id');

            // 3) If an AJAX call is needed
            if (typeof kissai_vars !== 'undefined' && kissai_vars.ajax_url) {
                // Example: store the assistant ID in a hidden input
                $('input[name="selected_assistant_id"]').val(assistantId);
                var action = $(this).data('action');
                if (action === '') {
                    return;
                }
                var ajax_before_send = $(this).closest('table').siblings('input[name="kissai_admin_element_before_ajax"]').val();
                var ajax_success = $(this).closest('table').siblings('input[name="kissai_admin_element_ajax_success"]').val();
                var ajax_after_button_click = $(this).closest('table').siblings('input[name="kissai_admin_element_after_button_click"]').val();
                $.ajax({
                    url: kissai_vars.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: action,
                        assistant_id: assistantId,
                        nonce: kissai_vars.nonce
                    },
                    beforeSend: function() {
                        if (ajax_before_send !== 'undefined' && ajax_before_send !== '') {
                            run_script(ajax_before_send);
                        }
                    },
                    success: function(response) {
                        if (response.success) {
                            if (ajax_success !== '') {
                                run_script(ajax_success, response);
                            } else {
                                console.log('Success:', response);
                            }
                        } else {
                            alert('Error: ' + (response.data ? response.data.message : 'Unknown'));
                        }
                    }
                });
                if (ajax_after_button_click !== '') {
                    run_script (ajax_after_button_click);
                }
            }
        });

    });
})(jQuery);

jQuery(document).ready(function($) {
    $(document).on('click', '.dismiss-button', function() {
        $(this).closest('.is-dismissible').fadeOut();
    });
});

function edit_assistant_handler(response) {
    var $ = jQuery;
    $('input[name="openai_assistant_name"]').val(response.data.name);
    var form = $('form.assistant_edit');
    form.append('<input type="hidden" name="assistant_id" value="' + response.data.assistant_id + '">');
    var deleteRow = form.find('table.form-table tbody').find('tr.delete-button-row');
    if (deleteRow.length > 0) {
        deleteRow.replaceWith('<tr class="delete-button-row"><td></td><td><input type="submit" name="delete" class="button-red" value="Delete Assistant"></td></tr>');
    } else {
        form.find('table.form-table tbody').append('<tr class="delete-button-row"><td></td><td><input type="submit" name="delete" class="button-red" value="Delete Assistant"></td></tr>');
    }
    form.find('input[type="hidden"][name="action"]').val('update_assistant');
    form.find('input[type="submit"][name="submit"]').val('Update');
    form.find('select[name="model_id"]').val(response.data.model);
    form.find('input[type="submit"][name="delete"]').on('click', function(e) {
        if (confirm('Are you sure you want to delete \'' + response.data.name + '\'? This will delete all training data and cannot be undone.') === false) {
            e.preventDefault();
            return;
        }
        form.find('input[type="hidden"][name="action"]').val('delete_assistant');
    });
    form.find('input[type="hidden"][name="import_action"]').val('export');
    form.find('custom-notice').fadeOut();
    init_cancel_button();
    init_export_button();
}
function init_export_button() {
    var $ = jQuery;
    var form = $('form.assistant_edit');
    var export_btn = form.find('input[name="export_assistant_btn"]');
    $('input[name="import_assistant_btn"]').hide();
    export_btn.show();
    export_btn.off('click').on('click', function(e) {
        e.preventDefault();
        if (!confirm("Export this assistant?")) return;

        // Suppose the assistant_id is in a hidden input
        var assistantId = form.find('input[name="assistant_id"]').val() || '';

        // Build FormData
        var formData = new FormData();
        formData.append('action', 'export_assistant');
        formData.append('nonce', kissai_vars.nonce);
        formData.append('assistant_id', assistantId);

        $.ajax({
            url: kissai_vars.ajax_url,   // /wp-admin/admin-ajax.php
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                // Optionally show spinner
            },
            success: function(response) {
                // If the server includes a 'download_url' in success, do e.g.:
                if (response.success && response.data && response.data.download_url) {
                    // Force the browser to navigate to the ZIP download URL
                    window.location.href = response.data.download_url;
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Unknown error.';
                    alert("Export error: " + msg);
                }
            },
            error: function(xhr, status, error) {
                alert("Export AJAX error: " + error);
            }
        });
    });
}

function init_import_button() {
    var $ = jQuery;
    var form = $('form.assistant_edit');

    // 1) Hide the Export button, show Import
    $('input[name="export_assistant_btn"]').hide();
    var import_btn = form.find('input[name="import_assistant_btn"]');
    import_btn.show();

    // 2) On "Import" click => open file dialog
    import_btn.off('click').on('click', function(e) {
        e.preventDefault();
        form.find('input[name="import_zip"]').click();
    });

    // 3) When user picks a file => do AJAX
    form.find('input[name="import_zip"]').off('change').on('change', function() {
        var file = this.files[0];
        if (!file) return;

        if (!confirm("Are you sure you want to import this ZIP? If the same assistant already exists, it will be overwritten.")) {
            return;
        }

        // Create a FormData object for AJAX
        var formData = new FormData();
        // The WP AJAX action
        formData.append('action', 'import_assistant');
        // If you have a security nonce in your localized JS (kissai_vars.nonce)
        formData.append('nonce', kissai_vars.nonce);

        // The file from the user
        formData.append('import_zip', file, file.name);

        // Optionally include any other data you need
        // e.g. a chosen assistant name, or an ID, etc.
        // formData.append('assistant_id', 'some-id');

        form.find('input[name="import_zip"]').val('');
        // Make the AJAX request
        $.ajax({
            url: kissai_vars.ajax_url,        // Typically /wp-admin/admin-ajax.php
            type: 'POST',
            data: formData,
            contentType: false,  // Required for FormData
            processData: false,  // Required for FormData
            beforeSend: function() {
                // Optionally show spinner
            },
            success: function(response) {
                if (response.success) {
                    alert("Import successful: " + (response.data.message || 'Done!'));
                    // Possibly refresh the page or update UI
                    location.reload();
                } else {
                    // The server returned a JSON error
                    var msg = (response.data && response.data.message) ? response.data.message : 'Unknown error.';
                    alert("Import failed: " + msg);
                }
            },
            error: function(xhr, status, error) {
                alert("Import AJAX error: " + error);
            }
        });
    });
}


function init_cancel_button() {
    var $ = jQuery;
    var form = $('form.assistant_edit');
    form.find('input[name="cancel"]').on('click', function(e) {
        e.preventDefault(); // Prevent default button click behavior
        form.find('input[type="hidden"][name="action"]').val('add_assistant');
        form.find('input[type="submit"][name="submit"]').val('Add');
        form.find('tr.delete-button-row').remove();
        form.find('input[name="export_assistant_btn"]').hide();
        form.find('input[name="import_assistant_btn"]').show();
    });
}
jQuery(init_cancel_button);
jQuery(init_import_button);