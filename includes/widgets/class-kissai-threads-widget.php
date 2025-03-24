<?php
require_once plugin_dir_path( __FILE__ ) . 'class-kissai-base-widget.php';

class KissAi_Threads_Widget extends KissAi_Base_Widget {
    function __construct() {
        parent::__construct(
            // Base ID of your widget
            'KissAi_Threads_Widget',

            // Widget name will appear in UI
            __('KissAi Threads Widget', 'kissai'),

            // Widget description
            array( 'description' => __( 'KissAi Threads Widget', 'kissai' ), )
        );
        $base_url = self::get_kissai_widget_path(); // Go up two levels from current directory

        $plugin_version = get_kissai_plugin_version();
        wp_enqueue_script(
            'kissai-threads-script', 
            $base_url . 'assets/js/threads.js', 
            array('jquery'), // Now dependent on jQuery
            $plugin_version,
            true
        );
        wp_enqueue_style('kissai-style');
        wp_register_style('kissai-threads-style', $base_url . 'assets/css/threads.css', [], $plugin_version);
    }

    public static function get_default_instance() {
        return [
            'thread_list_header' => 'Threads',
            'user_name' => 'You',
            'ai_name'    => 'KissAi'
        ];
    }

    public static function get_kissai_shortcode_atts($atts) {
        $atts = shortcode_atts(self::get_default_instance(), $atts );
        return $atts;
    }

    public function form($instance) {
		$defaults = self::get_default_instance();
		$instance = wp_parse_args( (array) $instance, $defaults );
        ?>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('thread_list_header')); ?>"><?php echo esc_html__('Thread List Header:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('thread_list_header')); ?>" name="<?php echo esc_attr($this->get_field_name('thread_list_header')); ?>" type="text" value="<?php echo esc_attr($instance['thread_list_header']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('user_name')); ?>"><?php echo esc_html__('User Name:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('user_name')); ?>" name="<?php echo esc_attr($this->get_field_name('user_name')); ?>" type="text" value="<?php echo esc_attr($instance['user_name']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('ai_name')); ?>"><?php echo esc_html__('AI Name:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('ai_name')); ?>" name="<?php echo esc_attr($this->get_field_name('ai_name')); ?>" type="text" value="<?php echo esc_attr($instance['ai_name']); ?>">
        </div>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['thread_list_header'] = (!empty($new_instance['thread_list_header'])) ? wp_strip_all_tags($new_instance['thread_list_header']) : '';
        $instance['user_name'] = (!empty($new_instance['user_name'])) ? wp_strip_all_tags($new_instance['user_name']) : '';
        $instance['ai_name'] = (!empty($new_instance['ai_name'])) ? wp_strip_all_tags($new_instance['ai_name']) : '';
        return $instance;
    }

    public function widget($args, $instance) {
        $atts = self::get_kissai_shortcode_atts($instance);
        // Widget content
        $body = self::kissai_shortcode($atts);
        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_enqueue_style('kissai-threads-style');
    }


    public static function kissai_shortcode($atts, $content = "") {
        $output = "";
        $atts = self::get_kissai_shortcode_atts( $atts );
        $output .= self::render_kissai_threads($atts);
        return $output;
    }

    public static function render_kissai_threads($atts = null) {
        if (!current_user_can('manage_options')) {
            return;
        }
        $content = '<div class="wrap">';
        $content .= '<div id="kissai-thread-container" style="display: flex;">'; // Flex container
        $content .= '<div id="threads-container" style="flex: 1; min-width: 300px; margin-right: 20px;">';
        $content .= self::render_kissai_thread_list($atts);
        $content .= '</div>'; // Container for threads
        $content .= '<div id="messages-container">';
        $content .= self::render_loading_animation();
        $content .= '<div class="messages">';
        $content .= '</div>';
        $content .= '</div>'; // Container for messages
        $content .= self::render_hidden_field_with_attributes($atts, false);
        $content .= '</div>';
        $content .= '</div>';
        return $content;
    }

    public static function render_kissai_thread_list_begin($thread_header_content) {
        $html = '';
        $html .= '<div class="thread-search-container">';
        $html .= '<input type="text" name="thread-search-input" class="thread-search-input" placeholder="Search threads...">';
        $html .= '<button class="button">Search</button>';
        $html .= '</div>';
        $html .= '<table class="widefat fixed thread-list"><thead><tr><th>'. $thread_header_content .'</th></tr></thead><tbody>';
        return $html;
    }

    public static function render_kissai_thread_list_body($assistant_id, $offset, $limit, $sort_order, $search, $include_utility_messages = false) {
        global $kissai_db, $kissai_api;
        $user = $kissai_api->get_current_kissai_user();
        $permitted = true;
        // DevCode Begins
        if ($user) {
            $permitted = $kissai_api->check_permission('thread', 4, $user);
        }
        if (!$permitted) {
            $sort_order = KissAi_Thread_SortOrder::newer;
            $search = '';
            $limit = 3;
        }
        // DevCode Ends
        $threads = $kissai_db->read_threads_list($assistant_id, $offset, $limit, $sort_order, $search, $include_utility_messages);
        $count = $kissai_db->count_threads_list_items($assistant_id, $search);
        $html = '';
        if (!empty($threads)) {
            foreach ($threads as $thread) {
                $html .= '<tr class="thread-link" data-thread-id="' . esc_attr($thread->thread_id) . '">';
                $html .= '<td><a>' . esc_html($thread->thread_id) . '</a>';
                $html .= '<br>' . '<span class="kissai-timestamp">' . esc_html($thread->created_at) . '</span>' . '</td></tr>';
            }
            if ($count > $offset + $limit) {
                if ($permitted) {
                    $html .= '<tr class="thread-load-more" data-offset="' . esc_attr($offset + $limit) . '">';
                    $html .= '<td><a>Load more... (' . ($count - ($offset + $limit)) . ' more)</a>';
                    $html .= '</td></tr>';
                }
                else {
                    $html .= '<tr>';
                    if (current_user_can('manage_options')) {
                        if ($user) {
                            $html .= '<td><a href="' . KissAi_API_Endpoints::SERVER_DOMAIN . '/my-account/#plan" class="open-upgrade-page" data-user-email="' . esc_html($user->email) . '" target="_blank">' . ($count - ($offset + $limit)) . ' more threads captured. Upgrade now</a>';
                        }
                        else {
                            $html .= '<td><a href="' . KissAi_API_Endpoints::SUPPORT . '" class="open-upgrade-page" target="_blank">' . ($count - ($offset + $limit)) . ' more threads captured. Upgrade now</a>';
                        }
                    }
                    else {
                        $html .= '<td>';
                    }
                    $html .= '</td></tr>';
                }
            }
        } else {
            $html .= '<tr><td>No threads with messages found.</td></tr>';
        }
        return $html;
    }

    public static function render_kissai_thread_list_end() {
        $html = '';
        $html .= '</tbody></table>';
        return $html;
    }

    public static function render_upgrade_link() {
        $html = '';
        $html .= '<script>';
        $html .= 'jQuery(document).ready(function($) {';
        $html .= "init_open_page_button('.open-upgrade-page', '/my-account/#plan');";
        $html .= '});';
        $html .= '</script>';
        return $html;
    }

    public static function render_dropdown_menu($array, $css_id = '', $css_class = '') {
        // Get the first key of the array
        $first_key = array_key_first($array);
    
        // Start building the HTML string
        $html = '<div id="' . htmlspecialchars($css_id) . '" class="' . htmlspecialchars($css_class) . '" style="display: none;">';
        $html .= '<ul data-sort="' . htmlspecialchars($first_key) . '">';
        
        // Loop through the array to create each menu item
        foreach ($array as $key => $value) {
            $html .= '<li><a href="#" data-sort="' . htmlspecialchars($key) . '">' . htmlspecialchars($value) . '</a></li>';
        }
    
        // Close the ul and div tags
        $html .= '</ul>';
        $html .= '</div>';
        
        // Return the complete HTML string
        return $html;
    }
    

    public static function render_sort_icon($order) {
        $order = $order === KissAi_Thread_SortOrder::newer ? KissAi_Thread_SortOrder::newer : KissAi_Thread_SortOrder::older;
        $output = '<a class="sort-icon" data-sort="'. $order . '" href="#">' ;
        if ($order == KissAi_Thread_SortOrder::newer) {
            $output .= self::sort_arrow_down . self::sort_clock;
        }
        else if ($order == KissAi_Thread_SortOrder::older) {
            $output .= self::sort_arrow_up . self::sort_clock;
        }
        $output .= '</a>';
        return $output;
    }

    public static function generate_sort_order_css($class_name) {
        $reflection = new ReflectionClass($class_name);
        $constants = $reflection->getConstants();
        
        $css = "<style>";
        $css_inner = '';
        foreach ($constants as $value) {
            if ($css_inner != '') {
                $css_inner .= ",";
            }
            $css_inner .= "ul[data-sort=\"{$value}\"] li a[data-sort=\"{$value}\"]";
        }
        if ($css_inner !== '') {
            $css_inner .= " {\n";
            $css_inner .= "    border-bottom: black solid 1px;\n";
            $css_inner .= "}\n";
        }
        $css .= $css_inner . "</style>";
    
        return $css;
    }

    public static function render_kissai_thread_list($atts, $offset = 0, $limit = KissAi_DB::default_thread_list_page_size) {
        $assistant_id = isset($atts["assistant_id"]) ? $atts["assistant_id"] : null;

        $thread_header = isset($atts['thread_list_header']) ? $atts['thread_list_header'] : "";

        $thread_header_content = '';
        $thread_header_content .= self::generate_sort_order_css('KissAi_Thread_SortOrder');
        global $kissai_api;
        $permitted = true;
// DevCode Begins
        $permitted = $kissai_api->check_permission('thread', 4);
// DevCode Ends
        if ($permitted) {
            $menu = [KissAi_Thread_SortOrder::newer => 'Newest to Oldest', KissAi_Thread_SortOrder::older => 'Oldest to Newest'];
        }
        else {
            $menu = [KissAi_Thread_SortOrder::newer => 'Newest to Oldest'];
        }
        $thread_header_content .= '<span>' . esc_html($thread_header) . '</span>';
        $thread_header_content .= '<div style="width:25px;">';
        $thread_header_content .= '<div style="position:absolute;">';
        $thread_header_content .= self::render_sort_icon(KissAi_Thread_SortOrder::newer) . self::render_dropdown_menu($menu, 'sortDropdown', 'dropdown-menu'). '</span>';
        $thread_header_content .= '</div>';
        $thread_header_content .= '</div>';
        $html = self::render_kissai_thread_list_begin($thread_header_content);
        $html .= self::render_kissai_thread_list_body($assistant_id, $offset, $limit, KissAi_Thread_SortOrder::newer, '');
        $html .= self::render_kissai_thread_list_end();
        $html .= self::render_upgrade_link();
        $html .= '<input type="hidden" name="current_page" value="' . '"/>';
        $html .= '<input type="hidden" name="page_limit" value="' . '"/>';
        $html .= '<div class="message"></div>';
        return $html;
    }

    public static function css_property_data($selector, $pseudoElement, $property, $value, $priority = '') {
        $data = array('selector' => $selector,
            'pseudoElement' => $pseudoElement,
            'property' => $property,
            'value' => $value,
            'priority' => $priority);
        return $data;
    }

    public static function load_kissai_threads() {
        check_ajax_referer('kissai_nonce', 'nonce');
        global $kissai_api;

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $assistant_id = isset($atts["assistant_id"]) ? $atts["assistant_id"] : null;

        $offset = isset($_POST['offset'])
            ? absint( wp_unslash( $_POST['offset'] ) )
            : 0;


        $target = isset($_POST['target'])
            ? sanitize_text_field( wp_unslash( $_POST['target'] ) )
            : '#threads-container .thread-list tbody';


        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_target_type = isset($_POST['target_type']) ? wp_unslash( $_POST['target_type'] ) : '';
        $target_type = sanitize_text_field($raw_target_type) === 'replace'
            ? 'replace'
            : 'container';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_sort_order = isset($_POST['sort_order']) ? wp_unslash( $_POST['sort_order'] ) : '';
        $sort_order = (sanitize_text_field($raw_sort_order) === KissAi_Thread_SortOrder::newer)
            ? KissAi_Thread_SortOrder::newer
            : KissAi_Thread_SortOrder::older;

        $permitted = true;
        // DevCode Begins
        $permitted = $kissai_api->check_permission('thread', 4);
        // DevCode Ends
        $script = '';
        if (isset($_POST['sort_order'])) {
            if (!$permitted) {
                $sort_order = KissAi_Thread_SortOrder::newer;
            }
            $sort_icon = self::render_sort_icon($sort_order);
            $script = 'jQuery("#threads-container .thread-list .sort-icon").replaceWith("' . addslashes($sort_icon) . '");';
            $script .= 'init_sort_icon();update_selected_sort("' . $sort_order .'");';
        }

        $search = isset($_POST['search'])
            ? sanitize_text_field( wp_unslash( $_POST['search'] ) )
            : '';

        $message = '';
        if (!$permitted) {
            // If user tries searching or toggling sort without permission
            if ($search !== '' || (isset($_POST['sort_order']) && $_POST['sort_order'] != KissAi_Thread_SortOrder::newer)) {
                $message = '<a href="' . KissAi_API_Endpoints::SERVER_DOMAIN . '/my-account/#plan" target="_blank">Search and sorting unavailable on current license. Upgrade to enable.</a>';
            }
        }

        $limit = KissAi_DB::default_thread_list_page_size;
        $html = self::render_kissai_thread_list_body($assistant_id, $offset, $limit, $sort_order, $search);

        wp_send_json_success([
            'body'        => $html,
            'offset'      => $offset,
            'target'      => $target,
            'target_type' => $target_type,
            'message'     => $message,
            'script'      => $script
        ]);
    }
    
    public static function load_kissai_messages() {
        check_ajax_referer('kissai_nonce', 'nonce');
        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $thread_id = isset($_POST['thread_id']) ? sanitize_text_field( wp_unslash( $_POST['thread_id'] ) ) : '';
    
        $html = '';
        $html .= '<div id="kissai-response">';
        $html .= self::render_thread_messages($thread_id, $atts);
        $html .= '</div>';
        wp_send_json_success(
            ['body' => $html,
             'script' => 'updateTimestamps();']);
    }
}

// function kissai_threads_widget_enqueue_scripts() {
//     // Enqueue the status codes script
//     $base_url = plugin_dir_url(dirname(__DIR__)); // Go up two levels from current directory

//     wp_enqueue_script(
//         'kissai-threads-script', 
//         $base_url . 'assets/js/threads.js', 
//         array('jquery'), // Now dependent on jQuery
//         null, 
//         true
//     );
//     wp_register_style('kissai-threads-style', $base_url . 'assets/css/threads.css');
//     wp_enqueue_style('kissai-threads-style');
// }
// add_action('wp_enqueue_scripts', 'kissai_threads_widget_enqueue_scripts');

add_shortcode('kissai_threads', [KissAi_Threads_Widget::class, 'kissai_shortcode']);

function register_kissai_threads_widget() {
    register_widget('KissAi_Threads_Widget');
}
add_action('widgets_init', 'register_kissai_threads_widget');

add_action('wp_ajax_load_kissai_threads', [KissAi_Threads_Widget::class, 'load_kissai_threads']);
add_action('wp_ajax_nopriv_load_kissai_threads', [KissAi_Threads_Widget::class, 'load_kissai_threads']);

add_action('wp_ajax_load_kissai_messages', [KissAi_Threads_Widget::class, 'load_kissai_messages']);
add_action('wp_ajax_nopriv_load_kissai_messages', [KissAi_Threads_Widget::class, 'load_kissai_messages']);
