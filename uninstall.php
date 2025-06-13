<?php
/**
 * Plugin Uninstall Handler
 *
 * @package NeuzaAI\Summary
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 */
function ai_summary_uninstall_cleanup() {
    global $wpdb;

    // Remove plugin options
    $options_to_delete = array(
        'ai_summary_api_key',
        'ai_summary_provider',
        'ai_summary_model',
        'ai_summary_temperature',
        'ai_summary_auto_generate',
        'ai_summary_robots_txt_integration',
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
        
        // Also remove from multisite
        if (is_multisite()) {
            delete_site_option($option);
        }
    }

    // Remove post meta data
    $meta_keys_to_delete = array(
        '_ai_summary_text',
        '_ai_summary_points',
        '_ai_summary_faq',
        '_ai_summary_done',
        '_ai_summary_generated',
    );

    foreach ($meta_keys_to_delete as $meta_key) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        ));
    }

    // Remove log files
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/ai-summary-logs';
    
    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($log_dir);
    }

    // Clean up any scheduled events
    wp_clear_scheduled_hook('ai_summary_cleanup_logs');
    wp_clear_scheduled_hook('ai_summary_maintenance');

    // Flush rewrite rules
    flush_rewrite_rules();

    // Log the uninstall
    error_log('[AI Summary] Plugin uninstalled and all data cleaned up on ' . date('Y-m-d H:i:s'));
}

// Run cleanup
ai_summary_uninstall_cleanup();
