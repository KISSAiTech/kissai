<?php
/* phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended */

if (!defined('ABSPATH')) {
    exit;
}

use KissAi\KissAi_DB;
use KissAi\OpenAI_API;

require_once( ABSPATH . '/wp-includes/pluggable.php' );

function kissai_admin_assistants_assets($hook_suffix) {
    // Suppose you want inline CSS on the “Register” page
    $inline_css = '
input[name="openai_assistant_name"]::placeholder {
    opacity: 0.4;
}
.button-red {
    background-color: #b84300;
    color: white;
    border-color: #b84300;
    border: 1px;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
}
.assistant-id-col {
    width: 17em;
}
.action {
    width: 10em;
    text-align: center !important;
}
.form-table td:has(input[type="submit"]) {
    display:flex;
    align-items: baseline;
}
.form-table p:has(input[type="submit"]) {
    padding: 0px 5px;
}
';

    kissai_register_inline_style($hook_suffix . '-style', [], $inline_css);
}

// Define the function to display the settings page.
function kissai_display_assistants_page() {
    wp_enqueue_style('kissai-style');

    global $kissai_api;
    $default_assistant_name = OpenAI_API::get_default_assistant_name();

    $notice_nonce_action = 'kissai_assistant_notice';

    $saved   = null;   // tri‑state: null = no notice, true / false = status
    $message = '';

    if (
        isset( $_REQUEST['_wpnonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash($_REQUEST['_wpnonce'] ) ), $notice_nonce_action )
    ) {
        if ( isset( $_REQUEST['saved'] ) ) {
            $saved = ( 'true' === sanitize_text_field( wp_unslash( $_REQUEST['saved'] ) ) );
        }
        if ( isset( $_REQUEST['message'] ) ) {
            $message = sanitize_text_field( wp_unslash( $_REQUEST['message'] ) );
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <h2>Assistants</h2>
        <form method="post" class="assistant_edit" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'manage_assistant' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="openai_assistant_name">Assistant Name (Optional):</label>
                    </th>
                    <td>
                        <input name="openai_assistant_name" type="text" placeholder="<?php echo esc_attr($default_assistant_name); ?>" style="width:100%;"/>
                        <p class="description">Enter the OpenAI Assistant Name. If there is an assistant associated with this API Key, the name will appear here and you can modify it.<br>
                        If this field is left empty, this website name will be used.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="model_id">Model:</label>
                    </td>
                    <td>
                        <?php
                            $model_list = KissAi_Admin_Elements::render_openai_model_list();
                            echo wp_kses($model_list, [
                                'select' => [
                                    'name'  => true,
                                    'id'    => true,
                                    'style' => true,
                                ],
                                'option' => [
                                    'value' => true,
                                    'selected' => true,
                                ],
                            ]);
                        ?>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <?php submit_button( 'Add' ); ?>
                        <?php submit_button( 'Cancel', '', 'cancel'); ?>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <div style="display:block;">
                        <?php submit_button( 'Export', '', 'export_assistant_btn', true, ['style' => "display:none;"] ) ?>
                        <?php submit_button( 'Import', '', 'import_assistant_btn' ); ?>
                        </div>
                        <input type="file" name="import_zip" accept=".zip" style="display:none;"/>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <?php 
                            $notification_class = "custom-notice";
                            if ($saved !== null) {
                                if ($saved === true) {
                                    $notification_class .= ' is-dismissible';
                                } else {
                                    $notification_class .= ' warning is-dismissible';
                                }
                            }
                            if (!empty($message)) {
                                $output = "<div class='{$notification_class}'><p>";
                                $message = esc_html($message);
                                $output .= $message;
                                $output .= '</p><span class="dismiss-button">×</span></div>';
                                $allowed_html = array(
                                    'div' => array(
                                        'class' => true,
                                    ),
                                    'p' => array(), // no special attributes needed
                                    'span' => array(
                                        'class' => true,
                                    ),
                                );
                                echo wp_kses($output, $allowed_html);
                            }
                        ?>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="kissai_add_assistant" />
            <input type="hidden" name="import_action" value="import" />
        </form>
        <?php
            $html = KissAi_Admin_Elements::render_assistant_list(
                'edit-assistant',
                'Edit',
                'kissai_get_assistant_details',
                "",
                "edit_assistant_handler(response);",
                null,
                true
            );
        ?>
        <?php
            $assistants = $kissai_api->get_assistants();
            if (!empty($assistants) && count($assistants) > 0) {
                $congretulation_message_displayed = kissai_get_option('assistant_message_displayed');
                if ($congretulation_message_displayed === "") {
                    kissai_update_option('assistant_message_displayed', 'true');
                    ?>
                    <div style="padding:20px;">
                        <h3 style="color: #007bff; margin-top: 0;">Your Assistant is Ready!</h3>
                        <p style="color: #333; font-size: 16px;">
                            Congratulations! You have successfully created your KissAi Assistant.
                        </p>
                        <p style="color: #333; font-size: 16px;">
                            Now, to make it even smarter, head over to <a href="<?php echo esc_url(admin_url( 'admin.php?page=kissai-plugin-training')); ?>"><strong>KissAi Admin > Training</strong></a> where you can:
                        </p>
                        <ul style="color: #333; font-size: 16px; padding-left: 20px;">
                            <li><b>Train your assistant</b> with custom training materials.</li>
                            <li><b>Adjust instructions</b> (we've pre-filled them for you, but feel free to tweak!).</li>
                            <li><b>Manage suggested questions</b> to guide user interactions.</li>
                        </ul>
                        <p style="color: #333; font-size: 16px;">
                            Ready to enhance your AI? Let's get started!
                        </p>
                    </div>

                    <?php
                }
                else {
                    ?>
                    <div style="padding:20px;">
                        <p style="color: #333; font-size: 16px;">
                            You can train your assistants at <a href="<?php echo esc_url(admin_url( 'admin.php?page=kissai-plugin-training')); ?>"><strong>KissAi Admin > Training</strong></a> where you can:
                        </p>
                        <ul style="color: #333; font-size: 16px; padding-left: 20px;">
                            <li><b>Train your assistant</b> with custom training materials.</li>
                            <li><b>Adjust instructions</b> (we've pre-filled them for you, but feel free to tweak!).</li>
                            <li><b>Manage suggested questions</b> to guide user interactions.</li>
                        </ul>
                    </div>
                    <?php
                }
            }

        ?>
    </div>
    <?php
}

/**
 * Redirect back to the Assistants page with a status flag
 * and (optionally) a notice message.
 *
 * @param bool        $saved   Whether the operation succeeded.
 * @param string|null $message Optional notice text.  When null / '', the
 *                             message parameter is omitted from the URL.
 */
function kissai_redirect_to_assistants_page( $saved, $message = null ) {

    // Base query‑string arguments (always present)
    $args = array(
        'page'     => 'kissai-assistants',
        'saved'    => $saved ? 'true' : 'false',
        '_wpnonce' => wp_create_nonce( 'kissai_assistant_notice' ),
    );

    // Add the message only if it contains something
    if ( ! is_null( $message ) && $message !== '' ) {
        $args['message'] = rawurlencode( $message );
    }

    $redirect_url = add_query_arg( $args, admin_url( 'admin.php' ) );
    wp_safe_redirect( $redirect_url );
    exit; // Always exit after redirect
}

// Define the function to save the settings.
function kissai_add_assistant() {
    // Check security nonce
    check_admin_referer('manage_assistant');

    // Ensure the user has necessary permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    if (isset($_POST['submit']) && $_POST['submit'] === 'Add') {
        $assistant_name = '';
        if (isset($_POST['openai_assistant_name'])) {
            $assistant_name = sanitize_text_field($_POST['openai_assistant_name']);
            if (empty($assistant_name)) {
                $assistant_name = OpenAI_API::get_default_assistant_name();
            }
        }

        $model_id = '';
        if (isset($_POST['model_id']) && !empty($_POST['model_id'])) {
            $model_id = sanitize_text_field($_POST['model_id']);
        }

        global $kissai_api;
        $assistant = $kissai_api->add_assistant($assistant_name, $model_id);
        if ($assistant && !empty($assistant['id'])) {
            $message = "Assistant '" . $assistant['name'] . "' is created";
            kissai_redirect_to_assistants_page(true, $message);
        }
        if (!empty($assistant['error'])) {
            $message = $assistant['error']['message'];
            kissai_redirect_to_assistants_page(false, $message);
        }
    }
    kissai_redirect_to_assistants_page(false);
}

// Add the action to handle saving the settings.
add_action( 'admin_post_kissai_add_assistant', 'kissai_add_assistant' );

// AJAX handler to fetch assistant details
function kissai_get_assistant_details() {
    check_ajax_referer('kissai_nonce', 'nonce');
    $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field($_POST['assistant_id']) : '';

    global $kissai_api;

    $chatgpt_api = $kissai_api->get_ai_api_instance();
    $assistant = $chatgpt_api->set_assistant($assistant_id);

    if ($assistant) {
        wp_send_json_success([
            'name' => $assistant['name'],
            'assistant_id' => $assistant['id'],
            'model' => $assistant['model']
        ]);
    } else {
        wp_send_json_error(['message' => 'Assistant not found']);
    }
}
add_action('wp_ajax_kissai_get_assistant_details', 'kissai_get_assistant_details');

// Form submission handler for updating an assistant
function kissai_update_assistant() {
    check_admin_referer('manage_assistant');

    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    if (isset($_POST['submit']) && $_POST['submit'] === 'Update') {
        $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field($_POST['assistant_id']) : '';
        $assistant_name = isset($_POST['openai_assistant_name']) ? sanitize_text_field($_POST['openai_assistant_name']) : '';
        $model_id = isset($_POST['model_id']) ? sanitize_text_field($_POST['model_id']) : '';

        global $kissai_api;

        $chatgpt_api = $kissai_api->get_ai_api_instance();

        $assistant = $chatgpt_api->set_assistant($assistant_id);

        $assistant = $chatgpt_api->update_assistant_function($assistant, OpenAI_API::get_current_time_function_def());

        $assistant = $chatgpt_api->update_assistant_name($assistant_name);
        $message = '';
        if (isset($assistant['id'])) {
            $assistant = $chatgpt_api->update_assistant_model($model_id);
            if (isset($assistant['id'])) {
                $message = "Assistant '" . $assistant['name'] . "' is updated";
                kissai_redirect_to_assistants_page(true, $message);
            } else {
                if (isset($assistant['error'])) {
                    $message = $assistant['error']['message'];
                }
                kissai_redirect_to_assistants_page(false, $message);
            }
        } else {
            if (isset($assistant['error'])) {
                $message = $assistant['error']['message'];
            }
            kissai_redirect_to_assistants_page(false, $message);
        }
    }
    kissai_redirect_to_assistants_page(false);
}
add_action('admin_post_kissai_update_assistant', 'kissai_update_assistant');

// Form submission handler for updating an assistant
function kissai_delete_assistant() {
    check_admin_referer('manage_assistant');

    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    if (isset($_POST['delete']) && $_POST['delete'] === 'Delete Assistant') {
        $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field($_POST['assistant_id']) : '';
        global $kissai_api;
        $assistant = $kissai_api->delete_assistant($assistant_id);
        if ($assistant && !empty($assistant['success']) && $assistant['success'] == true) {
            $message = "Assistant '" . $assistant['name'] . "' (id: " . $assistant['assistant_id'] . ") is deleted";
            kissai_redirect_to_assistants_page(true, $message);
        }
        if (!empty($assistant['error'])) {
            $message = $assistant['error']['message'];
            kissai_redirect_to_assistants_page(false, $message);
        } else if (isset($assistant['response'])) {
            $message = 'The server responded with an error: (Error code: ' . $assistant['response']['code'] . ') ';
            $message .= $assistant['response']['message'];
            kissai_redirect_to_assistants_page(false, $message);
        }
    }
    kissai_redirect_to_assistants_page(false);
}
add_action('admin_post_kissai_delete_assistant', 'kissai_delete_assistant');

function kissai_import_assistant() {
    check_ajax_referer('kissai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No permission']);
    }
    if (empty($_FILES['import_zip']['tmp_name'])) {
        wp_send_json_error(['message' => 'No file uploaded']);
    }

    // Use WordPress's built-in file handling
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $upload_overrides = array(
        'test_form' => false, // Skip form validation
        'mimes'     => array('zip' => 'application/zip') // Allow only ZIP files
    );
    $result = wp_handle_upload($_FILES['import_zip'], $upload_overrides);

    if (isset($result['error'])) {
        wp_send_json_error(['message' => 'Failed to upload ZIP file: ' . $result['error']]);
    }

    // $result['file'] contains the full path of the uploaded file
    $dest_path = $result['file'];

    // Now call your DB's import method
    global $kissai_db, $kissai_api;
    $assistant_id = $kissai_db->import_assistant($dest_path);

    $kissai_api->add_assistant_to_user($assistant_id);

    if (is_wp_error($assistant_id)) {
        $msg = $assistant_id->get_error_message();
        wp_send_json_error(['message' => 'Import failed: ' . $msg]);
    }

    wp_send_json_success([
        'message'      => 'Successfully imported assistant with ID: ' . $assistant_id,
        'assistant_id' => $assistant_id,
    ]);
}

function kissai_export_assistant() {
    check_ajax_referer('kissai_nonce','nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message'=>'No permission']);
    }

    $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field($_POST['assistant_id']) : '';
    if(!$assistant_id){
        wp_send_json_error(['message'=>'No assistant ID.']);
    }

    global $kissai_db;
    $upload_dir = wp_upload_dir();
    $zip_file = $kissai_db->export_assistant($assistant_id);
    $download_url = KissAi_DB::convert_export_assistant_path_to_url($zip_file);

    wp_send_json_success([
        'message' => 'Export complete.',
        'download_url' => $download_url
    ]);
}

add_action('wp_ajax_kissai_import_assistant','kissai_import_assistant');
add_action('wp_ajax_kissai_export_assistant','kissai_export_assistant');
