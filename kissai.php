<?php
/**
 * Plugin Name: KissAi Widget
 * Plugin URI: https://kissai.tech/
 * Description: A WordPress plugin that integrates OpenAI's ChatGPT API to add a conversational AI assistant.
 * Version: 1.7.93
 * Author: KissAi
 * Text Domain: kissai
 * Author URI: https://kissai.tech/about/
 * Requires at least: 6.4
 * Tested up to: 6.7.1
 * License: GPL2
 */

if ( ! defined( 'CHATGPT_REMOVE_DELTA' ) ) {
    define('CHATGPT_REMOVE_DELTA', true);
}

// Make sure to prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if (!defined('KISSAI_PLUGIN_URL')) {
    define('KISSAI_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined( 'KISSAI_BASENAME') ) {
    define('KISSAI_BASENAME', plugin_basename(__FILE__) );
}

if (!defined( 'KISSAI_SLUG') ) {
    define('KISSAI_SLUG', substr(KISSAI_BASENAME, 0, strpos(KISSAI_BASENAME, '/')) );
}



function get_kissai_option($option_name, $default = null) {
    $option_name = KISSAI_SLUG . '_' . $option_name;
    return get_option($option_name, $default);
}

function update_kissai_option($option_name, $value) {
    $option_name = KISSAI_SLUG . '_' . $option_name;
    update_option($option_name, $value);
}

function kissai_get_plugin_assets_url($file) {
    return plugins_url('assets/' . $file, dirname(__FILE__));
}

function is_plugin_installed( $plugin_basename ) {
    // Get all installed plugins
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $installed_plugins = get_plugins();

    // Check if the plugin basename exists in the installed plugins array
    return isset( $installed_plugins[ $plugin_basename ] );
}

// Include the settings page.
require_once plugin_dir_path( __FILE__ ) . 'constants.php';
require_once plugin_dir_path( __FILE__ ) . 'api-base.php';
require_once plugin_dir_path( __FILE__ ) . 'kissai-api.php';
require_once plugin_dir_path( __FILE__ ) . 'kissai-db.php';
require_once plugin_dir_path( __FILE__ ) . 'openai-api.php';
require_once plugin_dir_path( __FILE__ ) . 'training.php';
require_once plugin_dir_path( __FILE__ ) . 'register.php';
require_once plugin_dir_path( __FILE__ ) . 'settings.php';
require_once plugin_dir_path( __FILE__ ) . 'assistants.php';
require_once plugin_dir_path( __FILE__ ) . 'threads.php';


// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged
ini_set('serialize_precision', 10);
// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged

if ( ! function_exists( 'kissai_error_log' ) ) {
    /**
     * Conditionally logs a message if WP_DEBUG is enabled.
     *
     * @param string      $message            The error message to log.
     * @param int         $message_type       The error log type (0 = system default, 3 = to file, etc.).
     * @param string|null $destination        The file destination if $message_type is 3.
     * @param string|null $additional_headers For email message_type.
     *
     * @return bool True if logged successfully, false otherwise.
     */
    function kissai_error_log(
        string $message,
        int $message_type = 0,
        ?string $destination = null,
        ?string $additional_headers = null
    ): bool {
        // Only log if WP_DEBUG is enabled.
        $debug_mode = get_kissai_option('debug_mode');
        if ( (defined( 'WP_DEBUG' ) && WP_DEBUG) || ($debug_mode === 'true') ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return error_log( "<KISSAI>" . $message . "</KISSAI>", $message_type, $destination, $additional_headers );
        }
        return false;
    }
}

global $kissai_db;
register_activation_hook(__FILE__, array($kissai_db, 'create_plugin_database_tables'));

function get_kissai_plugin_info() {
    $plugin_data = get_kissai_plugin_data( __FILE__ );
    return $plugin_data;
}
function get_kissai_plugin_data($plugin_file) {
    $default_headers = array(
        'Name'            => 'Plugin Name',
        'PluginURI'       => 'Plugin URI',
        'Version'         => 'Version',
        'Description'     => 'Description',
        'Author'          => 'Author',
        'AuthorURI'       => 'Author URI',
        'TextDomain'      => 'Text Domain',
        'DomainPath'      => 'Domain Path',
        'Network'         => 'Network',
        'RequiresWP'      => 'Requires at least',
        'RequiresPHP'     => 'Requires PHP',
        'UpdateURI'       => 'Update URI',
        'RequiresPlugins' => 'Requires Plugins',
        'TestedWP'        => 'Tested up to', // Add custom header
    );

    return get_file_data($plugin_file, $default_headers, 'plugin');
}

function get_kissai_plugin_version() {
    $plugin_data = get_kissai_plugin_info();
    $plugin_version = $plugin_data['Version'];

    return $plugin_version;
}

function get_kissai_plugin_name() {
    $plugin_data = get_kissai_plugin_info();
    $plugin_name = $plugin_data['Name'];
}

function kissai_load_widget_classes($subdir) {
    // Define the path to the widgets directory
    $widgets_dir = plugin_dir_path(__FILE__) . $subdir;

    // Check if the directory exists
    if (!is_dir($widgets_dir)) {
        return; // Exit if directory not found
    }

    // Open the directory
    $dir = opendir($widgets_dir);

    // Loop through the files in the directory
    while (($file = readdir($dir)) !== false) {
        // Check for PHP files starting with 'class-'
        if (preg_match('/^class-.*\.php$/', $file)) {
            // Construct the full path to the file
            $class_file = $widgets_dir . $file;

            // Include the class file
            require_once $class_file;
        }
    }

    // Close the directory handle
    closedir($dir);
}
// Call the function to load widget classes
kissai_load_widget_classes('includes/');
kissai_load_widget_classes('includes/widgets/');

function kissai_enqueue_scripts() {
    $plugin_version = get_kissai_plugin_version();
    // Enqueue the status codes script
    wp_enqueue_script(
        'kissai-statuscodes-script',
        plugin_dir_url(__FILE__) . 'assets/js/statuscodes.js',
        array(), // No dependencies for this script
        $plugin_version,
        true
    );

    // Enqueue the main AJAX script with dependency on statuscodes.js
    wp_enqueue_script(
        'kissai-script',
        plugin_dir_url(__FILE__) . 'assets/js/kissai.js',
        array('jquery', 'kissai-statuscodes-script'), // Now dependent on jQuery and statuscodes.js
        $plugin_version,
        true
    );

    // Enqueue the main AJAX script with dependency on kissai.js
    wp_enqueue_script(
        'kissai-chat-widget-script',
        plugin_dir_url(__FILE__) . 'assets/js/kissai-chat-widget.js',
        array('jquery', 'kissai-script'), // Now dependent on jQuery and kissai.js
        $plugin_version,
        true
    );

    // Register style (no changes here)
    wp_register_style('kissai-chat-widget-style', plugin_dir_url(__FILE__) . 'assets/css/kissai-chat-widget.css', [], $plugin_version);
    wp_register_style('kissai-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], $plugin_version);


    // Localize script for passing server-side data to client-side
    wp_localize_script('kissai-chat-widget-script', 'kissai_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'api_server' => KissAi_API_Endpoints::SERVER_DOMAIN,
        'api_base' => KissAi_API_Endpoints::SERVER,
        'api_key' => get_kissai_option('api_key'),
        'user_email' => get_kissai_option('api_user_email'),
        'nonce' => wp_create_nonce('kissai_nonce') // Create nonce
    ));
    wp_enqueue_style( 'kissai-chat-widget-style' );
    wp_enqueue_style( 'kissai-style' );
}
add_action('wp_enqueue_scripts', 'kissai_enqueue_scripts');


function kissai_init_plugin() {
    $kissai_bg_process_api_key = get_kissai_option('bg_process_api_key');
    if (empty($kissai_bg_process_api_key)) {
        $kissai_bg_process_api_key = openssl_random_pseudo_bytes(26);
        $kissai_bg_process_api_key = substr($kissai_bg_process_api_key, 0, 13) . 'KissAi' . substr($kissai_bg_process_api_key, 13, 13);
        $kissai_bg_process_api_key = base64_encode($kissai_bg_process_api_key);
        update_kissai_option('bg_process_api_key', $kissai_bg_process_api_key);
    }

    if (!get_kissai_option('db_version')) {
        add_option('kissai_db_version', KissAi_DB_Tables::DB_VERSION);
    }


}
register_activation_hook(__FILE__, 'kissai_init_plugin');

add_filter( 'http_request_args', 'kissai_api_increase_timeout', 10, 2 );
function kissai_api_increase_timeout( $args, $url ) {
    if ( false !== strpos( $url, KISSAI_API_ENDPOINTS::SERVER_DOMAIN ) ) {
        $args['timeout'] = 30; // set to 30 seconds for example.com
    }
    return $args;
}

// Add settings link to plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kissai_add_plugin_action_links');
function kissai_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=kissai-plugin-settings') . '">' . __('Settings', 'kissai') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function kissai_cleanup_expired_guids() {
    global $wpdb, $kissai_db;

    // "1 day ago" in UTC
    $threshold = gmdate('Y-m-d H:i:s', time() - 86400);

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->prepare("DELETE FROM `{$kissai_db->table_names->api_call_nonce}` WHERE created_at < %s",
            $threshold
        )
    );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

// Schedule this cleanup to run daily (or at another appropriate interval)
if (!wp_next_scheduled('kissai_cleanup_expired_guids')) {
    wp_schedule_event(time(), 'daily', 'kissai_cleanup_expired_guids');
}
add_action('kissai_cleanup_expired_guids', 'kissai_cleanup_expired_guids');

function kissai_check_license() {
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        // Don't print notices during AJAX requests
        return;
    }
    global $kissai_api;
    $kissai_api_key = $kissai_api->get_api_key();
    if (empty($kissai_api_key)) {
        wp_admin_notice("<h3>KissAi</h3><p>KissAi API Key is missing. Please <a href=" . admin_url('admin.php?page=kissai-plugin-settings') .">enter your key</a> or <a href=" . admin_url('admin.php?page=kissai-plugin-register') . ">register for one</a> to access all features.</p>", array(
            'id'                 => 'warning',
            'additional_classes' => array( 'notice-warning' ),
            'dismissible'        => true,
        ));
    }
    else {
        $service_key = $kissai_api->get_service_api_key_from_kissai_server(true);
        if ($service_key  === null) {
            wp_admin_notice("<h3>KissAi</h3><p>The <a href='" . KissAi_API_Endpoints::SERVER_DOMAIN . "'>KissAi Hub server</a> is currently unreachable. Please verify your internet connection or <a href='" . KissAi_API_Endpoints::SUPPORT . "'>contact support</a> for assistance.</p>", array(
                'id'                 => 'warning',
                'additional_classes' => array( 'notice-warning' ),
                'dismissible'        => true,
            ));
        }
        else {
            $license_valid = false; // This should be replaced with your actual license check
            $message = "";
            if ($service_key && !is_array($service_key)) {
                $license_valid = true;
            }
            else if (is_array($service_key)) {
                $response_code = wp_remote_retrieve_response_code($service_key);
                if ($response_code == 200) {
                    $body = wp_remote_retrieve_body($service_key);
                    $data = json_decode($body);
                    $message = $data->message;
                }
                else {
                    $body = wp_remote_retrieve_body($service_key);
                    $data = json_decode($body);
                    $message = $data->message . '[' . $data->code .']';
                }
            }
    
            if (!$license_valid) {
                if (!empty($message)) {
                    $message = '(' . $message . ')';
                }
                wp_admin_notice("<h3>KissAi</h3><p>You do not have a valid license for KissAi. Please <a href='" . admin_url("admin.php?page=kissai-plugin-register") . "'>update your license</a> to access all features or <a href='" . KissAi_API_Endpoints::SUPPORT . "'>contact support</a> for assistance. {$message}</p>", array(
                    'id'                 => 'warning',
                    'additional_classes' => array( 'notice-warning' ),
                    'dismissible'        => true,
                ));
            }
        }
    }
}

function kissai_update_check() {
    global $kissai_api;
    $user = $kissai_api->get_current_kissai_user();
    if ($user && isset($user->latest_plugin)) {
        $download_url = $user->latest_plugin->download_url ?? '';
    }

}

add_action('admin_init', 'conditional_kissai_check_license');
function conditional_kissai_check_license() {
    if ( ! ( defined('DOING_AJAX') && DOING_AJAX ) ) {
        kissai_check_license();
    }
}


// Function to add the main About page
function add_kissai_about_page() {
    $logo_html = '<img alt="KissAi" style="width: 20px; margin-left: -28px; position: absolute; margin-top: -1px;" src="' . KissAi_Base_Widget::logo . '" />KissAi Admin';

    add_menu_page(
        'About KissAi',                 // Page Title
        $logo_html, // Menu Title
        'manage_options',               // Capability
        'kissai-about',                 // Menu Slug
        'display_kissai_about_page',    // Function to display the page content
        'none',                         // Icon URL
        81                              // Position in the menu
    );

    // Add 'About KissAi' as a submenu explicitly to fix the naming
    add_submenu_page(
        'kissai-about',                 // Parent slug
        'About KissAi',                 // Page title
        'About KissAi',                 // Menu title
        'manage_options',               // Capability
        'kissai-about',                 // Menu slug, same as the top-level to link to the same page
        'display_kissai_about_page'     // Function to display the page content
    );
}

// Function to add the Settings submenu
function add_kissai_settings_page() {
    add_submenu_page(
        'kissai-about',                 // Parent slug
        'KissAi Settings',              // Page title
        'Settings',                     // Menu title
        'manage_options',               // Capability
        'kissai-plugin-settings',       // Menu slug
        'display_kissai_settings_page'  // Function to display the page content
    );
}

// Function to add the Assistants submenu
function add_kissai_assistants_page() {
    add_submenu_page(
        'kissai-about',                 // Parent slug
        'KissAi Assistants',              // Page title
        'Assistants',                     // Menu title
        'manage_options',               // Capability
        'kissai-assistants',       // Menu slug
        'display_kissai_assistants_page'  // Function to display the page content
    );
}

// Function to add the Training submenu
function add_kissai_training_page() {
    add_submenu_page(
        'kissai-about',                 // Parent slug
        'KissAi Training',              // Page title
        'Training',                     // Menu title
        'manage_options',               // Capability
        'kissai-plugin-training',       // Menu slug
        'display_kissai_training_page'  // Function to display the page content
    );
}

// Function to add the Register submenu
function add_kissai_register_page() {
    add_submenu_page(
        'kissai-about',                 // Parent slug
        'KissAi Register',              // Page title
        'Register',                     // Menu title
        'manage_options',               // Capability
        'kissai-plugin-register',       // Menu slug
        'display_kissai_register_page'  // Function to display the page content
    );
}

// Function to add the Threads submenu
function add_kissai_threads_page() {
    add_submenu_page(
        'kissai-about',                 // Parent slug
        'Chat Threads',              // Page title
        'Threads',                     // Menu title
        'manage_options',               // Capability
        'kissai_threads_page',       // Menu slug
        'display_kissai_threads_page'  // Function to display the page content
    );
}



add_action('admin_menu', 'add_kissai_about_page');
add_action('admin_menu', 'add_kissai_register_page');
add_action('admin_menu', 'add_kissai_settings_page');
add_action('admin_menu', 'add_kissai_assistants_page');
add_action('admin_menu', 'add_kissai_training_page');
add_action('admin_menu', 'add_kissai_threads_page');


/**
 * Provide detailed plugin info (for the 'View details' popup).
 *
 * @param false|object|array $default The default result object or array.
 * @param string             $action  The type of information being requested from the plugins API.
 * @param object             $args    Plugin API arguments.
 * @return object $res Modified plugin information object.
 */
function kissai_plugins_api( $default, $action, $args ) {
    // WordPress calls 'plugins_api' with various actions; we only want 'plugin_information'.
    if ( $action !== 'plugin_information' ) {
        return $default;
    }

    // Make sure it's our plugin being requested.
    if ( isset( $args->slug ) && $args->slug === KISSAI_SLUG ) {

        // Get your plugin info (version, changelog, etc.) from the same or similar endpoint.
        global $kissai_api;
        $user = $kissai_api->get_current_kissai_user();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['section'])) {
            $plugin_file = get_kissai_plugin_info();
            // Create an object matching what WordPress expects.
            $res = new stdClass();
            $res->name = $plugin_file['Name'];                // Plugin name
            $res->slug = KISSAI_SLUG;                       // Must match $args->slug
            $res->version = $plugin_file['Version'];
            $res->download_link = $user->latest_plugin->download_url;
            $res->tested = $plugin_file['TestedWP'] ?? '';                        // Up to which WP version you've tested
            $res->requires = $plugin_file['RequiresWP'];                      // Minimum WP version required
            $res->author = '<a href="' . $plugin_file['AuthorURI'] . '">' . $plugin_file['Author'] . '</a>';
            $res->homepage = $user->latest_plugin->uri;    // Plugin homepage
        }
        else {
            // Create an object matching what WordPress expects.
            $res = new stdClass();
            $res->name = $user->latest_plugin->name;                // Plugin name
            $res->slug = KISSAI_SLUG;                       // Must match $args->slug
            $res->version = $user->latest_plugin->version;
            $res->download_link = $user->latest_plugin->download_url;
            $res->tested = $user->latest_plugin->tested_wp;                        // Up to which WP version you've tested
            $res->requires = $user->latest_plugin->requires_wp;                      // Minimum WP version required
            $res->author = '<a href="' . $user->latest_plugin->author_url . '">' . $user->latest_plugin->author_name . '</a>';
            $res->homepage = $user->latest_plugin->uri;    // Plugin homepage
        }
        $license = null;
        foreach ($user->licenses as $key => $value) {
            $kissai_api_key = get_kissai_option('api_key');
            if (!empty($kissai_api_key) && $kissai_api_key == $value->license_key) {
                $license = $value;
            }
        }
        $plan_name = $license->plan_name ?? '';
        $features = $license->features ?? '';

        $plugin_details = $kissai_api->get_plugin_details($user->latest_plugin->id);
        $changelog = $plugin_details->changelog ?? '';
        $changelog = str_replace("\r\n", "\n", $changelog);
        $changelog = str_replace("\n", '<br>', $changelog);
        // Provide sections for the popup: description, installation, changelog, FAQ, etc.
        $res->sections = array(
            'description' => '<h3>' . $res->name . ' (' . $res->version . ') '.  $plan_name . '</h3>'.'<p>' . $features . '</p>',
            'changelog'   => '<p>' . $changelog . '</p>',
            // You can add more sections if you like: 'faq', 'installation', etc.
        );

        return $res;
    }

    // Otherwise, return the default response for other plugins.
    return $default;
}
add_filter( 'plugins_api', 'kissai_plugins_api', 10, 3 );

function kissai_about_page_content() {
    if (!KissAi_DB::is_db_up_to_date()) {
        KissAi_DB::update_db_notice("custom-notice warning");
    }
    ?>
    <div class="wrap kissai-introduction">

        <h2>KissAi Plugin (Ver. <?php echo esc_html(get_kissai_plugin_version()); ?>): AI-Powered Assistance for Your Website</h2>

        <p>The <strong>KissAi Plugin</strong> delivers a robust, flexible platform for integrating AI-powered virtual assistants into your WordPress site. Leveraging both OpenAI (GPT-3.5 & GPT-4) and KissAi’s proprietary services, KissAi enables you to create, manage, and train custom assistants for truly interactive user experiences.</p>

        <h3>Key Features</h3>
        <ul>
            <li><strong>Multiple Assistants & Custom Training:</strong> Easily build AI assistants tailored to your business needs. Create, modify, and maintain each assistant’s settings and knowledge base via a user-friendly admin interface. <a href="/wp-admin/admin.php?page=kissai-plugin-register">Register your plugin</a> to unlock all the features.</li>
            <li><strong>Threaded Conversations (Optional):</strong> Store user sessions and display chat history for returning visitors, providing deeper context and more natural, persistent conversations.</li>
            <li><strong>Advanced Knowledge Base:</strong> Upload multiple files, manage them within WordPress, and generate vector stores to enhance domain-specific accuracy. Easily update or refine knowledge files over time.</li>
            <li><strong>Dynamic Content Interaction:</strong> Engage visitors with real-time Q&A, suggested questions, and conversation logs, all updated on the fly.</li>
            <li><strong>Data Privacy and Security:</strong> Choose between your own OpenAI API key or a KissAi API key. The plugin is designed with user-friendly settings, and thorough usage logging.</li>
            <li><strong>Seamless Integration:</strong> Use shortcodes (e.g., <code>[kissai_chat assistant_id="asst_xxxxx"]</code>) or popular page builders (Elementor, Avada, etc.) to embed your AI assistant anywhere on your site. (Only one kissai_chat element per page at the moment)</li>
        </ul>

        <h3>Ideal For</h3>
        <ul>
            <li><strong>Customer Support:</strong> Provide real-time FAQ assistance and product guidance while offloading basic inquiries from human agents.</li>
            <li><strong>Marketing & Sales:</strong> Qualify leads, assist prospective buyers, and guide them to relevant products or services.</li>
            <li><strong>Content Guidance & Education:</strong> Offer interactive tutorials, Q&A flows, or knowledge checks for training and educational use cases.</li>
            <li><strong>Interactive User Experiences:</strong> Keep users engaged with AI-driven suggestions, dynamic content, and advanced conversation flows.</li>
        </ul>

        <p>
            <strong>Getting Started:</strong> Simply install the KissAi plugin, activate it, and head to <em>Settings > KissAi</em> for configuration. No deep technical expertise required! For full access, be sure to <a href="/wp-admin/admin.php?page=kissai-plugin-register">register</a> your plugin license.
        </p>

        <h3>Unlock the Potential of AI</h3>
        <p>From real-time user guidance and content suggestions to advanced domain-trained assistants, KissAi (now at Version <?php echo esc_html(get_kissai_plugin_version()); ?>) offers everything you need to deliver exceptional AI experiences within WordPress. <a href="/wp-admin/admin.php?page=kissai-plugin-register">Register now</a> to access all capabilities and keep your site at the forefront of innovation!</p>
    </div>
    <?php
}

function kissai_manual_page_content() {
    ?>
    <div class="wrap kissai-manual">
        <h1>KissAi Plugin User Manual (Ver. <?php echo esc_html(get_kissai_plugin_version()); ?>)</h1>

        <h2>Introduction</h2>
        <p>The <strong>KissAi Plugin</strong> seamlessly integrates powerful AI assistants into your WordPress site. Whether you aim to boost customer support, guide visitors with interactive content, or create rich, persistent chat experiences, KissAi has you covered.</p>

        <h2>Getting Started</h2>
        <h3>Installation</h3>
        <ol>
            <li>
                <strong>Download and Install the Plugin:</strong>
                <ul>
                    <li>Download KissAi from the WordPress Plugin Repository.</li>
                    <li>In WordPress admin, go to <strong>Plugins > Add New</strong>.</li>
                    <li>Click <strong>Upload Plugin</strong>, choose the <code>.zip</code> file, and click <strong>Install Now</strong>.</li>
                    <li>After installation, click <strong>Activate</strong>.</li>
                </ul>
            </li>
            <li>
                <strong>Register and Enter API Key:</strong>
                <ul>
                    <li>Navigate to <strong>KissAi Admin > Register</strong>.</li>
                    <li>Enter First Name, Last Name, and Email and click Verify button</li>
                    <li>Please check your email to verify your email. Please also check your spam folder</li>
                    <li>Once you verify your email address, Welcome email will be sent to you along with KissAi API Key</li>
                    <li>Navigate to <strong>KissAi Admin > Settings</strong>.</li>
                    <li>Enter KissAi API Key from the Welcome email (Even if you decide to use your own OpenAI API Key KissAi API Key must be entered here)</li>
                    <li>Choose between a <strong>KissAi API Key</strong> or <strong>OpenAI API Key</strong>. If you don’t have one, <a href="/wp-admin/admin.php?page=kissai-plugin-register">register for a KissAi key</a> or visit <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI</a>.</li>
                </ul>
            </li>
        </ol>

        <h3>Configuration</h3>
        <ol>
            <li>
                <strong>Settings Page:</strong> Go to <em>KissAi Admin > Settings</em> to configure your keys. If "Debug Mode" is enabled, KissAi plugin will store debugging log in your server log file for our engineers to provide you support and trobleshoot. If there is no issue, this option should be off.
            </li>
            <li>
                <strong>API Key and Privacy:</strong>
                Decide which API key you want to use. KissAi supports both. Note that older chat histories or training data might not transfer seamlessly if you switch API keys later.
            </li>
            <li>
                <strong>Assistants:</strong>
                <ul>
                    <li><em>Add New</em>: Provide a name, description, and model (e.g. GPT-4). You can also link knowledge files or advanced instructions.</li>
                    <li><em>Manage Existing:</em> Edit settings, view logs, or delete assistants under <em>Assistants</em>.</li>
                    <li><em>Train Assistants</em>: </li>
                </ul>
            </li>
        </ol>

        <h2>Using the KissAi Plugin</h2>
        <h3>Create & Manage AI Assistants</h3>
        <ol>
            <li><strong>Creating Assistants:</strong> Under <em>KissAi Admin > Assistants > Add New</em>, supply your assistant’s name, model choice, and any custom instructions. Click <strong>Save</strong> to finalize.</li>
            <li><strong>Managing Assistants:</strong> From the <em>KissAi Admin > Training</em> page, you can add or edit knowledge files, track usage, or tweak behavior. Multiple assistants can run simultaneously.</li>
        </ol>

        <h3>Embedding Assistants</h3>
        <ol>
            <li><strong>Shortcode:</strong> Insert <code>[kissai_chat assistant_id="asst_xxxxx"]</code> in any post/page to display an AI chat interface. The older shortcode <code>[kissai_chat_widget]</code> still works, but specifying an assistant ID is recommended.</li>
            <li><strong>Page Builders:</strong> KissAi integrates smoothly with Elementor, Avada, and WPBakery. Drop in a KissAi widget or paste the shortcode wherever you prefer.</li>
        </ol>

        <h2>Advanced Features</h2>
        <h3>Knowledge Base</h3>
        <ol>
            <li><strong>File Upload:</strong> Go to <em>Knowledge Base</em> to upload text or data files. KissAi can build vector stores for advanced context matching, increasing accuracy of your AI responses.</li>
            <li><strong>Edit & Manage:</strong> Modify file contents, rename files, or remove outdated references. Re-training can be automatic upon file changes.</li>
        </ol>

        <h3>Threaded Conversations</h3>
        <p>Optionally track entire chat sessions as “threads.” Users can return later and pick up where they left off, preserving context for deeper, more natural interactions. Admins can view or moderate conversation logs directly from the WordPress dashboard.</p>

        <h3>Suggested Questions</h3>
        <p>Enable the auto-generated questions feature under <em>KissAi Admin > Training</em> so your AI can propose relevant or follow-up topics to users. This often boosts engagement by guiding them toward key points or FAQ-like inquiries.</p>

        <h2>Troubleshooting & FAQs</h2>
        <ul>
            <li><strong>No Responses?</strong> Double-check your API key under <em>KissAi Admin > Settings</em> and confirm your server can make outbound requests to OpenAI or the KissAi API.</li>
            <li><strong>Upgrading:</strong> Go to <em>Plugins > Installed Plugins</em>, locate KissAi, and click <strong>Update</strong>. Existing logs, knowledge files, and settings remain intact.</li>
            <li><strong>Performance/Timeout Issues?</strong> Large knowledge bases or slower servers might require increased WP memory limit or longer execution time. Consult your hosting provider for details.</li>
        </ul>

        <h2>Support</h2>
        <p>Need assistance? Visit the <a href="<?php echo esc_url(KissAi_API_Endpoints::SUPPORT); ?>" target="_blank" rel="noopener">KissAi Support Page</a> for documentation, FAQs, and a community forum to share insights or report any issues.</p>

        <h2>Conclusion</h2>
        <p>With KissAi <?php echo esc_html(get_kissai_plugin_version()); ?>, your WordPress site gains advanced AI capabilities—from knowledge-based chat to persistent user sessions. Whether you’re aiming to enhance support, boost conversions, or provide educational content, KissAi makes it easy. Try it today and see the difference an AI-powered assistant can make!</p>
    </div>
    <?php
}

// Display function for the About page
function display_kissai_about_page() {

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'about';
    ?>
    <style>
        .kissai-introduction ul {
            list-style: disc;
            padding-left: 40px;
        }
    </style>
<div class="wrap kissai-introduction">
    <h1>KissAi: Unleashing the Power of AI in WordPress (Ver. <?php echo esc_html(get_kissai_plugin_version()); ?>)</h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=kissai-about&tab=about" class="nav-tab <?php echo $tab == 'about' ? 'nav-tab-active' : ''; ?>">About KissAi</a>
        <a href="?page=kissai-about&tab=manual" class="nav-tab <?php echo $tab == 'manual' ? 'nav-tab-active' : ''; ?>">Manual</a>
    </h2>
    <?php
        // Display content based on the selected tab
        if ($tab == 'about') {
            // Call the function to display About content
            kissai_about_page_content();
        } elseif ($tab == 'manual') {
            // Call the function to display Manual content
            kissai_manual_page_content();
        }
        ?>
    </div>
    <?php
}

