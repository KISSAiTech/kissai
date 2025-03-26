<?php

class KissAi_API extends API_Base {
    private $api_key;
    private $kissai_user_id;
    private $kissai_user;
    public $assistants;
    private $session_identifier;
    private static $instance = null;
    private $is_service_key_from_kissai = true;

    public function __construct() {
        $this->api_key = self::get_api_key();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_session_id($user = null) {
        if ($user !== null)
            return self::get_user_session_id($user);
        $this->session_identifier = $this->get_session_identifier();
        return $this->session_identifier;
    }

    public function set_session_id($session_id) {
        $this->session_identifier = $session_id;
    }

    private function get_user_session_id($user) {
        return 'user_' . $user->ID;
    }

    public function is_service_key_from_kissai() {
        return $this->is_service_key_from_kissai;
    }

    public function get_ai_api_instance() {
        $openai_key = $this->get_service_api_key();
        $bg_process_key = KissAi_API::get_bg_process_api_key_setting();
        $chatgpt_api = new OpenAI_API($openai_key, $bg_process_key);
        return $chatgpt_api;
    }

    public static function save_guest_user_session_cookie() {
        if (!headers_sent() && !isset($_COOKIE['wp_guest_session_id'])) {
            // Generate a unique session identifier for the guest
            $guest_session_id = 'guest_' . wp_generate_uuid4();
            
            // Cookie parameters
            $secure = is_ssl(); // Set Secure flag if the site is served over HTTPS
            $httponly = true; // HttpOnly flag to prevent access via JavaScript
            $samesite = 'Strict'; // SameSite flag to prevent the cookie from being sent in cross-site requests
            $expiration = time() + DAY_IN_SECONDS; // Cookie expiration
            
            if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
                // PHP 7.3.0 and higher allows setting SameSite in the options array
                setcookie('wp_guest_session_id', $guest_session_id, [
                    'expires' => $expiration,
                    'path' => '/',
                    'domain' => COOKIE_DOMAIN,
                    'secure' => $secure,
                    'httponly' => $httponly,
                    'samesite' => $samesite
                ]);
            } else {
                // For PHP versions lower than 7.3.0, cannot set SameSite attribute directly
                setcookie('wp_guest_session_id', $guest_session_id, $expiration, COOKIEPATH . '; samesite=' . $samesite, COOKIE_DOMAIN, $secure, $httponly);
            }
            $_COOKIE['wp_guest_session_id'] = $guest_session_id; // Set for immediate availability
        }
    }

    private function get_session_identifier() {
        // Check if the user is logged in and use the user ID as the identifier
        if (is_user_logged_in()) {
            return self::get_user_session_id(wp_get_current_user());
        } else {
            // For guests, use a cookie to store a unique session identifier
            if (isset($_COOKIE['wp_guest_session_id'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $cookie_value = wp_unslash($_COOKIE['wp_guest_session_id']);
                return sanitize_text_field($cookie_value);
            }
        }
        return null;
    }
    
    public static function get_bg_process_api_key_setting() {
        $apikey = get_kissai_option('bg_process_api_key');
        if ($apikey)
            return $apikey;
        return null;
    }

    public static function get_api_key() {
        $apikey = get_kissai_option('api_key');
        if($apikey)
            return $apikey;
        return null;
    }

    private static function get_api_key_type($apikey) {
        if (str_starts_with($apikey, "sk-")) {
            return 'openai';
        }
        else if (str_starts_with($apikey, "ka-")) {
            return 'kissaiai';
        }
        return null;
    }

    public function get_default_header() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';

        $host = sanitize_text_field($raw_host);

        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'Origin' => $host,
            'API-ver' => 'kissai=v1',
            'KissAi-ver' => get_kissai_plugin_version(),
        );
    }

    public function get_header($additional_headers = array()) {
        $api_key = [
            'AIServiceKey' => 'openai ' . $this->get_service_api_key()
        ];
        $additional_headers = array_merge($additional_headers, $api_key);
        $default_headers = $this->get_default_header();
    
        // Merge the default headers with any additional headers provided
        $headers = array_merge($default_headers, $additional_headers);
    
        return $headers;
    }

    public function add_assistant($assistant_name, $model_id = null) {
        if ($this->api_key) {
            $headers = $this->get_header();
            if (empty($model_id)) {
                $body_sent = json_encode(array(
                    'assistant_name' => $assistant_name
                ));
            }
            else {
                $body_sent = json_encode(array(
                    'assistant_name' => $assistant_name,
                    'model_id' => $model_id
                ));
            }
            $endpoint = KissAi_API_Endpoints::ASSISTANT;
            $response = wp_remote_post($endpoint, array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => $body_sent
            ));
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: adding assistants: ' . $response->get_error_message());
                return null;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $response_body = self::decapsulate_and_decrypt_data($response_body);

            if (!empty($response_body['response']) && !empty($response_body['body'])) {
                $response = $response_body;
                $response_code = $response_body['response']['code'];
                $response_body = $response_body['body'];
            }
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body , 'error' => $response_body['error']);
            }
            $this->save_assistant($response_body);
            return $response_body; // Or process the response as needed
        }
        return null;
    }

    public function add_assistant_to_user($assistant_id) {
        if ($this->api_key) {
            $headers = $this->get_header();
            if (empty($assistant_id)) {
                return null;
            }
            $body_sent = json_encode(array(
                'assistant_id' => $assistant_id
            ));
            $endpoint = KissAi_API_Endpoints::ASSISTANT;
            $response = wp_remote_post($endpoint, array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => $body_sent
            ));
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: adding assistants to user: ' . $response->get_error_message());
                return null;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $response_body = self::decapsulate_and_decrypt_data($response_body);

            if (!empty($response_body['response']) && !empty($response_body['body'])) {
                $response = $response_body;
                $response_code = $response_body['response']['code'];
                $response_body = $response_body['body'];
            }
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body , 'error' => $response_body['error']);
            }
            return $response_body; // Or process the response as needed
        }
        return null;
    }

    public static function kissai_api_error_log($endpoint, $response_body, $response_code) {
        kissai_error_log('Error response from KissAi API (' . $endpoint . '): ' . $response_body['code'] . ': ' . $response_body['message'] . ' (Code ' . $response_code . ')');
    }
    public function get_assistants($save = false) {
        if ($this->api_key) {
            $headers = $this->get_header();
            $email = get_kissai_option('api_user_email');
            if ($email) {
                $user = $this->get_kissai_user($email);
                if (!empty($user) && !empty($user->id)) {
                    $endpoint = add_query_arg('user_id', $user->id, KissAi_API_Endpoints::ASSISTANT);
                    $response = wp_remote_get($endpoint, array(
                        'method' => 'GET',
                        'headers' => $headers
                    ));
                    if (is_wp_error($response)) {
                        kissai_error_log( 'Error: getting assistants: ' . $response->get_error_message());
                        return null;
                    } else {
                        $response_code = wp_remote_retrieve_response_code($response);
                        $response_body = json_decode(wp_remote_retrieve_body($response), true);

                        $assistants = self::decapsulate_and_decrypt_data($response_body);

                        // Check for HTTP error codes
                        if ($response_code >= 400) {
                            // Log the error or handle it as needed
                            self::kissai_api_error_log($endpoint, $response_body, $response_code);
                            return array();
                        }
                        $this->assistants = $assistants;
                        if ($save) {
                            $this->save_assistants();
                        }
                        return $assistants; // Or process the response as needed
                    }
                }
            }
        }
        return null;
    }

    public function save_assistant($assistant) {
        global $kissai_db;
        $kissai_db->save_assistant($assistant);
    }

    public function delete_assistant($assistant_id) {
        if ($this->api_key) {
            $headers = $this->get_header();
            $body_sent = json_encode(array(
                'assistant_id' => $assistant_id
            ));
            $endpoint = KissAi_API_Endpoints::ASSISTANT;
            $response = wp_remote_post($endpoint, array(
                'method' => 'DELETE',
                'headers' => $headers,
                'body' => $body_sent
            ));
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: deleting assistants: ' . $response->get_error_message());
                return null;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $response_body = self::decapsulate_and_decrypt_data($response_body);

            if (!empty($response_body['response']) && !empty($response_body['body'])) {
                $response = $response_body;
                $response_code = $response_body['response']['code'];
                $response_body = $response_body['body'];
            }
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body , 'error' => $response_body['error']);
            }
            global $kissai_db;
            $kissai_db->delete_assistant($assistant_id);
            return $response_body; // Or process the response as needed
        }
        return null;
    }



    public function save_assistants($assistants = null) {
        if ($assistants === null) {
            $assistants = $this->assistants;
        }
        foreach($assistants as $assistant) {
            $this->save_assistant($assistant);
        }
    }

    private function get_saved_service_api_key() {
        $service_key = get_kissai_option('openai_api_key');
        if (empty($service_key)) {
            $service_key = get_transient('kissai_service_key');
        }
        return $service_key;
    }

    public function get_key_validity($api_key) {
        $endpoint = Kissai_API_Endpoints::KEY_VALIDATE;
        $headers = $this->get_default_header();
        // Fetch the API key from the custom REST endpoint
        $response = wp_remote_get($endpoint, array(
            'headers' => $headers
        ));
        if (is_wp_error($response)) {
            kissai_error_log('Error retrieving service API key: ' . $response->get_error_message());
            return null;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $validity = false;
        if ($response_code == 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            $data = self::decapsulate_and_decrypt_data($data);
            if (isset($data['validity'])) {
                $validity = $data['validity'] == 'valid' ? true : false;
            }
        }
        return $validity;
    }
    
    public function get_service_api_key_from_kissai_server($return_response = false) {
        $service_key = null;
        $email = get_kissai_option('api_user_email');
        if ($email) {
            $user = $this->get_kissai_user($email);
            if ($user) {
                // Endpoint URL
                $endpoint = add_query_arg('user_id', $user->id, KissAi_API_Endpoints::KEY);
        
                $headers = $this->get_default_header();
                // Fetch the API key from the custom REST endpoint
                $response = wp_remote_get($endpoint, array(
                    'headers' => $headers
                ));
        
                if (is_wp_error($response)) {
                    kissai_error_log('Error retrieving service API key: ' . $response->get_error_message());
                    return null; 
                }
        
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code == 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body);

                    $data = self::decapsulate_and_decrypt_data($data);

                    if (isset($data['api_key'])) {
                        $service_key = $data['api_key'];
                        // Cache the key for 1 hour if it's successfully retrieved
                        set_transient('kissai_service_key', $service_key, MINUTE_IN_SECONDS);
                    }
                    else if ($return_response) {
                        return $response;
                    }
                }
                else if ($return_response) {
                    return $response;
                }
            }
        }
        return $service_key;
    }

    public function get_service_api_key_from_kissai() {
        $service_key = get_transient('kissai_service_key');
        if (false === $service_key) {
            $service_key = $this->get_service_api_key_from_kissai_server();
        }
        return $service_key;
    }

    public function get_service_api_key() {
        $api_key_type = get_kissai_option('api_key_type', 'kissai');
        
        if ($api_key_type === 'kissai') {
            $service_key = $this->get_service_api_key_from_kissai();
        }
        else if ($api_key_type === 'openai') {
            $service_key = get_kissai_option('openai_api_key');
            if (!empty($service_key)) {
                $this->is_service_key_from_kissai = false;
            }
            else {
                update_kissai_option('api_key_type', 'kissai');
                $service_key = $this->get_service_api_key_from_kissai();
            }
        }
        return $service_key;
    }

    public function get_kissai_user_id() {
        $data = get_kissai_option('api_user_data');
        if ($data !== null && !empty($data))
            $this->kissai_user = json_decode($data);
        else {
            $email = get_kissai_option('api_user_email');
            if ($email) {
                $data = $this->get_kissai_user($email);
            }
        }
        $this->kissai_user_id = get_kissai_option('api_user_id');
        return $this->kissai_user_id;
    }

    public function update_kissai_user_data($data) {
        $this->kissai_user_id = $data->id;
        $this->kissai_user = $data;
        $json = json_encode($data);
        update_kissai_option('api_user_id', $this->kissai_user_id);
        update_kissai_option('api_user_data', $json);
    }

    public function get_kissai_user($email) {
        if ($this->kissai_user)
            return $this->kissai_user;
        // Append the email as a query parameter to the endpoint

        $endpoint = add_query_arg('email', $email, KissAi_API_Endpoints::USER);
        // Get headers for the request
        $headers = $this->get_default_header();
    
        // Perform the GET request
        $response = wp_remote_get($endpoint, array(
            'headers' => $headers
        ));
    
        // Check for errors in the response
        if (is_wp_error($response)) {
            kissai_error_log('Error retrieving user: ' . $response->get_error_message());
            return null;
        }
    
        // Check the response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code == 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            if (isset($data->id)) {
                $this->update_kissai_user_data($data);
                return $data;
            }
        }
        return null;
    }

    public function get_plugin_details($plugin_id) {
        $endpoint = add_query_arg('plugin_id', $plugin_id, KissAi_API_Endpoints::PLUGIN);
        $headers = $this->get_default_header();
        $response = wp_remote_get($endpoint, array(
            'headers' => $headers
        ));
        if (is_wp_error($response)) {
            kissai_error_log('Error retrieving plugin details: ' . $response->get_error_message());
            return null;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code == 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            if (isset($data->id)) {
                return $data;
            }
        }
        return null;
    }

    public function get_current_kissai_user() {
        $email = get_kissai_option('api_user_email');
        $user = null;
        if ($email) {
            $user = $this->get_kissai_user($email);
        }
        return $user;
    }

    public function get_key_license($user, $kissai_api_key) {
        if (is_object($user)) {
            if ($user->licenses) {
                foreach ($user->licenses as $license) {
                    if ($license->license_key === $kissai_api_key) {
                        return $license;
                    }
                }
            }
        }
        return null;
    }
    public function check_permission($action, $index = -1, $user = null) {
        if ($user === null) {
            $user = $this->get_current_kissai_user();
        }
        $license = null;
        if ($action == "create-message") {
            if (!$this->is_service_key_from_kissai()) {
                return true;
            }
            if ($user->credit) {
                if ($user->credit->minimum_credit < $user->credit->credit_balance) {
                    return true;
                }
                else {
                    return false;
                }
            }
        }
        else {
            if ($user && $user->licenses) {
                $kissai_api_key = get_kissai_option('api_key');
                $license = $this->get_key_license($user, $kissai_api_key);
                if ($license && $license->price > 0) {
                    return true;
                }
            }
            if ($license && $license->price == 0) {
                if ($action == "edit-assistant") {
                    if ($index >= 3)
                        return false;
                    else
                        return true;
                } else if ($action == "upload-training-file") {
                    if ($index >= 5)
                        return false;
                    else
                        return true;
                } else if ($action == "thread") {
                    if ($index >= 3)
                        return false;
                    else
                        return true;
                } else {
                    return true;
                }
            }
        }
        return false;
    }
    public function update_kissai_user($user) {
        if (is_array($user)) {
            $endpoint = KissAi_API_Endpoints::USER;
            $headers = $this->get_header();
            $body_sent = json_encode($user);
            $response = wp_remote_post($endpoint, array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => $body_sent
            ));
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: updating kissai user: ' . $response->get_error_message());
                return $response;
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
                // Check for HTTP error codes
                if ($response_code >= 400) {
                    // Log the error or handle it as needed
                    self::kissai_api_error_log($endpoint, $response_body, $response_code);
                    return array( 'response' => $response['response'], 'body' => $response_body );
                }
                $this->update_kissai_user_data($response_body);
                return $response_body; // Or process the response as needed
            }
        }
        return null;
    }
    public function request_kissai_user_token($email) {
        if (is_email($email)) {
            $endpoint = KissAi_API_Endpoints::USER_LOGIN;
            $headers = $this->get_header();
            $body_sent = json_encode(['email' => $email]);
            $response = wp_remote_post($endpoint, array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => $body_sent
            ));
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: requesting user token: ' . $response->get_error_message());
                return $response;
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = json_decode(wp_remote_retrieve_body($response), true);

                // Check for HTTP error codes
                if ($response_code >= 400) {
                    // Log the error or handle it as needed
                    self::kissai_api_error_log($endpoint, $response_body, $response_code);
                    return array( 'response' => $response['response'], 'body' => $response_body );
                }
                $data = self::decapsulate_and_decrypt_data($response_body);
                return isset($data['token']) ? $data : null;
            }
        }
        return null;
    }

    public function call_server_token_count($call_nonce, $assistant_id, $model_id, $thread_id, $message_type, $data) {
        // Prepare the endpoint URL
        if ($this->api_key) {
            $kissai_user_id = $this->get_kissai_user_id();
            $endpoint = KissAi_API_Endpoints::TOKENIZER;
            $headers = $this->get_header();

            // Prepare the payload
            $payload = [
                'user_id' => $kissai_user_id,
                'call_nonce' => $call_nonce,
                'assistant_id' => $assistant_id,
                'model_id' => $model_id,
                'thread_id' => $thread_id,
                'message_type' => $message_type,
                'data' => $data,
            ];

            // Send the POST request
            $response = wp_remote_post($endpoint, [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($payload),
                'timeout' => 30, // Timeout in seconds
            ]);

            // Check for errors in the response
            if (is_wp_error($response)) {
                kissai_error_log("Error: Unable to connect to TOKEN endpoint. " . $response->get_error_message());
                return null;
            }

            // Process the response
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($response_code >= 200 && $response_code < 300) {
                return $response_body; // Successful response
            } else {
                // Log error details
                $error_message = isset($response_body['error']) ? $response_body['error'] : 'Unknown error';
                kissai_error_log("Error: TOKEN endpoint call failed with code $response_code. Message: $error_message");
                return null;
            }
        }
        return null;
    }

    public function call_update_token_usage($user_id, $call_nonce, $token_input_count, $token_output_count) {
        // Ensure we have an API key before proceeding
        $api_key_type = KissAi_DB::get_current_api_key_type();
        if ($api_key_type == 'openai') {
            return null;
        }
        if ($this->api_key) {
            $headers = $this->get_header();

            // The endpoint constant should be defined in KissAi_API_Endpoints
            // For example: const TOKEN_USAGE = '/token-usage';
            $endpoint = KissAi_API_Endpoints::TOKEN_USAGE;

            // Prepare the payload
            $payload = [
                'user_id' => (int) $user_id,
                'call_nonce' => sanitize_text_field($call_nonce),
                'token_input_count' => (int) $token_input_count,
                'token_output_count' => (int) $token_output_count
            ];

            // Send the POST request
            $response = wp_remote_post($endpoint, [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($payload),
                'timeout' => 30, // Timeout in seconds
            ]);

            // Check for errors in the response
            if (is_wp_error($response)) {
                kissai_error_log("Error: Unable to call TOKEN_USAGE endpoint. " . $response->get_error_message());
                return null;
            }

            // Process the response
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if ($response_code >= 200 && $response_code < 300) {
                // Successfully updated token usage
                return $response_body;
            } else {
                // Log error details
                $error_message = isset($response_body['error']) ? $response_body['error'] : 'Unknown error occurred.';
                kissai_error_log("Error: TOKEN_USAGE endpoint call failed with code $response_code. Message: $error_message");
                return null;
            }
        }

        return null; // No API key available, cannot proceed
    }


    public function get_kissai_updater() {
        $user = $this->get_current_kissai_user();
        $user_id = $user->id ?? null;

        // Check if user_id is available
        if (!$user_id) {
            kissai_error_log("Error in get_kissai_updater: user_id is required but not provided.");
            return (object) [
                'success' => false,
                'message' => "Failed to retrieve updater: user_id is missing.",
            ];
        }

        // Append the user_id as a query parameter to the endpoint
        $endpoint = KissAi_API_Endpoints::UPDATER;
        $url = add_query_arg(['user_id' => $user_id], $endpoint);

        // Get headers for the request
        $headers = $this->get_default_header();

        // Perform the GET request
        $response = wp_remote_get($url, [
            'headers' => $headers,
        ]);

        // Check for errors in the response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            kissai_error_log("Error in get_kissai_updater: $error_message");
            return (object) [
                'success' => false,
                'message' => "Failed to retrieve updater: $error_message",
            ];
        }

        // Check the response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);

            if (json_last_error() !== JSON_ERROR_NONE) {
                kissai_error_log("JSON decoding error in get_kissai_updater: " . json_last_error_msg());
                return (object) [
                    'success' => false,
                    'message' => "Failed to decode response: " . json_last_error_msg(),
                ];
            }

            return $data;
        }

        // Handle non-200 response codes
        $error_body = wp_remote_retrieve_body($response);
        kissai_error_log("Non-200 response in get_kissai_updater. Code: $response_code, Body: $error_body");

        return (object) [
            'success' => false,
            'message' => "Unexpected response code: $response_code. Body: $error_body",
        ];
    }


    public static function encrypt_data($json_data, $secret_key) {
        // Convert the array to JSON
        $data = json_encode($json_data);

        // Define a method and initialization vector (IV)
        $cipher = "AES-256-CBC";
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt the data
        $encrypted_data = openssl_encrypt($data, $cipher, $secret_key, 0, $iv);

        // Combine IV and encrypted data (IV needs to be passed for decryption)
        $result = base64_encode($iv . $encrypted_data);

        return $result;
    }

    public static function decrypt_data($encrypted_data, $secret_key) {
        // Decode the base64-encoded encrypted data
        $encrypted_data = base64_decode($encrypted_data);

        // Extract the IV and the actual encrypted data
        $cipher = "AES-256-CBC";
        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = substr($encrypted_data, 0, $iv_length);
        $encrypted_data = substr($encrypted_data, $iv_length);

        // Decrypt the data
        $decrypted_data = openssl_decrypt($encrypted_data, $cipher, $secret_key, 0, $iv);

        // Convert JSON back to array
        return json_decode($decrypted_data, true);
    }

    public static function encrypt_and_encapsulate_with_key($data_array, $key, $timestamp) {
        // Encrypt the data
        $encrypted_data = self::encrypt_data($data_array, $key);
        if ($encrypted_data === false) {
            return null; // Handle error (encryption failed)
        }

        // Return an array with the key and encrypted data
        return [
            'kissai_key' => $key,
            'timestamp' => $timestamp,
            'data' => $encrypted_data
        ];
    }

    public static function decapsulate_and_decrypt_data($data_array) {
        // Check if the input is a JSON string and decode it if necessary
        if (is_string($data_array)) {
            $data_array = json_decode($data_array, true);
            // Check if json_decode failed
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null; // Handle error (invalid JSON string)
            }
        }

        if (is_array($data_array)) {
            // Check if the necessary keys are present in the array
            if (!isset($data_array['kissai_key']) || !isset($data_array['data'])) {
                return null; // Handle error (missing key or data)
            }
            // Retrieve the key and encrypted data
            $key = $data_array['kissai_key'];
            $encrypted_data = $data_array['data'];
        }
        else if (is_object($data_array)) {
            if (!isset($data_array->kissai_key) || !isset($data_array->data)) {
                return null; // Handle error (missing key or data)
            }
            // Retrieve the key and encrypted data
            $key = $data_array->kissai_key;
            $encrypted_data = $data_array->data;
        }
        else {
            return null;
        }

        // Decrypt the data
        $decrypted_data = self::decrypt_data($encrypted_data, $key);
        if ($decrypted_data === false) {
            return null; // Handle error (decryption failed)
        }

        return $decrypted_data;
    }

    public static function download_url($url, $timeout = 300) {
        if (empty($url)) {
            return new WP_Error('http_no_url', __('No URL Provided.', 'kissai'));
        }

        $tmpfname = wp_tempnam();
        if (!$tmpfname) {
            return new WP_Error('http_no_file', __('Could not create temporary file.', 'kissai'));
        }

        $response = wp_remote_get($url, [
            'timeout' => $timeout,
            'stream' => true,
            'filename' => $tmpfname,
        ]);

        if (is_wp_error($response)) {
            wp_delete_file($tmpfname);
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if (200 !== $response_code) {
            wp_delete_file($tmpfname);
            return new WP_Error('http_error', __('HTTP request failed with code: ', 'kissai') . $response_code);
        }

        return $tmpfname;
    }
}

// Initialize the API class
global $kissai_api;
$kissai_api = new KissAi_API();

function http_dev_request_args($parsed_args, $url) {
    // Check if the site URL ends with '.local'
    if (strpos(home_url(), '.local') === (strlen(home_url()) - strlen('.local'))) {
        $parsed_args['sslverify'] = false;
    }
    return $parsed_args;
}
add_filter('http_request_args', 'http_dev_request_args', 10, 2);
add_action('send_headers', [KissAi_API::class, 'save_guest_user_session_cookie']);
