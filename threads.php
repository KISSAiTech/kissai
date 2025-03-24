<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once( ABSPATH . '/wp-includes/pluggable.php' );

function display_kissai_threads_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $atts = KissAi_Threads_Widget::get_default_instance();
    $title = get_admin_page_title();
    $content = '<div class="wrap"><h1>' . esc_html($title) . '</h1>';

    $content .= '<h2>Assistants</h2>';

    $content .= KissAi_Admin_Elements::render_assistant_list(
        'view-threads',
        'View Threads',
        'view_assistant_threads',
        "var $ = jQuery; $('#threads-container').html('Loading...');$('#messages-container .messages').html('');",
        'update_thread_list(response.data.threads, response.data.kissai_widget_atts);', '', false);
    $content .= KissAi_Threads_Widget::render_kissai_threads($atts);
    $allowed_html = KissAi_Admin_Elements::get_allowed_html();
    echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    // Enqueue a script for AJAX (ensure jQuery is loaded as well)
    wp_enqueue_style('kissai-style');
    wp_enqueue_style('kissai-threads-style');
}

// AJAX handler to fetch assistant details
function view_assistant_threads() {
    check_ajax_referer('kissai_nonce', 'nonce');
    $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field( wp_unslash( $_POST['assistant_id'] ) ) : '';

    global $kissai_api;

    $chatgpt_api = $kissai_api->get_ai_api_instance();
    $assistant = $chatgpt_api->set_assistant($assistant_id);

    $atts = KissAi_Threads_Widget::get_kissai_shortcode_atts(null);
    $atts = KissAi_Threads_Widget::widget_atts_from_ajax($atts);
    $atts['assistant_id'] = $assistant_id;
    $threads = KissAi_Threads_Widget::render_kissai_thread_list($atts);
    $encoded_atts = KissAi_Threads_Widget::encode_attributes($atts);

    if ($assistant) {
        wp_send_json_success(['threads' => $threads,
            'kissai_widget_atts' => $encoded_atts]);
    } else {
        wp_send_json_error(['message' => 'Assistant not found']);
    }
}
add_action('wp_ajax_view_assistant_threads', 'view_assistant_threads');