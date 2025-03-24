<?php
/* phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended */
if (!defined('ABSPATH')) {
    exit;
}

require_once( ABSPATH . '/wp-includes/pluggable.php' );

// Define the function to display the settings page.
function display_kissai_assistants_page() {
    wp_enqueue_style('kissai-style');

    global $kissai_api;
    $default_assistant_name = OpenAI_API::get_default_assistant_name();
    ?>
    <style>
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

    </style>
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
                            $saved = null;
                            if (isset($_REQUEST['saved'])) {
                                $saved = sanitize_text_field($_REQUEST['saved']) === "true" ? true : false;
                            }
                            $notification_class = "custom-notice";
                            if ($saved !== null) {
                                if ($saved === true) {
                                    $notification_class .= ' is-dismissible';
                                } else {
                                    $notification_class .= ' warning is-dismissible';
                                }
                            }
                            if (isset($_REQUEST['message'])) {
                                $output = "<div class='{$notification_class}'><p>";
                                $message = sanitize_text_field($_REQUEST['message']);
                                $message = wp_unslash($message);
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
            <input type="hidden" name="action" value="add_assistant" />
            <input type="hidden" name="import_action" value="import" />
        </form>
        <?php
            $html = KissAi_Admin_Elements::render_assistant_list(
                'edit-assistant',
                'Edit',
                'get_assistant_details',
                "",
                "edit_assistant_handler(response);"
            );
        ?>
        <?php
            $assistants = $kissai_api->get_assistants();
            if (!empty($assistants) && count($assistants) > 0) {
                $congretulation_message_displayed = get_kissai_option('assistant_message_displayed');
                if ($congretulation_message_displayed === "") {
                    update_kissai_option('assistant_message_displayed', 'true');
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

// Define the function to save the settings.
function add_assistant() {
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

        global $kissai_api;
        $assistant = $kissai_api->add_assistant($assistant_name);
        if ($assistant && !empty($assistant['id'])) {
            $message = "Assistant '" . $assistant['name'] . "' is created";
            wp_redirect( admin_url( 'admin.php?page=kissai-assistants&saved=true&message=' . rawurlencode($message) ) );
            exit;
        }
        if (!empty($assistant['error'])) {
            $message = $assistant['error']['message'];
            wp_redirect( admin_url( 'admin.php?page=kissai-assistants&saved=false&message=' . rawurlencode($message) ) );
            exit;
        }
    }
    wp_redirect( admin_url( 'admin.php?page=kissai-assistants&saved=false' ) );
    exit;
}

// Add the action to handle saving the settings.
add_action( 'admin_post_add_assistant', 'add_assistant' );

// AJAX handler to fetch assistant details
function get_assistant_details() {
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
add_action('wp_ajax_get_assistant_details', 'get_assistant_details');

// Form submission handler for updating an assistant
function update_assistant() {
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
                wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=true&message=' . rawurlencode($message)));
                exit;
            } else {
                if (isset($assistant['error'])) {
                    $message = $assistant['error']['message'];
                }
                wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=false&message=' . rawurlencode($message)));
                exit;
            }
        } else {
            if (isset($assistant['error'])) {
                $message = $assistant['error']['message'];
            }
            wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=false&message=' . rawurlencode($message)));
            exit;
        }
    }
    wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=false'));
    exit;
}
add_action('admin_post_update_assistant', 'update_assistant');

// Form submission handler for updating an assistant
function delete_assistant() {
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
            wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=true&message=' . rawurlencode($message)));
            exit;
        }
        if (!empty($assistant['error'])) {
            $message = $assistant['error']['message'];
            wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=false&message=' . rawurlencode($message)));
            exit;
        } else if (isset($assistant['response'])) {
            $message = 'The server responded with an error: (Error code: ' . $assistant['response']['code'] . ') ';
            $message .= $assistant['response']['message'];
            wp_redirect(admin_url('admin.php?page=kissai-assistants&saved=false&message=' . rawurlencode($message)));
            exit;
        }
    }
    wp_redirect( admin_url( 'admin.php?page=kissai-assistants&saved=false' ) );
    exit;
}
add_action('admin_post_delete_assistant', 'delete_assistant');

function import_assistant() {
    check_ajax_referer('kissai_nonce','nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message'=>'No permission']);
    }
    if (empty($_FILES['import_zip']['tmp_name'])) {
        wp_send_json_error(['message'=>'No file uploaded']);
    }

    // Move uploaded file to a safe location in /wp-content/uploads
    $upload_dir  = wp_upload_dir();
    $dest_folder = trailingslashit($upload_dir['basedir']) . 'kissai_imports';
    wp_mkdir_p($dest_folder);

    $uniq_name   = uniqid('assist_') . '.zip';
    $dest_path   = trailingslashit($dest_folder) . $uniq_name;

    if (!@move_uploaded_file($_FILES['import_zip']['tmp_name'], $dest_path)) {
        wp_send_json_error(['message' => 'Failed to move uploaded ZIP file.']);
    }

    // Now call your DB's import method
    global $kissai_db, $kissai_api;
    $assistant_id = $kissai_db->import_assistant($dest_path);

    $kissai_api->add_assistant_to_user($assistant_id);
    // Check if the import returned a WP_Error
    if (is_wp_error($assistant_id)) {
        $msg = $assistant_id->get_error_message();
        wp_send_json_error(['message' => 'Import failed: ' . $msg]);
    }

    // If all good, send success
    wp_send_json_success([
        'message' => 'Successfully imported assistant with ID: ' . $assistant_id,
        'assistant_id' => $assistant_id,
    ]);
}

function export_assistant() {
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

add_action('wp_ajax_import_assistant','import_assistant');
add_action('wp_ajax_export_assistant','export_assistant');
