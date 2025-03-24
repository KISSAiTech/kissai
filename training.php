<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once( ABSPATH . '/wp-includes/pluggable.php' );

// Define the function to display the training page.
function display_kissai_training_page() {
    wp_enqueue_style('kissai-chat-widget-style');
    wp_enqueue_style('kissai-style');
    ?>
    <style>
        .assistant-id-col {
            width: 17em;
        }
        .action {
            width: 10em;
            text-align: center !important;
        }
    </style>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <h2>Assistants</h2>
        <?php
        $html = KissAi_Admin_Elements::render_assistant_list(
            'train-assistant',
            'Train');
        ?>
        <div id="admin-kissai-widget-container">
            <div class="spinner-animation"><?php echo KissAi_Base_Widget::spinner_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </div>
        <div id="admin-kissai-widget-container-response">
        </div>
    </div>
    <?php
}