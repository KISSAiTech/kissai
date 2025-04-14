<?php
if (!defined('ABSPATH')) {
    exit;
}

use KissAi\KissAi_Base_Widget;

require_once( ABSPATH . '/wp-includes/pluggable.php' );

function kissai_admin_training_assets($hook_suffix) {
    $inline_css = "
.assistant-id-col {
    width: 17em;
}
.action {
    width: 10em;
    text-align: center !important;
}
";

    kissai_register_inline_style($hook_suffix . '-style', [], $inline_css);
}

// Define the function to display the training page.
function kissai_display_training_page() {
    wp_enqueue_style('kissai-chat-widget-style');
    wp_enqueue_style('kissai-style');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <h2>Assistants</h2>
        <?php
        $html = KissAi_Admin_Elements::render_assistant_list(
            'train-assistant',
            'Train');
        ?>
        <div id="admin-kissai-widget-container">
            <div class="spinner-animation"><?php
                $allowed_html = KissAi_Base_Widget::get_allowed_svg_html();
                echo wp_kses(KissAi_Base_Widget::spinner_icon, $allowed_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?></div>
        </div>
        <div id="admin-kissai-widget-container-response">
        </div>
    </div>
    <?php
}