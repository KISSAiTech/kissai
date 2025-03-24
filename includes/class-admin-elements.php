<?php

class KissAi_Admin_Elements {
    public static function init() {
        $plugin_version = get_kissai_plugin_version();
        wp_enqueue_style(
            'kissai-admin-elements',
            KISSAI_PLUGIN_URL . 'assets/css/kissai-admin-elements.css',
            array(),
            $plugin_version
        );
        wp_enqueue_script(
            'kissai-admin-elements',
            KISSAI_PLUGIN_URL . 'assets/js/kissai-admin-elements.js',
            ['jquery'],
            $plugin_version,
            true
        );
    }

    public static function get_allowed_html() {
        $allowed_html = array(
            'table' => array(
                'class'       => true,
                'cellspacing' => true,
            ),
            'thead' => array(),
            'tbody' => array(),
            'tr' => array(),
            'th' => array(
                'style' => true,
                'class' => true,
            ),
            'td' => array(
                'class' => true,
            ),
            'a' => array(
                'href'             => true,
                'data-assistant-id'=> true,
                'data-action'      => true,
                'class'            => true,
            ),
            'span' => array(),
            'input' => array(
                'type'  => true,
                'name'  => true,
                'value' => true,
            ),
        );
        return $allowed_html;
    }
    public static function render_assistant_list($button_class, $button_text, $ajax_function = null, $js_ajax_before_send = null, $js_ajax_success_code = null, $js_after_button_click = null, $echo = true) {
        global $kissai_api;
        $assistants = $kissai_api->get_assistants(true);
        $chatgpt_api = $kissai_api->get_ai_api_instance();
        $count = 0;
        ob_start();
        ?>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th style="width:5em;">ID</th>
                    <th>Assistant Name</th>
                    <th class="assistant-id-col">Assistant ID</th>
                    <th class="action">Action</th>
                </tr>
            </thead>
    
            <tbody>
                <?php if ($assistants !== null && !empty($assistants)) :?>
                <?php foreach ($assistants as $assistant): ?>
                    <?php
                    $openai_assistant = $chatgpt_api->get_assistant($assistant['assistant_id']);
                    if ($openai_assistant && isset($openai_assistant['name'])) {
                        $assistant_name = $openai_assistant['name'];
                    }
                    else {
                        $assistant_name = "<span>Not accessible</span>";
                    }
                    ?>
                <tr>
                    <td><?php echo esc_attr($assistant['id']); ?></td>
                    <td><?php echo wp_kses($assistant_name, ['span' => true]); ?></td>
                    <td><?php echo esc_attr($assistant['assistant_id']); ?></td>
                    <td class="action">
                        <?php
                        if ($openai_assistant && isset($openai_assistant['name'])) :
// DevCode Begins
                        ?>
                        <?php

                            if ($kissai_api->check_permission($button_class, $count)) {
// DevCode Ends
                        ?>
                        <a href="#" data-assistant-id="<?php echo esc_attr($assistant['assistant_id']); ?>" data-action="<?php echo esc_attr($ajax_function); ?>" class="button assistant-action-button <?php echo esc_html($button_class); ?>"><?php echo esc_html($button_text); ?></a>
                        <?php
// DevCode Begins
                            }
                            else {
                        ?>
                            <a href="<?php echo esc_url(KissAi_API_Endpoints::SERVER_DOMAIN . '/my-account/#plan'); ?>" class="button">Upgrade</a>
                        <?php
                            }
// DevCode Ends
                        $count++;
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                    $assistant_name = "";
                ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" name="selected_assistant_id" />
        <input type="hidden" name="kissai_admin_element_before_ajax" value="<?php echo esc_attr($js_ajax_before_send); ?>"/>
        <input type="hidden" name="kissai_admin_element_ajax_success" value="<?php echo esc_attr($js_ajax_success_code); ?>"/>
        <input type="hidden" name="kissai_admin_element_after_button_click" value="<?php echo esc_attr($js_after_button_click); ?>"/>
        <?php
        $html = ob_get_clean();
        if ($echo) {
            echo wp_kses($html, self::get_allowed_html());
        }
        return $html;
    }
    public static function render_openai_model_list($selected_model_id = null) {
        global $kissai_api;
        
        // Get the OpenAI API instance
        $chatgpt_api = $kissai_api->get_ai_api_instance();
        
        // Fetch the available models from OpenAI
        $models = $chatgpt_api->get_models();

        // Start building the dropdown list HTML
        $output = '<select name="model_id" id="model_id">';

        if (!empty($models)) {
            $models = OpenAI_API::sort_models_by_key($models, 'created', 'desc');

            $models = OpenAI_API::filter_models_by_conditions($models, ['gpt'], ['system', 'openai']);

            $models = OpenAI_API::filter_models_by_conditions($models, ['preview'], ['openai-internal'], false, false);

            if (isset($models['data'])) {
                $filtered_models = array_filter($models['data'], function($model) use($chatgpt_api) {
                    if (OpenAI_API::check_model_id($model, ['o1', 'o3'])) {
                        if ($chatgpt_api->test_model_compatibility_with_assistant($model['id'])) {
                            return true;
                        }
                        return false;
                    }
                    else
                        return true;
                });
                $models = OpenAI_API::models_data_into_object($filtered_models);
            }
        
            // Check if models were retrieved successfully
            if (isset($models['data']) && is_array($models['data'])) {
                // Loop through each model and create an option in the dropdown
                foreach ($models['data'] as $model) {
                    $model_id = htmlspecialchars($model['id']); // Model ID (used as value)
                    if (!empty($selected_model_id) && $selected_model_id === $model_id) {
                        $output .= '<option value="' . $model_id . '" selected>' . $model_id . '</option>';
                    }
                    else {
                        $output .= '<option value="' . $model_id . '">' . $model_id . '</option>';
                    }
                }
            } else {
                // If no models are available, show a placeholder option
                $output .= '<option value="">No models available</option>';
            }
        }
    
        // Close the select element
        $output .= '</select>';
        
        return $output;
    }
    public static function request_user_token() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (empty($email)) {
            wp_send_json_error(['message' => 'Invalid email provided.']);
            wp_die();
        }

        global $kissai_api;
        $token = $kissai_api->request_kissai_user_token($email);

        if (is_wp_error($token)) {
            wp_send_json_error(['message' => $token->get_error_message()]);
        } else {
            if ($token && isset($token['token'])) {
                wp_send_json_success([
                    'token' => $token['token'],
                    'endpoint' => KissAi_API_Endpoints::SIGN_IN
                ]);
            } elseif (isset($token['response'])) {
                $response_code = wp_remote_retrieve_response_code($token);
                $response_body = json_decode(wp_remote_retrieve_body($token), true);

                if ($response_code !== 200) {
                    if (!empty($response_body) && isset($response_body['message'])) {
                        wp_send_json_success([
                            'token' => '',
                            'endpoint' => KissAi_API_Endpoints::SIGN_IN,
                            'message' => $response_body['message']
                        ]);
                    } else {
                        wp_send_json_error(['message' => 'Failed to retrieve token.']);
                    }
                } else {
                    wp_send_json_error(['message' => 'Failed to retrieve token.']);
                }
            } else {
                wp_send_json_error(['message' => 'Failed to retrieve token.']);
            }
        }

        wp_die();
    }
}

// Register the AJAX action
add_action('wp_ajax_request_user_token', [KissAi_Admin_Elements::class, 'request_user_token']);
add_action('admin_enqueue_scripts', 'kissai_enqueue_scripts');

add_action('admin_enqueue_scripts', [KissAi_Admin_Elements::class, 'init']);