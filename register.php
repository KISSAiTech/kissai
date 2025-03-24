<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once( ABSPATH . '/wp-includes/pluggable.php' );
require_once plugin_dir_path(__FILE__) . 'kissai_api_endpoints.php';

// Define the function to display the register page.
function display_kissai_register_page() {
    wp_enqueue_style('kissai-style');
    $first_name = get_kissai_option('api_user_first_name');
    $last_name = get_kissai_option('api_user_last_name');
    $email = get_kissai_option('api_user_email');
    $user = null;
    if ($email) {
        global $kissai_api;
        $user = $kissai_api->get_kissai_user($email);
    }
    $kissai_api_key = get_kissai_option('api_key');
    ?>
    <style>
        .verified-text {
            color: #0073aa; /* Standard WordPress blue */
            padding: 0px 10px;
        }
        td.valign-center {
            display: flex;
            align-items: center;
        }
        .upgrade-button-container {
            padding-left: 30px;
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            init_open_page_button('.open-upgrade-page', '/my-account/#plan');
            init_open_page_button('.open-buy-credit-page', '/my-account/#credit');
            init_open_page_button('.open-view-transactions-page', '/my-account/#transactions');
        });

    </script>
    <form id="kissai_register_form" method="post">
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <table class="form-table">
                <tr>
                    <th>First Name</th>
                    <td><input type="text" name="first_name" value="<?php echo esc_attr($first_name); ?>" required></td>
                </tr>
                <tr>
                    <th>Last Name</th>
                    <td><input type="text" name="last_name" value="<?php echo esc_attr($last_name); ?>" required></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>
                        <input type="email" name="email" value="<?php echo esc_attr($email); ?>" required>
                        <?php if ($user !== null && $user->verified): ?>
                            <span class="verified-text">Verified!</span>
                            <p><b>Important</b>: Your email address serves as your unique ID on KissAi. Please do not change your email address once it's verified.<br>
                                If you need to update your email for any reason, feel free to <a href="<?php echo esc_url(KissAi_API_Endpoints::SUPPORT); ?>">reach out to our support team</a>, and we’ll be happy to assist you.</p>
                        <?php else: ?>
                            <button type="button" class="button-secondary" id="verify_email_btn">Verify Email</button>
                        <?php endif; ?>
                        <span id="verification_status"></span>
                    </td>
                </tr>
                <?php if ($user !== null) : ?>
                    <?php if ($user->verified && !empty($kissai_api_key)): ?>
                <tr>
                    <th>License</th>
                    <td>
                        <table class="fixed kissai-admin-table">
                            <tbody>
                            <?php
                                if (!empty($kissai_api_key) && empty($user->licenses)) {
                                    ?>
                                    <div style="height: 20px;"></div>
                                    <p class="notice notice-error"><b>Important:</b> <b>We're sorry, but your key appears to be invalid.</b><br>
                                        Please double-check your key from the <a href="<?php echo esc_url(admin_url('admin.php?page=kissai-plugin-settings')); ?>">Settings</a> or reach out to our support team for assistance.<br>
                                        <a href="<?php echo esc_url(KissAi_API_Endpoints::SUPPORT); ?>" target="_blank">Contact Us</a>.</p>
                            <?php
                                }
                            ?>
                            <?php foreach ($user->licenses as $license): ?>
                            <tr>
                                <th>License Key</th>
                                <td><?php echo esc_html($license->license_key ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Plan</th>
                                <td class="valign-center"><?php echo esc_html($license->plan_name ?? ''); ?> <div class="upgrade-button-container"><?php if ($license->price == 0) echo '<input type="button" value="Upgrade" class="button open-upgrade-page" data-user-id="' . esc_attr($user->id) . '" data-user-email="' . esc_attr($user->email). '"/>'; ?></div></td>
                            </tr>
                            <tr>
                                <th>Features</th>
                                <td><?php echo wp_kses_post($license->features ?? ''); ?></td>
                            </tr>
                            <?php if ($license->price !== 0) { ?>
                            <tr>
                                <th>Start Date</th>
                                <td><span class="kissai-timestamp"><?php echo esc_html($license->start_date ?? ''); ?></span></td>
                            </tr>
                            <tr>
                                <th>End Date</th>
                                <td><span class="kissai-timestamp"><?php echo esc_html($license->end_date ?? 'N/A'); ?></span></td>
                            </tr>
                            <?php } ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <tr>
                    <th>Credit</th>
                    <td>
                        <table class="fixed kissai-admin-table">
                            <tbody>
                            <?php if (!empty($user->credit)) : ?>
                            <tr>
                                <th>Credit Balance</th>
                                <td class="valign-center"><?php echo '$' . number_format($user->credit->credit_balance ?? 0, 2); ?><div class="upgrade-button-container"><?php echo '<input type="button" value="Buy Credit" class="button open-buy-credit-page" data-user-id="' . esc_attr($user->id) . '" data-user-email="' . esc_attr($user->email). '"/>'; ?></div></td>
                            </tr>
                            <tr>
                                <th>Total Credits Added</th>
                                <td><?php echo '$' . number_format($user->credit->total_credits_added ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <th>Total Credits Spent</th>
                                <td><?php echo '$' . number_format($user->credit->total_credits_spent ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <th>Last Credit Update</th>
                                <td class="valign-center"><span class="kissai-timestamp"><?php echo esc_html($user->credit->last_updated ?? ''); ?></span><div class="upgrade-button-container"><?php echo '<input type="button" value="View Transactions" class="button open-view-transactions-page" data-user-id="' . esc_attr($user->id) . '" data-user-email="' . esc_attr($user->email) . '"/>'; ?></div></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><span class="dashicons dashicons-warning" style="position:absolute; margin-left:-30px;"></span>What is Credit?</th>
                                <td>
                                    <div class="credit-description">
                                        <p>Credits are used to access OpenAI's API services without the need to create your own OpenAI API key. We provide the API key for you, so you don't need to manage or set up your own. Each time you use the KissAi services, the corresponding credit amount will be automatically deducted from your balance based on your usage.</p>
                                        <p>Your credit balance will be reduced with each API call depending on the complexity and length of the request. You can view the detailed pricing and how credits are calculated for each API call on our <a href="/path-to-formula-page" target="_blank">Credit Usage Formula Page</a>.</p>
                                        <p>Ensure you have sufficient credits to continue using our OpenAI-powered features seamlessly.</p>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                    <?php elseif ($user->verified && empty($kissai_api_key)):?>
                <tr>
                    <th>License</th>
                        <?php if (empty($user->verification_email_sent)) : ?>
                            <td>Your email has been verified manually by the KissAi Hub admin, but the API key is missing in the settings. Please <a href="<?php echo esc_url(KissAi_API_Endpoints::SUPPORT); ?>" target="_blank">Contact Us</a>.</td>
                        <?php else : ?>
                            <td>Your email has been verified, but the API key is missing in the settings. Please check the email we sent you on <span class="kissai-timestamp"><?php echo esc_html($user->verification_email_sent); ?></span> for your KissAi API key, then <a href="/wp-admin/admin.php?page=kissai-plugin-settings" target="_blank">go to the settings page</a> and enter the key to activate the plugin.</td>
                        <?php endif; ?>
                </tr>
                    <?php endif; ?>
                <?php endif; ?>
                <tr>
                    <td></td>
                    <td>
                        <?php
                        if ($user !== null && $user->verified) {
                            submit_button('Update');
                        }
                        else {
                            submit_button('Update', 'primary', 'submit', true, array('disabled' => ''));
                        }
                         ?>
                    </td>
                </tr>
            </table>
        </div>
    </form>
    <div id="register_response"></div>
    <?php
}

add_action('wp_ajax_kissai_save_email_ajax', 'kissai_save_email_ajax');
add_action('wp_ajax_nopriv_kissai_save_email_ajax', 'kissai_save_email_ajax');

function kissai_save_email_ajax() {
    check_ajax_referer('kissai_nonce', 'nonce');

    if ( isset( $_POST['first_name'] ) ) {
        $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
        update_kissai_option( 'api_user_first_name', $first_name );
    }

    if ( isset( $_POST['last_name'] ) ) {
        $last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
        update_kissai_option( 'api_user_last_name', $last_name );
    }

    if ( isset( $_POST['email'] ) ) {
        // Unslash first
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below
        $email_raw = wp_unslash( $_POST['email'] );
        // Then sanitize
        $email     = sanitize_email( $email_raw );

        // Check if it’s valid
        if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $existing_email = get_kissai_option( 'api_user_email' );
            if ( $existing_email !== $email ) {
                update_kissai_option( 'api_user_email', $email );
            }
        } else {
            wp_send_json_error( 'Invalid email address.' );
        }
    } else {
        wp_send_json_error( 'Invalid email address.' );
    }

    wp_send_json_success( array(
        'endpoint' => KissAi_API_Endpoints::VERIFY_EMAIL
    ) );

    exit;
}

function kissai_ajax_update_user_details() {
    check_ajax_referer('kissai_nonce', 'nonce');
    $data = array();

    if ( isset( $_POST['email'] ) ) {
        // Unslash then sanitize
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below
        $email_raw = wp_unslash( $_POST['email'] );
        $email     = sanitize_email( $email_raw );

        if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            update_kissai_option( 'api_user_email', $email );
            $data['email'] = $email;

            if ( isset( $_POST['first_name'] ) ) {
                $first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
                update_kissai_option( 'api_user_first_name', $first_name );
                $data['first_name'] = $first_name;
            }

            if ( isset( $_POST['last_name'] ) ) {
                $last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
                update_kissai_option( 'api_user_last_name', $last_name );
                $data['last_name'] = $last_name;
            }

            global $kissai_api;
            $response = $kissai_api->update_kissai_user( $data );

            if ( $response ) {
                if ( ! empty( $response['id'] ) ) {
                    wp_send_json_success( array(
                        'message' => 'KissAi user detail is updated',
                        'user'    => $response
                    ) );
                } elseif ( isset( $response['response'], $response['body'] ) ) {
                    $response_body = $response['body'];
                    if ( isset( $response_body['error']['message'] ) ) {
                        wp_send_json_error( $response_body['error']['message'] );
                    }
                } else {
                    wp_send_json_error( 'Unknown response from KissAi server.' );
                }
            } else {
                wp_send_json_error( 'KissAi server connection failed.' );
            }
        } else {
            wp_send_json_error( 'Invalid email address.' );
        }
    } else {
        wp_send_json_error( 'Invalid email address.' );
    }
}

add_action('wp_ajax_kissai_ajax_update_user_details', 'kissai_ajax_update_user_details');
add_action('wp_ajax_nopriv_kissai_ajax_update_user_details', 'kissai_ajax_update_user_details');
