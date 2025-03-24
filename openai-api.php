<?php
class OpenAI_Endpoints {
    const server = "https://api.openai.com/v1/";
    const models = self::server . "models";
    const model = self::server . "models/{model}";
    const assistants = self::server . "assistants";
    const assistant = self::server . "assistants/{assistant_id}";
    const threads = self::server . "threads";
    const message = self::server . "threads/{thread_id}/messages";
    const runs = self::server . "threads/{thread_id}/runs";
    const run_status = self::server . "threads/{thread_id}/runs/{run_id}";
    const vector_stores = self::server . "vector_stores";
    const vector_store_files = self::server . "vector_stores/{vector_store_id}/files?limit={limit}";
    const files = self::server . "files";
    const file = self::server . "files/{file_id}";
    const vector_store_file_delete = self::server . "vector_stores/{vector_store_id}/files/{file_id}";
}

class OpenAI_API {

    const API_SERVER = 'openai';
    const DEFAULT_INSTRUCTIONS = '';
    const MESSAGE_TYPE_SENT = 'sent';
    const MESSAGE_TYPE_REPEATED = 'repeated';
    const MESSAGE_TYPE_RECEIVED = 'received';
    const MAX_GET_EVENT_RETRY = 5;
    const MAX_SUGGESTED_QUESTIONS = 3;
    const FILE_SEARCH_SCORE_THRESHOLD = 0.8;
    const AI_SUGESSTED_QUESTIONS_PROMPT = 'Based on the documents you have, give me ' . self::MAX_SUGGESTED_QUESTIONS . ' questions that people might most likely ask in JSON format without answers. Return your response exclusively as a JSON array with one object per question. Each object must have a "question" field containing the question text as in this format:
[
  { "question": "question 1?" },
  { "question": "question 2?" },
  { "question": "question 3?" }
]';

    private static $instance = null;

    private $api_key;
    private $bg_process_api_key;
    private $assistant_id;
    private $vector_store_id;
    private $thread_id;

    public $assistants;
    public $assistant;

    /**
     * Constructs a new instance of the OpenAI_API class.
     *
     * This constructor initializes the OpenAI_API object with the necessary API keys for interacting
     * with the OpenAI services. It sets the primary API key used for standard operations and a 
     * background processing key that may be used for asynchronous tasks or operations that
     * require continuous communication with the API.
     *
     * @param string $openai_api_key The primary API key for OpenAI services, used for all standard API calls.
     * @param string $bg_process_key The key used specifically for background processes or extended operations
     *                               that might not directly interact with the user but require API access.
     */
    public function __construct($openai_api_key, $bg_process_key) {
        $this->api_key = $openai_api_key;
        $this->bg_process_api_key = $bg_process_key;
    }

    public function get_bg_process_api_key() {
        return $this->bg_process_api_key;
    }

    public static function getInstance($openai_api_key, $bg_process_key) {
        if (self::$instance === null) {
            self::$instance = new self($openai_api_key, $bg_process_key);
        }
        $chatgpt_api = self::$instance;
        return $chatgpt_api;
    }

    public function get_session_id($user = null) {
        global $kissai_api;
        return $kissai_api->get_session_id($user);
    }
    
    public function get_assistant_id() {
        return $this->assistant_id;
    }

    public function get_vector_store_id() {
        if ($this->vector_store_id === null) {
            $assistant = $this->get_assistant();
            if (isset($assistant['id']) && $assistant['id'] == $this->get_assistant_id()) {
                if (isset($assistant['tool_resources']['file_search']['vector_store_ids'][0])) {
                    $this->vector_store_id = $assistant['tool_resources']['file_search']['vector_store_ids'][0];
                }
            }
            if (!empty($assistant) && $this->vector_store_id === null) {
                $vector_store_name = $assistant['id'];
                if (isset($assistant['name']) && $assistant['name'] != '') {
                    $vector_store_name = $assistant['name'];
                }
                $vector_store_name .= '_store';
                $vector_store = $this->create_vector_store($vector_store_name);
                if (isset($vector_store['id']) && $vector_store['name'] === $vector_store_name) {
                    $this->vector_store_id = $vector_store['id'];
                    $this->update_assistant_vector_store($this->vector_store_id);
                }
            }
        }
        return $this->vector_store_id;
    }

    public static function is_call_get_api_success($result) {
        if (isset($result['response']['code']) && $result['response']['code'] >= 400)
            return false;
        if (isset($result['body']['error']))
            return false;
        return true;
    }

    public function get_vector_store_files($vector_store_id = null) {
        if ($vector_store_id === null)
            $vector_store_id = $this->get_vector_store_id();
        if ($vector_store_id !== null) {
            $files = $this->call_get_api(strtr(OpenAI_Endpoints::vector_store_files, array( '{vector_store_id}' => $vector_store_id, '{limit}' => 100 )));
            if (self::is_call_get_api_success($files) && isset($files['data'])) {
                foreach ($files['data'] as $key => $value) {
                    $file = $this->call_get_api(strtr(OpenAI_Endpoints::file, array( '{file_id}' => $value['id'] )));
                    if (self::is_call_get_api_success($file)) {
                        if (isset($file['filename'])) {
                            $files['data'][$key]['filename'] = $file['filename'];
                        }
                        if (isset($file['bytes'])) {
                            $files['data'][$key]['bytes'] = $file['bytes'];
                        }
                        if (isset($file['created_at'])) {
                            $files['data'][$key]['created_at'] = $file['created_at'];
                        }
                    }
                }
                return $files;
            }
        }
    }

    public function delete_vector_store_file($file_id, $vector_store_id = null) {
        $endpoint = '';
        if ($vector_store_id === null)
            $vector_store_id = $this->get_vector_store_id();
        if ($vector_store_id !== null) {
            $endpoint = strtr(OpenAI_Endpoints::vector_store_file_delete, array(
                '{vector_store_id}' => $vector_store_id,
                '{file_id}' => $file_id ));
            $result = $this->call_delete_api($endpoint);
            if (self::is_call_get_api_success($result) && isset($result['deleted'])) {
                if ($result['deleted'] === true)
                    return true;
            }
        }
        $jsondata = '';
        if ($result)
            $jsondata = json_encode($result);
        $this->api_log($endpoint, 'vector.file.delete', 'vector store file delete failed', $jsondata );
        return false;
    }

    public function api_log($endpoint, $event, $message, $data) {
        global $kissai_db;
        $result = $kissai_db->api_log(self::API_SERVER, $endpoint, $event, $message, $data);
        return $result;
    }

    public function delete_file($file_id) {
        $endpoint = strtr(OpenAI_Endpoints::file, array(
            '{file_id}' => $file_id ));
        $result = $this->call_delete_api($endpoint);
        if (self::is_call_get_api_success($result) && isset($result['deleted'])) {
            if ($result['deleted'] === true)
                return true;
        }
        $this->api_log($endpoint, 'file.delete', 'file delete failed', json_encode($result) );
        return false;
    }

    public function upload_file($filePath, $purpose = 'assistants') {
        // Get the file's contents and original name
        $fileContents = file_get_contents($filePath);
        $filename = basename($filePath);

        // Prepare the body data as multipart/form-data
        $boundary = wp_generate_password(24);
        $headers = $this->get_header();

        $body = "--{$boundary}\r\n" .
                "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n" .
                "{$purpose}\r\n" .
                "--{$boundary}\r\n" .
                "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n" .
                "Content-Type: application/octet-stream\r\n\r\n" .
                $fileContents . "\r\n" .
                "--{$boundary}--";

        // Use wp_remote_post to perform the upload
        $response = wp_remote_post(OpenAI_Endpoints::files, array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => 45
        ));

        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            if ($response_code >= 200 && $response_code < 300) {
                return $response_body;
            } else {
                return array( 'response' => $response['response'], 'body' => $response_body );
            }
        }
    }    

    private function get_session_thread($session_id = null, $set_thread_id = true) {
        global $wpdb, $kissai_api, $kissai_db; // Make sure to have access to the global $wpdb object

        // Table name with prefix
        $table_name = $kissai_db->table_names->threads;

        // Query to check if the session_id already has a thread_id
        if ($session_id === null) {
            $session_id = $kissai_api->get_session_id();
        }

        $session_thread = $kissai_db->cached_get_row(
            "kissai_session_thread_{$session_id}",
            "kissai_session_threads",
            "SELECT thread_id FROM {$table_name} 
              WHERE session_id = %s ORDER BY created_at DESC LIMIT 1",
            [$session_id]
        );

        // Check if a thread_id was found for the session_id
        if (null !== $session_thread) {
            // A thread_id exists, so use it
            if ($set_thread_id)
                $this->thread_id = $session_thread->thread_id;
            return $session_thread->thread_id;
        }
        return null;
    }

    private function initialize_thread($assistant_id, $create = false) {
        global $wpdb, $kissai_api, $kissai_db; // Make sure to have access to the global $wpdb object
    
        // Table name with prefix
        $table_name = $kissai_db->table_names->threads;
    
        if ($create) {
            $session_thread = null;
        }
        else {
            // Query to check if the session_id already has a thread_id
            $session_thread = $this->get_session_thread();
        }
    
        // Check if a thread_id was found for the session_id
        if (null === $session_thread) {
            // No thread_id found for this session_id, so create a new thread
            // This is a placeholder for the actual API call to create a new thread
            // After creating a new thread, you should have a new thread_id
            $new_thread_id = $this->create_thread();
    
            if ($new_thread_id != null) {
                // Insert the new session_id and thread_id into the database
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->insert(
                    $table_name,
                    array(
                        'assistant_id' => $assistant_id,
                        'api_server' => self::API_SERVER,
                        'session_id' => $kissai_api->get_session_id(),
                        'thread_id' => $new_thread_id,
                    ),
                    array('%s', '%s')
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        
                // Initialize the object's thread_id with the new one
                $this->thread_id = $new_thread_id;
                $session_thread = $new_thread_id;
            }
        }
        return $session_thread;
    }

    public function chatgpt_save_nonce() {
        global $wpdb, $kissai_api, $kissai_db;
    
        // Generate a UUID for the guid column. Consider using wp_generate_uuid4() if available.
        $guid = wp_generate_uuid4(); // WordPress 5.6 and above. For older versions, consider an alternative method.
    
        $table_name = $kissai_db->table_names->api_call_nonce;
    
        // Prepare and execute the INSERT query with the generated GUID and the current timestamp.
        $timestamp = current_time('mysql', 1); // Use WordPress's current_time() function for 'created_at'
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table_name,
            [
                'guid'       => $guid,
                'session_id' => $kissai_api->get_session_id(),
                'created_at' => $timestamp,
            ],
            ['%s', '%s', '%s']
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    
        // Check for errors or handle the result as needed
        if ($result === false) {
            kissai_error_log("Error - insertion failed: Table: {$table_name} | Data: ({$guid},{$timestamp}) | WPDB Error: " . $wpdb->last_error);
        }
        return $guid;
    }

    public function chatgpt_new_thread_and_start_new_session() {
        $assistant_id = $this->assistant_id;
        $this->initialize_thread($assistant_id, true);
    }

    public function chatgpt_check_nonce($nonce, $delete = true) {
        global $kissai_api, $kissai_db;
    
        // Get session id from nonce
        $session_id = KissAi_DB::get_session_id_with_nonce($nonce);
    
        // Check for errors or handle the result as needed
        if ($session_id === false) {
            // No matching nonce found within the last 5 minutes
            return false;
        } else {
            // Nonce found
            $kissai_api->set_session_id($session_id);
            $thread_id = $this->get_session_thread($session_id, false);
            if ($thread_id !== null) {
                $this->thread_id = $thread_id;
            }
            else {
                $assistant_id = $this->assistant_id;
                $this->initialize_thread($assistant_id);
            }
            if ($delete) {
                $delete_result = $kissai_db->delete_nonce($nonce);
                if ($delete_result === false) {
                    return false;
                }
            }
            return true;
        }
    }

    public function isThreadId() {
        if ($this->thread_id === null) {
            return false;
        }
        return true;
    }

    public function getThreadId() {
        // Check if thread_id is not initialized
        if ($this->thread_id === null) {
            // Attempt to initialize the thread
            $assistant_id = $this->assistant_id;
            $this->initialize_thread($assistant_id);
        }

        // Return the thread_id (which might still be null if initialization failed)
        return $this->thread_id;
    }

    public function set_thread_id($thread_id) {
        $this->thread_id = $thread_id;
    }

    public function get_default_header() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2',
        );
    }

    public function get_header($additional_headers = array()) {
        $default_headers = $this->get_default_header();
    
        // Merge the default headers with any additional headers provided
        $headers = array_merge($default_headers, $additional_headers);
    
        return $headers;
    }
    
    public function create_thread() {
        $response = wp_remote_post(OpenAI_Endpoints::threads, array(
            'headers' => $this->get_header()
        ));
    
        if (is_wp_error($response)) {
            // Handle error appropriately; perhaps log it and return false or null
            kissai_error_log('Error creating thread: ' . $response->get_error_message());
            return null; // Indicate failure to the caller
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return isset($body['id']) ? $body['id'] : null; // Ensure 'id' exists
        }
    }

    private static function replace_url_with_args($url, $args) {
        foreach ($args as $key => $value) {
            if (str_starts_with($key, "{") && str_ends_with($key, "}")) {
                $url = str_replace($key, urlencode($value), $url);
            }
        }
        return $url;
    }

    private function call_get_api($endpoint, $args = null) {
        // Ensure $args is initialized if not provided
        if ($args !== null && !isset($args['headers'])) {
            $args['headers'] = $this->get_header();
        } elseif ($args === null) {
            $args = array('headers' => $this->get_header());
        }

        // Replace placeholders in the endpoint URL with values from $args
        $endpoint = self::replace_url_with_args($endpoint, $args);
    
        $response = wp_remote_get($endpoint, $args);

        if (is_wp_error($response)) {
            kissai_error_log('Error: getting data from API (' . $endpoint . '): ' . $response->get_error_message());
            return null;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): Message: ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body );
            }
            else {
                return $response_body;
            }
        }
    }

    private function call_delete_api($endpoint, $args = null) {
        // Ensure $args is initialized if not provided
        if ($args === null) {
            $args = array(
                'method' => 'DELETE',
                'headers' => $this->get_header()
            );
        }

        // Replace placeholders in the endpoint URL with values from $args
        $endpoint = self::replace_url_with_args($endpoint, $args);
    
        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            kissai_error_log('Error: getting data from API (' . $endpoint . '): ' . $response->get_error_message());
            return null;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): Message: ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body );
            }
            else {
                return $response_body;
            }
        }
    }
    
    public function get_assistants() {
        if ($this->assistants === null) {
            $response = wp_remote_get(OpenAI_Endpoints::assistants, array(
                'headers' => $this->get_header()
            ));
        
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: getting assistants: ' . $response->get_error_message());
                return null;
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $this->assistants = $body;
                return $body;
            }
        }
        else {
            return $this->assistants;
        }
    }

    public function get_assistant_by_id($assistant_id) {
        return $this->call_get_api(strtr(OpenAI_Endpoints::assistant, array( '{assistant_id}' => $assistant_id )));
    }

    public function get_assistant($assistant_id = null) {
        $assistant = null;
        if ($assistant_id === null) {
            if ($this->assistant !== null)
                return $this->assistant;
            if ($this->assistant_id !== null)
                $assistant_id = $this->assistant_id;
        }
        if ($assistant_id !== null) {
            $assistant = $this->get_assistant_by_id( $assistant_id );
            if ($assistant !== null && isset($assistant['id'])) {
                $this->assistant = $assistant;
            }
            else {
                $this->assistant = null;
            }
        }
        return $assistant;
    }

    public function set_assistant($assistant_id) {
        if ($assistant_id !== null) {
            $assistant = $this->get_assistant_by_id( $assistant_id );
            if ($assistant !== null && isset($assistant['id'])) {
                $this->assistant_id = $assistant_id;
                $this->assistant = $assistant;
                return $assistant;
            }
        }
        return null;
    }

    public function get_models() {
        $models = $this->call_get_api(OpenAI_Endpoints::models);
        if ($models !== null && isset($models['object']) && $models['object'] == 'list') {
            return $models;
        }
        return null;
    }

    public function get_model($model_id) {
        $model = $this->call_get_api(OpenAI_Endpoints::model, ['{model}' => $model_id]);
        if ($model !== null && isset($model['object']) && $model['object'] == 'model') {
            return $model;
        }
        return null;
    }
    
    public static function sort_models_by_key($models, $key, $order = 'asc') {
        // Use usort to sort the array by the specified key and order
        usort($models['data'], function ($a, $b) use ($key, $order) {
            // Compare the values of the specified key
            if ($a[$key] == $b[$key]) {
                return 0;
            }
    
            // Ascending order
            if ($order === 'asc') {
                return ($a[$key] < $b[$key]) ? -1 : 1;
            }
    
            // Descending order
            return ($a[$key] > $b[$key]) ? -1 : 1;
        });
    
        return $models;
    }

    public static function check_model_id($model, $id_conditions = [], $id_inclusive = true) {
        // Split the 'id' by hyphen (-)
        $id_sections = explode('-', $model['id']);
        $id_match = false;

        // Check if each condition matches the respective section of the 'id'
        foreach ($id_conditions as $index => $condition) {
            // If the condition does not match, set id_match to false
            if (in_array($condition, $id_sections)) {
                $id_match = true;
                break;
            }
        }

        // Check if it's inclusive or exclusive
        if ($id_inclusive && !$id_match) {
            return false; // Exclude if inclusive and no match
        }
        if (!$id_inclusive && $id_match) {
            return false; // Exclude if exclusive and match
        }
        return true;
    }
    
    public static function filter_models_by_conditions($models, $id_conditions = [], $owned_by_conditions = [], $id_inclusive = true, $owned_by_inclusive = true) {
        // Filter the models based on the conditions
        $filtered_models = array_filter($models['data'], function($model) use ($id_conditions, $owned_by_conditions, $id_inclusive, $owned_by_inclusive) {
            
            // Filter based on 'owned_by'
            if (!empty($owned_by_conditions)) {
                $owned_by_match = in_array($model['owned_by'], $owned_by_conditions);
    
                // Check if it's inclusive or exclusive
                if ($owned_by_inclusive && !$owned_by_match) {
                    return false; // Exclude if inclusive and no match
                }
                if (!$owned_by_inclusive && $owned_by_match) {
                    return false; // Exclude if exclusive and match
                }
            }
    
            // Filter based on 'id' sections if any condition is provided
            if (!empty($id_conditions)) {
                return self::check_model_id($model, $id_conditions, $id_inclusive);
            }
    
            // If all conditions pass, include the model
            return true;
        });
    
        return self::models_data_into_object($filtered_models);
    }

    public static function models_data_into_object($models) {
        return ['object' => 'list', 'data' => array_values($models)];
    }
    
    public static function is_model_compatible_with_assistant($model) {
        return self::check_model_id($model, ['gpt']);
    }
    
    public function get_latest_model() {
        $models = $this->get_models(); // Retrieve all models
        if ($models === null || !isset($models['data']) || empty($models['data'])) {
            return null; // Return null if no models were found or there was an error
        }
    
        $models = OpenAI_API::sort_models_by_key($models, 'created', 'desc');

        $latestModel = null;
    
        foreach ($models['data'] as $model) {
            // Check if the model is chatgpt-4o-latest. Refer to https://platform.openai.com/docs/models/gpt-4o#4ofootnote
            if ($model['id'] !== 'chatgpt-4o-latest') {
                // Check if the model is compatible with assistant 
                // https://platform.openai.com/docs/models/model-endpoint-compatibility
                if (self::is_model_compatible_with_assistant($model)) {
                    $latestModel = $model;
                    break;
                }
            }
        }
    
        return $latestModel; // Return the latest model or null if none found
    }

    public function create_assistant($name, $model_id = null, $tools = null) {
        if (empty($model_id)) {
            $model = $this->get_latest_model();
        }
        else {
            $model = $this->get_model($model_id);
        }
        if ($tools === null) {
            $tools = [['type' => "file_search"],['type' => "function", 'function' => self::get_current_time_function_def()]];
        }
        if ($model) {
            $body_sent = json_encode(array(
                'name' => $name,
//                'tools'=> [['type' => "file_search"],['type' => "code_interpreter"]],
                'tools'=> $tools,
                'model' => $model['id'],
                'instructions' => self::DEFAULT_INSTRUCTIONS
            ));
            $sent = array(
                'headers' => $this->get_header(),
                'body' => $body_sent,
                'data_format' => 'body',
            );
            $endpoint = OpenAI_Endpoints::assistants;
            $response = wp_remote_post($endpoint, $sent);
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: creating assistant (' . $body_sent . "): " . $response->get_error_message());
                return false; // Or handle the error as needed
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body );
            }
            if (isset($response_body['id'])) {
                $this->assistant = $response_body;
                $this->assistant_id = $response_body['id'];
                $response_body['assistant_id'] = $response_body['id'];
            }
            // Assuming the response is successful if no error code
            return $response_body; // Or process the response as needed
        }
        return null;
    }

    public function delete_assistant($assistant_id) {
        $endpoint = strtr(OpenAI_Endpoints::assistant, array(
            '{assistant_id}' => $assistant_id));
        $result = $this->call_delete_api($endpoint);
        if (self::is_call_get_api_success($result) && isset($result['deleted'])) {
            if ($result['deleted'] === true)
                return true;
        }
        $jsondata = '';
        if ($result)
            $jsondata = json_encode($result);
        $this->api_log($endpoint, 'assistant.delete', 'delete assistant failed', $jsondata );
        return false;
    }

    /**
     * Check if a given model is compatible with the Assistant API
     * by attempting to create (and then delete) a temporary assistant.
     *
     * @param string $model_id
     * @return bool True if the model can be used to create an assistant, false otherwise
     */
    /**
     * Check if a given model is compatible with the Assistant API
     * by attempting to create (and then delete) a temporary assistant.
     * Also verifies that the returned assistant actually uses the requested model (no fallback).
     *
     * @param string $model_id
     * @return bool True if the model is used exactly as requested, false otherwise.
     */
    public function test_model_compatibility_with_assistant($model_id)
    {
        // Create a unique name to avoid collisions
        $temp_name = 'temp_assistant_' . uniqid();

        // Attempt to create an assistant using the specified model
        $assistant_response = $this->create_assistant($temp_name, $model_id, []);

        // Prepare to store the assistant ID if creation succeeds
        $temp_assistant_id = null;
        if (is_array($assistant_response) && isset($assistant_response['assistant_id'])) {
            $temp_assistant_id = $assistant_response['assistant_id'];
        }

        // Attempt to delete the temporary assistant if we got an ID
        // We'll do this in a `finally`-type approach below, but let's store the response first
        try {
            // Check if creation was truly successful
            // 1. Must be a valid array
            // 2. Must not contain an error field
            // 3. Must contain an assistant_id
            // 4. Must contain the *exact* model in the response that we requested
            if (
                !is_array($assistant_response) ||
                isset($assistant_response['error']) ||
                isset($assistant_response['response']) ||
                !isset($assistant_response['assistant_id']) ||
                !isset($assistant_response['model'])
            ) {
                return false;
            }

            // Check if OpenAI actually used the model we passed or fell back to another
            $returned_model_id = $assistant_response['model'];
            if ($returned_model_id !== $model_id) {
                // The model wasn't the same => fallback or mismatch => not compatible
                return false;
            }

            // If we reach here, the assistant was successfully created with the *exact* requested model
            return true;
        }
        finally {
            // Clean up: attempt to delete the assistant if we have an ID
            if (!empty($temp_assistant_id)) {
                $this->delete_assistant($temp_assistant_id);
            }
        }
    }


    public static function get_default_assistant_name() {
        function get_sanitized_site_name() {
            // Fetch the site title
            $site_name = html_entity_decode(get_bloginfo('name'));
        
            // Replace & with "and"
            $site_name = str_replace('&', 'and', $site_name);
        
            // Remove special characters and replace spaces with underscores
            $sanitized_site_name = preg_replace('/[^a-zA-Z0-9 ]/', '', $site_name);
            $sanitized_site_name = str_replace(' ', '_', $sanitized_site_name);
        
            return $sanitized_site_name;
        }
        
        return get_sanitized_site_name() . "_assistant";
    }
    public function create_vector_store($name) {
        if ($this->assistant_id != null) {
            $sent = array(
                'headers' => $this->get_header(),
                'body' => json_encode(array(
                    'name' => $name,
                )),
                'data_format' => 'body',
            );
            $endpoint = OpenAI_Endpoints::vector_stores;
            $response = wp_remote_post($endpoint, $sent);
    
            // First, check if the request itself failed
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: creating vector store (' . $this->assistant_id . "): " . $response->get_error_message());
                return false; // Or handle the error as needed
            }
    
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body );
            }
            if (isset($response_body['id'])) {
                $this->vector_store_id = $response_body['id'];
            }
            // Assuming the response is successful if no error code
            return $response_body; // Or process the response as needed
        }
    
        // If thread ID isn't set, handle accordingly
        kissai_error_log('Error: Assistant ID is not set.');
        return false;
    }

    private function update_assistant($data) {
        if ($this->assistant_id != null) {
            $sent_body = "";
            if (is_array($data)) {
                $sent_body = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);
            }
            else if (is_string($data)) {
                $sent_body = $data;
            }
            else {
                return false;
            }
            $sent = array(
                'headers' => $this->get_header(),
                'body' => $sent_body,
                'data_format' => 'body',
            );
            $endpoint = strtr(OpenAI_Endpoints::assistant, array ( '{assistant_id}' => $this->assistant_id ));
            $response = wp_remote_post($endpoint, $sent);
    
            // First, check if the request itself failed
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: update assistant (' . $this->assistant_id . "): sent: " . $sent . " | " . $response->get_error_message());
                return false; // Or handle the error as needed
            }
    
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
            if (!empty($response_body['response']) && !empty($response_body['body'])) {
                $response = $response_body;
                $response_code = $response_body['response']['code'];
                $response_body = $response_body['body'];
            }
            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): sent: ' . json_encode($sent) . " | response: ". $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return array( 'response' => $response['response'], 'body' => $response_body , 'error' => $response_body['error']);
            }

            // Assuming the response is successful if no error code
            return $response_body; // Or process the response as needed
        }
    
        // If thread ID isn't set, handle accordingly
        kissai_error_log('Error: Assistant ID is not set.');
        return false;
    }

    public function update_assistant_vector_store($vector_store_id) {
        return $this->update_assistant(array(
                'tool_resources' => array (
                    'file_search' => array (
                        'vector_store_ids' => [ $vector_store_id ]
                    )
                )
            ));
    }
    
    public function update_assistant_instructions($instructions) {
        return $this->update_assistant(array(
            'instructions' => $instructions
        ));
    }

    public function update_assistant_name($name) {
        return $this->update_assistant(array(
            'name' => $name
        ));
    }
    
    public function update_assistant_model($model_id) {
        return $this->update_assistant(array(
            'model' => $model_id
        ));
    }

    public function update_assistant_function($assistant, $function_definition) {
        if ($function_definition === null || !isset($function_definition['name']) || empty($function_definition['name'])) {
            return null;
        }
        $tools = [];
        if ($assistant !== null && isset($assistant['tools']) && is_array($assistant['tools'])) {
            $tools = $assistant['tools'];
        }
        $function_exists = false;
        $new_tools = [];
        foreach ($tools as $index => $tool) {
            if ($tool['type'] == 'file_search') {
                $new_tools[] = ['type' => 'file_search', 'file_search' => ['ranking_options' => ['score_threshold' => self::FILE_SEARCH_SCORE_THRESHOLD]]];
            }
            else if ($tool['type'] == "code_interpreter") {
                $new_tools[] = ['type' => "code_interpreter"];
            }
            else if ($tool['type'] == "function" && $tool['function']["name"] == $function_definition["name"]) {
                $function_exists = true;
            }
        }
        if (!$function_exists) {
            $new_tools[] = ['type' => "function", 'function' => $function_definition];
            return $this->update_assistant(array(
                'tools' => $new_tools
            ));
        }
        return $assistant;
    }

    public function save_message_and_token_count($call_nonce, $message_type, $data, $utility_message = false) {
        global $kissai_db, $kissai_api;

        $assistant_id = $this->assistant_id;
        if ($assistant_id != null) {
            $thread_id = $this->getThreadId();

            $assistant = $this->get_assistant();

            if (!$assistant) {
                kissai_error_log("save_message_and_token_count: Failed to retrieve assistant for assistant_id: $assistant_id");
                return false;
            }

            $model_id = $assistant['model'] ?? '';
            if (empty($model_id)) {
                kissai_error_log("save_message_and_token_count: No model ID found for assistant_id: $assistant_id");
                return false;
            }

            $message_record_id = 0;

            $message_content = "";
            // Attempt to save the message
            $kissai_db->save_message($assistant_id, $thread_id, $call_nonce, $data, $message_type, $utility_message, $message_record_id, $message_content);

            if ($message_record_id == 0) {
                kissai_error_log("save_message_and_token_count: Failed to save message for assistant_id: $assistant_id, thread_id: $thread_id");
                return false;
            }

            // Call the server to get token count
            $token = $kissai_api->call_server_token_count($call_nonce, $assistant_id, $model_id, $thread_id, $message_type, $message_content);

            if (!$token || empty($token['token_count'])) {
                kissai_error_log("save_message_and_token_count: Tokenization failed for call_nonce: $call_nonce, assistant_id: $assistant_id");
                return false;
            }

            $token_count = $token['token_count'];

            // Attempt to update the token count in the database
            $update_success = $kissai_db->update_token_count($message_record_id, $token_count);

            if ($update_success) {
                return $token_count;
            } else {
                kissai_error_log("save_message_and_token_count: Failed to update token count for message_record_id: $message_record_id");
            }
        } else {
            kissai_error_log("save_message_and_token_count: Assistant ID is null");
        }

        return false;
    }

    public function update_token_usage($call_nonce, $data, $utility_message = false) {
        global $kissai_db, $kissai_api;

        // Ensure $data is a stdClass object
        if (is_object($data) && isset($data->object) && $data->object === 'thread.run' && isset($data->usage)) {
            // Already a stdClass object, use as-is
            $event_data = $data;
        } elseif (is_array($data) && isset($data['object']) && $data['object'] === 'thread.run' && isset($data['usage'])) {
            // Convert array to stdClass object
            $event_data = json_decode(json_encode($data));
        } else {
            // Assume $data is a JSON string and decode it into stdClass
            $event_data = json_decode($data);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($event_data->object) || $event_data->object !== 'thread.run') {
                kissai_error_log("update_token_usage: Failed to decode JSON data or invalid format");
                return false;
            }
        }

        // Extract usage data
        $usage = $event_data->usage ?? null;
        if (empty($usage)) {
            kissai_error_log("update_token_usage: No usage data found in event_data");
            return false;
        }

        $prompt_tokens = $usage->prompt_tokens ?? null;
        $completion_tokens = $usage->completion_tokens ?? null;
        $total_tokens = $usage->total_tokens ?? null;

        // Retrieve existing message record IDs
        $sent_message_record_id = $kissai_db->get_existing_message($call_nonce, OpenAI_API::MESSAGE_TYPE_SENT, $utility_message);
        $rcvd_message_record_id = $kissai_db->get_existing_message($call_nonce, OpenAI_API::MESSAGE_TYPE_RECEIVED, $utility_message);

        if ($sent_message_record_id == 0 && $rcvd_message_record_id == 0) {
            kissai_error_log("update_token_usage: No matching messages found for call_nonce: $call_nonce");
            return false;
        }

        // Update token counts in the database
        if ($sent_message_record_id != 0 && $prompt_tokens !== null) {
            $update_success = $kissai_db->update_token_count($sent_message_record_id, (int) $prompt_tokens);
            if (!$update_success) {
                kissai_error_log("update_token_usage: Failed to update prompt token count for sent_message_record_id: $sent_message_record_id");
            }
        }

        if ($rcvd_message_record_id != 0 && $completion_tokens !== null) {
            $update_success = $kissai_db->update_token_count($rcvd_message_record_id, (int) $completion_tokens);
            if (!$update_success) {
                kissai_error_log("update_token_usage: Failed to update completion token count for rcvd_message_record_id: $rcvd_message_record_id");
            }
        }

        $kissai_user_id = $kissai_api->get_kissai_user_id();

        if (!empty($kissai_user_id) && $prompt_tokens !== null && $completion_tokens !== null) {
            $api_response = $kissai_api->call_update_token_usage($kissai_user_id, $call_nonce, $prompt_tokens, $completion_tokens);
            $api_key_type = KissAi_DB::get_current_api_key_type();
            if ($api_key_type === 'kissai') {
                if ($api_response === null) {
                    kissai_error_log("update_token_usage: Failed to update token usage on TOKEN_USAGE API endpoint.");
                }
            }
        } else {
            kissai_error_log("update_token_usage: Unable to call TOKEN_USAGE API - missing user_id or token data.");
        }

        // Optionally return total_tokens or a boolean indicating success
        return $total_tokens !== null ? $total_tokens : true;
    }

    /**
     * Sends a message to the ChatGPT API and saves both the sent message and the received response.
     *
     * This function first checks if a thread ID is available. If so, it constructs a message payload
     * with the provided prompt and sends this message to the ChatGPT API using the thread-specific endpoint.
     * The sent message is saved immediately before sending. Upon receiving a response from the API, 
     * the response is also saved. Both the sent message and the received response are stored with 
     * appropriate message types ('sent' or or 'repeated' or 'received').
     *
     * If the API call is successful, the received response is returned. In case of an error (e.g., network issues,
     * API errors), the error is logged, and false is returned. This function relies on WordPress's HTTP API
     * for making the remote POST request and handles potential errors using WordPress's error handling mechanisms.
     *
     * @param string $prompt The text content to be sent to the ChatGPT API as part of the user's message.
     * @return mixed The response from the ChatGPT API if successful; otherwise, false.
     */
    public function chatgpt_create_message($prompt, $nonce = null, $utility_message = false) {
        if ($this->getThreadId() != null) {
            $sent = [
                'headers' => $this->get_header(),
                'body' => json_encode([
                    'role' => "user",
                    'content' => $prompt,
                ]),
                'data_format' => 'body',
            ];
            $json_sent = json_encode($sent);
            if ($nonce !== null) {
                $this->save_message_and_token_count($nonce, self::MESSAGE_TYPE_SENT, $json_sent, $utility_message);
            }
            $endpoint = strtr(OpenAI_Endpoints::message, ['{thread_id}' => $this->thread_id]);
            $response = wp_remote_post($endpoint, $sent);

            // First, check if the request itself failed
            if (is_wp_error($response)) {
                kissai_error_log( 'Error: creating message within thread (' . $this->thread_id . "): " . $response->get_error_message());
                return false; // Or handle the error as needed
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            // Check for HTTP error codes
            if ($response_code >= 400) {
                // Log the error or handle it as needed
                kissai_error_log('Error response from ChatGPT API (' . $endpoint . '): ' . $response_body['error']['message'] . ' (Code ' . $response_code . ')');
                return $response_body; // Or include more detailed error handling as needed
            }
            if (isset($response_body['object'])) {
                $message_object = $response_body['object'];
                if ($message_object == 'thread.message') {
                    if ($nonce !== null) {
                        $this->save_message_and_token_count($nonce, self::MESSAGE_TYPE_RECEIVED, json_encode($response_body), $utility_message);
                    }
                }
            }
            // Assuming the response is successful if no error code
            return $response_body; // Or process the response as needed
        }

        // If thread ID isn't set, handle accordingly
        kissai_error_log('Error: Thread ID is not set.');
        return false;
    }
    public function process_openai_function_calls($requires_action_data) {
        if (isset($requires_action_data['tool_calls'])) {
            foreach ($requires_action_data['tool_calls'] as $tool_call) {
                $this->handle_openai_function_call($tool_call['function']['name'], $tool_call['id']);
            }
        }
    }

    public function handle_openai_function_call($function_name, $toll_call_id) {
        if ( $function_name === 'get_current_time' ) {
            // Use gmdate() with the current Unix timestamp for raw UTC
            $function_response = [
                'current_time' => gmdate( 'Y-m-d H:i:s \U\T\C' )
            ];
            $this->send_function_response( $function_response, $toll_call_id );
        }
    }


    public function send_function_response($function_response, $tool_call_id) {
        $endpoint = strtr(OpenAI_Endpoints::message, ['{thread_id}' => $this->thread_id]);

        $sent = [
            'headers' => $this->get_header(),
            'body' => json_encode([
                'role' => "tool",
                'tool_call_id' => $tool_call_id,
                'content' => json_encode($function_response)
            ]),
            'data_format' => 'body',
        ];

        $response = wp_remote_post($endpoint, $sent);

        if (is_wp_error($response)) {
            kissai_error_log('Error: sending function response (' . $this->thread_id . "): " . $response->get_error_message());
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    // public function store_event_data($nonce, $eventName, $eventData, $sequenceNumber) {
    //     $transientKey = "chatgpt_stream_event_{$this->thread_id}_{$sequenceNumber}";
    //     $eventJson = json_encode(['event' => $eventName, 'data' => json_decode($eventData)]);
    //     set_transient($transientKey, $eventJson, 60 * 5); // Expire in 5 minutes
    // }

    public static function get_current_time_function_def() {
        return [
            "name" => "get_current_time",
            "description" => "Returns the current date and time.",
            "parameters" => new stdClass(),
        ];
    }
    public function chatgpt_run_thread($nonce = null, $model = null, $filter = null, $return_data = false, $instructions = null)
    {
        if ($this->getThreadId() === null) {
            // No thread to work with
            return null;
        }

        global $kissai_db;

        // Prepare the URL by replacing the placeholder with the actual thread_id
        $url = strtr(OpenAI_Endpoints::runs, ['{thread_id}' => $this->thread_id]);

        kissai_error_log("chatgpt_run_thread: URL: " . $url . " | assistant_id: " . $this->assistant_id);
        // Prepare headers
        $headers = $this->get_header();
        $headerString = "";
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }

        // Prepare the body
        $body = [
            'assistant_id' => $this->assistant_id,
            'stream'       => true,
            'instructions' => $instructions,
        ];

        if ($model !== null) {
            $body['model'] = $model;
        }

        // Convert the body to JSON
        $json_body = json_encode($body);

        // Verify the filter
        if (!is_null($filter) && !is_array($filter)) {
            kissai_error_log('Error: Filter should be an array of strings. None provided.');
            $filter = null;
        }

        // We'll store all collected data here if $return_data === true
        $eventDataCollection = [];

        /**
         * Internal function to handle SSE event data
         */
        $handleEventData = function (
            $nonce,
            $eventName,
            $eventData,
            &$sequenceNumber,
            $return_data
        ) use ($kissai_db, &$eventDataCollection, $filter) {

            // Only store or return if the event is in the allowed filter or if no filter is set
            if (is_null($filter) || in_array($eventName, $filter)) {
                // Possibly store to DB
                if ($nonce !== null) {
                    $kissai_db->store_event_data($nonce, $eventName, $eventData, $sequenceNumber++);
                }
                // Possibly return data to caller
                if ($return_data) {
                    $eventDataCollection[] = [$eventName => $eventData];
                }
            }

            // For function call event
            if ($eventName === 'thread.run.requires_action') {
                $eventDataCollection[] = [$eventName => $eventData];
                $this->process_openai_function_calls(json_decode($eventData, true));
            }

            // For completion
            if ($eventName === 'thread.run.completed') {
                $eventDataCollection[] = [$eventName => $eventData];
            }
        };

        // Create a context for SSE
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => $headerString . "Content-Type: application/json\r\n",
                'content' => $json_body,
            ]
        ]);

        // -----------------------------
        //  STREAM OPEN WITH RETRIES
        // -----------------------------
        $maxRetries   = 3;    // number of attempts
        $retryDelay   = 1;    // seconds between attempts
        $attempt      = 0;
        $responseStream = false;

        while ($attempt < $maxRetries) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
            $responseStream = fopen($url, 'r', false, $context);
            if ($responseStream !== false) {
                // Successfully opened
                break;
            }
            $attempt++;
            if ($attempt < $maxRetries) {
                // Optionally log or debug
                kissai_error_log("Retrying stream open (attempt $attempt) for thread ({$this->thread_id})...");
                sleep($retryDelay);
            }
        }

        if (!$responseStream) {
            kissai_error_log('Error: Unable to open stream for thread (' . $this->thread_id . ') after retries.');
            return null;
        }

        $sequenceNumber = 0;
        $eventData      = '';
        $eventName      = '';

        // Start reading the SSE stream
        while (!feof($responseStream)) {
            $line = fgets($responseStream);

            // Check if line is an event name
            if (strpos($line, 'event:') === 0) {
                // If there was a previous event accumulated, handle it
                if (!empty($eventName) && !empty($eventData)) {
                    $handleEventData($nonce, $eventName, $eventData, $sequenceNumber, $return_data);
                    $eventData = '';
                }
                $eventName = trim(substr($line, strlen('event:')));
            } elseif (strpos($line, 'data:') === 0) {
                $eventData .= trim(substr($line, strlen('data:'))) . "\n";
            } elseif (trim($line) === '') {
                // End of an event block
                if (!empty($eventName) && !empty($eventData)) {
                    $handleEventData($nonce, $eventName, $eventData, $sequenceNumber, $return_data);
                    $eventName = '';
                    $eventData = '';
                }
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($responseStream);

        // Return collected data if requested
        return $return_data ? $eventDataCollection : null;
    }
    public function chatgpt_query($assistant_id, $prompt, $event_filter = null, $utility_message = false, $model = null, $thread_id = null, $instructions = null) {
        $assistant = $this->set_assistant($assistant_id);
        if ($assistant) {
            if ($thread_id === null) {
                if (!$utility_message) {
                    $this->initialize_thread($assistant_id, true);
                }
            }
            else {
                $this->thread_id = $thread_id;
            }
            $nonce = $this->chatgpt_save_nonce();
            $response = $this->chatgpt_create_message($prompt, $nonce, $utility_message);
            if (isset($response['id'])) {
                $result = $this->chatgpt_run_thread($nonce, $model, $event_filter, true, $instructions);
                $completeMessage = array_filter($result, function ($item) {
                    return array_key_exists('thread.message.completed', $item);
                });
                if (!empty($completeMessage)) {
                    try {
                        $message = reset($completeMessage)['thread.message.completed'];
                        $this->save_message_and_token_count($nonce, self::MESSAGE_TYPE_RECEIVED, $message, $utility_message);
                    }
                    catch (Exception $e) {
                        kissai_error_log('Error: ' . $e->getMessage());
                    }
                }
                $run_complete = array_filter($result, function ($item) {
                    return array_key_exists('thread.run.completed', $item);
                });
                if (!empty($run_complete)) {
                    try {
                        $message = reset($run_complete)['thread.run.completed'];
                        $this->update_token_usage($nonce, $message, $utility_message);
                    }
                    catch (Exception $e) {
                        kissai_error_log('Error: ' . $e->getMessage());
                    }
                }
                return $result;
            }
            else
                kissai_error_log("Error : ChatGPT response format error -" . json_encode($response));
        }
        return null;
    }

    private function fallbackToPrevious($previous, $output = null, $errorMessage = null)
    {
        if (!empty($errorMessage)) {
            kissai_error_log($errorMessage);
        }

        // If $previous is non-empty, return it. Otherwise return an empty string or whatever default makes sense.
        return !empty($previous) ? $previous : $output;
    }

    public function query_suggested_questions($assistant_id = null, $thread_id = null)
    {
        if ($assistant_id === null) {
            $assistant_id = $this->get_assistant_id();
        }

        global $kissai_db;
        $previous = $kissai_db->get_suggested_questions($assistant_id, OpenAI_API::MAX_SUGGESTED_QUESTIONS);

        // We'll store our final output in $output
        $output = "";

        try {
            // 1) Call your chatgpt_query function as before
            $result = $this->chatgpt_query(
                $assistant_id,
                OpenAI_API::AI_SUGESSTED_QUESTIONS_PROMPT,
                ['thread.message.completed'],
                true,
                null,
                $thread_id
            );

        } catch (Exception $ex) {
            return $this->fallbackToPrevious($previous, $output, "Error: query_suggested_questions chatgpt_query call failed assistant_id=" . $assistant_id . " (Exception: " . $ex->getMessage() . ")");
        }

        if (empty($result)) {
            kissai_error_log("Error: query_suggested_questions received empty or null \$result for assistant_id=" . $assistant_id);
            // FALLBACK: retrieve the previously stored suggestions (if any)
            return $this->fallbackToPrevious($previous, $output, "Info: Using fallback for assistant_id=$assistant_id because current query returned empty.");
        }

        // 2) Filter the result
        try {
            $completeMessage = array_filter($result, function ($item) {
                return array_key_exists('thread.message.completed', $item);
            });
        } catch (Exception $e) {
            // FALLBACK
            return $this->fallbackToPrevious($previous, $output, "Error: Exception while filtering result for assistant_id=" . $assistant_id .
                "; Message=" . $e->getMessage());
        }

        if (empty($completeMessage)) {
            // FALLBACK
            return $this->fallbackToPrevious($previous, $output, "Warning: No 'thread.message.completed' found for assistant_id=" . $assistant_id);
        }

        // Extract the first complete message
        $message = reset($completeMessage)['thread.message.completed'];
        if (empty($message)) {
            // FALLBACK
            return $this->fallbackToPrevious($previous, $output, "Warning: 'thread.message.completed' was empty for assistant_id=" . $assistant_id);
        }

        // 3) Decode the JSON and parse out the suggestions
        try {
            $messageData = json_decode($message);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // FALLBACK
                return $this->fallbackToPrevious($previous, $output, "Error: Unable to JSON-decode 'thread.message.completed': " . json_last_error_msg() .
                    " (message: " . $message . ")");
            }

            if (!isset($messageData->content[0]->text->value) || empty($messageData->content[0]->text->value)) {
                // FALLBACK
                return $this->fallbackToPrevious($previous, $output, "Warning: Could not find 'content[0]->text->value' in the decoded message for assistant_id="
                    . $assistant_id . "; Full messageData: " . json_encode($messageData));
            }

            // 4) Extract final suggestions
            $extracted = KissAi_Widget::extractJsonContent($messageData->content[0]->text->value);
            if (empty($extracted)) {
                // If extractJsonContent returns nothing
                kissai_error_log("Warning: extractJsonContent returned empty data: " . json_encode($result));

                // FALLBACK
                $output = $this->fallbackToPrevious($previous, $output, "Info: Using fallback for assistant_id=$assistant_id because extractJsonContent was empty.") ?? $output;
            } else {
                // We have valid suggestions; store them for next time
                $output = $extracted;
                if (!$kissai_db->save_suggested_questions($assistant_id, $extracted)) {
                    $output = $previous;
                }
            }
        } catch (Exception $e) {
            // FALLBACK
            $output = $this->fallbackToPrevious($previous, $output, "Error: Failed to parse or process message for assistant_id=" . $assistant_id .
                "; Exception=" . $e->getMessage() .
                "; Original message=" . $message);
        }

        if (is_array($output)) {
            $output = json_encode($output);
        }
        $json_output = json_decode($output);
        if (empty($json_output) || !is_array($json_output) || count($json_output) < 3) {
            $output = $previous;
        }

        return $output;
    }


    /**
     * Mark a single file ID as deleted for one minute using a transient.
     *
     * @param string $file_id The file ID to mark as deleted.
     */
    public static function add_deleted_openai_file_id( $file_id ) {
        // Each file ID gets its own transient key.
        // The transient will store 'true', expiring in 60 seconds.
        set_transient( "deleted_openai_file_id_{$file_id}", true, 60 );
    }

    /**
     * Check if a file ID is still marked as deleted (i.e., within the 1-minute window).
     *
     * @param string $file_id The file ID to check.
     * @return bool True if the file is marked as deleted and hasn't expired yet; false otherwise.
     */
    public static function is_deleted_openai_file_id( $file_id ) {
        // If the transient is still valid, this returns 'true'; otherwise it returns false/empty.
        return ( get_transient( "deleted_openai_file_id_{$file_id}" ) === true );
    }

}