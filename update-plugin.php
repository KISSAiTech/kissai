<?php
function install_kissai_updater() {
    kissai_error_log("install_kissai_updater: Start function.");

    $updater_dir = WP_PLUGIN_DIR . '/kissai-updater';
    $updater_plugin_basename = plugin_basename($updater_dir . '/kissai-updater.php');

    global $kissai_api;

    $updater = $kissai_api->get_kissai_updater();

    if (is_null($updater)) {
        kissai_error_log("Failed to fetch updater plugin.");
        wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode('Failed to fetch updater plugin.')));
        exit;
    }

    if (!$updater->success || empty($updater->download_url)) {
        kissai_error_log("Failed to fetch updater: " . json_encode($updater));
        wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode($updater->message)));
        exit;
    }

    $download_url = $updater->download_url;

    // Download the updater ZIP
    $tmp_file = KissAi_API::download_url($download_url);

    if (is_wp_error($tmp_file)) {
        kissai_error_log("Failed to download updater ZIP: " . $tmp_file->get_error_message());
        wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode('Failed to download updater plugin.')));
        exit;
    }

    // Extract the updater ZIP to the plugins directory
    $unzip_result = unzip_file($tmp_file, $updater_dir);
    wp_delete_file($tmp_file); // Clean up temporary file

    if (is_wp_error($unzip_result)) {
        kissai_error_log("Failed to unzip updater ZIP: " . $unzip_result->get_error_message());
        wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode('Failed to unzip updater plugin.')));
        exit;
    }

    // Delay to ensure filesystem updates are visible
    sleep(1);

    // Clear the plugins cache to force WordPress to re-read the plugin directory
    wp_clean_plugins_cache(true);

    // Activate the updater plugin if not already active
    if (file_exists($updater_dir . '/kissai-updater.php')) {
        $start_time = time();

        while (!is_plugin_active($updater_plugin_basename)) {
            // Attempt to activate the plugin
            $activation_result = activate_plugin($updater_plugin_basename);

            // Check for errors or success
            if (is_wp_error($activation_result)) {
                kissai_error_log("Plugin activation failed: " . $activation_result->get_error_message());
            } elseif (empty($activation_result)) {
                kissai_error_log("Plugin activation returned null but may have succeeded.");
            } else {
                break;
            }

            // Check if 10 seconds have passed since the start of the loop
            if ((time() - $start_time) > 10) {
                kissai_error_log("Timeout reached: Failed to activate the plugin within 10 seconds.");
                wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode('Failed to activate the updater plugin within the timeout.')));
                exit;
            }

            sleep(1); // Short delay between retries
        }
    } else {
        kissai_error_log("Plugin main file not found: $updater_dir/kissai-updater.php");
    }

    kissai_error_log("install_kissai_updater: Completed successfully.");
}



function update_kissai_plugin() {
    check_admin_referer('kissai_update_plugin_nonce');

    if (!current_user_can('manage_options')) {
        return;
    }

    $update = sanitize_text_field(wp_unslash($_POST['update'] ?? ''));
    $reinstall = sanitize_text_field(wp_unslash($_POST['reinstall'] ?? ''));

    // Determine action: update or reinstall
    $action = !empty($update) ? 'update' : (!empty($reinstall) ? 'reinstall' : '');

    if ($action) {
        global $kissai_api;
        $user = $kissai_api->get_current_kissai_user();
        $download_url = '';

        if ($user && isset($user->latest_plugin)) {
            $download_url = $user->latest_plugin->download_url ?? '';
        }

        if (empty($download_url)) {
            kissai_error_log("No download URL provided for KissAi Plugin {$action}.");
            wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode('No download URL available.')));
            exit;
        }

        // Set transients for updater plugin
        set_transient('kissai_updater_download_url', $download_url, MINUTE_IN_SECONDS);
        set_transient('kissai_updater_action', $action, MINUTE_IN_SECONDS);

        try {
            install_kissai_updater();
            sleep(1);
            // Redirect to the updater page
            wp_redirect(admin_url('admin.php?page=kissai-updater'));
        } catch (Exception $e) {
            kissai_error_log("Error during KissAi Updater installation for {$action}: " . $e->getMessage());
            wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode("Failed to install the updater plugin for {$action}.")));
            exit;
        }
    }
}
add_action('admin_post_update_kissai_plugin', 'update_kissai_plugin');

function rollback_kissai_plugin() {
    check_admin_referer('kissai_rollback_plugin_nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $rollback = sanitize_text_field(wp_unslash($_POST['rollback'] ?? ''));
    if (!empty($rollback)) {
        $rollback_version = sanitize_text_field(wp_unslash($_POST['rollback_version'] ?? ''));

        if (empty($rollback_version)) {
            wp_redirect(admin_url('admin.php?page=kissai-update-plugin&kissai_error=' . urlencode('No rollback version specified.')));
            exit;
        }

        set_transient('kissai_updater_rollback_version', $rollback_version, MINUTE_IN_SECONDS);
        set_transient('kissai_updater_action', 'rollback', MINUTE_IN_SECONDS);

        install_kissai_updater();

        wp_safe_redirect(admin_url('admin.php?page=kissai-updater'));
        exit;
    }
}
add_action('admin_post_rollback_kissai_plugin', 'rollback_kissai_plugin');

function display_kissai_update_plugin() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if the plugin is registered
    $kissai_api_key = get_kissai_option('api_key');
    if (empty($kissai_api_key)) {
        echo '<h1>KissAi Update Plugin</h1>';
        echo '<p>This page allows you to update or reinstall the KissAi plugin.</p>';
        echo '<p><strong>Note:</strong> The plugin is not registered. Please register the plugin first to enable updates.</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=kissai-plugin-register')) . '" class="button-primary">Go to Plugin Registration</a></p>';
        return;
    }

    // If the plugin is registered, proceed with displaying update options
    $current_version = get_kissai_plugin_version();
    global $kissai_api;
    $user = $kissai_api->get_current_kissai_user();
    $latest_version = $current_version;

    if ($user && isset($user->latest_plugin)) {
        $latest_version = $user->latest_plugin->version;
    }

    echo '<h1>KissAi Update Plugin</h1>';
    echo '<p>This page allows you to update or reinstall the KissAi plugin.</p>';
    echo '<p><strong>Current Version:</strong> ' . esc_html( $current_version ) . '</p>';
    echo "<p><strong>Latest Version:</strong> " . esc_html( $latest_version ) . "</p>";
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="update_kissai_plugin" />
        <div class="button-container">
            <?php
            wp_nonce_field('kissai_update_plugin_nonce');
            if (version_compare($current_version, $latest_version, '<')) {
                submit_button('Update', 'primary', 'update', true);
            } elseif (version_compare($current_version, $latest_version, '==')) {
                submit_button('Reinstall', 'secondary', 'reinstall', true);
            } else {
                echo "<p>You are using the latest version of the plugin.</p>";
            }
            ?>
        </div>
    </form>
    <?php
    //echo render_kissai_rollback_form_from_backup();
}

function render_kissai_rollback_form_from_backup() {
    $backup_dir = WP_CONTENT_DIR . '/uploads/kissai_backups';
    $backup_files = glob($backup_dir . '/kissai-backup-*.zip');
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="rollback_kissai_plugin" />
        <div class="button-container">
            <?php
            wp_nonce_field('kissai_rollback_plugin_nonce');
            if (!empty($backup_files)) {
                echo '<p>Select a version to rollback:</p><select name="rollback_version">';
                foreach ($backup_files as $backup_file) {
                    $filename = basename($backup_file);
                    if (preg_match('/kissai-backup-v([\d\.]+)\.zip$/', $filename, $matches)) {
                        $version = $matches[1];
                        echo '<option value="' . esc_attr($version) . '">' . esc_html($version) . '</option>';
                    }
                }
                echo '</select>';
                submit_button('Rollback', 'secondary', 'rollback', true);
            } else {
                echo "<p>No backups found for rollback.</p>";
            }
            ?>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

add_action('admin_notices', function () {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['kissai_updated'])) {
        echo '<div class="notice notice-success">';
        echo '<h3>KissAi</h3>';
        echo '<p>The KissAi Plugin has been updated successfully.</p>';
        echo '</div>';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['kissai_rollbacked'])) {
        echo '<div class="notice notice-success">';
        echo '<h3>KissAi</h3>';
        echo '<p>The KissAi Plugin has been rolled back to the previous version successfully.</p>';
        echo '</div>';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['kissai_error'])) {
        echo '<div class="notice notice-error">';
        echo '<h3>KissAi</h3>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        echo '<p>Error: ' . esc_html(sanitize_text_field( $_GET['kissai_error'] )) . '</p>';
        echo '</div>';
    }
});
