<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'kissai_get_option' ) ) {
    function kissai_get_option($option_name, $default = null)
    {
        $option_name = KISSAI_SLUG . '_' . $option_name;
        return get_option($option_name, $default);
    }
}

if ( ! function_exists( 'kissai_update_option' ) ) {

    function kissai_update_option($option_name, $value)
    {
        $option_name = KISSAI_SLUG . '_' . $option_name;
        update_option($option_name, $value);
    }
}

if ( ! function_exists( 'kissai_get_plugin_assets_url' ) ) {

    function kissai_get_plugin_assets_url($file)
    {
        return plugins_url('assets/' . $file, dirname(__FILE__));
    }
}

if ( ! function_exists( 'kissai_is_plugin_installed' ) ) {
    function kissai_is_plugin_installed($plugin_basename)
    {
        // Get all installed plugins
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();

        // Check if the plugin basename exists in the installed plugins array
        return isset($installed_plugins[$plugin_basename]);
    }
}

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
        $debug_mode = kissai_get_option('debug_mode');
        if ( (defined( 'WP_DEBUG' ) && WP_DEBUG) || ($debug_mode === 'true') ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return error_log( "<KISSAI>" . $message . "</KISSAI>", $message_type, $destination, $additional_headers );
        }
        return false;
    }
}

if ( ! function_exists( 'kissai_get_plugin_version' ) ) {
    function kissai_get_plugin_version() {
        $plugin_data = kissai_get_plugin_info();
        $plugin_version = $plugin_data['Version'];

        return $plugin_version;
    }
}

if ( ! function_exists( 'kissai_get_plugin_name' ) ) {
    function kissai_get_plugin_name() {
        $plugin_data = kissai_get_plugin_info();
        $plugin_name = $plugin_data['Name'];
    }
}

// DevCode Begins
if ( ! function_exists( 'kissai_update_check' ) ) {
    function kissai_update_check() {
        global $kissai_api;
        $user = $kissai_api->get_current_kissai_user();
        if ($user && isset($user->latest_plugin)) {
            $download_url = $user->latest_plugin->download_url ?? '';
        }
    }
}
// DevCode Ends

if ( ! function_exists( 'kissai_json_encode' ) ) {
    /**
     * JSON‑encode with a temporary serialize_precision.
     *
     * @param mixed $data     Data to encode.
     * @param int   $options  json_encode() options.
     * @param int   $depth    Maximum depth.
     * @param int   $precision Desired serialize_precision (default 10).
     *
     * @return string|false   JSON string or false on failure.
     */
    function kissai_json_encode( $data, $options = 0, $depth = 512, $precision = 10 ) {
        // We temporarily adjust `serialize_precision` so the JSON we return
        // does not contain unnecessarily long float strings. The previous value
        // is saved, changed for the duration of this helper only, and then
        // restored immediately. Nothing persists beyond this function call,
        // so no other plugin, theme, or core routine ever sees a different
        // `serialize_precision` value.

        $old_precision = ini_get( 'serialize_precision' );

        // Only change if we really need to.
        if ( (int) $old_precision !== $precision ) {
            // serialize_precision is temporarily adjusted
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
            ini_set( 'serialize_precision', (string) $precision );
        }

        $json = json_encode( $data, $options, $depth );

        // Restore previous setting.
        if ( (int) $old_precision !== $precision ) {
            // serialize_precision is restored
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
            ini_set( 'serialize_precision', $old_precision );
        }

        return $json;
    }
}

if ( ! function_exists( 'kissai_register_inline_style' ) ) {
    /**
     * Register and enqueue inline CSS under a given handle, using the KissAi plugin version.
     *
     * @param string $handle The name (handle) of the style to create.
     * @param array $dependencies Array of style handles this style depends on.
     * @param string $style The raw CSS code to be inlined.
     */
    function kissai_register_inline_style($handle, $dependencies, $style)
    {
        // 1) Get the KissAi plugin version
        $version = kissai_get_plugin_version();

        // 2) Register a "dummy" stylesheet handle (no actual file)
        wp_register_style(
            $handle,      // Handle name
            false,        // No physical file URL; we’re just adding inline CSS
            $dependencies,// Dependencies
            $version      // Version (from kissai_get_plugin_version())
        );

        // 3) Enqueue it so WordPress outputs a <link> tag for it
        wp_enqueue_style($handle);

        // 4) Add inline CSS to this handle
        wp_add_inline_style($handle, $style);
    }
}

if ( ! function_exists( 'kissai_register_inline_script' ) ) {
    /**
     * Register and enqueue inline JS under a given handle, using the KissAi plugin version.
     *
     * @param string $handle The name (handle) of the script.
     * @param array $dependencies An array of script handles this script depends on.
     * @param string $script The raw JavaScript code to be inlined.
     * @param bool $in_footer Whether to load the script in the footer (default: true).
     */
    function kissai_register_inline_script($handle, $dependencies, $script, $in_footer = true)
    {
        // 1) Get the KissAi plugin version
        $version = kissai_get_plugin_version();

        // 2) Register a "dummy" script (no actual file)
        wp_register_script(
            $handle,      // Script handle name
            false,        // No actual file URL
            $dependencies,// Dependencies
            $version,     // Version from kissai_get_plugin_version()
            $in_footer    // Whether to load in footer
        );

        // 3) Enqueue the script so WP outputs <script> for it
        wp_enqueue_script($handle);

        // 4) Add inline JS to the handle
        //    ‘after’ means your code will appear after any dependencies are loaded.
        wp_add_inline_script($handle, $script, 'after');
    }
}