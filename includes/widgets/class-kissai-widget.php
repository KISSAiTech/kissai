<?php
require_once plugin_dir_path( __FILE__ ) . 'class-kissai-base-widget.php';

class KissAi_Widget extends KissAi_Base_Widget {
    public const openai_key_missing_message = 'Please set up the OpenAI API Key from the <a href="/wp-admin/kissai-plugin-settings">Settings page</a>';
    public const openai_no_assistant_message = 'This KissAi Widget is not currently associated with any OpenAI Assistant. <a href="/wp-admin/admin.php?page=kissai-about&tab=manual">' . self::circled_question . '</a>';

    public $api_key;
    function __construct() {
        parent::__construct(
            // Base ID of your widget
            'KissAi_Widget',

            // Widget name will appear in UI
            __('KissAi Widget', 'kissai'),

            // Widget description
            array( 'description' => __( 'KissAi Widget', 'kissai' ), )
            );
    }


    public static function get_default_instance() {
        return [
			'mode'  => 'client',
            'button_class' => 'button-flat fusion-button-default-size button-default',
            'textarea_style' => 'width:100%;padding:10px;',
            'form_style' => 'width:100%;',
			'title'    => 'New title',
			'user_name'    => 'You',
			'ai_name'    => 'KissAi',
            'suggested_questions_enabled' => 'on',
			'suggested_questions_text'    => 'These are AI AGENT generated suggested questions',
			'suggested_questions_label'    => 'Q:',
			'suggested_questions_style'    => 'Button',
			'suggested_questions_auto_load'    => 'off',
			'suggested_questions_trigger_text'    => 'Generate questions I can ask',
            'admin_title' => 'Train Your AI',
			'admin_file_list_title'    => 'Knowledge',
            'admin_file_text_divider_text' => 'Or, enter text data below.',
            'assistant_id' => '',
            'is_being_edited' => false
		];
    }

    public static function get_kissai_shortcode_atts($atts) {
        $editing_mode = self::is_in_editing_mode();
        $atts['is_being_edited'] = $editing_mode;
        $atts = shortcode_atts(self::get_default_instance(), $atts );
        return $atts;
    }

    public function form($instance) {
        $modes = array( 'admin', 'client' );
        $suggested_questions_style = array( 'Button', 'Link' );
		$defaults = self::get_default_instance();
        global $kissai_api;

        $chatgpt_api = $kissai_api->get_ai_api_instance();

        $assistants = $kissai_api->get_assistants();
		$instance = wp_parse_args( (array) $instance, $defaults );
        $suggested_questions_enabled = isset($instance['suggested_questions_enabled']) ? $instance['suggested_questions_enabled'] : 'on';
        $suggested_questions_auto_load = isset($instance['suggested_questions_auto_load']) ? $instance['suggested_questions_auto_load'] : 'off';
        $instance_assistant_id = $instance['assistant_id'] ?? '';
        ?>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('assistant_id')); ?>"><?php echo esc_html__('Assistant:', 'kissai'); ?></label>
            <select name="<?php echo esc_attr($this->get_field_name('assistant_id')); ?>" id="<?php echo esc_attr($this->get_field_id('assistant_id')); ?>">
                <?php
                if (empty($instance_assistant_id)) {
                    echo '<option selected value="">Unassigned</option>';
                }
                else {
                    echo '<option value="">Unassigned</option>';
                }
                foreach($assistants as $key => $item){
                    $assistant_accessible = false;
                    $openai_assistant = $chatgpt_api->get_assistant($item['assistant_id']);
                    if ($openai_assistant && isset($openai_assistant['name'])) {
                        $assistant_name = $openai_assistant['name'];
                        $assistant_accessible = true;
                    }
                    else {
                        $assistant_name = "";
                    }
                    $selected = (trim($item['assistant_id']) == trim($instance['assistant_id'])) ? ' selected ' : '';
                    if ($assistant_accessible) {
                        echo wp_kses('<option '.$selected.' value="'.$item['assistant_id'].'">' . esc_html($assistant_name) . '</option>',
                            [
                                'option' => [
                                    'value' => true,
                                    'selected' => true,
                                ]
                            ]
                        );
                    }
                }
                ?>
            </select>
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('mode')); ?>"><?php echo esc_html__('Mode:', 'kissai'); ?></label>
            <select name="<?php echo esc_attr($this->get_field_name('mode')); ?>" id="<?php echo esc_attr($this->get_field_id('mode')); ?>">
                <?php
                foreach($modes as $key => $item){
                    $selected = (trim($item) == trim($instance['mode'])) ? ' selected ' : '';
                    echo wp_kses('<option '.$selected.' value="'.$item.'">'.$item.'</option>',
                        [
                            'option' => [
                                'value' => true,
                                'selected' => true
                            ]
                        ]);
                }
                ?>
            </select>
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php echo esc_html__('Title:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($instance['title']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('user_name')); ?>"><?php echo esc_html__('User Name:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('user_name')); ?>" name="<?php echo esc_attr($this->get_field_name('user_name')); ?>" type="text" value="<?php echo esc_attr($instance['user_name']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('ai_name')); ?>"><?php echo esc_html__('AI Name:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('ai_name')); ?>" name="<?php echo esc_attr($this->get_field_name('ai_name')); ?>" type="text" value="<?php echo esc_attr($instance['ai_name']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr(esc_attr($this->get_field_id('suggested_questions_enabled'))); ?>">Suggested Questions Enabled</label>
            <input class="checkbox" type="checkbox" <?php checked($suggested_questions_enabled, 'on'); ?>
                   id="<?php echo esc_attr($this->get_field_id('suggested_questions_enabled')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('suggested_questions_enabled')); ?>" />
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('suggested_questions_text')); ?>"><?php echo esc_html__('Suggested Questions Header:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('suggested_questions_text')); ?>" name="<?php echo esc_attr($this->get_field_name('suggested_questions_text')); ?>" type="text" value="<?php echo esc_attr($instance['suggested_questions_text']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('suggested_questions_label')); ?>"><?php echo esc_html__('Suggested Questions Label:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('suggested_questions_label')); ?>" name="<?php echo esc_attr($this->get_field_name('suggested_questions_label')); ?>" type="text" value="<?php echo esc_attr($instance['suggested_questions_label']); ?>">
        </div>
        <div style="display: flex;align-items: center;">
            <label for="<?php echo esc_attr($this->get_field_id('suggested_questions_style')); ?>" style="margin-right:10px;"><?php echo esc_html__('Suggested Questions Style :', 'kissai'); ?></label>
            <select name="<?php echo esc_attr($this->get_field_name('suggested_questions_style')); ?>" id="<?php echo esc_attr($this->get_field_id('suggested_questions_style')); ?>">
                <?php
                foreach($suggested_questions_style as $key => $item){
                    $selected = (trim($item) == trim($instance['suggested_questions_style'])) ? ' selected ' : '';
                    echo wp_kses('<option '.$selected.' value="'.$item.'">'.$item.'</option>',
                        [
                            'option' => [
                                'value' => true,
                                'selected' => true
                            ]
                        ]);
                }
                ?>
            </select>
        </div>
        <div style="border: solid 1px rgba(0,0,0,0.5); padding: 5px;">
            <label for="<?php echo esc_attr(esc_attr($this->get_field_id('suggested_questions_auto_load'))); ?>">Suggested Questions Auto Load</label>
            <input class="checkbox" type="checkbox" <?php checked($suggested_questions_auto_load, 'on'); ?>
                id="<?php echo esc_attr($this->get_field_id('suggested_questions_auto_load')); ?>"
                name="<?php echo esc_attr($this->get_field_name('suggested_questions_auto_load')); ?>" />
            <p><div style="width:10px;height:10px;display: inline-block;"><?php echo self::circled_exclamation; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div> Enable this option to automatically generate questions each time the page loads. Please note, this may increase API usage and associated costs.</p>
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('suggested_questions_trigger_text')); ?>"><?php echo esc_html__('Suggested Questions Request Text:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('suggested_questions_trigger_text')); ?>" name="<?php echo esc_attr($this->get_field_name('suggested_questions_trigger_text')); ?>" type="text" value="<?php echo esc_attr($instance['suggested_questions_trigger_text']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('admin_title')); ?>"><?php echo esc_html__('Admin Title:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('admin_title')); ?>" name="<?php echo esc_attr($this->get_field_name('admin_title')); ?>" type="text" value="<?php echo esc_attr($instance['admin_title']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('admin_file_list_title')); ?>"><?php echo esc_html__('File List Title:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('admin_file_list_title')); ?>" name="<?php echo esc_attr($this->get_field_name('admin_file_list_title')); ?>" type="text" value="<?php echo esc_attr($instance['admin_file_list_title']); ?>">
        </div>
        <div>
            <label for="<?php echo esc_attr($this->get_field_id('admin_file_text_divider_text')); ?>"><?php echo esc_html__('File/Text Divider Text:', 'kissai'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('admin_file_text_divider_text')); ?>" name="<?php echo esc_attr($this->get_field_name('admin_file_text_divider_text')); ?>" type="text" value="<?php echo esc_attr($instance['admin_file_text_divider_text']); ?>">
        </div>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = $new_instance;
        self::update_instance_settings(
            ['mode',
            'title',
            'user_name',
            'ai_name',
            'admin_title',
            'admin_file_list_title',
            'admin_file_text_divider_text',
            'assistant_id',
            'suggested_questions_enabled',
            'suggested_questions_text',
            'suggested_questions_label',
            'suggested_questions_style',
            'suggested_questions_trigger_text'],
            $instance,
            $old_instance);

        $instance['suggested_questions_enabled'] = (!empty($new_instance['suggested_questions_enabled'])) ? 'on' : 'off';
        $instance['suggested_questions_auto_load'] = (!empty($new_instance['suggested_questions_auto_load'])) ? 'on' : 'off';
        return $instance;
    }

    public static function get_kissai_widget_css_url($finename) {
        $css_url = self::get_kissai_widget_path() . 'assets/css/' . $finename;

        return $css_url;
    }


    public function widget($args, $instance) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $atts = self::get_kissai_shortcode_atts($instance);
        $body = self::kissai_shortcode($atts);
        echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }


    public static function render_vector_store_file_upload($atts) {
        ?>
        <div class="file-drop-area">
            <form id="file-upload-form" class="uploader">
                <input id="file-upload" type="file" name="fileUpload" accept=".c,.cpp,.cs,.css,.doc,.docx,.go,.html,.java,.js,.json,.md,.pdf,.php,.pptx,.py,.rb,.sh,.tex,.ts,.txt"  multiple/>
                <label for="file-upload">Drag and drop a file here or click to select a file</label>
                <?php self::render_hidden_field_with_attributes($atts); ?>
            </form>
        </div>
        <?php
    }

    public static function render_vector_store_text_upload($atts) {
        ?>
        <div class="kissai-text-upload-area">
            <form id="kissai-text-upload-form" class="uploader">
                <p>
                    <input id="kissai-text-upload-name" type="text" name="kissai_text_upload_name" placeholder="Enter a text data name (optional). If empty, the first line of the text data will be used as the name."/>
                    <input type="hidden" name="kissai_text_file_id" />
                </p>
                <p>
                    <textarea name="kissai_text_upload" style="<?php echo esc_attr($atts['textarea_style']); ?>" placeholder="Enter text data..."></textarea>
                </p>
                <p class="kissai-widget-text-upload-button-container" style="text-align:center;">
                    <button type="submit" class="<?php echo esc_attr($atts['button_class']); ?>">Upload</button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function render_kissai_instructions_form($atts, $assistant = null) {
        if ($assistant === null) {
            $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
            $assistant = $chatgpt_api->get_assistant();
        }
        ?>
            <form id="kissai-admin-instructions-form" method="post">
                <p>
                    <label for='[name="kissai_admin_instructions"]'>Instructions:</label>
                </p>
                <p>
                    <textarea name="kissai_admin_instructions" style="<?php echo esc_attr($atts['textarea_style']); ?>" placeholder="Enter instructions..."><?php
                        if (isset($assistant['instructions'])) {
                            echo esc_textarea($assistant['instructions']);
                        }
                    ?></textarea>
                </p>
                <p class="kissai-widget-admin-button-container" style="text-align:center;">
                    <button type="submit" class="<?php echo esc_attr($atts['button_class']); ?>">Update</button>
                </p>
                <?php self::render_hidden_field_with_attributes($atts); ?>
            </form>
        <?php
    }

    public static function kissai_render_suggested_questions_ui($assistant_id)
    {
        // Sanitize to avoid potential issues
        $assistant_id_esc = esc_attr($assistant_id);

        // We'll create a container with an input box for the question,
        // "Add"/"Edit" buttons, and a table listing existing questions.
        // We'll also include some inline JS for handling user interactions.

        ob_start();
        ?>
        <div class="kissai-suggested-questions-container">
            <input type="hidden" id="kissai_assistant_id" name="kissai_assistant_id" value="<?php echo esc_attr($assistant_id); ?>">
            <h3>Manage Suggested Questions (Assistant: <?php echo esc_attr($assistant_id_esc); ?>)</h3>

            <!-- Informational Message -->
            <p><b>About Suggested Questions:</b><br>
                The questions in this list are automatically generated based on user requests from the KissAi Widget interface. You can review, edit, or add your own custom questions to enhance the user experience and ensure relevant suggestions. Up to <?php echo esc_attr(KissAi_DB::max_saved_suggested_questions); ?> suggested questions can be saved.</p>

            <!-- Hidden field to store assistant ID for AJAX requests -->
            <input type="hidden" id="kissai-assistant-id" value="<?php echo esc_attr($assistant_id_esc); ?>">

            <!-- Textbox for adding or editing a question -->
            <label for="kissai-question-input">Question:</label>
            <br>
            <textarea id="kissai-question-input" rows="2" style="width: 100%;"></textarea>
            <br><br>

            <!-- Action buttons for Add / Edit -->
            <!-- By default, we only show "Add". When an item in the list is selected, we'll show "Add" & "Edit". -->
            <button id="kissai-btn-add" style="display:inline-block;">Add</button>
            <button id="kissai-btn-update" style="display:none;">Update</button>

            <hr>

            <!-- We'll show the list (or table) of questions here -->
            <table id="kissai-questions-table" class="widefat striped">
                <thead>
                <tr>
                    <th>Select</th>
                    <th>Question</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <!-- Dynamically populated by JS -->
                </tbody>
            </table>
            <div class="button-container">
            <button id="kissai-btn-select-all">Select All</button>
            <button id="kissai-btn-delete-selected">Delete Selected</button>
            </div>
        </div>
        <?php
        // Return the entire block of HTML/JS
        return ob_get_clean();
    }

    public static function render_kissai_admin_form($atts) {
        ?>
        <div id="kissai-admin-widget-container" style="<?php echo esc_attr($atts['form_style']); ?>">
        <?php
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        if ($chatgpt_api === null) {
            ?>
                <p><?php echo self::openai_key_missing_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
            <?php
        }
        else {
            $assistant = $chatgpt_api->get_assistant();
            if ($assistant === null) {
                ?>
                <p><?php echo self::openai_no_assistant_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                <?php
            }
            ?>
            <h2><?php echo esc_attr($atts['admin_title']); ?></h2>
            <div id="kissai-file-list-container">
            <?php echo self::render_vector_store_file_list($atts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="kissai-file-list-update-response"></div>
            <div class="spinner-animation"><?php echo self::spinner_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

            <?php self::render_vector_store_file_upload($atts); ?>
            <?php self::render_divider($atts['admin_file_text_divider_text']); ?>
            <?php self::render_vector_store_text_upload($atts); ?>
            <?php self::render_kissai_instructions_form($atts, $assistant); ?>
            <?php echo self::kissai_render_suggested_questions_ui($assistant['id']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="admin-widget-response"></div>
            <?php
        }
        ?>
        </div>
        <?php
    }

    public static function render_kissai_shortcode_admin_mode($atts, $user = null) {
        ob_start();
    ?>
        <div id="kissai-widget-container">
    <?php
        if ($user != null) {
            if (!user_can($user, 'manage_options')) {
                self::render_no_permission_message($atts);
            } else {
                self::render_kissai_admin_form($atts);
            }
        }
        else if (is_user_logged_in()) {
            if (!current_user_can('manage_options')) {
                self::render_no_permission_message($atts);
            } else {
                self::render_kissai_admin_form($atts);
            }
        } else {
            self::render_kissai_login_form($atts);
        }
    ?>
        </div>
    <?php
        return ob_get_clean();
    }

    public static function extractJsonContent($inputString) {
        $startTag = "```json";
        $endTag = "```";

        // Check if the input string starts with the specified tag
        if (preg_match('/'. $startTag . '(.*?)' . $endTag . '/s', $inputString, $matches)) {
            return trim($matches[1]);
        }

        $startPosCurly  = strpos($inputString, '{');
        $startPosSquare = strpos($inputString, '[');

        // If neither is found, return empty
        if ($startPosCurly === false && $startPosSquare === false) {
            return "";
        }

        // Determine the earliest bracket
        if ($startPosCurly === false) {
            $startPos = $startPosSquare;
        } elseif ($startPosSquare === false) {
            $startPos = $startPosCurly;
        } else {
            $startPos = min($startPosCurly, $startPosSquare);
        }

        // Grab everything from first bracket until matching bracket closure.
        // We'll track nested { } and [ ] pairs to find the balanced end.
        $nesting   = 0;
        $length    = strlen($inputString);
        $jsonChunk = "";

        for ($i = $startPos; $i < $length; $i++) {
            $char = $inputString[$i];
            $jsonChunk .= $char;

            if ($char === '{' || $char === '[') {
                $nesting++;
            } elseif ($char === '}' || $char === ']') {
                $nesting--;
                // Once nesting hits 0, we've closed our top-level bracket
                if ($nesting === 0) {
                    break;
                }
            }
        }

        return trim($jsonChunk);

//        if (strpos($inputString, $startTag) >= 0) {
//            $strPos = strpos($inputString, $startTag);
//            if ($strPos !== false) {
//                $strPos += strlen($startTag);
//                // Find the position of the closing tag
//                $endPos = strpos($inputString, $endTag, $strPos);
//
//                // Extract the content between the tags
//                if ($endPos !== false) {
//                    $jsonContent = substr($inputString, $strPos, $endPos - $strPos);
//                    return trim($jsonContent);
//                }
//            }
//            else {
//                return $inputString;
//            }
//        }
//        else {
//
//        }
//
//        // If not found or invalid format, return an empty string or handle as needed
//        return "";
    }

    public static function render_suggested_questions_buttons($q_label, $style, $question, $class = 'query-sample') {
        $output = "";
        if ($question) {
            if ($style == "Button") {
                $output .= "<button class='{$class} button'>{$q_label}{$question}</button>";
            }
            else {
                $output .= "<a class='{$class}'>{$q_label}{$question}</a>";
            }
        }
        return $output;
    }

    public static function render_suggested_questions_reload_button() {
        $output = '';
        $output .= '<div class="reload-button-container">';
        $output .= '<a href="#" class="svgbutton load-query-sample">';
        $output .= self::rotate_right;
        $output .= '</a>';
        $output .= '</div>';
        return $output;
    }

    public static function render_suggested_questions_from_json($atts, $json) {
        $atts = self::get_kissai_shortcode_atts($atts);
        $q_label = $atts['suggested_questions_label'];
        if ($q_label !== null && $q_label !== '') {
            $q_label = "<span>{$q_label}</span>";
        }
        else {
            $q_label = '';
        }

        $output = "";
        if (!empty($json)) {
            $jsonData = json_decode($json);
            if ($jsonData) {
                foreach ($jsonData as $item) {
                    $output .= self::render_suggested_questions_buttons($q_label, $atts['suggested_questions_style'], $item->question);
                }
            }
        }
        return $output;
    }

    public static function render_suggested_questions($atts) {
        $atts = self::get_kissai_shortcode_atts($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        $assistant_id = $chatgpt_api->get_assistant_id();
        $output = "";
        if ($chatgpt_api === null) {
            return $output;
        }
        $result = $chatgpt_api->chatgpt_query($assistant_id, OpenAI_API::AI_SUGESSTED_QUESTIONS_PROMPT, ['thread.message.completed'], true);
        if ($result) {
            $completeMessage = array_filter($result, function ($item) {
                return array_key_exists('thread.message.completed', $item);
            });
        }
        $message = '';
        if (!empty($completeMessage)) {
            $message = reset($completeMessage)['thread.message.completed'];
        }
        if (!empty($message)) {
            $messageData = json_decode($message);
            $json = self::extractJsonContent($messageData->content[0]->text->value);
            $output .= self::render_suggested_questions_from_json($atts, $json);
        }
        return $output;
    }

    private static function render_kissai_shortcode_client_mode($atts) {
        $atts = self::get_kissai_shortcode_atts($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        $output = '';
        $instance_assistant_id = $atts['assistant_id'] ?? '';
        $suggested_questions_enabled = false;
        if (isset($atts['suggested_questions_enabled']) && $atts['suggested_questions_enabled'] === 'on') {
            $suggested_questions_enabled = true;
        }
        if (isset($atts['suggested_questions_auto_load']) && $atts['suggested_questions_auto_load'] === 'on') {
            $suggested_questions_auto_load = true;
        } else {
            $suggested_questions_auto_load = false;
        }
        $suggested_questions_trigger_text = $atts['suggested_questions_trigger_text'] !== '' ? $atts['suggested_questions_trigger_text'] : self::get_default_instance()['suggested_questions_trigger_text'];
        $q_label = $atts['suggested_questions_label'];
        if ($q_label !== null && $q_label !== '') {
            $q_label = "<span>{$q_label}</span>";
        }
        else {
            $q_label = '';
        }

        if ($chatgpt_api === null) {
            return '<p>'. self::openai_key_missing_message . '</p>';
        }
        else {
            if (empty($instance_assistant_id)) {
                ?>
                <p><?php echo self::openai_no_assistant_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                <?php
            }
            $output .= '<div id="kissai-widget-container" style="width:100%;">';
            $output .= '<div id="kissai-response">';
            if (self::is_in_editing_mode() === false)
                $output .= self::render_thread_messages($chatgpt_api->getThreadId(), $atts, $chatgpt_api->get_assistant_id());
            $output .= '</div>';
            $output .= '<div class="loading-animation-container">';
            $output .= self::render_loading_animation();
            $output .= '</div>';
            $output .= '<form id="kissai-form" method="post" style="' . esc_attr($atts['form_style']) . '">';
            $output .= self::render_hidden_field_with_attributes($atts, false);
            $output .= '<div class="kissai-widget-button-container"><a href="#" class="svgbutton delete-chat">' . self::delete_icon . '</a></div>';
            $output .= '<p>';
            $output .= '<textarea name="kissai_prompt" style="' . esc_attr($atts['textarea_style']) . '"></textarea>';
            $output .= '</p>';
            $output .= '<p class="kissai-widget-button-container" style="text-align:center;">';
            $output .= '<button type="submit" class="' . esc_attr($atts['button_class']) . '">Ask</button>';
            $output .= '</p>';
            $output .= '</form>';
            $output .= '<div class="suggested-questions">';
            if ($suggested_questions_enabled) {
                $output .= '<div class="suggested-questions-header">';
                $output .= esc_attr($atts['suggested_questions_text']);
                $output .= '</div>';
            }
            $output .= self::render_loading_animation();
            if ($suggested_questions_enabled) {
                $output .= '<div class="suggested-questions-body">';
                $output .= self::render_suggested_questions_buttons($q_label, $atts['suggested_questions_style'], $suggested_questions_trigger_text, 'load-query-sample');
                $output .= '</div>';
            }
            $output .= '</div>';
            $output .= '</div>';
            if ($suggested_questions_auto_load) {
                $output .= '<script>';
                $output .= 'jQuery(document).ready(function($) { load_suggested_questions(); });';
                $output .= '</script>';
            }
        }
        return $output;
    }

    public static function kissai_ajax_login() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $username = sanitize_text_field( wp_unslash($_POST['username'] ?? '') );
        $password = sanitize_text_field( wp_unslash($_POST['password'] ?? '') );
        $method = sanitize_text_field( wp_unslash($_POST['method'] ?? '') );
        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true
        );

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'Login failed. Please check your credentials and try again.'));
        } else {
            if ($method) {
                $content = self::execute_method_from_string($method, $atts, $user);
                if ($content !== null) {
                    wp_send_json_success(array(
                        'html' => $content,
                        'script' => self::get_reset_kissai_vars_nonce_script($user),
                        'delay' => 500
                    ));
                }
                else
                    wp_send_json_error(array('message' => 'Login Successful.'));
            }
            else {
                wp_send_json_success(array(
                    'html' => "",
                    'script' => self::get_reset_kissai_vars_nonce_script($user),
                    'delay' => 500
                ));
            }
        }
    }

    public static function kissai_ajax_admin_widget_refresh() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $atts = self::get_kissai_shortcode_atts(null);

        $assistant_id = sanitize_text_field( wp_unslash($_POST['assistant_id'] ?? '') );
        $atts = shortcode_atts($atts, ['assistant_id' => $assistant_id]);

        $atts = self::widget_atts_from_ajax($atts);

        // Check user permissions and prepare content accordingly
        $content = self::render_kissai_shortcode_admin_mode($atts);
        wp_send_json_success(array(
            'html' => $content,
            'script' => self::get_reset_kissai_vars_nonce_script(),
            'delay' => 500
        ));
    }

    public static function kissai_ask_ajax() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        if(isset($_POST['kissai_prompt'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $prompt = _sanitize_text_fields( wp_unslash( $_POST['kissai_prompt'] ?? '' ), true);
            $process_from = sanitize_text_field( wp_unslash( $_POST['process_from'] ?? '' ) );
            $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
            if ($chatgpt_api === null) {
                wp_send_json_error('API Key required.');
            }
            else {
                if ($chatgpt_api->getThreadId() !== null) {
                    $nonce = $chatgpt_api->chatgpt_save_nonce();
                    global $kissai_api;
                    if ($kissai_api->check_permission("create-message" ) === false) {
                        $response = ['error' => [
                                "message" => "It appears that your account doesn't have sufficient credit to complete this request. Please review your KissAi Credit balance or add credit to continue."
                        ]];
                        $response['message_type'] = OpenAI_API::MESSAGE_TYPE_RECEIVED;
                        $response['created_at'] = self::convertUnixToUTC(time());
                        $response['guid'] = $nonce;
                        wp_send_json_error($response);
                    }
                    $response = $chatgpt_api->chatgpt_create_message($prompt, $nonce);
                    if (isset($response['id'])) {
                        $timestamp = $response['created_at'] ?? time();
                        $thread_id = $response['thread_id'];
                        $vector_store_id = $chatgpt_api->get_vector_store_id();
                        $created_at = self::convertUnixToUTC($timestamp);
                        if ($process_from == 'server') {
                            $rest_url = home_url( '/wp-json/kissai_api/v1/run_thread/' );

                            $response = wp_remote_post($rest_url, [
                                'headers'   => [
                                    'X-Custom-Secret' => $chatgpt_api->get_bg_process_api_key(),
                                    'Content-Type' => 'application/json',
                                ],
                                'body'      => json_encode(['nonce' => $nonce]),
                                'data_format' => 'body',
                                'permission_callback' => '__return_true',
                                'sslverify' => false, // Disable SSL verification
                                'blocking' => false, // Make the request non-blocking
                                'timeout' => 0.01, // Set a very short timeout
                            ]);

                            if (is_wp_error($response)) {
                                // Handle error appropriately; perhaps log it and return false or null
                                kissai_error_log('Error calling back kissai_run_thread_bg: ' . $response->get_error_message());
                                return null; // Indicate failure to the caller
                            }
                            else {
                                $response_code = wp_remote_retrieve_response_code($response);
                                $response_body = wp_remote_retrieve_body($response);
                                if ($response_code >= 400) {
                                    // Log the error or handle it as needed
                                    kissai_error_log('Error response from run thread API: ' . $response_body . ' (Code ' . $response_code . ')');
                                    return false; // Or include more detailed error handling as needed
                                }
                                wp_send_json_success(
                                    ['message_type' => OpenAI_API::MESSAGE_TYPE_SENT,
                                    'message' => nl2br($prompt, false),
                                    'guid' => $nonce,
                                    'created_at' => $created_at]);
                            }
                        }
                        else if ($process_from == 'local') {
                            $chatgpt_api->update_thread($thread_id, [
                                'tool_resources' => [
                                    'file_search' => [
                                        'vector_store_ids' => [ $vector_store_id ]
                                    ]
                                ]
                            ]);
                            $url = strtr(OpenAI_Endpoints::runs, array('{thread_id}' => $thread_id));
                            $headers = $chatgpt_api->get_header();

                            $body = array(
                                'assistant_id' => $chatgpt_api->get_assistant_id(),
                                'stream' => true,
                                'tool_choice' => ['type' => 'file_search'],
                            );

                            wp_send_json_success(
                                ['message_type' => OpenAI_API::MESSAGE_TYPE_SENT,
                                'message' => nl2br($prompt, false),
                                'fetch_url' => $url,
                                'fetch_headers' => $headers,
                                'fetch_body' => $body,
                                'fetch_message_type' => OpenAI_API::MESSAGE_TYPE_RECEIVED,
                                'guid' => $nonce,
                                'created_at' => $created_at,
                            ]);
                        }
                    } else {
                        if ( isset( $response['error'] ) && isset( $response['error']['message'] ) ) {
                            $response['message_type'] = OpenAI_API::MESSAGE_TYPE_RECEIVED;
                            $response['created_at'] = self::convertUnixToUTC(time());
                            $response['guid'] = $nonce;
                            wp_send_json_error($response);
                        }
                        else
                            wp_send_json_error(['message' => 'API response is not valid.']);
                    }
                }
            }
        } else {
            wp_send_json_error('No prompt provided!');
        }

        wp_die();
    }

    public static function kissai_fetch_delta_ajax() {
        global $kissai_db;
        check_ajax_referer('kissai_nonce', 'nonce');

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        if (isset($_POST['guid']) && isset($_POST['seq'])) {
            $seq = intval(sanitize_text_field( wp_unslash( $_POST['seq'] ) ));
            $guid = sanitize_text_field( wp_unslash( $_POST['guid'] ) );
            $attempt = sanitize_text_field( wp_unslash($_POST['atmt'] ?? '' ) );

            $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
            if ($chatgpt_api === null) {
                wp_send_json_error('API Key required.');
            }
            else {
                if ($chatgpt_api->getThreadId() !== null) {
                    // Assuming get_event_data() method fetches the specific event data based on guid and seq
                    $response = $kissai_db->get_event_data($guid, 'thread.message.delta', $seq);

                    if ($response) {
                        $responseData = json_decode($response->data, true);
                        $max_seq = $response->max_seq;
                        if (isset($responseData['delta']['content'][0]['text']['value'])) {
                            $message = $responseData['delta']['content'][0]['text']['value'];
                            $timestamp = $responseData['delta']['created_at'];
                            $created_at = self::convertUnixToUTC($timestamp);

                            // Replace newline characters with HTML <br> tags
                            $message = nl2br($message, false);
                            $seq_id = intval($response->sequence_id);
                            wp_send_json_success([
                                'message_type' => OpenAI_API::MESSAGE_TYPE_RECEIVED,
                                'message' => $message,
                                'guid' => $guid,
                                'created_at' => $created_at,
                                'max_seq' => $max_seq,
                                'seq' => $seq_id // Pass the next sequence number for client-side JS to use
                            ]);
                        } else {
                            wp_send_json_error('Data format unexpected.');
                        }
                    } else {
                        if ($attempt < OpenAI_API::MAX_GET_EVENT_RETRY) {
                            $response = $kissai_db->get_event_data($guid, 'thread.message.completed');
                            if ($response) {
                                if (count($response) === 1) {
                                    $seq_completed = intval($response[0]->sequence_id);
                                    if ($seq_completed > $seq) {
                                        wp_send_json_success([
                                            'script' => 'append_message("' . $guid . '", ' . $seq .',' . ($attempt + 1) .');'
                                        ]);
                                    }
                                    else if ($seq_completed === $seq) {
                                        $storedData = json_decode($response[0]->data);
                                        if (defined(CHATGPT_REMOVE_DELTA) && CHATGPT_REMOVE_DELTA !== false) {
                                            $kissai_db->remove_event_data($guid, 'thread.message.delta');
                                        }
                                        wp_send_json_success([
                                            'end_of_stream' => true,
                                            'message' => $storedData->content[0]->text->value
                                        ]);
                                    }
                                }
                            }
                            else {
                                $eventCnt = $kissai_db->get_event_count($guid, 'thread.message.delta');
                                if ($eventCnt > 0) {
                                    if ($eventCnt > $seq) {
                                        wp_send_json_success([
                                            'script' => 'append_message("' . $guid . '", ' . $seq .',' . ($attempt + 1) .');'
                                        ]);
                                    }
                                    else {
                                        wp_send_json_success([
                                            'script' => 'append_message("' . $guid . '", ' . $seq .',' . ($attempt + 1) .');',
                                            'delay' => 1000
                                        ]);
                                    }
                                }
                            }
                        }
                        else {
                            wp_send_json_error('No data found for the given parameters. (Maximum retry allowed exceeded)');
                        }
                    }
                } else {
                    wp_send_json_error('Thread ID is not set.');
                }
            }
        } else {
            wp_send_json_error('Required parameters not provided.');
        }

        wp_die(); // Terminate and return a proper response
    }

    public static function kissai_ajax_admin_refresh() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);

        $content = self::render_kissai_shortcode_admin_mode($atts);
        wp_send_json_success(array(
            'html' => $content,
            // No need to update nonce at this stage. Only required when user login status is changed from annonymouse to logged in.
            // 'script' => self::get_reset_kissai_vars_nonce_script(),
            // 'delay' => 500
        ));
    }

    public static function kissai_ajax_admin_file_list_refresh() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);

        $content = self::render_vector_store_file_list($atts);
        wp_send_json_success(array(
            'html' => $content,
            // No need to update nonce at this stage. Only required when user login status is changed from annonymouse to logged in.
            // 'script' => self::get_reset_kissai_vars_nonce_script(),
            // 'delay' => 500
        ));
    }

    public static function kissai_ajax_admin_instruction_update() {
        check_ajax_referer('kissai_nonce', 'nonce');

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            $headers = $chatgpt_api->get_header();
            $url = strtr(OpenAI_Endpoints::assistant, array( '{assistant_id}' => $chatgpt_api->get_assistant_id()));
            wp_send_json_success(
                ['fetch_url' => $url,
                'fetch_headers' => $headers]);
        }
    }

    public static function kissai_delete_file_ajax() {
        check_ajax_referer('kissai_nonce', 'nonce'); // Check nonce for security

        $file_id = isset($_POST['file_id']) ? sanitize_text_field(wp_unslash($_POST['file_id'])) : false;
        if (!$file_id) {
            wp_send_json_error(['message' => 'Invalid file ID']);
        }

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);

        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            $result = $chatgpt_api->delete_vector_store_file($file_id);
            if ($result) {
                $result = $chatgpt_api->delete_file($file_id);
                if ($result){
                    wp_send_json_success();
                }
                else {
                    wp_send_json_error(['message' => 'File is removed from vector store but not deleted from files']);
                }
            } else {
                wp_send_json_error(['message' => 'Failed to delete the file']);
            }
        }
    }

    public static function make_kissai_file_path($ai_service, $assistant_id) {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/' . 'kissai' . '/' . $ai_service . '/'. $assistant_id;
        return $base_dir;
    }

    public static function kissai_handle_file_upload() {
        global $kissai_db, $kissai_api;
        check_ajax_referer('kissai_nonce', 'nonce'); // Check the nonce for security

        $process_from = sanitize_text_field( wp_unslash( $_POST['process_from'] ?? '' ) );
        // If $file_name is provided separately via POST, sanitize & unslash it too
        $file_name    = isset($_POST['filename'])
            ? sanitize_file_name( wp_unslash($_POST['filename']) )
            : null;
        
        $file_id      = isset($_POST['file_id'])
            ? sanitize_text_field( wp_unslash($_POST['file_id']) )
            : null;

        $atts        = self::get_kissai_shortcode_atts(null);
        $atts        = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);

        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        } else {
            $assistant_id = $chatgpt_api->get_assistant_id();
            $file_count   = $kissai_db->get_assistant_knowledge_count($assistant_id) + 1;
            $permitted = true;
            

            if (! $permitted) {
                wp_send_json_error('Your current license does not permit adding additional training materials.');
            }

            // If $file_id and $file_name are provided, remove existing file from OpenAI if needed
            if ($file_name !== null && $file_id !== null) {
                $existing_file = $kissai_db->find_knowledge_id_by_assistant_and_name($assistant_id, $file_name);
                if ($existing_file) {
                    if ($chatgpt_api->delete_file($file_id)) {
                        // File was deleted from OpenAI server
                        OpenAI_API::add_deleted_openai_file_id($file_id);
                    }
                }
            }

            // Branch based on $process_from
            if ($process_from === 'server') {
                $base_dir = self::make_kissai_file_path('openai', $assistant_id);

                if (! file_exists($base_dir)) {
                    wp_mkdir_p($base_dir);
                }

                // ------------------------------------------------------------------
                // PHPCS sees $_FILES as unsanitized input, so we handle it manually:
                // ------------------------------------------------------------------
                // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw_file_array = isset($_FILES['file']) ? $_FILES['file'] : null;
                // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

                if ($raw_file_array) {
                    // Because $raw_file_array['name'] can be single or an array
                    if (is_array($raw_file_array['name'])) {
                        // Multiple files
                        foreach ($raw_file_array['name'] as $index => $original_name) {
                            // Unslash and sanitize each filename
                            $unslashed_name = wp_unslash($original_name);
                            $raw_file_array['name'][$index] = sanitize_file_name($unslashed_name);
                            
                        }
                    } else {
                        // Single file
                        $unslashed_name = wp_unslash($raw_file_array['name']);
                        $raw_file_array['name'] = sanitize_file_name($unslashed_name);
                        
                    }

                    // Optionally override the sanitized single filename if $file_name is set
                    // (If you really need to override the name passed in $_FILES)
                    if (!empty($file_name) && !is_array($raw_file_array['name'])) {
                        $raw_file_array['name'] = $file_name; // Already sanitized above
                    }

                    $responses = [];
                    // Now that $raw_file_array['name'] is sanitized, proceed
                    if (is_array($raw_file_array['name'])) {
                        // Multiple files
                        foreach ($raw_file_array['name'] as $key => $sanitized_name) {
                            $response = self::process_single_file($raw_file_array, $key, $base_dir);
                            if ($response['result']) {
                                $kissai_file_id = $kissai_db->update_knowledge_path($assistant_id, $sanitized_name, $response['path']);
                                if ($kissai_file_id) {
                                    $response['kissai_file_id'] = $kissai_file_id;
                                }
                            }
                            $responses[] = $response;
                        }
                    } else {
                        // Single file scenario
                        $response = self::process_single_file($raw_file_array, null, $base_dir);
                        if ($response['result']) {
                            $kissai_file_id = $kissai_db->update_knowledge_path($assistant_id, $raw_file_array['name'], $response['path']);
                            if ($kissai_file_id) {
                                $response['kissai_file_id'] = $kissai_file_id;
                            }
                        }
                        $responses[] = $response;
                    }
                    wp_send_json_success($responses);
                } else {
                    wp_send_json_error('No files provided.');
                }

            } elseif ($process_from === 'local') {
                $url     = OpenAI_Endpoints::files;
                $headers = $chatgpt_api->get_header();
                unset($headers['Content-Type']);

                global $kissai_api;
                $kissai_user_id    = $kissai_api->get_kissai_user_id();
                $kissai_api_header = $kissai_api->get_header();
                unset($kissai_api_header['Content-Type']);

                wp_send_json_success([
                    'guid'                => $chatgpt_api->get_session_id(),
                    'fetch_url'           => $url,
                    'kissai_endpoint'     => KissAi_API_Endpoints::FILE,
                    'kissai_header'       => $kissai_api_header,
                    'kissai_user_id'      => $kissai_user_id,
                    'assistant_id'        => $assistant_id,
                    'create_vector_store_url' => strtr(
                        OpenAI_Endpoints::vector_store_files,
                        [
                            '{vector_store_id}' => $chatgpt_api->get_vector_store_id(),
                            '{limit}'           => 100
                        ]
                    ),
                    'fetch_headers' => $headers
                ]);
            }
        }
    }

    public static function kissai_update_knowledge_file_id() {
        global $kissai_db;
        check_ajax_referer('kissai_nonce', 'nonce'); // Check the nonce for security

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            $assistant_id = sanitize_text_field(wp_unslash($_POST['assistant_id'] ?? ''));
            $file_name = sanitize_text_field(wp_unslash($_POST['file_name'] ?? ''));
            $file_id = sanitize_text_field(wp_unslash($_POST['file_id'] ?? ''));
            $updated = $kissai_db->update_knowledge_file_id($assistant_id, $file_name, $file_id);
            if ($updated) {
                wp_send_json_success(
                    ['guid' => $chatgpt_api->get_session_id(),
                    'updated_id' => $updated]);
            }
            else {
                wp_send_json_error('Update knowledge file_id failed.');
            }
        }
    }

    public static function kissai_handle_file_download() {
        check_ajax_referer('kissai_nonce', 'nonce'); // Check the nonce for security

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        global $kissai_db;
        if (!isset($_POST['file_id']) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Invalid file ID or insufficient permissions']);
            exit;
        }
        $file_id = sanitize_text_field(wp_unslash($_POST['file_id']));

        $file_path = $kissai_db->get_knowledge_file_path_by_file_id($file_id);

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(['message' => 'File does not exist']);
            exit;
        }
        $upload_dir = wp_upload_dir();

        $file_url = str_replace($upload_dir['basedir'], $upload_dir['url'], $file_path);

        wp_send_json_success(
            ['url' => $file_url]);
    }

    public static function kissai_handle_file_edit() {
        check_ajax_referer('kissai_nonce', 'nonce'); // Check the nonce for security

        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);

        if (!isset($_POST['file_id']) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Invalid file ID or insufficient permissions']);
            exit;
        }

        $assistant_id = self::get_assistant_id_from_widget_atts($atts);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $file_id = sanitize_text_field(wp_unslash($_POST['file_id']));
        $file_name = sanitize_text_field(wp_unslash($_POST['file_name'] ?? ''));
        global $wpdb, $kissai_db;
        $file_path = $kissai_db->get_knowledge_file_path_by_file_id($file_id);
        if (!$file_path) {
            $kissai_file_id = $kissai_db->find_knowledge_id_by_assistant_and_name($assistant_id, $file_name);
            if ($kissai_file_id) {
                $file_path = $kissai_db->get_knowledge_file_path_by_file_id($kissai_file_id);
                if ($file_path) {
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $updated = $wpdb->update(
                        $kissai_db->table_names->assistant_knowledges,
                        ['file_id' => $file_id],  // New values
                        ['id' => $kissai_file_id]   // Where conditions
                    );
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    if ($updated) {
                        kissai_error_log("info: file_id is updated by kissai_handle_file_edit (id: " . $kissai_file_id . " / file_id: " . $file_id . " / file_name: " . $file_name . ")");
                    }
                }
            }
        }

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(['message' => 'File does not exist']);
            exit;
        }

        // Check if the file is a .txt file
        if (pathinfo($file_path, PATHINFO_EXTENSION) === 'txt') {
            $title = basename($file_path, '.txt');
            $body = file_get_contents($file_path);

            if ($body === false) {
                wp_send_json_error(['message' => 'Failed to read the file contents']);
                exit;
            }

            wp_send_json_success([
                'file_id' => $file_id,
                'title' => $title,
                'body' => $body
            ]);
        } else {
            wp_send_json_error(['message' => 'The file is not a text file']);
        }
    }


    public static function process_single_file( $file_array, $key, $base_dir ) {
        // 1) Check the raw PHP file error code
        $error = ( $key !== null ) ? $file_array['error'][ $key ] : $file_array['error'];

        if ( $error !== UPLOAD_ERR_OK ) {
            // Same switch-case logic from your original code
            switch ( $error ) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "File too large";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = "File upload was not completed";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = "No file was uploaded";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = "No temporary directory";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = "Failed to write file to disk";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = "File upload stopped by extension";
                    break;
                default:
                    $message = "Unknown upload error";
                    break;
            }
            kissai_error_log( "process_single_file() $message" );
            return [ 'result' => false, 'message' => $message ];
        }

        // 2) Build the final target path in your custom $base_dir
        $filename  = ( $key !== null ) ? $file_array['name'][ $key ] : $file_array['name'];
        $base_dir  = rtrim( $base_dir, '/\\' );
        $file_path = $base_dir . '/' . $filename;

        // 3) Load WordPress's file handling functions if not already loaded
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // 4) If a file with the same name already exists in your target dir, delete it
        //    using WP_Filesystem. Must init WP_Filesystem *before* we do anything else.
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Request credentials if needed (for FTP, etc.)
        $creds = request_filesystem_credentials( admin_url() );
        if ( ! WP_Filesystem( $creds ) ) {
            // If we cannot init WP_Filesystem, bail out with an error
            kissai_error_log( 'process_single_file() Could not initialize WP_Filesystem.' );
            return [
                'result'  => false,
                'message' => 'Could not initialize WP_Filesystem.',
            ];
        }

        global $wp_filesystem;

        if ( $wp_filesystem->exists( $file_path ) ) {
            kissai_error_log( "process_single_file() $file_path already exists" );
            if ( ! $wp_filesystem->delete( $file_path ) ) {
                kissai_error_log( "process_single_file() $file_path could not be deleted" );
                return [
                    'result'  => false,
                    'message' => 'Failed to delete existing file before moving new file',
                ];
            }
        }

        // 5) Upload the file from PHP temp to WordPress's uploads folder
        $uploaded_file = [
            'name'     => $filename,
            'type'     => ( $key !== null ) ? $file_array['type'][ $key ]     : $file_array['type'],
            'tmp_name' => ( $key !== null ) ? $file_array['tmp_name'][ $key ] : $file_array['tmp_name'],
            'error'    => $error,
            'size'     => ( $key !== null ) ? $file_array['size'][ $key ]     : $file_array['size'],
        ];
        $upload_overrides = [ 'test_form' => false ];

        $upload_result = wp_handle_upload( $uploaded_file, $upload_overrides );

        if ( isset( $upload_result['error'] ) && $upload_result['error'] ) {
            kissai_error_log( "process_single_file() wp_handle_upload error: {$upload_result['error']}" );
            return [
                'result'  => false,
                'message' => $upload_result['error'],
            ];
        }

        // 6) Now we have a file in WP's uploads folder at $upload_result['file']
        $source_path = $upload_result['file'];

        // 7) Use WP_Filesystem to "move" (rename) from uploads to $file_path
        //    The 3rd param "true" = overwrite if file exists (we already deleted any existing above).
        $moved = $wp_filesystem->move( $source_path, $file_path, true );
        if ( ! $moved ) {
            // If move failed, do cleanup
            $wp_filesystem->delete( $source_path );
            kissai_error_log( "process_single_file() Could not move {$source_path} to {$file_path} with WP_Filesystem." );
            return [
                'result'  => false,
                'message' => 'Failed to move the uploaded file',
            ];
        }

        // 8) Success
        return [
            'result'  => true,
            'message' => 'File uploaded successfully',
            'file'    => $filename,   // same final name
            'path'    => $file_path,  // your custom directory
        ];
    }




    public static function render_js_shortcode_settings_block($atts) {
        // Sanitize attributes to ensure they're safe for output
        $json_atts = array_map('esc_js', $atts);
        // Encode attributes to JSON
        $json_encoded_atts = json_encode($json_atts);

        // Output the JavaScript block
        return "<script type='text/javascript'>
            var KissAiSettings = {$json_encoded_atts};
        </script>";
    }

    public static function render_vector_store_file_list($atts) {
        global $kissai_db, $kissai_api;
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        $output = "";

        if ($chatgpt_api !== null && !empty($atts['assistant_id'])) {
            $vector_store_id = $chatgpt_api->get_vector_store_id();
            $files           = $chatgpt_api->get_vector_store_files($vector_store_id);

            // Check if $files is a valid array with 'data' before counting
            $file_count = (is_array($files) && isset($files['data']) && is_array($files['data']))
                ? count($files['data'])
                : 0;

            // Update local DB knowledge count
            $kissai_db->update_assistant_knowledge_count(
                $chatgpt_api->get_assistant_id(),
                $file_count
            );

            $output = '<div class="kissai-file-list-title">' . esc_attr($atts['admin_file_list_title']) . '</div>';
            $output .= '<div class="kissai-file-list"><ul>';

            // Safely check if $files is valid and that the API call succeeded
            if (
                is_array($files)
                && OpenAI_API::is_call_get_api_success($files)
                && isset($files['data'])
                && is_array($files['data'])
            ) {
                foreach ($files['data'] as $key => $value) {
                    $file_id = $value['id'];
                    // Skip files that appear to be deleted or have zero bytes
                    if (
                        OpenAI_API::is_deleted_openai_file_id($file_id)
                        || (!empty($value['bytes']) && $value['bytes'] == 0)
                    ) {
                        kissai_error_log(
                            "render_vector_store_file_list() deleted/zero-byte file skipped. ID: {$file_id} | size: {$value['bytes']}"
                        );
                        continue;
                    }

                    $created_at = self::convert_unix_timestamp_to_wordpress_timezone($value['created_at']);

                    $output .= '<li data-file-id="' . esc_attr($file_id) . '">';
                    $output .= '<div>' . esc_html($value['filename']) . '</div>'
                        .  '<div>' . esc_html(self::format_size_units($value['bytes'])) . '</div>';
                    $output .= '<div class="file-list-datetime">' . esc_html($created_at) . '</div>';

                    // Check if we know a local path for this file
                    $file_path = $kissai_db->get_knowledge_file_path_by_file_id($file_id);

                    if ($file_path !== null) {
                        if (str_ends_with($value['filename'], 'txt')) {
                            // Provide an Edit link
                            $output .= '<a href="#" class="svgbutton edit-file" '
                                . 'data-file-name="' . esc_attr($value['filename']) . '" '
                                . 'data-file-id="' . esc_attr($file_id) . '">'
                                . self::edit_icon . '</a>';
                        } else {
                            // Provide a Download link
                            $output .= '<a href="#" class="svgbutton download-file" '
                                . 'data-file-id="' . esc_attr($file_id) . '">'
                                . self::download_icon . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                    } else {
                        // If the file doesn't exist on disk
                        $output .= '<span class="svgbutton disabled" title="File not found">';
                        if (str_ends_with($value['filename'], 'txt')) {
                            $output .= self::edit_icon;
                        } else {
                            $output .= self::download_icon;
                        }
                        $output .= '</span>';
                    }

                    // Provide a Delete link
                    $output .= '<a href="#" class="svgbutton delete-file" data-file-id="' . esc_attr($file_id) . '">'
                        . self::delete_icon . '</a>';
                    $output .= '</li>';
                }
            } else {
                // If $files is null/invalid, you might want to log or show a message
                kissai_error_log(
                    "render_vector_store_file_list() get_vector_store_files() returned invalid or empty data for store ID: $vector_store_id"
                );
                // Optionally, you could display a message instead of returning an empty list
                //$output .= '<li>' . esc_html__('No files found or error retrieving files.', 'kissai') . '</li>';
            }

            $output .= '</ul>';

            // Show a message if user has reached max uploads
            $user       = $kissai_api->get_current_kissai_user();
            $permitted = true;
            

            if (!$permitted) {
                $output .= "<div style='text-align: center;'>"
                    . "It looks like you've uploaded the maximum number of training files allowed for your current plan. "
                    . "Please <a href='" . esc_url(admin_url("admin.php?page=kissai-plugin-register")) . "'>update your license</a> to access all features</div>";
            }

            /// TODO: update the number 100 with latest information
            /// https://platform.openai.com/docs/api-reference/vector-stores-files/listFiles#vector-stores-files-listfiles-limit
            // If we have (or detect) 100 files, note the OpenAI limit
            if ($file_count === 100) {
                $output .= '<div>' . esc_html__(
                        'OpenAI API allows showing only up to 100 files.',
                        'kissai'
                    ) . '</div>';
            }

            $output .= '</div>';
        }

        return $output;
    }

    public static function kissai_shortcode($atts, $content = "") {
        $output = "";
        $atts = self::get_kissai_shortcode_atts( $atts );
        if ($atts['mode'] === 'admin') {
            $output .= self::render_kissai_shortcode_admin_mode($atts);
        }
        else {
            $output .= self::render_kissai_shortcode_client_mode($atts);
        }

        return $output;
    }

    public static function kissai_save_message_ajax() {
        check_ajax_referer('kissai_nonce', 'nonce');
        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        global $kissai_db;
        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            if (isset($_POST['guid'], $_POST['seq'], $_POST['event'], $_POST['data'])) {
                $seq = intval(sanitize_text_field(wp_unslash($_POST['seq'])));
                $guid = sanitize_text_field(wp_unslash($_POST['guid']));
                $eventName = sanitize_text_field(wp_unslash($_POST['event']));
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $eventData = wp_unslash($_POST['data']);
        
                $event = json_decode($eventData);  // making sure the data json is properly formatted
                if ($eventName == "thread.message.completed") {
                    if ($event) {
                        $chatgpt_api->save_message_and_token_count($guid, OpenAI_API::MESSAGE_TYPE_RECEIVED, $eventData);
                    }
                }
                if (json_last_error() === JSON_ERROR_NONE) {
                    $kissai_db->store_event_data($guid, $eventName, $eventData, $seq);
                    wp_send_json_success([
                        'guid' => $guid,
                        'seq' => $seq // Pass the next sequence number for client-side JS to use
                    ]);
                }
                else {
                    wp_send_json_error('Invalid JSON data.');
                }
            } else {
                wp_send_json_error('Missing data.');
            }
        }
        wp_die(); // Terminate and return a proper response
    }

    public static function kissai_save_usage_ajax() {
        check_ajax_referer('kissai_nonce', 'nonce');
        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        global $kissai_db;
        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            if (isset($_POST['guid'], $_POST['seq'], $_POST['event'], $_POST['data'])) {
                $seq = intval(sanitize_text_field(wp_unslash($_POST['seq'])));
                $guid = sanitize_text_field(wp_unslash($_POST['guid']));
                $eventName = sanitize_text_field(wp_unslash($_POST['event']));
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $eventData = wp_unslash($_POST['data']);

                $event = json_decode($eventData);  // making sure the data json is properly formatted
                if ($eventName == "thread.run.completed") {
                    if ($event) {
                        $chatgpt_api->update_token_usage($guid, $event);
                    }
                }
                if (json_last_error() === JSON_ERROR_NONE) {
                    $kissai_db->store_event_data($guid, $eventName, $eventData, $seq);
                    wp_send_json_success([
                        'guid' => $guid,
                        'seq' => $seq // Pass the next sequence number for client-side JS to use
                    ]);
                }
                else {
                    wp_send_json_error('Invalid JSON data.');
                }
            } else {
                wp_send_json_error('Missing data.');
            }
        }
        wp_die(); // Terminate and return a proper response
    }

    public static function kissai_ajax_delete_chat() {
        check_ajax_referer('kissai_nonce', 'nonce');
        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            $old_thread_id = $chatgpt_api->getThreadId();
            kissai_error_log('kissai_ajax_delete_chat() old_thread_id: ' . $old_thread_id);
            $chatgpt_api->chatgpt_new_thread_and_start_new_session();
            $new_thread_id = $chatgpt_api->getThreadId();
            kissai_error_log('kissai_ajax_delete_chat() new_thread_id: ' . $new_thread_id);
            if ($new_thread_id && $old_thread_id != $new_thread_id) {
                wp_send_json_success([
                    'message' => 'This chat room is cleared!',
                    'thread_id' => $new_thread_id
                ]);
            }
            else {
                wp_send_json_error('Invalid JSON data.');
            }
        }
        wp_die(); // Terminate and return a proper response
    }

    public static function kissai_ajax_suggested_questions() {
        check_ajax_referer('kissai_nonce', 'nonce');
        $atts = self::get_kissai_shortcode_atts(null);
        $atts = self::widget_atts_from_ajax($atts);
        if (!isset($atts['assistant_id'])) {
            wp_send_json_error('An Assistant is not assigned to KissAi Widget.');
        }
        $chatgpt_api = self::get_chatgpt_api_with_widget_atts($atts);
        if ($chatgpt_api === null) {
            wp_send_json_error('API Key required.');
        }
        else {
            $assistant_id = $chatgpt_api->get_assistant_id();
            if ($assistant_id === null) {
                wp_send_json_error('The assistant ' . $atts['assistant_id'] . ' cannot be accessed. Please make sure your API key is correct');
            }
            else {
                $assistant = $chatgpt_api->set_assistant($assistant_id);
                if ($assistant) {
                    $thread_id = $chatgpt_api->getThreadId();
                    $chatgpt_api->set_thread_id($thread_id);
                    $reload_button = self::render_suggested_questions_reload_button();
                    $process_from = 'server';
                    if ($process_from == 'server') {
                        $response = $chatgpt_api->query_suggested_questions($assistant_id);
                        kissai_error_log('kissai_ajax_suggested_questions() response: ' . json_encode($response));
                        $data = json_decode($response);
                        if (!is_array($data)) {
                            $data = []; // default to empty array on invalid JSON or empty
                        }
                        wp_send_json_success(
                            ['count' => count($data),
                                'questions' => $data,
                                'reload_button' => $reload_button,
                            ]);
                    }
                    else {
                        $prompt = OpenAI_API::AI_SUGESSTED_QUESTIONS_PROMPT;
                        $response = $chatgpt_api->chatgpt_create_message($prompt);
                        if (isset($response['id'])) {
                            $url = strtr(OpenAI_Endpoints::runs, array('{thread_id}' => $thread_id));
                            $headers = $chatgpt_api->get_header();

                            $body = array(
                                'assistant_id' => $assistant_id,
                                'stream' => true,
                            );

                            wp_send_json_success(
                                ['message_type' => OpenAI_API::MESSAGE_TYPE_SENT,
                                    'message' => nl2br($prompt, false),
                                    'fetch_url' => $url,
                                    'fetch_headers' => $headers,
                                    'fetch_body' => $body,
                                    'fetch_message_type' => OpenAI_API::MESSAGE_TYPE_RECEIVED,
                                    'reload_button' => $reload_button,
                                ]);
                        }
                        else
                            kissai_error_log("Error : ChatGPT response format error -" . json_encode($response));
                    }
                }
            }
        }
        wp_die(); // Terminate and return a proper response
    }

    /**
     * 2) Handler to get existing suggested questions.
     *    We'll assume you have a method $kissai_db->get_assistant_setting($assistant_id, 'suggested_questions').
     */
    public static function kissai_get_suggested_questions() {
        check_ajax_referer('kissai_nonce', 'nonce');
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field(wp_unslash($_POST['assistant_id'])) : '';
        if (empty($assistant_id)) {
            wp_send_json_error('assistant_id missing.');
        }
        global $kissai_db;
        $questions = $kissai_db->get_suggested_questions($assistant_id, -1, true);
        if ($questions === null) {
            $questions = [];
        }
        // Send back as JSON
        wp_send_json_success(['count' => count($questions),
            'questions' => $questions]);
    }

    /**
     * 3) Handler to save updated suggested questions.
     *    We'll assume you have a method $kissai_db->save_suggested_questions($assistant_id, $questions_input).
     */
    public static function kissai_save_suggested_questions() {
        // Security checks
        check_ajax_referer('kissai_nonce', 'nonce');
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $assistant_id = isset($_POST['assistant_id']) ? sanitize_text_field(wp_unslash($_POST['assistant_id'])) : '';
        if (empty($assistant_id)) {
            wp_send_json_error('No assistant_id provided.');
        }
        $merge_mode = isset($_POST['merge_mode']) ? (sanitize_text_field(wp_unslash($_POST['merge_mode'])) === "true") : true;

        // "questions" is an array of strings or a JSON string
        // phpcs:ignore
        $questions = isset($_POST['questions']) ? wp_unslash($_POST['questions']) : '';

        if (empty($questions)) {
            // If no data, we can clear or skip
            wp_send_json_error('No questions provided.');
        }

        $questions = wp_unslash($questions);

        global $kissai_db;
        // Your function that merges & saves:
        $questions_array = json_decode($questions);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions_array)) {
            wp_send_json_error('Questions provided is not a valid format.');
        }
        else {
            foreach ($questions_array as $q) {
                if (!is_string($q)) {
                    wp_send_json_error('All questions must be strings.');
                }
            }
            if (count($questions_array) > KissAi_DB::max_saved_suggested_questions) {
                wp_send_json_error('Maximum saved suggested questions ' . KissAi_DB::max_saved_suggested_questions . ' reached.');
            }
            else {
                $success = $kissai_db->save_suggested_questions($assistant_id, $questions_array, $merge_mode);

                if ($success) {
                    wp_send_json_success('Questions saved successfully.');
                } else {
                    wp_send_json_error('Failed to save questions.');
                }
            }
        }
    }

}

add_shortcode('kissai_chat', [KissAi_Widget::class, 'kissai_shortcode']);

function register_kissai_widget() {
    register_widget('KissAi_Widget');
}
add_action('widgets_init', 'register_kissai_widget');

add_action('rest_api_init', function () {
    register_rest_route('kissai_api/v1', '/run_thread/', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'kissai_run_thread_bg',
        'permission_callback' => function () {
            $server_ip = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : null;
            $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
            if ($server_ip === null || $remote_ip === null || $server_ip !== $remote_ip) {
                return false;
            }
            return true;
        }
    ));
});

function kissai_run_thread_bg($request) {
    $server_ip = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : null;
    $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
    if ($server_ip === null || $remote_ip === null || $server_ip !== $remote_ip) {
        return new WP_Error('unauthorized', 'Unauthorized access', ['status' => 403]);
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $provided_secret = $_SERVER['HTTP_X_CUSTOM_SECRET'] ?? '';

    global $kissai_api;

    $chatgpt_api = $kissai_api->get_ai_api_instance();

    if ($provided_secret !== $chatgpt_api->get_bg_process_api_key()) {
        return new WP_Error('unauthorized', 'Unauthorized access', ['status' => 403]);
    }
    if ($chatgpt_api === null) {
        return new WP_Error('openai_api_error', 'API Key required', ['status' => 403]);
    }
    else {
        // Assume $data is the POSTed data containing the events.
        $data = $request->get_json_params();

        $nonce = $data['nonce'];

        $session_id = KissAi_DB::get_session_id_with_nonce($nonce);
        if ($session_id !== false) {
            $kissai_api->set_session_id($session_id);
            if ($chatgpt_api->chatgpt_check_nonce($nonce)) {
                $chatgpt_api->chatgpt_run_thread($nonce);
                $stream_id = 'chatgpt_stream_' . $nonce;

                // Return the stream ID to the client for subsequent polling.
                return new WP_REST_Response(['stream_id' => $stream_id], 200);
            }
        }
        return new WP_REST_Response(['stream_id' => null], 400);
    }
}

/**
 * 1) Register our admin AJAX actions.
 *    We'll use two actions:
 *    - kissai_get_suggested_questions
 *    - kissai_save_suggested_questions
 */
add_action('wp_ajax_kissai_get_suggested_questions', [KissAi_Widget::class, 'kissai_get_suggested_questions']);
add_action('wp_ajax_nopriv_kissai_get_suggested_questions', [KissAi_Widget::class, 'kissai_get_suggested_questions']);

add_action('wp_ajax_kissai_save_suggested_questions', [KissAi_Widget::class, 'kissai_save_suggested_questions']);
add_action('wp_ajax_nopriv_kissai_save_suggested_questions', [KissAi_Widget::class, 'kissai_save_suggested_questions']);

add_action('wp_ajax_nopriv_kissai_ajax_login', [KissAi_Widget::class, 'kissai_ajax_login']);

add_action('wp_ajax_kissai_ask_ajax', [KissAi_Widget::class, 'kissai_ask_ajax']); // Handling logged-in users
add_action('wp_ajax_nopriv_kissai_ask_ajax', [KissAi_Widget::class, 'kissai_ask_ajax']); // Handling non-logged-in users

add_action('wp_ajax_kissai_fetch_delta_ajax', [KissAi_Widget::class, 'kissai_fetch_delta_ajax']);
add_action('wp_ajax_nopriv_kissai_fetch_delta_ajax', [KissAi_Widget::class, 'kissai_fetch_delta_ajax']);

add_action('wp_ajax_kissai_save_message_ajax', [KissAi_Widget::class, 'kissai_save_message_ajax']);
add_action('wp_ajax_nopriv_kissai_save_message_ajax', [KissAi_Widget::class, 'kissai_save_message_ajax']);

add_action('wp_ajax_kissai_save_usage_ajax', [KissAi_Widget::class, 'kissai_save_usage_ajax']);
add_action('wp_ajax_nopriv_kissai_save_usage_ajax', [KissAi_Widget::class, 'kissai_save_usage_ajax']);

add_action('wp_ajax_kissai_delete_file_ajax', [KissAi_Widget::class, 'kissai_delete_file_ajax']);
add_action('wp_ajax_nopriv_kissai_delete_file_ajax', [KissAi_Widget::class, 'kissai_delete_file_ajax']);

add_action('wp_ajax_kissai_handle_file_upload', [KissAi_Widget::class, 'kissai_handle_file_upload']);
add_action('wp_ajax_nopriv_kissai_handle_file_upload', [KissAi_Widget::class, 'kissai_handle_file_upload']);

add_action('wp_ajax_kissai_handle_file_download', [KissAi_Widget::class, 'kissai_handle_file_download']);
add_action('wp_ajax_nopriv_kissai_handle_file_download', [KissAi_Widget::class, 'kissai_handle_file_download']);

add_action('wp_ajax_kissai_handle_file_edit', [KissAi_Widget::class, 'kissai_handle_file_edit']);
add_action('wp_ajax_nopriv_kissai_handle_file_edit', [KissAi_Widget::class, 'kissai_handle_file_edit']);

add_action('wp_ajax_kissai_ajax_admin_refresh', [KissAi_Widget::class, 'kissai_ajax_admin_refresh']);
add_action('wp_ajax_nopriv_kissai_ajax_admin_refresh', [KissAi_Widget::class, 'kissai_ajax_admin_refresh']);

add_action('wp_ajax_kissai_ajax_admin_file_list_refresh', [KissAi_Widget::class, 'kissai_ajax_admin_file_list_refresh']);
add_action('wp_ajax_nopriv_kissai_ajax_admin_file_list_refresh', [KissAi_Widget::class, 'kissai_ajax_admin_file_list_refresh']);

add_action('wp_ajax_kissai_ajax_admin_instruction_update', [KissAi_Widget::class, 'kissai_ajax_admin_instruction_update']);
add_action('wp_ajax_nopriv_kissai_ajax_admin_instruction_update', [KissAi_Widget::class, 'kissai_ajax_admin_instruction_update']);

add_action('wp_ajax_kissai_ajax_admin_widget_refresh', [KissAi_Widget::class, 'kissai_ajax_admin_widget_refresh']);
add_action('wp_ajax_nopriv_kissai_ajax_admin_widget_refresh', [KissAi_Widget::class, 'kissai_ajax_admin_widget_refresh']);

add_action('wp_ajax_kissai_ajax_delete_chat', [KissAi_Widget::class, 'kissai_ajax_delete_chat']);
add_action('wp_ajax_nopriv_kissai_ajax_delete_chat', [KissAi_Widget::class, 'kissai_ajax_delete_chat']);

add_action('wp_ajax_kissai_ajax_suggested_questions', [KissAi_Widget::class, 'kissai_ajax_suggested_questions']);
add_action('wp_ajax_nopriv_kissai_ajax_suggested_questions', [KissAi_Widget::class, 'kissai_ajax_suggested_questions']);

add_action('wp_ajax_kissai_update_knowledge_file_id', [KissAi_Widget::class, 'kissai_update_knowledge_file_id']);
add_action('wp_ajax_nopriv_kissai_update_knowledge_file_id', [KissAi_Widget::class, 'kissai_update_knowledge_file_id']);

