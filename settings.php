<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once( ABSPATH . '/wp-includes/pluggable.php' );

// Define the function to display the settings page.
function display_kissai_settings_page() {
    wp_enqueue_style('kissai-style');
    global $kissai_api;
    $user = $kissai_api->get_current_kissai_user();
    $credit_balance = 0;
    if ($user && $user->credit) {
        $credit_balance = $user->credit->credit_balance;
    }

    // Fetch the current API key type or default to 'kissai'
    $api_key_type = get_kissai_option('api_key_type', 'kissai');  // Default to 'kissai' if not set
    $kissai_api_key = get_kissai_option('api_key');
    $openai_api_key = get_kissai_option('openai_api_key');
    $debug_mode = get_kissai_option('debug_mode');

    $validity = $kissai_api->get_key_validity($kissai_api_key);

    // If the current selection is 'openai' but the key is empty, switch to 'kissai'
    if ($api_key_type === 'openai' && empty($openai_api_key)) {
        if ($credit_balance > 0) {
            $api_key_type = 'kissai'; // Default to 'kissai' if OpenAI key is not set
        }
    }
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
        .form-table div.api-key-wrapper {
            display: flex;
            align-items: center;
        }
        .kissai-toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }
        .kissai-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .kissai-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }
        .kissai-slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 2px;
            bottom: 2px;
            background-color: #fff;
            transition: .4s;
        }
        input:checked + .kissai-slider {
            background-color: #2271b1;  /* Toggle color when checked */
        }
        input:focus + .kissai-slider {
            box-shadow: 0 0 1px #2271b1;
        }
        input:checked + .kissai-slider:before {
            transform: translateX(18px);
            border-color: white;
        }
        /* Round slider */
        .kissai-slider.round {
            border-radius: 24px;
        }
        .kissai-slider.round:before {
            border-radius: 50%;
            border-color: #fff;
            border-width: 2px;
            border-style: solid;
            backdrop-filter: none;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle changes on the radio buttons
            $('input[type=radio][name=api_key_type]').change(function() {
                // Disable all inputs initially
                $('input[name=kissai_api_key], input[name=openai_api_key]').prop('disabled', true);
                
                // Enable the appropriate input based on selected option
                if (this.value === 'kissai') {
                    $('input[name=kissai_api_key]').prop('disabled', false);
                } else if (this.value === 'openai') {
                    $('input[name=openai_api_key]').prop('disabled', false);
                }
            });
        });
    </script>

    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'save_kissai_plugin_settings' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="kissai_api_key">KissAi API Key:</label>
                    </th>
                    <td>
                        <div class="api-key-wrapper">
                            <input type="radio" name="api_key_type" value="kissai" <?php checked('kissai', $api_key_type); ?>>
                            <input name="kissai_api_key" type="text" value="<?php echo esc_attr($kissai_api_key); ?>" style="width:100%;" <?php if ($api_key_type !== 'kissai') echo 'disabled'; ?>/>
                        </div>
                        <p class="description">Enter the KissAi API Key. To get your API key please go to <a href="/wp-admin/admin.php?page=kissai-plugin-register">KissAi Plugin Register</a></p>
                        <?php
                        if (!$validity) { ?>
                            <div style="height: 20px;"></div>
                            <p class="notice notice-error"><b>Important:</b> <b>We're sorry, but your key appears to be invalid.</b><br>
                                Please double-check your key or reach out to our support team for assistance.<br>
                                <a href="<?php echo esc_url(KissAi_API_Endpoints::SUPPORT); ?>" target="_blank">Contact Us</a>.</p>
                        <?php } else { ?>
                        <?php if ($credit_balance <= 0): // Check if there is no credit available ?>
                            <div style="height: 20px;"></div>
                            <p class="notice notice-error"><b>Important:</b> You currently have no credit balance. To continue using the KissAi API, please <a href="<?php echo esc_url(KissAi_API_Endpoints::SERVER_DOMAIN); ?>/my-account/#credit" target="_blank">purchase credits</a>. Alternatively, you can use your own OpenAI API key by selecting the option below.</p>
                        <?php endif; ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="openai_api_key">OpenAI API Key:</label>
                    </th>
                    <td>
                        <div class="api-key-wrapper">
                            <input type="radio" name="api_key_type" value="openai" <?php checked('openai', $api_key_type); ?> />
                            <input name="openai_api_key" type="text" value="<?php echo esc_attr($openai_api_key); ?>" style="width:100%;" <?php if ($api_key_type !== 'openai') echo 'disabled'; ?>/>
                        </div>
                        <p class="description">Enter the OpenAI API Key. To get your API key please go to <a href="https://platform.openai.com/api-keys">OpenAI Platform</a></p>
                        <p class="description"><b>Important Note:</b> If you choose to use your own OpenAI API key, please be aware that you will not have access to previously created Assistants, trainings, and chat histories associated with a different API key. This ensures your data privacy and security across different accounts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="debug_mode">Debug Mode:</label>
                    </th>
                    <td>
                        <div>
                            <label class="kissai-toggle-switch">
                                <input type="checkbox"
                                       name="debug_mode"
                                       value="true"
                                    <?php checked('true', $debug_mode); ?>
                                />
                                <span class="kissai-slider round"></span>
                            </label>
                            <p class="description">If you turn on Debug Mode, KissAi will write additional debugging information
                                to your website log file. This can help pinpoint issues if you need support,
                                but if you’re not experiencing any problems, you do not need to enable it</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <?php submit_button( 'Save Settings' ); ?>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_kissai_plugin_settings" />
        </form>
        <form method="post">
            <p>
            <!-- Reset Database Button -->
            <input type="submit" name="reset_database" class="button-red" value="Reset Database" onclick="return confirm('Are you sure you want to reset the plugin database? This will delete all data and cannot be undone.');"/>
            </p>
        </form>
    </div>
    <?php
}

// Define the function to save the settings.
function save_kissai_plugin_settings() {
    // Check security nonce
    check_admin_referer('save_kissai_plugin_settings');

    if (isset($_POST['reset_database'])) {
        global $kissai_db;
        // Call the reset function
        $kissai_db->reset_plugin_database_tables();
        wp_redirect(admin_url('admin.php?page=kissai-plugin-settings&reset=true'));
        exit;
    }
    
    // Ensure the user has necessary permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    /// Save the selected API key type or default to 'kissai'
    $api_key_type = isset($_POST['api_key_type']) ? sanitize_text_field( wp_unslash( $_POST['api_key_type'] ) ) : 'kissai';
    update_kissai_option('api_key_type', $api_key_type);

    // Update the KissAi and OpenAI API keys
    if (isset($_POST['kissai_api_key'])) {
        update_kissai_option('api_key', sanitize_text_field( wp_unslash( $_POST['kissai_api_key'] ) ));
    }
    if (isset($_POST['openai_api_key'])) {
        update_kissai_option('openai_api_key', sanitize_text_field( wp_unslash( $_POST['openai_api_key'] ) ));
    }

    $debug_mode = isset($_POST['debug_mode']) ? 'true' : 'false';
    update_kissai_option('debug_mode', $debug_mode);

    // Redirect back to the settings page with a success message.
    wp_redirect( admin_url( 'admin.php?page=kissai-plugin-settings&saved=true' ) );
    exit;
}

// Add the action to handle saving the settings.
add_action( 'admin_post_save_kissai_plugin_settings', 'save_kissai_plugin_settings' );
