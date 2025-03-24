function replace_element(selector, newHtml) {
    // Use jQuery to find the element with the given selector
    var element = jQuery(selector);

    // If the element exists, replace it with the new HTML
    if (element.length) {
        element.replaceWith(newHtml);
    } else {
        console.warn('Element not found for selector:', selector);
    }
}

function kissai_load_threads(target_selector, target_type, offset) {
    var $ = jQuery;
    var kissai_widget_atts = get_kissai_widget_atts('kissai-thread-container');
    var search = $('#threads-container .thread-search-container input[name="thread-search-input"]').val();
    var sort_order = $('#threads-container .thread-list .sort-icon').data('sort');
    $.ajax({
        url: kissai_vars.ajax_url,
        type: 'POST',
        data: {
            'action': 'load_kissai_threads',
            'nonce': kissai_vars.nonce,
            'offset': offset,
            'target': target_selector,
            'target_type': target_type,
            'sort_order': sort_order,
            'search': search,
            'kissai_widget_atts': kissai_widget_atts,
        },
        beforeSend: function() {
            if (target_type === 'container') {
                $(target_selector).html('<tr><td>Loading...</td></tr>');
            }
            else {
                $(target_selector).find('a').html('Loading...');
            }
        },
        success: function(response) {
            if (response.hasOwnProperty('data')) {
                var responseData = response.data;
                if (responseData.target_type == 'container') {
                    $(responseData.target).html(responseData.body);    
                }
                else {
                    $(responseData.target).replaceWith(responseData.body);
                }
                process_script_from_response(responseData);
                init_load_thread_list_more();
                if (responseData.hasOwnProperty('message') && responseData.message) {
                    $('#threads-container .message').html(responseData.message);
                }
            }
        }
    });
}

function init_load_thread_list_more() {
    var $ = jQuery;
    $('.thread-load-more').on('click', function(e) {
        e.preventDefault();
        var offset = $(this).data('offset');
        kissai_load_threads('#threads-container .thread-list .thread-load-more', 'replace', offset);
    });
}

function init_search_thread() {
    var $ = jQuery;
    $('#threads-container .thread-search-container button').on('click', function() {
        kissai_load_threads('#threads-container .thread-list tbody', 'container', 0);
    });
    $('#threads-container .thread-search-container input[name="thread-search-input"]').on('keypress', function(e) {
        if (e.which === 13) { // 13 is the key code for Enter key
            e.preventDefault(); // Prevent default form submission if inside a form
            kissai_load_threads('#threads-container .thread-list tbody', 'container', 0);
        }
    });
}

function update_selected_sort(selectedSort) {
    $('.dropdown-menu ul').attr('data-sort', selectedSort);
}

function init_sort_icon() {
    var $ = jQuery;
    $('.sort-icon').on('click', function(e) {
        e.preventDefault(); // Prevent the default action
        var dropdown = $('#sortDropdown');
        dropdown.toggle(); // Toggle the dropdown visibility
    });

    // Handle dropdown menu item selection
    $('.dropdown-menu ul li a').on('click', function(e) {
        e.preventDefault(); // Prevent the default action
        var selectedSort = $(this).data('sort'); // Get the selected sort order

        $('#threads-container .thread-list .sort-icon').attr('data-sort', selectedSort);
        kissai_load_threads('#threads-container .thread-list tbody', 'container', 0);
        // Perform your sorting logic here based on selectedSort
        console.log('Selected Sort Order:', selectedSort);

        // Hide the dropdown menu after selection
        $('.dropdown-menu').hide();
    });

    // Hide the dropdown if clicked outside
    $(document).on('click', function(event) {
        if (!$(event.target).closest('.sort-icon, .dropdown-menu').length) {
            $('.dropdown-menu').hide();
        }
    });
}

function init_thread_ui_events() {
    init_load_thread_list_more();
    init_search_thread();
    init_sort_icon();
    init_search_highlight('#threads-container .thread-search-container input[name="thread-search-input"]', '#messages-container');
}

jQuery(document).ready(function($) {
    init_thread_ui_events();
    // Load threads initially
    function loadMessages(threadId) {
        var kissai_widget_atts = get_kissai_widget_atts('kissai-thread-container');
        $.ajax({
            url: kissai_vars.ajax_url,
            type: 'POST',
            data: {
                'action': 'load_kissai_messages',
                'nonce': kissai_vars.nonce,
                'kissai_widget_atts': kissai_widget_atts,
                'thread_id': threadId
            },
            beforeSend: function() {
                $('#messages-container .messages').html('');
                $('#messages-container .loading-animation').show();
            },
            success: function(response) {
                $('#messages-container .loading-animation').hide();
                let search_phrase = $('#kissai-thread-container .thread-search-container input[name="thread-search-input"]').val();
                if (response.hasOwnProperty('data')) {
                    var responseData = response.data;
                    $('#messages-container .messages').html(responseData.body);
                    process_script_from_response(responseData);
                    if (search_phrase && search_phrase.trim() !== '') {
                        highlightSearchText('#messages-container', search_phrase);
                    }
                }
            }
        });
    }

    $('#threads-container').on('click', '.thread-link', function(e) {
        e.preventDefault();
        var threadId = $(this).data('thread-id');

        // Highlight the selected thread
        $('.thread-link').closest('tr').removeClass('selected-thread'); // Remove from all rows
        $(this).closest('tr').addClass('selected-thread'); // Add to the selected row

        loadMessages(threadId);
    });

});

function update_thread_list(threads, atts) {
    var $ = jQuery;
    $('#threads-container').html(threads);
    if (typeof get_kissai_widget_atts_selector === 'function') {
        $(get_kissai_widget_atts_selector('kissai-thread-container')).val(atts);
    }
    init_thread_ui_events();
}