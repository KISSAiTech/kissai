<?php

class KissAi_Base_Widget extends WP_Widget {
    public const logo = KISSAI_PLUGIN_URL . 'assets/kissai-logo.svg';
    public const delete_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M10.556 4a1 1 0 0 0-.97.751l-.292 1.14h5.421l-.293-1.14A1 1 0 0 0 13.453 4h-2.897Zm6.224 1.892-.421-1.639A3 3 0 0 0 13.453 2h-2.897A3 3 0 0 0 7.65 4.253l-.421 1.639H4a1 1 0 1 0 0 2h.1l1.215 11.425A3 3 0 0 0 8.3 22h7.4a3 3 0 0 0 2.984-2.683l1.214-11.425H20a1 1 0 1 0 0-2h-3.22Zm1.108 2H6.112l1.192 11.214A1 1 0 0 0 8.3 20h7.4a1 1 0 0 0 .995-.894l1.192-11.214ZM10 10a1 1 0 0 1 1 1v5a1 1 0 1 1-2 0v-5a1 1 0 0 1 1-1Zm4 0a1 1 0 0 1 1 1v5a1 1 0 1 1-2 0v-5a1 1 0 0 1 1-1Z" clip-rule="evenodd"></path></svg>';
    public const edit_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M13.293 4.293a4.536 4.536 0 1 1 6.414 6.414l-1 1-7.547 7.547a3 3 0 0 1-1.628.838l-5.368.894a1 1 0 0 1-1.15-1.15l.894-5.368a3 3 0 0 1 .838-1.628l8.547-8.547ZM13 7.414l-6.84 6.84a1 1 0 0 0-.279.543l-.664 3.986 3.986-.664a1 1 0 0 0 .543-.28L16.586 11 13 7.414Zm5 2.172L14.414 6l.293-.293a2.536 2.536 0 0 1 3.586 3.586L18 9.586Z" clip-rule="evenodd"></path></svg>';
    public const edit_in_box_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 24 24" style=""><path fill-rule="evenodd" d="M16.793 2.793a3.121 3.121 0 1 1 4.414 4.414l-8.5 8.5A1 1 0 0 1 12 16H9a1 1 0 0 1-1-1v-3a1 1 0 0 1 .293-.707l8.5-8.5Zm3 1.414a1.121 1.121 0 0 0-1.586 0L10 12.414V14h1.586l8.207-8.207a1.121 1.121 0 0 0 0-1.586ZM6 5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4a1 1 0 1 1 2 0v4a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h4a1 1 0 1 1 0 2H6Z" clip-rule="evenodd"></path></svg>';
    public const download_icon = '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="icon-md text-white"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.70711 10.2929C7.31658 9.90237 6.68342 9.90237 6.29289 10.2929C5.90237 10.6834 5.90237 11.3166 6.29289 11.7071L11.2929 16.7071C11.6834 17.0976 12.3166 17.0976 12.7071 16.7071L17.7071 11.7071C18.0976 11.3166 18.0976 10.6834 17.7071 10.2929C17.3166 9.90237 16.6834 9.90237 16.2929 10.2929L13 13.5858L13 4C13 3.44771 12.5523 3 12 3C11.4477 3 11 3.44771 11 4L11 13.5858L7.70711 10.2929ZM5 19C4.44772 19 4 19.4477 4 20C4 20.5523 4.44772 21 5 21H19C19.5523 21 20 20.5523 20 20C20 19.4477 19.5523 19 19 19L5 19Z" fill="currentColor"></path></svg>';
    //public const loading_icon = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" stroke="black" stroke-width="20" fill="transparent"/></svg>';
    public const loading_icon = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="black"/></svg>';
    public const circled_exclamation = '<svg viewBox="0 0 26.9 26.9" xmlns="http://www.w3.org/2000/svg"><g transform="matrix(0.300000011921, 0, 0, 0.299919068813, -205.143035731995, -205.840928058207)" style="transform-origin: 205.143px 205.841px;"><circle cx="250" cy="250.582" r="40" stroke="black" fill="transparent" style="stroke-width: 8px;"/><text style="font-family: \'Cascadia Mono\'; font-size: 74px; font-weight: 700; stroke-linecap: round; stroke-linejoin: round; stroke-width: 0px; white-space: pre;" y="275.637" x="228.228">!</text></g></svg>';

    public const circled_question = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="20" height="20"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM169.8 165.3c7.9-22.3 29.1-37.3 52.8-37.3l58.3 0c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24l0-13.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1l-58.3 0c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>';
    public const sort_arrow_up_wide_short = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M151.6 42.4C145.5 35.8 137 32 128 32s-17.5 3.8-23.6 10.4l-88 96c-11.9 13-11.1 33.3 2 45.2s33.3 11.1 45.2-2L96 146.3 96 448c0 17.7 14.3 32 32 32s32-14.3 32-32l0-301.7 32.4 35.4c11.9 13 32.2 13.9 45.2 2s13.9-32.2 2-45.2l-88-96zM320 480l32 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-32 0c-17.7 0-32 14.3-32 32s14.3 32 32 32zm0-128l96 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-96 0c-17.7 0-32 14.3-32 32s14.3 32 32 32zm0-128l160 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-160 0c-17.7 0-32 14.3-32 32s14.3 32 32 32zm0-128l224 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L320 32c-17.7 0-32 14.3-32 32s14.3 32 32 32z"/></svg>';
    public const sort_arrow_up_short_wide = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M151.6 42.4C145.5 35.8 137 32 128 32s-17.5 3.8-23.6 10.4l-88 96c-11.9 13-11.1 33.3 2 45.2s33.3 11.1 45.2-2L96 146.3 96 448c0 17.7 14.3 32 32 32s32-14.3 32-32l0-301.7 32.4 35.4c11.9 13 32.2 13.9 45.2 2s13.9-32.2 2-45.2l-88-96zM320 32c-17.7 0-32 14.3-32 32s14.3 32 32 32l32 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-32 0zm0 128c-17.7 0-32 14.3-32 32s14.3 32 32 32l96 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-96 0zm0 128c-17.7 0-32 14.3-32 32s14.3 32 32 32l160 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-160 0zm0 128c-17.7 0-32 14.3-32 32s14.3 32 32 32l224 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-224 0z"/></svg>';
    public const sort_arrow_down_wide_short = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M151.6 469.6C145.5 476.2 137 480 128 480s-17.5-3.8-23.6-10.4l-88-96c-11.9-13-11.1-33.3 2-45.2s33.3-11.1 45.2 2L96 365.7 96 64c0-17.7 14.3-32 32-32s32 14.3 32 32l0 301.7 32.4-35.4c11.9-13 32.2-13.9 45.2-2s13.9 32.2 2 45.2l-88 96zM320 480c-17.7 0-32-14.3-32-32s14.3-32 32-32l32 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-32 0zm0-128c-17.7 0-32-14.3-32-32s14.3-32 32-32l96 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-96 0zm0-128c-17.7 0-32-14.3-32-32s14.3-32 32-32l160 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-160 0zm0-128c-17.7 0-32-14.3-32-32s14.3-32 32-32l224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L320 96z"/></svg>';
    public const sort_arrow_down_short_wide = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M151.6 469.6C145.5 476.2 137 480 128 480s-17.5-3.8-23.6-10.4l-88-96c-11.9-13-11.1-33.3 2-45.2s33.3-11.1 45.2 2L96 365.7 96 64c0-17.7 14.3-32 32-32s32 14.3 32 32l0 301.7 32.4-35.4c11.9-13 32.2-13.9 45.2-2s13.9 32.2 2 45.2l-88 96zM320 32l32 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-32 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 128l96 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-96 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 128l160 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-160 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 128l224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-224 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg>';
    public const sort_arrow_down = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M169.4 502.6c12.5 12.5 32.8 12.5 45.3 0l128-128c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 402.7 224 32c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 370.7L86.6 329.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l128 128z"/></svg>';
    public const sort_arrow_up = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M214.6 9.4c-12.5-12.5-32.8-12.5-45.3 0l-128 128c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 109.3 160 480c0 17.7 14.3 32 32 32s32-14.3 32-32l0-370.7 73.4 73.4c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3l-128-128z"/></svg>';
    public const sort_clock = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M464 256A208 208 0 1 1 48 256a208 208 0 1 1 416 0zM0 256a256 256 0 1 0 512 0A256 256 0 1 0 0 256zM232 120l0 136c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2 280 120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></svg>';
    public const rotate_right = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 512 512"><path d="M463.5 224l8.5 0c13.3 0 24-10.7 24-24l0-128c0-9.7-5.8-18.5-14.8-22.2s-19.3-1.7-26.2 5.2L413.4 96.6c-87.6-86.5-228.7-86.2-315.8 1c-87.5 87.5-87.5 229.3 0 316.8s229.3 87.5 316.8 0c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0c-62.5 62.5-163.8 62.5-226.3 0s-62.5-163.8 0-226.3c62.2-62.2 162.7-62.5 225.3-1L327 183c-6.9 6.9-8.9 17.2-5.2 26.2s12.5 14.8 22.2 14.8l119.5 0z"/></svg>';
//    public const spinner_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M222.7 32.1c5 16.9-4.6 34.8-21.5 39.8C121.8 95.6 64 169.1 64 256c0 106 86 192 192 192s192-86 192-192c0-86.9-57.8-160.4-137.1-184.1c-16.9-5-26.6-22.9-21.5-39.8s22.9-26.6 39.8-21.5C434.9 42.1 512 140 512 256c0 141.4-114.6 256-256 256S0 397.4 0 256C0 140 77.1 42.1 182.9 10.6c16.9-5 34.8 4.6 39.8 21.5z"/></svg>';
    public const spinner_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" ><path d="M10,3.5 C6.41015,3.5 3.5,6.41015 3.5,10 C3.5,10.4142 3.16421,10.75 2.75,10.75 C2.33579,10.75 2,10.4142 2,10 C2,5.58172 5.58172,2 10,2 C14.4183,2 18,5.58172 18,10 C18,14.4183 14.4183,18 10,18 C9.58579,18 9.25,17.6642 9.25,17.25 C9.25,16.8358 9.58579,16.5 10,16.5 C13.5899,16.5 16.5,13.5899 16.5,10 C16.5,6.41015 13.5899,3.5 10,3.5 Z"></path></svg>';

    public static function get_api_server_from_endpoint($endpoint) {
        if (strpos($endpoint, 'api.openai.com') > 0)
            return 'openai';
        return '';
    }

    public function __construct($id_base, $name, $widget_options = array(), $control_options = array()) {
        parent::__construct($id_base, $name, $widget_options, $control_options);
    }

    protected static function _convert_to_pascal_case($input) {
        return implode('_', array_map('ucfirst', explode('_', $input)));
    }

    protected static function _convert_to_title_case($input) {
        return implode(' ', array_map('ucfirst', explode('_', $input)));
    }
    protected static function update_instance_setting($setting_name, &$new_instance, $old_instance, $strip_tags = true) {
        // Ensure `$new_instance` and `$old_instance` are arrays
        if (!is_array($new_instance)) {
            $new_instance = [];
        }
        if (!is_array($old_instance)) {
            $old_instance = [];
        }

        // Retrieve the old value safely
        $old_value = $old_instance[$setting_name] ?? '';

        // Update the new instance with strip_tags or retain old value
        if ($strip_tags) {
            $new_instance[$setting_name] = !empty($new_instance[$setting_name])
                ? wp_strip_all_tags($new_instance[$setting_name])
                : wp_strip_all_tags($old_value);
        } else {
            $new_instance[$setting_name] = !empty($new_instance[$setting_name])
                ? $new_instance[$setting_name]
                : $old_value;
        }
    }

    protected static function update_instance_settings($setting_names, &$new_instance, $old_instance, $strip_tags = true) {
        foreach ($setting_names as $name) {
            // Ensure $new_instance[$name] exists and is a string
            if (!isset($new_instance[$name]) || !is_string($new_instance[$name])) {
                $new_instance[$name] = ''; // Default to empty string if not set or not a string
            }

            // Ensure $old_instance[$name] exists and is a string
            if (!isset($old_instance[$name]) || !is_string($old_instance[$name])) {
                $old_instance[$name] = ''; // Default to empty string if not set or not a string
            }

            // Now safely call update_instance_setting
            self::update_instance_setting($name, $new_instance, $old_instance, $strip_tags);
        }
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? wp_strip_all_tags($new_instance['title']) : '';
        return $instance;
    }

    public function widget($args, $instance) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget'];
    }

    public static function get_visual_editor_name() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Elementor Page Builder
        if (isset($_REQUEST['action']) && ($_REQUEST['action'] === 'elementor' || $_REQUEST['action'] === 'elementor_ajax')) {
            return 'Elementor';
        }
    
        // WPBakery Page Builder
        if ((isset($_REQUEST['page']) && $_REQUEST['page'] === 'et_theme_builder') || (isset($_REQUEST['et_fb']) && $_REQUEST['et_fb'] == 1)) {
            return 'Divi';
        }

        if (isset($_REQUEST['page']) && $_REQUEST['action'] && $_REQUEST['action'] === 'edit') {
            return 'Gutenberg Block Builder';
        }
        
        // WPBakery Page Builder
        if (isset($_REQUEST['vc_action']) && $_REQUEST['vc_action'] === 'vc_inline') {
            return 'WPBakery';
        }
    
        // Avada Builder
        if (isset($_REQUEST['builder']) && $_REQUEST['builder'] == "true") {
            return 'Avada';
        }
    
        // Beaver Builder
        if (isset($_REQUEST['fl_builder']) && $_REQUEST['fl_builder']) {
            return 'Beaver Builder';
        }
    
        // Gutenberg (Block Editor)
        if (isset($_REQUEST['context']) && $_REQUEST['context'] === 'edit') {
            return 'Gutenberg';
        }

        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return false;
    }
    
    public static function get_kissai_widget_path() {
        // Assuming this file is within the /wp-content/plugins/kissai/include/widgets directory
        // We need to get the URL of the base plugin directory.
        $base_url = plugin_dir_url(dirname(__DIR__));
    
        return $base_url;
    }
    
    public static function is_in_editing_mode() {
        if (self::get_visual_editor_name() !== false)
            return true;
        return false;
    }
        
    public static function format_bold_text($text) {
        $count = 0; // To track replacements and toggle between <b> and </b>
        return preg_replace_callback('/\*\*/', function() use (&$count) {
            return $count++ % 2 == 0 ? '<b>' : '</b>';
        }, $text);
    }

    public static function format_h3_text($text) {
        return preg_replace('/###\s*(.+?)\<br\>/s', '<h3>$1</h3><br>', $text);
    }
    public static function format_h4_text($text) {
        return preg_replace('/####\s*(.+?)\<br\>/s', '<h4>$1</h4><br>', $text);
    }
    public static function format_reference_text($text) {
        return preg_replace('/【.+?】/', '', $text);
    }
    public static function format_h_text($text) {
        $text = self::format_h4_text($text);
        return self::format_h3_text($text);
    }

    /**
     * Converts lines containing Markdown table syntax into <table> HTML blocks,
     * leaving other lines intact. Loosely mimics your JS convertMarkdownTableToHtml,
     * including the check for lines like |---|---|.
     *
     * This version expects the text to have <br> for line breaks, so
     * it splits on <br> to handle partial lines or streaming contexts.
     */
    public static function format_markdown_table($text) {
        // Split the entire text by <br>
        $lines = explode("\n", $text);

        $html = '';
        $tableBuffer = [];
        $inTable = false;
        $headerDetected = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check if the line looks like a table row (contains at least one pipe '|')
            if (strpos($trimmedLine, '|') !== false) {
                // If we haven't started a table yet, now we do
                if (!$inTable) {
                    $inTable = true;
                }

                // If we haven't detected a header yet,
                // see if this line looks like: |---|---... (start with '|-', ignoring optional spaces)
                if (!$headerDetected && preg_match('/^\|[-]+/', $trimmedLine)) {
                    $headerDetected = true;
                    // In your JS code, you also push the separator line to tableBuffer, so let's do that:
                    $tableBuffer[] = $trimmedLine;
                }
                else {
                    // Normal table row or post-header row
                    $tableBuffer[] = $trimmedLine;
                }
            } else {
                // If we do NOT have '|', the table ended (if we were in one)
                if ($inTable) {
                    // Convert the buffered table lines to HTML, clear the buffer
                    $html .= self::parse_table_buffer($tableBuffer);
                    $tableBuffer = [];
                    $inTable = false;
                    $headerDetected = false;
                }
                // Then append this non-table line to $html
                // (We add <br> so you don't lose your original line breaks)
                $html .= $line . "\n";
            }
        }

        // If text ended while we were still in a table
        if ($inTable && !empty($tableBuffer)) {
            $html .= self::parse_table_buffer($tableBuffer);
        }

        return $html;
    }

    /**
     * Receives an array of lines that are part of a markdown table
     * (header row, optional separator row, body rows) and converts them
     * to an HTML <table>.
     */
    protected static function parse_table_buffer(array $tableLines) {
        // We'll store our final table HTML here
        $html = "<table class='msg-table'><thead>";

        // If there's only one line, we can't have a real table. Let's handle gracefully:
        if (count($tableLines) < 1) {
            // Just return them joined by <br> so you don't lose data
            return implode("\n", $tableLines) . "\n";
        }

        // The first line is typically the header row: e.g. "| Col1 | Col2 |"
        $headerLine = array_shift($tableLines);
        $headers = self::split_table_row($headerLine);

        // Build the <thead> from the header row
        $html .= "<tr>";
        foreach ($headers as $header) {
            // You could optionally run your bold/h4/h3 formatters here
            $html .= '<th>' . $header . '</th>';
        }
        $html .= "</tr></thead><tbody>";

        // If there's a second line that looks like the '---' separator, skip it
        if (!empty($tableLines)) {
            // e.g. "| --- | --- |"
            if (preg_match('/^\|[-: ]+\|/', trim($tableLines[0]))) {
                array_shift($tableLines);
            }
        }

        // Remaining lines in $tableLines are the table body
        foreach ($tableLines as $rowLine) {
            $cells = self::split_table_row($rowLine);
            $html .= "<tr>";
            foreach ($cells as $cell) {
                $html .= "<td>" . $cell . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "</tbody></table>";

        return $html;
    }

    /**
     * Helper to split a row like: "|  some text  |  more text |"
     * into an array: ["some text","more text"].
     */
    protected static function split_table_row($rowLine) {
        // Remove multiple leading pipes: e.g., '||---'
        $rowLine = preg_replace('/^\|+/', '', $rowLine);
        // Remove multiple trailing pipes: e.g., '---||'
        $rowLine = preg_replace('/\|+$/', '', $rowLine);

        // Now split the row by '|'
        $cells = explode('|', $rowLine);

        // Trim each cell
        $cells = array_map('trim', $cells);

        return $cells;
    }



    public static function get_current_timestamp_for_prompt() {
        $timestamp = new DateTime("now", new DateTimeZone(date_default_timezone_get()));
        return "[Current Time: " . $timestamp->format('Y-m-d H:i:s T') . "]";
    }

    public static function convertUnixToUTC($unixTimestamp) {
        // Create a DateTime object and set the timezone to UTC
        $date = new DateTime("@$unixTimestamp"); // The "@" symbol tells DateTime to interpret the timestamp
        $date->setTimezone(new DateTimeZone('UTC'));
    
        // Return the formatted date in yyyy-mm-dd hh:mm:ss
        return $date->format('Y-m-d H:i:s');
    }
    
    public static function convert_unix_timestamp_to_wordpress_timezone($unixTimestamp) {
        // Create a DateTime object and set the timezone to UTC
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        // Combine them if you want to display both date and time
        $datetime_format = $date_format . ' ' . $time_format;

        $dateTime = new DateTime();
        $dateTime->setTimestamp($unixTimestamp);

        $output = $dateTime->format($datetime_format);

        return $output;
    }
    

    public static function render_thread_messages($thread_id, $atts, $assistant_id = null) {
        global $wpdb, $kissai_db;
    
        // Set default values
        $assistant_id = $assistant_id ?? $atts['assistant_id'] ?? null;
        $ai_name = $atts['ai_name'] ?? "KissAi";
        $user_name = $atts['user_name'] ?? "You";
    
        // Handle editing mode
        if (self::is_in_editing_mode()) {
            $thread_id = null;
        }

        // Simplified SQL query to fetch both 'sent' and 'received' messages
        $query = "SELECT m1.call_nonce, m1.message_content, m1.message_data, m1.message_type, m1.created_at
          FROM {$kissai_db->table_names->messages} AS m1
          WHERE m1.message_type IN (%s, %s)
          AND m1.thread_id = %s AND (m1.utility_message IS NULL OR m1.utility_message = 0)";

        $params = [OpenAI_API::MESSAGE_TYPE_SENT, OpenAI_API::MESSAGE_TYPE_RECEIVED, $thread_id];

        // Append the assistant_id conditionally
        if ($assistant_id) {
            $query .= " AND m1.assistant_id = %s";
            $params[] = $assistant_id;
        }

        $query .= " ORDER BY m1.created_at";

        // Execute the query and retrieve results
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_results($wpdb->prepare($query, ...$params));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Initialize output string
        $output = "";
    
        foreach ($result as $message) {
            // Decode message data if available
            $response_message = "";
            $message_text = $message->message_content;
            $time_stamp = $message->created_at;
    
            // Process message content based on message type
            if ($message->message_type === OpenAI_API::MESSAGE_TYPE_RECEIVED) {
                // Apply formatting to the received message
                $response_message = self::format_markdown_table($message_text);
                $response_message = self::format_bold_text($response_message);
                $response_message = self::format_h_text($response_message);
                $response_message = self::format_reference_text($response_message);
                $response_message = nl2br($response_message, false);
            }
            else {
                // Format the sent message if applicable
                $request_message = nl2br($message_text, false);
            }
            // Construct the output HTML based on the message type
            if ($message->message_type === OpenAI_API::MESSAGE_TYPE_SENT) {
                $output .= '<div class="msg-sent" data-guid="' . esc_attr($message->call_nonce) . '">' .
                                '<div class="msg_header">' .
                                '<span class="label_text">' . esc_html($user_name) . '</span>' .
                                '<span class="kissai-timestamp msg_time_stamp">' . esc_html($time_stamp) . '</span>' .
                                '</div>' .
                                $request_message .
                            '</div>';
            } elseif ($message->message_type === OpenAI_API::MESSAGE_TYPE_RECEIVED) {
                $output .= '<div class="msg-rcvd" data-guid="' . esc_attr($message->call_nonce) . '">' .
                                '<div class="msg_header">' .
                                '<span class="label_text">' . esc_html($ai_name) . '</span>' .
                                '<span class="kissai-timestamp msg_time_stamp">' . esc_html($time_stamp) . '</span>' .
                                '</div>' .
                                $response_message .
                            '</div>';
            }
        }
    
        return $output;
    }
    
    

    public static function render_kissai_login_form($atts) {
        ?>
        <h2>Login Required</h2>
        <form id="kissai-login-form">
            <p><label for="username">Username:<br>
            <input type="text" name="username" id="username" placeholder="Username">
            </p>
            <p><label for="password">Password:<br>
            <input type="password" name="password" id="password" placeholder="Password">
            </p>
            <p class="kissai-widget-button-container" style="text-align:center;">
                <button type="submit" class="<?php echo esc_attr($atts['button_class']); ?>">Login</button>
            </p>
            <?php self::render_hidden_field_with_attributes($atts); ?>
            <div class="login-response"></div>
        </form>
        <?php
    }

    public static function render_divider($text) {
        ?>
        <div class="widget-divider">
            <div class="horizontal-line"></div>
            <span class="divider-text"><?php echo esc_attr($text); ?></span>
            <div class="horizontal-line"></div>
        </div>
        <p></p>
        <?php
    }

    public static function render_loading_animation() {
        $output = '';
        $output .= '<div class="loading-animation">';
        $output .= self::loading_icon;
        $output .= '</div>';
        return $output;
    }

    public static function get_assistant_id_from_widget_atts($atts) {
        $assistant_id = null;
        if (isset($atts['assistant_id']) && !empty($atts['assistant_id'])) {
            $assistant_id = $atts['assistant_id'];
        }
        return $assistant_id;
    }

    public static function get_chatgpt_api_with_widget_atts($atts) {
        global $kissai_api;

        $chatgpt_api = $kissai_api->get_ai_api_instance();

        $assistant_id = self::get_assistant_id_from_widget_atts($atts);
        if ($assistant_id) {
            $assistant = $chatgpt_api->set_assistant($assistant_id);
        }

        return $chatgpt_api;
    }

    public static function render_kissai_register_form($atts) {
        ?>
        <form id="kissai-user-register-form" method="post">
            <div class="form-group">
                <input type="text" name="first_name" required placeholder="First Name" class="form-control">
            </div>
            <div class="form-group">
                <input type="text" name="last_name" required placeholder="Last Name" class="form-control">
            </div>
            <div class="form-group">
                <input type="email" name="email" required placeholder="Email" class="form-control">
            </div>
            <p class="submit"><input type="submit" name="register" class="button-primary" value="Register"></p>
        </form>
        <?php
    }

    public static function render_no_permission_message($atts, $echo = true) {
        $msg = '<p>You do not have permission to view this content.</p>';
        $msg .= '<a type="button" class="' . esc_attr($atts['button_class']) . '" href="' . wp_logout_url() . '">Log out</a>';

        // Get WordPress's default allowed HTML for a 'post' context
        $allowed_tags = wp_kses_allowed_html('post');

        // Sanitize using wp_kses + the allowed tags
        if ($echo) {
            echo wp_kses($msg, $allowed_tags);;
        } else {
            return $msg;
        }
    }

    public static function execute_method_from_string($methodString, ...$params) {
        // Check if '::' is present, indicating a static method call
        if (strpos($methodString, '::') !== false) {
            list($className, $methodName) = explode('::', $methodString, 2);
            // Check if both the class and the method exist
            if (class_exists($className) && method_exists($className, $methodName)) {
                // Call the method with parameters
                return call_user_func_array([$className, $methodName], $params);
            } else {
                kissai_error_log('Error in execute_method_from_string: ' . $methodString);
                return null;
            }
        } else {
            // Check if it's a global function
            if (function_exists($methodString)) {
                return call_user_func_array($methodString, $params);
            } else {
                kissai_error_log('Error in execute_method_from_string: ' . $methodString);
                return null;
            }
        }
    }

    public static function widget_atts_from_ajax($atts) {
        // Nonce verification is performed elsewhere; explicitly disabling warning.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if (isset($_POST['kissai_widget_atts'])) {
            $encoded_atts = sanitize_text_field($_POST['kissai_widget_atts']);
            $json_atts = base64_decode($encoded_atts);
            $atts = json_decode($json_atts, true);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        return $atts;
    }
    
    public static function get_reset_kissai_vars_nonce_script($user = null) {
        $temp_nonce = wp_generate_uuid4();
        $kissai_api = KissAi_API::getInstance();
        $script = "";
        if ($kissai_api !== null) {
            $session_id = $kissai_api->get_session_id($user);
            set_transient('kissai_vars_nonce_' . $session_id, $temp_nonce, 10000);
            $script = "jQuery.ajax({
                type: 'POST',
                dataType: 'json',
                url: kissai_vars.ajax_url,
                data: {
                    action: 'reset_kissai_vars_nonce',
                    qualifier: '{$temp_nonce}'
                },
                success: function(response) {
                    kissai_vars.nonce = response.data.new_nonce;
                }
            });";
            strtr($script, array ( ' ' => '', '\r' => '', '\n' => ''));
        }
        return $script;
    }

    public static function reset_kissai_vars_nonce() {
        // We use a custom transient-based token, so standard WP Nonce verification is intentionally bypassed.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if (isset($_POST['qualifier'])) {
            $nonce = sanitize_text_field($_POST['qualifier']);
            $kissai_api = KissAi_API::getInstance();
            if ($kissai_api === null) {
                wp_send_json_error('API Key required.');
            }
            else {
                $session_id = $kissai_api->get_session_id();
                $temp_nonce = get_transient('kissai_vars_nonce_' . $session_id);
                if (hash_equals($temp_nonce, $nonce)) {
                    wp_send_json_success(array(
                        'new_nonce' => wp_create_nonce('kissai_nonce') 
                    ));
                }
                else {
                    wp_send_json_error(['message' => 'Request not authorized']);
                }
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    }

    public static function encode_attributes($atts, $esc = false) {
        // Convert attributes to JSON
        $json_atts = json_encode($atts);

        // Encode JSON to base64
        $encoded_atts = base64_encode($json_atts);
        if ($esc)
            return esc_attr($encoded_atts);
        else
            return $encoded_atts;
    }

    public static function render_hidden_field_with_attributes($atts, $echo = true) {
        $encoded_atts = self::encode_attributes($atts, true);

        // Render hidden input field with encoded attributes
        $html = '<input type="hidden" name="kissai_widget_atts" value="' . $encoded_atts . '">';
        if ($echo) {
            echo wp_kses($html, ['input' => [
                'type' => true,
                'name' => true,
                'value' => true]
            ]);
        }
        return $html;
    }

    public static function format_size_units($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    public static function kissai_save_api_log_ajax() {
        check_ajax_referer('kissai_nonce', 'nonce'); // Standard WP nonce check

        // Safely retrieve and sanitize the endpoint as a URL
        $endpoint = isset($_POST['endpoint'])
            ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) )
            : '';

        // event and message are just text
        $event = isset($_POST['event'])
            ? sanitize_text_field( wp_unslash( $_POST['event'] ) )
            : '';
        $message = isset($_POST['message'])
            ? sanitize_text_field( wp_unslash( $_POST['message'] ) )
            : '';

        // data is JSON – keep as raw string, just unslash
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = isset($_POST['data'])
            ? wp_unslash( $_POST['data'] )
            : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        global $kissai_db;
        if ($kissai_db === null) {
            wp_send_json_error('API Key required.');
        } else {
            $api_server = self::get_api_server_from_endpoint($endpoint);
            $result = $kissai_db->api_log($api_server, $endpoint, $event, $message, $data);
            if ($result) {
                wp_send_json_success();
            } else {
                wp_send_json_error(['message' => 'Failed to store log']);
            }
        }
    }
}

add_action('wp_ajax_reset_kissai_vars_nonce', [KissAi_Base_Widget::class, 'reset_kissai_vars_nonce']);
add_action('wp_ajax_nopriv_reset_kissai_vars_nonce', [KissAi_Base_Widget::class, 'reset_kissai_vars_nonce']);

add_action('wp_ajax_kissai_save_api_log_ajax', [KissAi_Base_Widget::class, 'kissai_save_api_log_ajax']);
add_action('wp_ajax_nopriv_kissai_save_api_log_ajax', [KissAi_Base_Widget::class, 'kissai_save_api_log_ajax']);