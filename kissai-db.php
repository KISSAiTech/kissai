<?php
/*
 * We use custom database tables and direct queries intentionally.
 * Caching isn't applied because the data changes frequently.
 *
 * phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
 */

class KissAi_DB_Tables {
    public const DB_VERSION = '1.5.9';
    public $table_prefix;
    public $threads;
    public $messages;
    public $assistants;
    public $assistant_settings;
    public $assistant_knowledges;
    public $run_thread;
    public $api_call_nonce;
    public $api_call_run_temp_data;
    public $api_log;
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'kissai_';
        $this->threads = $this->table_prefix . 'threads';
        $this->messages = $this->table_prefix . 'messages';
        $this->assistants = $this->table_prefix . 'assistants';
        $this->assistant_settings = $this->table_prefix . 'assistant_settings';
        $this->assistant_knowledges = $this->table_prefix . 'assistant_knowledges';
        $this->run_thread = $this->table_prefix . 'run_thread';
        $this->api_call_nonce = $this->table_prefix . 'api_call_nonce';
        $this->api_call_run_temp_data = $this->table_prefix . 'api_call_run_temp_data';
        $this->api_log = $this->table_prefix . 'api_log';
    }
}

class KissAi_DB {
    public const default_thread_list_page_size = 10;
    public const max_saved_suggested_questions = 50;
    public $table_names;
    public function __construct() {
        $this->table_names = new KissAi_DB_Tables();
    }
    public function create_plugin_database_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE `{$this->table_names->threads}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `session_id` varchar(255) NOT NULL,
            `assistant_id` varchar(36) NOT NULL,
            `thread_id` varchar(255) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->messages}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `call_nonce` varchar(36) NOT NULL,
            `assistant_id` varchar(36) NOT NULL,
            `thread_id` varchar(255) NULL,
            `message_id` varchar(255),
            `message_type` enum('sent', 'repeated', 'received') NOT NULL,
            `message_content` text,
            `message_data` text,
            `utility_message` boolean,
            `token_count` mediumint NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `thread_id` (`thread_id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->assistants}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `assistant_id` varchar(36) NOT NULL,
            `knowledges` int,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->assistant_settings}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `assistant_id` varchar(36) NOT NULL,
            `setting_name` varchar(255) NOT NULL,
            `setting_value` text,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->assistant_knowledges}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `assistant_id` varchar(36) NOT NULL,
            `file_id` varchar(36) NOT NULL,
            `name` varchar(255) NOT NULL,
            `path` varchar(255) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->run_thread}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `thread_id` varchar(255) NOT NULL,
            `message_id` bigint(20),
            `run_id` varchar(255),
            `step_id` varchar(255),
            `data` text,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `thread_id` (`thread_id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->api_call_nonce}` (
            `guid` varchar(36) NOT NULL,
            `session_id` varchar(255) NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`guid`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->api_call_run_temp_data}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `call_nonce` varchar(36) NOT NULL,
            `event` varchar(255),
            `message_id` varchar(255),
            `sequence_id` int,
            `data` text,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE `{$this->table_names->api_log}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_server` varchar(255) NOT NULL,
            `session_id` varchar(255) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `endpoint` varchar(512),
            `event` varchar(255),
            `message` varchar(512),
            `data` text,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    public static function is_db_up_to_date() {
        $installed_ver = get_kissai_option('db_version');
        if ($installed_ver == KissAi_DB_Tables::DB_VERSION) {
            return true;
        }
        return false;
    }

    public static function update_success_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <h3>KissAi</h3>
            <p>KissAi database update completed successfully.</p>
        </div>
        <?php
    }

    public static function update_db() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        global $kissai_db;
        $kissai_db->create_plugin_database_tables();
        $installed_ver = get_kissai_option('db_version');
        if ($installed_ver < '1.5') {
            $kissai_db->update_all_messages_assistant_ids();
        }
        if ($installed_ver < '1.5.2') {
            $kissai_db->update_all_message_repeated();
        }
        if ($installed_ver < '1.5.9') {
            $openai_api_key = get_option('openai_api_key');
            update_kissai_option('openai_api_key', $openai_api_key);
            delete_option('openai_api_key');
            $api_key_type = get_option('api_key_type');
            update_kissai_option('api_key_type', $api_key_type);
            delete_option('api_key_type');
        }
        update_kissai_option('db_version', KissAi_DB_Tables::DB_VERSION);

        add_action('admin_notices', [KissAi_DB::class, 'update_success_notice']);
    }

    public static function update_db_notice($class = null) {
        if ($class == null)
            $class = "notice notice-warning";
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $update_url = add_query_arg(array(
            'kissai_db_update' => 'true',
            'kissai_nonce' => wp_create_nonce('kissai_db_update_nonce')
        ), $current_url);
        ?>
        <div class="<?php echo esc_attr($class) ?>">
            <h3>KissAi</h3>
            <p>KissAi database update required. Please click <a href="<?php echo esc_url($update_url); ?>">here</a> to perform the update.</p>
        </div>
        <?php
    }

    public static function check_db_version() {
        if (!KissAi_DB::is_db_up_to_date()) {
            add_action('admin_notices', [KissAi_DB::class, 'update_db_notice']);
        }
    }

    public static function handle_db_update() {
        if (isset($_GET['kissai_db_update']) && $_GET['kissai_db_update'] === 'true') {
            // Security checks: Nonce verification and user capability check
            if (!isset($_GET['kissai_nonce']) || !wp_verify_nonce($_GET['kissai_nonce'], 'kissai_db_update_nonce') || !current_user_can('manage_options')) {
                wp_die('You do not have permission to perform this action.');
            }

            if (!KissAi_DB::is_db_up_to_date()) {
                // Perform the actual database update
                self::update_db();
            }

            add_action('admin_notices', [KissAi_DB::class, 'update_success_notice']);
        }
    }

    public function reset_plugin_database_tables() {
        global $wpdb;

        // Prepare a query to drop each table
        $tables_to_drop = [
            $this->table_names->threads,
            $this->table_names->messages,
            $this->table_names->run_thread,
            $this->table_names->api_call_nonce,
            $this->table_names->api_call_run_temp_data,
            $this->table_names->api_log
        ];

        // Loop through the table names and drop each one
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table;");
        }
        $this->create_plugin_database_tables();
    }

    public function update_thread_assistant_id($thread_id, $assistant_id) {
        global $wpdb;
        $table_name = $this->table_names->threads;
        $saved_assistant_id = $wpdb->get_var($wpdb->prepare(
            "SELECT assistant_id FROM $table_name WHERE thread_id = %s",
            $thread_id
        ));
        if (empty($saved_assistant_id)) {
            $data = array(
                'assistant_id' => $assistant_id,
            );
            $where = array(
                'thread_id' => $thread_id
            );
            $updated = $wpdb->update($table_name, $data, $where);
            return $updated;
        }
        return false;
    }

    public function update_assistant_knowledge_count($assistant_id, $count) {
        global $wpdb;
        $table_name = $this->table_names->assistants;

        // Use $wpdb->update to update the knowledge count for the assistant
        $result = $wpdb->update(
            $table_name,
            array( 'knowledges' => intval($count) ), // Data to update
            array( 'assistant_id' => $assistant_id ),  // Where clause
            array( '%d' ),  // Data format for the 'knowledges' field
            array( '%s' )   // Data format for the 'id' field in the where clause
        );

        // Check if update was successful
        if ($result === false) {
            kissai_error_log("Failed to update knowledge count for assistant ID: $assistant_id");
        }
    }

    public static function get_current_api_key_type() {
        $api_key_type = get_kissai_option('api_key_type', 'kissai');
        $openai_api_key = get_kissai_option('openai_api_key');
        if ($api_key_type === 'openai' && empty($openai_api_key)) {
            return 'openai';
        }
        return 'kissai';
    }
    public function get_assistant_knowledge_count($assistant_id) {
        global $wpdb;
        $table_name = $this->table_names->assistants;

        // Execute the query and fetch the result
        $knowledge_count = $wpdb->get_var($wpdb->prepare(
            "SELECT knowledges FROM $table_name WHERE assistant_id = %s",
            $assistant_id
        ));

        // Return the knowledge count (or 0 if no result found)
        return is_null($knowledge_count) ? 0 : intval($knowledge_count);
    }


    public function match_thread_assistant_id($thread_id) {
        global $wpdb;
        $threads_table = $this->table_names->threads;
        $messages_table = $this->table_names->messages;

        // Fetch the message_data from messages table
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT message_data FROM {$messages_table} WHERE thread_id = %s",
            $thread_id
        ));

        $updated = false;
        foreach ($messages as $row) {
            if ($row->message_data) {
                // Decode the JSON message_data
                $data = json_decode($row->message_data, true);

                if (isset($data['assistant_id'])) {
                    // Extract assistant_id from message_data
                    $assistant_id = $data['assistant_id'];

                    // Update the assistant_id in the threads table
                    $updated = $wpdb->update(
                        $threads_table,
                        ['assistant_id' => $assistant_id],  // New values
                        ['thread_id' => $thread_id]  // Where conditions
                    );

                    if (false !== $updated) {
                        $updated = true;
                        break;
                    } else {
                        kissai_error_log("match_thread_assistant_id({$thread_id}) - Failed to update Assistant ID.");
                    }
                }
            }
        }
        return $updated;
    }

    public function match_message_assistant_id($id) {
        global $wpdb;
        $messages_table = $this->table_names->messages;

        // Fetch the message_data from messages table
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, message_data FROM {$messages_table} WHERE id = %s",
            $id
        ));

        $updated = false;
        foreach ($messages as $row) {
            if ($row->message_data) {
                // Decode the JSON message_data
                $data = json_decode($row->message_data, true);

                if (isset($data['assistant_id'])) {
                    // Extract assistant_id from message_data
                    $assistant_id = $data['assistant_id'];

                    // Update the assistant_id in the threads table
                    $updated = $wpdb->update(
                        $messages_table,
                        ['assistant_id' => $assistant_id],  // New values
                        ['id' => $row->id]  // Where conditions
                    );

                    if (false !== $updated) {
                        $updated = true;
                        break;
                    } else {
                        kissai_error_log("match_message_assistant_id({$id}) - Failed to update Assistant ID.");
                    }
                }
            }
        }
        return $updated;
    }


    public function update_all_thread_assistant_ids() {
        global $wpdb;
        $messages_table = $this->table_names->messages;

        // Fetch all unique thread IDs from the messages table
        $thread_ids = $wpdb->get_col("SELECT DISTINCT thread_id FROM {$messages_table}");

        if (!empty($thread_ids)) {
            foreach ($thread_ids as $thread_id) {
                // Call the match_message_assistant_id for each thread ID
                $result = $this->match_thread_assistant_id($thread_id);
                if (!$result) {
                    kissai_error_log("update_all_thread_assistant_ids() - Failed to update assistant ID for thread ID: {$thread_id}");
                }
            }
            return true;
        } else {
            return kissai_error_log("update_all_thread_assistant_ids() - No threads found in messages table.");
        }
        return false;
    }

    public function update_all_messages_assistant_ids() {
        global $wpdb;
        $messages_table = $this->table_names->messages;

        // Fetch all unique thread IDs from the messages table
        $messages = $wpdb->get_results("SELECT id, message_data FROM {$messages_table}");

        if (!empty($messages)) {
            foreach ($messages as $row) {
                // Call the match_message_assistant_id for each thread ID
                $data = json_decode($row->message_data, true);

                if (isset($data['assistant_id'])) {
                    // Extract assistant_id from message_data
                    $assistant_id = $data['assistant_id'];

                    // Update the assistant_id in the messages table
                    $updated = $wpdb->update(
                        $messages_table,
                        ['assistant_id' => $assistant_id],  // New values
                        ['id' => $row->id]  // Where conditions
                    );

                    if (false === $updated) {
                        kissai_error_log("update_all_messages_assistant_ids() - Failed to update Assistant ID of message ID{$row->id}.");
                    }
                }
            }
            // Update the empty assistant_id of records that have the same call_nonce with ones that are just updated.
            $wpdb->query($wpdb->prepare(
                "UPDATE {$messages_table} m1
                    JOIN (
                        SELECT call_nonce, assistant_id
                        FROM {$messages_table}
                        WHERE assistant_id <> ''
                    ) m2 ON m1.call_nonce = m2.call_nonce
                    SET m1.assistant_id = m2.assistant_id
                    WHERE m1.assistant_id = '';
                ", null));
            return true;
        } else {
            return kissai_error_log("update_all_messages_assistant_ids() - No messages found in messages table.");
        }
        return false;
    }

    public function update_all_messages_api_server($api_server) {
        global $wpdb;
        $messages_table = $this->table_names->messages;
        $updated = $wpdb->query($wpdb->prepare("UPDATE {$messages_table} SET api_server = %s WHERE api_server = '' or api_server IS NULL", $api_server));
        return $updated;
    }

    public function update_all_message_repeated() {
        global $wpdb, $kissai_db;
        $table_name = $kissai_db->table_names->messages;
    
        // Prepare and run the update query
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} AS received_msg
             JOIN {$table_name} AS sent_msg
             ON received_msg.call_nonce = sent_msg.call_nonce
             AND received_msg.message_content = sent_msg.message_content
             SET received_msg.message_type = %s
             WHERE received_msg.message_type = %s
             AND sent_msg.message_type = %s",
             OpenAI_API::MESSAGE_TYPE_REPEATED, // Set to repeated
             OpenAI_API::MESSAGE_TYPE_RECEIVED, // Target only received messages
             OpenAI_API::MESSAGE_TYPE_SENT      // Look for sent messages
        ));
    
        // Check for errors
        if (false === $result) {
            kissai_error_log("Error - update failed: Table: {$table_name} | WPDB Error: " . $wpdb->last_error);
            return false;
        }
    
        return true; // Return true on success
    }

    public function is_existing_message($call_nonce, $message_type, $message_content) {
        global $wpdb;
        $table_name = $this->table_names->messages;
        $existing_message = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$table_name}
             WHERE call_nonce = %s
             AND message_type = %s
             AND message_content = %s",
            $call_nonce, $message_type, $message_content
        ));
        if ($existing_message > 0) {
            return true;
        }
        return false;
    }

    public function get_existing_message($call_nonce, $message_type, $message_content) {
        global $wpdb;
        $table_name = $this->table_names->messages;
        $message_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id
             FROM {$table_name}
             WHERE call_nonce = %s
             AND message_type = %s LIMIT 1",
            $call_nonce, $message_type
        ));
        if ($message_id === null) {
            return 0;
        }
        return $message_id;
    }

    public function save_message($assistant_id, $thread_id, $guid, $message, &$message_type, $utility_message = false, &$record_id = null, &$message_content = null) {
        global $wpdb;
        $table_name = $this->table_names->messages;
    
        // Validate message_type
        if (!in_array($message_type, [OpenAI_API::MESSAGE_TYPE_SENT, OpenAI_API::MESSAGE_TYPE_REPEATED, OpenAI_API::MESSAGE_TYPE_RECEIVED])) {
            kissai_error_log("Error - invalid message type: KissAi_DB::save_message(): Message type: {$message_type}");
            return false;
        }
    
        // Ensure $message is a JSON string for message_data field
        $message_json = is_string($message) ? $message : json_encode($message);
    
        // Decode the JSON message for processing if not already an array
        $message_array = is_array($message) ? $message : json_decode($message, true);

        $timestamp = $message_array['created_at'] ?? time();
        $created_at = KissAi_Base_Widget::convertUnixToUTC($timestamp);
        if ($message_type === OpenAI_API::MESSAGE_TYPE_RECEIVED) {
            // Check if the necessary fields are present in the message_array
            if (!isset($message_array['content'][0]['type'], $message_array['content'][0]['text']['value'])) {
                kissai_error_log("Error - message does not have required structure: - {$message_json}");
                return false;
            }
    
            // Extract the message text
            $message_text = $message_array['content'][0]['text']['value'];
            if (isset($message_array['assistant_id'])) {
                $assistant_id = $message_array['assistant_id'];
            }
        } else if ($message_type === OpenAI_API::MESSAGE_TYPE_SENT && isset($message_array['body'])) {
            $message_array['id'] = null;
            $message_body = json_decode($message_array['body']);
            $message_text = $message_body->content;
        }
    
        // Update thread's assistant ID
        if (!empty($thread_id))
            $this->update_thread_assistant_id($thread_id, $assistant_id);
    
        // Check if a message with the same call_nonce, message_type = 'sent', and message_content already exists
        $existing_message = $this->is_existing_message($guid, OpenAI_API::MESSAGE_TYPE_SENT, $message_text);
    
        if ($existing_message) {
            // Change message_type to be OpenAI_API::MESSAGE_TYPE_REPEATED
            $message_type = OpenAI_API::MESSAGE_TYPE_REPEATED;
        }
    
        // Prepare data for insertion
        $data = array(
            'call_nonce' => $guid,
            'assistant_id' => $assistant_id,
            'thread_id' => $thread_id === null ? null : (isset($message_array['thread_id']) ? $message_array['thread_id'] : $thread_id), // Use thread_id from the message if available
            'message_id' => $message_array['id'],
            'message_type' => $message_type,
            'message_content' => $message_text,
            'message_data' => $message_json, // Save the original JSON response
            'utility_message' => $utility_message,
            'created_at' => $created_at, // WP function to get current time in MySQL format
        );

        if ($message_content !== null) {
            $message_content = $message_text;
        }
        // Insert the message into the database
        $result = $wpdb->insert($table_name, $data);
    
        // Check for errors
        if (false === $result) {
            $data_json = json_encode($data);
            kissai_error_log("Error - insertion failed: Table: {$table_name} | Data: {$data_json} | WPDB Error: " . $wpdb->last_error);
            return false;
        }
        if ($record_id !== null) {
            $record_id = $wpdb->insert_id;
        }

        return $message_array; // Message saved successfully
    }

    public function update_token_count($message_record_id, $token_count) {
        global $wpdb;
        $table_name = $this->table_names->messages;
        $result = $wpdb->update($table_name, ['token_count' => $token_count],
            ['id' => $message_record_id],
            ['%d'],
            ['%s']
        );
        return $result;
    }
    /**
     * Get a single row with caching support.
     *
     * @param string $cache_group - Cache group name.
     * @param string $cache_key Unique key for caching.
     * @param string $query SQL query with placeholders.
     * @param array $params Parameters for the prepared query.
     * @param int $cache_duration Cache expiration in seconds.
     * @return object|null
     */
    public function cached_get_row($cache_key, $cache_group, $query, $params = [], $cache_duration = HOUR_IN_SECONDS) {
        global $wpdb;

        // Try fetching from cache first
        $result = wp_cache_get($cache_key, $cache_group);

        if ($result === false) {
            // Cache miss: Perform the database query
            $result = $wpdb->get_row($wpdb->prepare($query, ...$params));

            // Cache the result
            wp_cache_set($cache_key, $result, $cache_group, $cache_duration);
        }

        return $result;
    }
    public function get_event_count($nonce, $eventName) {
        global $wpdb, $kissai_db;

        $table_name = $kissai_db->table_names->api_call_run_temp_data;
        $eventCnt = 0;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as cnt FROM {$table_name} WHERE call_nonce = %s AND `event` = %s",
            $nonce, $eventName
        ));
        if ($result !== false) {
            $eventCnt = intval($result->cnt);
        }
        return $eventCnt;
    }

    public function store_event_data($nonce, $eventName, $eventData, $sequenceNumber) {
        global $wpdb, $kissai_db;

        $eventJsonData = json_decode($eventData);
        if ($eventName === 'thread.message.delta' || $eventName === 'thread.message.completed') {
            $table_name = $kissai_db->table_names->api_call_run_temp_data;
            $data = array(
                'created_at' => current_time('mysql', 1), // WP function to get current time in MySQL format
                'call_nonce' => $nonce,
                'event' => $eventName,
                'message_id' => $eventJsonData->id,
                'sequence_id' => $sequenceNumber,
                'data' => $eventData
            );
            $result = $wpdb->insert($table_name, $data);

            // Check for errors
            if (false === $result) {
                $data_json = json_encode($data);
                kissai_error_log("Error - insertion failed: Table: {$table_name} | Data: {$data_json} | WPDB Error: " . $wpdb->last_error);
                return false;
            }
        }
    }

    public function get_event_max_seq($nonce, $eventName) {
        global $wpdb, $kissai_db;

        $table_name = $kissai_db->table_names->api_call_run_temp_data;
        $max_seq = 0;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT MAX(sequence_id) as max_seq FROM {$table_name} WHERE call_nonce = %s AND `event` = %s",
            $nonce, $eventName
        ));
        if ($result !== false) {
            $max_seq = intval($result->max_seq);
        }
        return $max_seq;
    }

    public function get_event_data($nonce, $eventName, $sequenceNumber = null) {
        global $wpdb, $kissai_db;

        $table_name = $kissai_db->table_names->api_call_run_temp_data;
        $max_seq = $this->get_event_max_seq($nonce, $eventName);
        $result = null;
        if ($max_seq > 0 && $sequenceNumber === null) {
            $result = $wpdb->get_results($wpdb->prepare(
                "SELECT data, sequence_id, {$max_seq} as max_seq FROM {$table_name} WHERE call_nonce = %s AND `event` = %s",
                $nonce, $eventName
            ));
        }
        else {
            while ($result == null && $max_seq >= $sequenceNumber ) {
                $result = $wpdb->get_row($wpdb->prepare(
                    "SELECT data, sequence_id, {$max_seq} as max_seq FROM {$table_name} WHERE call_nonce = %s AND `event` = %s AND sequence_id = %d",
                    $nonce, $eventName, $sequenceNumber
                ));
                $sequenceNumber++;
            }
        }

        // Check for errors
        if (false === $result) {
            kissai_error_log("Error - select error Table: {$table_name} | call_nonce: {$nonce} | event: {$eventName} | sequence_id: {$sequenceNumber} | WPDB Error: " . $wpdb->last_error);
            return false;
        }
        return $result;
    }

    public function remove_event_data($nonce, $eventName) {
        global $wpdb, $kissai_db;

        $table_name = $kissai_db->table_names->api_call_run_temp_data;
        $result = null;
        $result = $wpdb->delete(
            $table_name,
            ['call_nonce' => $nonce, 'event' => $eventName], // WHERE
            ['%s', '%s']  // WHERE format
        );

        // Check for errors
        if (false === $result) {
            kissai_error_log("Error - select error Table: {$table_name} | call_nonce: {$nonce} | event: {$eventName} | WPDB Error: " . $wpdb->last_error);
            return false;
        }
        return $result;
    }

    public function save_assistant($assistant) {
        global $wpdb;
        $table_name = $this->table_names->assistants;
        if (isset($assistant['id'])) {
            $assistant_id = $assistant['assistant_id'];
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE assistant_id = %s", $assistant_id));
            if ($id === null) {
                $wpdb->insert($table_name, array(
                    'api_server' => $assistant['assistant_type'],
                    'assistant_id' => $assistant_id,
                    'created_at' => $assistant['created_at']
                ));
            }
        }
    }

    public function delete_assistant($assistant_id) {
        global $wpdb;
        $table_name = $this->table_names->assistants;
        if (isset($assistant['id'])) {
            $wpdb->delete($table_name,
                ['assistant_id' => $assistant_id], // WHERE
                ['%s']  // WHERE format
            );
        }
    }

    public function save_assistant_setting($assistant_id, $setting_name, $setting_value, $api_server = 'openai') {
        global $wpdb; // Global WordPress database access
        $table_name = $this->table_names->assistant_settings; // Assuming you have prefixed your tables

        // Prepare the data for insertion or update
        $data = array(
            'api_server' => $api_server, // Assuming you have a way to determine or get the API server
            'assistant_id' => $assistant_id,
            'setting_name' => $setting_name,
            'setting_value' => $setting_value
        );

        // Check if the setting already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE api_server = %s AND assistant_id = %s AND setting_name = %s",
            $api_server, $assistant_id, $setting_name
        ));

        if ($exists) {
            // Update existing row
            $where = array(
                'api_server' => $api_server,
                'assistant_id' => $assistant_id,
                'setting_name' => $setting_name
            );
            $updated = $wpdb->update($table_name, $data, $where);
            return $updated;
        } else {
            // Insert new row
            $inserted = $wpdb->insert($table_name, $data);
            if ($inserted) {
                return $wpdb->insert_id;
            } else {
                return false;
            }
        }
    }

    /**
     * Retrieves a specific setting for an assistant.
     *
     * @param string $assistant_id The unique identifier for the assistant.
     * @param string $setting_name The name of the setting to retrieve.
     * @param string $api_server Optional. The API server associated with the setting. Defaults to 'openai'.
     * @return string|null The value of the setting if found, or null if not.
     */
    public function get_assistant_setting($assistant_id, $setting_name, $api_server = 'openai') {
        global $wpdb;
        $table_name = $this->table_names->assistant_settings; // Assuming you have prefixed your tables
        $setting_value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_name WHERE api_server = %s AND assistant_id = %s AND setting_name = %s",
            $api_server, $assistant_id, $setting_name
        ));
        return $setting_value;
    }

    /**
     * Finds the ID of a knowledge entry based on the assistant ID and the file name.
     *
     * @param string $assistant_id The assistant ID associated with the knowledge entry.
     * @param string $name The name of the file to search for.
     * @return int|null Returns the ID of the knowledge entry if found, null otherwise.
     */
    public function find_knowledge_id_by_assistant_and_name($assistant_id, $name) {
        global $wpdb;

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_names->assistant_knowledges} WHERE assistant_id = %s AND name = %s",
            $assistant_id,
            $name
        ));

        if ($id) {
            return (int) $id;  // Return the ID as an integer if found
        } else {
            return null;  // Return null if no matching record is found
        }
    }

    /**
     * Adds a new knowledge file entry for an assistant, where file ID can be optional.
     *
     * @param string $assistant_id The unique identifier for the assistant.
     * @param string|null $file_id The unique identifier for the file, can be null.
     * @param string $name The name of the knowledge file.
     * @param string $path The path where the knowledge file is stored.
     * @return int|bool The newly created entry's ID on success, or false on failure.
     */
    public function add_knowledge($assistant_id, $name, $path, $file_id) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_names->assistant_knowledges,
            [
                'assistant_id' => $assistant_id,
                'file_id' => $file_id,
                'name' => $name,
                'path' => $path,
                'created_at' => current_time('mysql', 1)  // Use WordPress's current_time function for proper time handling
            ],
            [
                '%s',  // assistant_id is a string
                '%s',  // file_id is a string but can be null
                '%s',  // name is a string
                '%s',  // path is a string
                '%s'   // created_at is a string (datetime)
            ]
        );

        if ($inserted) {
            return $wpdb->insert_id;  // Return the last inserted ID
        } else {
            // Optionally log the error
            kissai_error_log('Failed to insert new knowledge entry: ' . $wpdb->last_error);
            return false;
        }
    }

    /**
     * Updates the file ID of a knowledge entry based on the assistant ID and the file name, or inserts a new record if no existing record is found.
     * Returns the ID of the updated or inserted row, or false on failure.
     *
     * @param string $assistant_id The assistant ID associated with the knowledge entry.
     * @param string $name The name of the file.
     * @param string $file_id The new file ID to update or insert.
     * @return mixed Returns the ID of the updated/inserted row on success, false on failure.
     */
    public function update_knowledge_file_id($assistant_id, $name, $file_id) {
        global $wpdb;

        // Check if an entry exists
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_names->assistant_knowledges} WHERE assistant_id = %s AND name = %s",
            $assistant_id, $name
        ));

        if ($existing_id) {
            // Entry exists, update it
            $updated = $wpdb->update(
                $this->table_names->assistant_knowledges,
                ['file_id' => $file_id],  // New values
                ['id' => $existing_id]   // Where conditions
            );

            if ($updated !== false) {
                return $existing_id;  // Return the existing ID
            } else {
                kissai_error_log("update_knowledge_file_id({$assistant_id}, {$name}) - Failed to update file ID.");
                return false;  // Return false if the update operation failed
            }
        } else {
            // No existing entry, insert new one
            $inserted = $wpdb->insert(
                $this->table_names->assistant_knowledges,
                [
                    'assistant_id' => $assistant_id,
                    'name' => $name,
                    'file_id' => $file_id
                ]
            );
            if ($inserted) {
                return $wpdb->insert_id;  // Return the ID of the newly inserted row
            } else {
                kissai_error_log("update_knowledge_file_id({$assistant_id}, {$name}) - Failed to insert new file ID.");
                return false;  // Return false if the insert failed
            }
        }
    }

    /**
     * Updates the path of a knowledge entry based on the assistant ID and the file name, or inserts a new record if no existing record is found.
     * Returns the ID of the updated or inserted row, or false on failure.
     *
     * @param string $assistant_id The assistant ID associated with the knowledge entry.
     * @param string $name The name of the knowledge entry.
     * @param string $path The new path to update or insert.
     * @return mixed Returns the ID of the updated/inserted row on success, false on failure.
     */
    public function update_knowledge_path($assistant_id, $name, $path) {
        global $wpdb;

        // Check if an entry exists
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_names->assistant_knowledges} WHERE assistant_id = %s AND name = %s",
            $assistant_id, $name
        ));

        if ($existing_id) {
            // Entry exists, update it
            $updated = $wpdb->update(
                $this->table_names->assistant_knowledges,
                ['path' => $path],  // New values
                ['id' => $existing_id]   // Where conditions
            );

            if ($updated !== false) {
                return $existing_id;  // Return the existing ID
            } else {
                kissai_error_log("update_knowledge_path({$assistant_id}, {$name}) - Failed to update path.");
                return false;  // Return false if the update operation failed
            }
        } else {
            // No existing entry, insert new one
            $inserted = $wpdb->insert(
                $this->table_names->assistant_knowledges,
                [
                    'assistant_id' => $assistant_id,
                    'name' => $name,
                    'path' => $path
                ]
            );
            if ($inserted) {
                return $wpdb->insert_id;  // Return the ID of the newly inserted row
            } else {
                kissai_error_log("update_knowledge_path({$assistant_id}, {$name}) - Failed to insert new path.");
                return false;  // Return false if the insert failed
            }
        }
    }

    /**
     * Retrieves the file path associated with a given file ID from the 'assistant_knowledges' table.
     *
     * @param string $file_id The unique identifier for the file.
     * @return string|null The file path if found, null if not found or error occurs.
     */
    public function get_knowledge_file_path_by_file_id($file_id) {
        global $wpdb; // WordPress database abstraction object

        // Ensure file_id is sanitized if coming from user input (already assumed sanitized here for brevity)
        $table_name = $this->table_names->assistant_knowledges;
        
        // Prepare SQL query to fetch the 'path' based on 'file_id'
        $file_path = $wpdb->get_var($wpdb->prepare("SELECT path FROM {$table_name} WHERE file_id = %s", $file_id));

        if ($file_path) {
            return $file_path;
        } else {
            // Log error or handle error scenario
            kissai_error_log("No file path found for file_id: {$file_id}");
            return null;
        }
    }

    /**
     * Export the data for a single assistant into a ZIP file containing:
     * 1) JSON manifest with DB rows from assistants, assistant_settings, assistant_knowledges
     * 2) The actual knowledge files from assistant_knowledges.path
     *
     * Returns the file path (or URL) to the generated ZIP for download.
     *
     * @param string $assistant_id
     * @return string|WP_Error Path to the exported ZIP file or WP_Error on failure
     */
    public function export_assistant($assistant_id) {
        global $wpdb;

        // 1) Fetch the assistant row
        $assistant = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_names->assistants} WHERE assistant_id = %s LIMIT 1",
                $assistant_id
            ),
            ARRAY_A
        );
        if (!$assistant) {
            return new WP_Error('export_error', 'Assistant not found.');
        }

        // 2) Fetch settings
        $settings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_names->assistant_settings} WHERE assistant_id = %s",
                $assistant_id
            ),
            ARRAY_A
        );

        // 3) Fetch knowledges
        $knowledges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_names->assistant_knowledges} WHERE assistant_id = %s",
                $assistant_id
            ),
            ARRAY_A
        );

        // 4) Create a temporary folder for packaging
        $export_dir = wp_upload_dir();  // Or some other place
        $tmp_folder = trailingslashit($export_dir['basedir']) . 'kissai_exports/kissai_export_' . uniqid();
        if (!wp_mkdir_p($tmp_folder)) {
            return new WP_Error('export_error', 'Failed to create export temp folder.');
        }

        // 5) Copy all knowledge files
        foreach ($knowledges as $index => $row) {
            $source_path = $row['path']; // e.g. /wp-content/uploads/... or absolute
            $knowledges[$index]['path'] = '';
            if (!file_exists($source_path)) {
                // You may want to skip or throw an error
                continue;
            }
            // Decide on a name to store in the zip folder
            // e.g. "file_[file_id]_[original_filename]"
            $filename  = basename($source_path);
            $targetPath = $tmp_folder . '/' . $filename;

            copy($source_path, $targetPath);

            // Optionally store the new relative name in knowledge array
            // in case you want the JSON to reflect the zip's relative path
            $knowledges[$index]['zip_filename'] = $filename;
        }

        // 6) Create a JSON manifest of the data we need to re-import
        $manifest_data = [
            'assistant'  => $assistant,
            'settings'   => $settings,
            'knowledges' => $knowledges,
        ];
        $manifest_json = json_encode($manifest_data, JSON_PRETTY_PRINT);
        file_put_contents($tmp_folder . '/manifest.json', $manifest_json);

        // 7) Create the ZIP
        $zip_filename = 'assistant_export_' . $assistant_id . '.zip';
        $zip_filepath = $tmp_folder . '/' . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('export_error', 'Could not create ZIP archive.');
        }

        // Add all files in $tmp_folder to the zip
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp_folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            // Convert path to relative
            $filePath    = $file->getRealPath();

            // Convert all backslashes to forward slashes so the str_replace works:
            $filePath    = str_replace('\\', '/', $filePath);
            $tmp_folder  = str_replace('\\', '/', $tmp_folder);

            // Now strip off the $tmp_folder prefix
            $relativePath = str_replace($tmp_folder . '/', '', $filePath);

            if (!$file->isDir()) {
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();

        // 8) Optionally, copy out the resulting ZIP
        // e.g. to /uploads/kissai_exports for direct download
        $final_exports_dir = trailingslashit($export_dir['basedir']) . 'kissai_exports';
        if (!wp_mkdir_p($final_exports_dir)) {
            // fallback: just return the file in the temp folder
        }

        // e.g. rename or copy to the final folder
        $final_zip_path = $final_exports_dir . '/' . $zip_filename;
        rename($zip_filepath, $final_zip_path);

        // Clean up temp folder if you want
        // If you keep it, remove at some scheduled event
        // $this->rrmdir($tmp_folder); // A custom function to recursively remove a dir

        // Return the path. You might convert to a URL for direct download:
        // $final_url = trailingslashit($export_dir['baseurl']) . 'kissai_exports/' . $zip_filename;
        return $final_zip_path;
    }

    public static function convert_export_assistant_path_to_url($path) {
        $export_dir = wp_upload_dir();
        $final_url = trailingslashit($export_dir['baseurl']) . 'kissai_exports/' . basename($path);
        return $final_url;
    }

    /**
     * Import assistant data from a ZIP file.
     *
     * This function presumes you already have a ZIP containing:
     *  - manifest.json (with assistant, settings, knowledges)
     *  - the knowledge files
     *
     * The $assistant_id can either be the same or new.
     * In practice, you might pass a file path instead or handle $_FILES.
     *
     * For simplicity, below we assume the ZIP is located at a known path
     * (maybe the user placed it in the uploads folder) and we want to
     * import it into the DB, rewriting any existing data or merging.
     *
     * @param string $zip_path  Path to the zip file to import
     * @return bool|WP_Error
     */
    public function import_assistant($zip_path) {
        global $wpdb;

        // 1) Unzip into a temp folder
        $upload_dir = wp_upload_dir();
        $tmp_folder = trailingslashit($upload_dir['basedir']) . 'kissai_imports/kissai_import_' . uniqid();
        if (!wp_mkdir_p($tmp_folder)) {
            return new WP_Error('import_error', 'Failed to create import temp folder.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('import_error', 'Unable to open ZIP archive.');
        }
        $zip->extractTo($tmp_folder);
        $zip->close();

        // 2) Read the manifest.json
        $manifest_file = $tmp_folder . '/manifest.json';
        if (!file_exists($manifest_file)) {
            return new WP_Error('import_error', 'manifest.json not found in zip.');
        }
        $json_data      = file_get_contents($manifest_file);
        $manifest_array = json_decode($json_data, true);
        if (!$manifest_array) {
            return new WP_Error('import_error', 'Invalid JSON in manifest.');
        }

        $assistant_data  = $manifest_array['assistant']  ?? [];
        $settings_data   = $manifest_array['settings']   ?? [];
        $knowledge_data  = $manifest_array['knowledges'] ?? [];

        // 3) Insert or update the assistant row
        $assistant_id = $assistant_data['assistant_id'] ?? null;
        if (empty($assistant_id)) {
            return new WP_Error('import_error', 'Invalid assistant ID.');
        }
        $assistant_data['assistant_id'] = $assistant_id;
        // Remove the DB PK if present
        unset($assistant_data['id']);

        // Check if the assistant_id already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_names->assistants} WHERE assistant_id = %s",
                $assistant_id
            )
        );

        if ($exists) {
            // Update
            $wpdb->update(
                $this->table_names->assistants,
                $assistant_data,
                ['assistant_id' => $assistant_id]
            );
        } else {
            // Insert
            $wpdb->insert($this->table_names->assistants, $assistant_data);
        }

        // 4) Insert or update assistant_settings
        // (Remove primary key "id" if present, set the new assistant_id)
        foreach ($settings_data as $row) {
            unset($row['id']);
            $row['assistant_id'] = $assistant_id;

            // Check if setting row already exists
            $rowExists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_names->assistant_settings}
                     WHERE assistant_id = %s AND setting_name = %s",
                    $assistant_id,
                    $row['setting_name']
                )
            );
            if ($rowExists) {
                // update
                $wpdb->update(
                    $this->table_names->assistant_settings,
                    $row,
                    [
                        'assistant_id'  => $assistant_id,
                        'setting_name'  => $row['setting_name']
                    ]
                );
            } else {
                // insert
                $wpdb->insert($this->table_names->assistant_settings, $row);
            }
        }

        // 5) Insert or update knowledges
        // We need to copy the files out of the unzipped folder to your final location
        foreach ($knowledge_data as $row) {
            unset($row['id']);
            $row['assistant_id'] = $assistant_id;

            // The 'zip_filename' is presumably the local file in the unzipped folder
            $zip_filename = isset($row['zip_filename']) ? $row['zip_filename'] : $row['name'];
            unset($row['zip_filename']);

            $unzipped_file_path = $tmp_folder . '/' . $zip_filename;
            if (!file_exists($unzipped_file_path)) {
                // Possibly skip or error
                continue;
            }

            // Decide how to store it in WP or your custom folder
            $target_dir = KissAi_Widget::make_kissai_file_path('openai', $assistant_id);
            wp_mkdir_p($target_dir);
            $target_file = $target_dir . '/' . $zip_filename;
            copy($unzipped_file_path, $target_file);

            // Update the path field
            $row['path'] = $target_file;

            // Check if we already have a row with the same file_id
            $existsKnowledge = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_names->assistant_knowledges}
                     WHERE assistant_id = %s AND file_id = %s",
                    $assistant_id,
                    $row['file_id']
                )
            );
            if ($existsKnowledge) {
                $wpdb->update(
                    $this->table_names->assistant_knowledges,
                    $row,
                    [
                        'assistant_id' => $assistant_id,
                        'file_id'      => $row['file_id']
                    ]
                );
            } else {
                $wpdb->insert($this->table_names->assistant_knowledges, $row);
            }
        }

        // 6) Cleanup
         $this->rrmdir($tmp_folder);

        return $assistant_id;
    }

    /**
     * Simple helper to recursively remove a directory.
     * Use with caution in production code.
     */
    private function rrmdir($dir) {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object !== "." && $object !== "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($path) && !is_link($path)) {
                    $this->rrmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }

    public static function get_session_id_with_nonce($nonce) {
        global $wpdb, $kissai_db;

        $table_name = $kissai_db->table_names->api_call_nonce;

        // Calculate a UTC timestamp for "5 minutes ago"
        // gmdate() returns a string in 'Y-m-d H:i:s' format in UTC
        // time() is the current Unix timestamp in server's UTC
        $threshold = gmdate( 'Y-m-d H:i:s', time() - 300 );

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT guid, session_id FROM `{$table_name}` WHERE guid = %s AND created_at > %s",
                $nonce,
                $threshold
            )
        );

        // Check for errors or handle the result as needed
        if ( $result === null ) {
            return false;
        } else {
            return $result->session_id;
        }
    }

    public function delete_nonce($nonce) {
        global $wpdb, $kissai_db;

        $table_name = $kissai_db->table_names->api_call_nonce;

        $delete_result = $wpdb->delete(
            $table_name,
            ['guid' => $nonce], // WHERE
            ['%s']  // WHERE format
        );
        if ($delete_result === false) {
            kissai_error_log("Error - delete nonce failed: Table: {$table_name} | Nonce: {$nonce} | WPDB Error: " . $wpdb->last_error);
        }

        return $delete_result;
    }

    public function api_log($api_server, $endpoint, $event, $message, $data) {
        global $wpdb, $kissai_api;
        $table_name = $this->table_names->api_log;
        $data = array(
            'api_server' => $api_server,
            'session_id' => $kissai_api->get_session_id(),
            'created_at' => current_time('mysql', 1), // WP function to get current time in MySQL format
            'endpoint' => $endpoint, // endpoint cannot be longer than 512 bytes
            'event' => $event,
            'message' => $message, // Message cannot be longer than 512 bytes
            'data' => $data
        );
        $result = $wpdb->insert($table_name, $data);
        return $result;
    }

    private static function get_threads_list_where_clause($assistant_id, $search = '') {
        global $wpdb;
        $where_clause = '';
    
        // If assistant_id is provided, add it to the WHERE clause
        if (!empty($assistant_id)) {
            $where_clause .= $wpdb->prepare("WHERE m.assistant_id = %s ", $assistant_id);
        }

        // If search is provided, process it to construct the search conditions
        if (!empty($search)) {
            $search_conditions = [];
            preg_match_all('/"([^"]+)"|(\S+)/', $search, $matches);
    
            foreach ($matches[0] as $term) {
                if (strpos($term, '"') === 0) {
                    // Exact phrase search
                    $term = trim($term, '"');
                    $search_conditions[] = $wpdb->prepare("m.message_content LIKE %s", '%' . $wpdb->esc_like($term) . '%');
                } else {
                    // Search for individual words
                    $search_conditions[] = $wpdb->prepare("m.message_content LIKE %s", '%' . $wpdb->esc_like($term) . '%');
                }
            }
    
            if (!empty($search_conditions)) {
                // Append search conditions to the WHERE clause
                $where_clause .= (!empty($where_clause) ? " AND " : " WHERE ") . '(' . implode(' OR ', $search_conditions) . ') ';
            }
        }
        $where_clause .= (!empty($where_clause) ? " AND " : " WHERE ") . '(utility_message IS NULL or utility_message = 0) ';
        return $where_clause;
    }

    private static function get_threads_list_where_clause_params($assistant_id, $search = '') {
        global $wpdb;

        $where_fragments = [];
        $params = [];

        if (!empty($assistant_id)) {
            $where_fragments[] = 'm.assistant_id = %s';
            $params[] = $assistant_id;
        }

        // If search is provided, process it and add placeholders
        if (!empty($search)) {
            // Suppose we build fragments like `m.message_content LIKE %s` for each term
            // and accumulate them in $search_fragments.
            // Then we combine them with OR, for instance:
            $search_fragments = [];
            preg_match_all('/"([^"]+)"|(\S+)/', $search, $matches);

            foreach ($matches[0] as $term) {
                if (strpos($term, '"') === 0) {
                    $term = trim($term, '"');
                }
                $search_fragments[] = 'm.message_content LIKE %s';
                $params[] = '%' . $wpdb->esc_like($term) . '%';
            }

            // Combine them with parentheses and OR
            if (!empty($search_fragments)) {
                $where_fragments[] = '(' . implode(' OR ', $search_fragments) . ')';
            }
        }

        // Always exclude utility_message
        $where_fragments[] = '(m.utility_message IS NULL OR m.utility_message = 0)';

        // Build the final WHERE clause piece
        $where_sql = '';
        if (!empty($where_fragments)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_fragments);
        }

        // Return both pieces
        return [
            'sql'    => $where_sql,
            'params' => $params
        ];
    }

    public function count_threads_list_items($assistant_id, $search = '') {
        global $wpdb;
        $messages_table = $this->table_names->messages;

        $query = "SELECT COUNT(DISTINCT m.thread_id) as count FROM {$messages_table} m";

        // Get the where clause and parameters
        $where_data    = self::get_threads_list_where_clause_params($assistant_id, $search);
        $where_sql     = $where_data['sql'];
        $where_params  = $where_data['params'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Our $where_sql is safe, prepared below.
        $query .= $where_sql;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- We call $wpdb->prepare() on $query below.
        return $wpdb->get_var( $wpdb->prepare( $query, $where_params ) );
    }


    public function read_threads_list(
        $assistant_id,
        $offset = 0,
        $limit = self::default_thread_list_page_size,
        $sort_order = KissAi_Thread_SortOrder::newer,
        $search = '',
        $include_utility_messages = false
    ) {
        global $wpdb;
        $messages_table = $this->table_names->messages;

        // 1) Get the initial WHERE clause & its parameters
        $clause_data = self::get_threads_list_where_clause_params($assistant_id, $search);
        $where_sql   = $clause_data['sql'];
        $where_params = $clause_data['params'];

        // 2) Conditionally exclude utility messages
        if ( ! $include_utility_messages ) {
            // If there's no existing WHERE clause, start one
            if ( stripos($where_sql, 'WHERE') === false ) {
                $where_sql .= " WHERE (m.utility_message IS NULL OR m.utility_message = 0) ";
            } else {
                // Append an additional AND
                $where_sql .= " AND (m.utility_message IS NULL OR m.utility_message = 0) ";
            }
        }

        // 3) Figure out sort order
        $order = ($sort_order === KissAi_Thread_SortOrder::newer) ? 'DESC' : 'ASC';

        // 4) Build the final query with placeholders
        // We still have 2 more placeholders for LIMIT and OFFSET, which are always %d
        $sql = "
        SELECT m.thread_id,
               MAX(m.created_at) AS created_at
          FROM {$messages_table} m
          $where_sql
         GROUP BY m.thread_id
         ORDER BY m.created_at {$order}
         LIMIT %d OFFSET %d
    ";

        // 5) Merge the WHERE parameters with the pagination parameters
        $final_params = array_merge($where_params, [ $limit, $offset ]);

        // 6) Prepare and execute
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $threads = $wpdb->get_results($wpdb->prepare($sql, $final_params));

        return $threads;
    }

    public function save_suggested_questions($assistant_id, $questions_input, $merge = true, $limit = self::max_saved_suggested_questions)
    {
        // 1) Convert $questions_input into a PHP array (if it's JSON).
        $questions_array = [];

        if (is_string($questions_input)) {
            // Attempt to parse the string as JSON
            $decoded = json_decode($questions_input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $questions_array = $decoded;
            } else {
                kissai_error_log("save_suggested_questions: JSON decode failed or invalid format. Input = " . $questions_input);
                return false;
            }
        } elseif (is_array($questions_input)) {
            $questions_array = $questions_input;
        } else {
            kissai_error_log("save_suggested_questions: Unsupported \$questions_input type. Must be array or valid JSON string.");
            return false;
        }

        // 2) Normalize everything into an array of question strings.
        $newQuestionStrings = [];
        foreach ($questions_array as $item) {
            if (is_string($item)) {
                $newQuestionStrings[] = trim($item);
            } elseif (is_array($item) && isset($item['question']) && is_string($item['question'])) {
                $newQuestionStrings[] = trim($item['question']);
            } else {
                kissai_error_log("save_suggested_questions: Skipping unrecognized item: " . json_encode($item));
            }
        }

        // 3) Merge with existing questions if $merge = true
        $finalQuestions = [];

        if ($merge) {
            // Retrieve existing questions from DB
            $existingJson = $this->get_assistant_setting($assistant_id, 'suggested_questions');
            $existingQuestions = [];
            if (!empty($existingJson)) {
                $decodedExisting = json_decode($existingJson, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedExisting)) {
                    $existingQuestions = $decodedExisting;
                } else {
                    kissai_error_log("save_suggested_questions: Could not decode existing JSON for assistant_id=$assistant_id. Overwriting...");
                }
            }

            // Merge & remove duplicates
            $merged = array_merge($existingQuestions, $newQuestionStrings);
            $merged = array_unique(array_map('trim', $merged));
            $finalQuestions = array_values($merged);
        } else {
            // Replace existing (no merge)
            $finalQuestions = $newQuestionStrings;
        }

        // 4) Enforce the limit
        if ($limit > 0 && count($finalQuestions) > $limit) {
            $finalQuestions = array_slice($finalQuestions, 0, $limit);
        }

        // 5) Save the merged/limited array of strings into the DB
        $jsonToSave = json_encode($finalQuestions, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            kissai_error_log("Error: Failed to JSON-encode updated questions for assistant_id=$assistant_id.");
            return false;
        }

        $result = $this->save_assistant_setting($assistant_id, 'suggested_questions', $jsonToSave);
        if (!$result) {
            kissai_error_log("Error: Failed to save 'suggested_questions' for assistant_id=$assistant_id into DB.");
            return false;
        }

        return true;
    }


    public function get_suggested_questions($assistant_id, $num_of_questions = -1, $return_array = false)
    {
        // 1) Retrieve the questions (stored as JSON array) from 'assistant_settings'
        $existingJson = $this->get_assistant_setting($assistant_id, 'suggested_questions');
        if (empty($existingJson)) {
            // No saved questions
            return [];
        }

        // 2) Decode the JSON
        $decoded = json_decode($existingJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            kissai_error_log("Error: Could not decode 'suggested_questions' for assistant_id=$assistant_id. JSON error: " . json_last_error_msg());
            return [];
        }

        if ($num_of_questions !== -1) {
            // 3) If we have fewer questions than $num_of_questions, just return all in random order
            $total = count($decoded);
            if ($total <= $num_of_questions) {
                shuffle($decoded);
                return $decoded;
            }
            // 4) Otherwise, shuffle and return a slice
            shuffle($decoded);
            $selected = array_slice($decoded, 0, $num_of_questions);
        }
        else {
            $selected = $decoded;
        }

        $named_selected = array_map(function ($item) {
            return ['question' => $item];
        }, $selected);

        if ($return_array) {
            return $named_selected;
        }
        $json_selected = json_encode($named_selected, JSON_UNESCAPED_UNICODE);

        return $json_selected;
    }
}

global $kissai_db;
$kissai_db = new KissAi_DB();

add_action('admin_init', [KissAi_DB::class, 'handle_db_update']);
add_action('admin_init', [KissAi_DB::class, 'check_db_version']);
