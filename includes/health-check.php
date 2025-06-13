<?php
/**
 * AI Summary Plugin Health Check
 *
 * Quick health check script for the AI Summary plugin
 *
 * @package NeuzaAI\Summary
 * @since 1.0.1
 */

// This file should be run within WordPress context
if (!defined('ABSPATH')) {
    die('This script must be run within WordPress.');
}

/**
 * Perform health check
 */
function ai_summary_health_check() {
    $health = array(
        'status' => 'healthy',
        'issues' => array(),
        'warnings' => array(),
        'info' => array()
    );

    // Check if WordPress is loaded
    if (!defined('ABSPATH')) {
        $health['issues'][] = 'WordPress not loaded';
        $health['status'] = 'critical';
        return $health;
    }

    // Check plugin activation - Use a safer approach
    if (!class_exists('AI_Summary_Plugin')) {
        // Plugin might not be loaded, check if file exists
        $plugin_file = dirname(__DIR__) . '/plugin-main.php';
        if (!file_exists($plugin_file)) {
            $health['issues'][] = 'Main plugin file not found';
            $health['status'] = 'critical';
        } else {
            $health['warnings'][] = 'Main plugin class not loaded (plugin may be inactive)';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
    }

    // Check required PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $health['issues'][] = 'PHP version too old. Requires 7.4+, current: ' . PHP_VERSION;
        $health['status'] = 'critical';
    }

    // Check WordPress version - with safety check
    if (function_exists('get_bloginfo')) {
        $wp_version = get_bloginfo('version');
        if (version_compare($wp_version, '5.0', '<')) {
            $health['issues'][] = 'WordPress version too old. Requires 5.0+, current: ' . $wp_version;
            $health['status'] = 'critical';
        }
    } else {
        // Fallback to global if get_bloginfo not available
        global $wp_version;
        if (isset($wp_version) && version_compare($wp_version, '5.0', '<')) {
            $health['issues'][] = 'WordPress version too old. Requires 5.0+, current: ' . $wp_version;
            $health['status'] = 'critical';
        } elseif (!isset($wp_version)) {
            $health['warnings'][] = 'Cannot determine WordPress version';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
    }

    // Check required classes
    $required_classes = array(
        'NeuzaAI\\Summary\\Settings',
        'NeuzaAI\\Summary\\Generator',
        'NeuzaAI\\Summary\\MetaBox',
        'NeuzaAI\\Summary\\Endpoint'
    );

    foreach ($required_classes as $class) {
        if (!class_exists($class)) {
            $health['issues'][] = "Required class missing: {$class}";
            $health['status'] = 'critical';
        }
    }

    // Check enhanced classes
    $enhanced_classes = array(
        'NeuzaAI\\Summary\\Security',
        'NeuzaAI\\Summary\\Cache',
        'NeuzaAI\\Summary\\Performance'
    );

    foreach ($enhanced_classes as $class) {
        if (!class_exists($class)) {
            $health['warnings'][] = "Enhanced feature class missing: {$class}";
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
    }

    // Check file permissions - with safe constant check
    if (defined('AI_SUMMARY_PLUGIN_DIR')) {
        $plugin_dir = AI_SUMMARY_PLUGIN_DIR;
        if (!is_readable($plugin_dir)) {
            $health['issues'][] = 'Plugin directory not readable';
            $health['status'] = 'critical';
        }
    } else {
        $health['warnings'][] = 'Plugin directory constant not defined';
        if ($health['status'] === 'healthy') {
            $health['status'] = 'warning';
        }
    }

    // Check upload directory - with function check
    if (function_exists('wp_upload_dir')) {
        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['basedir']) && !is_writable($upload_dir['basedir'])) {
            $health['warnings'][] = 'Upload directory not writable (affects logging)';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }

        // Check log directory
        if (isset($upload_dir['basedir'])) {
            $log_dir = $upload_dir['basedir'] . '/ai-summary-logs';
            if (!file_exists($log_dir)) {
                $health['warnings'][] = 'Log directory does not exist';
                if ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            }
        }
    } else {
        $health['warnings'][] = 'wp_upload_dir function not available';
        if ($health['status'] === 'healthy') {
            $health['status'] = 'warning';
        }
    }

    // Check API configuration - with safe class instantiation
    try {
        if (class_exists('NeuzaAI\\Summary\\Settings')) {
            $settings = new NeuzaAI\Summary\Settings();
            $api_key = $settings->get_option('api_key');
            if (empty($api_key)) {
                $health['warnings'][] = 'No API key configured';
                if ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            }
        } else {
            $health['warnings'][] = 'Settings class not available for API check';
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
    } catch (Exception $e) {
        $health['warnings'][] = 'Error checking API configuration: ' . $e->getMessage();
        if ($health['status'] === 'healthy') {
            $health['status'] = 'warning';
        }
    }

    // Check database tables - with safe wpdb access
    if (isset($GLOBALS['wpdb'])) {
        global $wpdb;
        try {
            $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->posts}'");
            if (!$tables_exist) {
                $health['issues'][] = 'WordPress database tables missing';
                $health['status'] = 'critical';
            }
        } catch (Exception $e) {
            $health['warnings'][] = 'Error checking database: ' . $e->getMessage();
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
    } else {
        $health['warnings'][] = 'WordPress database object not available';
        if ($health['status'] === 'healthy') {
            $health['status'] = 'warning';
        }
    }

    // Check rewrite rules - with function check
    if (function_exists('get_option')) {
        try {
            $rules = get_option('rewrite_rules');
            $has_ai_rules = false;
            if (is_array($rules)) {
                foreach ($rules as $pattern => $rewrite) {
                    if (strpos($pattern, 'ai-summary') !== false) {
                        $has_ai_rules = true;
                        break;
                    }
                }
            }
            if (!$has_ai_rules) {
                $health['warnings'][] = 'AI Summary rewrite rules not found - may need to flush permalinks';
                if ($health['status'] === 'healthy') {
                    $health['status'] = 'warning';
                }
            }
        } catch (Exception $e) {
            $health['warnings'][] = 'Error checking rewrite rules: ' . $e->getMessage();
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }
    } else {
        $health['warnings'][] = 'get_option function not available for rewrite check';
        if ($health['status'] === 'healthy') {
            $health['status'] = 'warning';
        }
    }

    // Collect system information - with safe function calls
    $wp_version_for_info = 'Unknown';
    if (function_exists('get_bloginfo')) {
        $wp_version_for_info = get_bloginfo('version');
    } elseif (isset($GLOBALS['wp_version'])) {
        $wp_version_for_info = $GLOBALS['wp_version'];
    }

    $plugin_version = defined('AI_SUMMARY_VERSION') ? AI_SUMMARY_VERSION : 'Unknown';
    $active_plugins_count = 0;
    $current_theme = 'Unknown';
    $is_multisite = false;

    if (function_exists('get_option')) {
        try {
            $active_plugins_count = count(get_option('active_plugins', array()));
        } catch (Exception $e) {
            // Ignore error, keep default
        }
    }

    if (function_exists('get_template')) {
        try {
            $current_theme = get_template();
        } catch (Exception $e) {
            // Ignore error, keep default
        }
    }

    if (function_exists('is_multisite')) {
        try {
            $is_multisite = is_multisite();
        } catch (Exception $e) {
            // Ignore error, keep default
        }
    }

    $health['info'] = array(
        'php_version' => PHP_VERSION,
        'wordpress_version' => $wp_version_for_info,
        'plugin_version' => $plugin_version,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'active_plugins' => $active_plugins_count,
        'theme' => $current_theme,
        'multisite' => $is_multisite,
        'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
    );

    return $health;
}

/**
 * Display health check results
 */
function ai_summary_display_health_check() {
    $health = ai_summary_health_check();
    
    echo "<div class='wrap'>";
    echo "<h1>AI Summary Plugin Health Check</h1>";
    
    // Overall status
    $status_class = '';
    switch ($health['status']) {
        case 'healthy':
            $status_class = 'notice-success';
            break;
        case 'warning':
            $status_class = 'notice-warning';
            break;
        case 'critical':
            $status_class = 'notice-error';
            break;
    }
    
    echo "<div class='notice {$status_class}'>";
    echo "<p><strong>Overall Status: " . ucfirst($health['status']) . "</strong></p>";
    echo "</div>";
    
    // Issues
    if (!empty($health['issues'])) {
        echo "<h2>Critical Issues</h2>";
        echo "<ul>";
        foreach ($health['issues'] as $issue) {
            echo "<li style='color: red;'>" . esc_html($issue) . "</li>";
        }
        echo "</ul>";
    }
    
    // Warnings
    if (!empty($health['warnings'])) {
        echo "<h2>Warnings</h2>";
        echo "<ul>";
        foreach ($health['warnings'] as $warning) {
            echo "<li style='color: orange;'>" . esc_html($warning) . "</li>";
        }
        echo "</ul>";
    }
    
    // System Information
    echo "<h2>System Information</h2>";
    echo "<table class='form-table'>";
    foreach ($health['info'] as $key => $value) {
        $label = ucwords(str_replace('_', ' ', $key));
        $value = is_bool($value) ? ($value ? 'Yes' : 'No') : $value;
        echo "<tr>";
        echo "<th scope='row'>{$label}</th>";
        echo "<td>" . esc_html($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "</div>";
}

// Add admin page for health check
add_action('admin_menu', function() {
    add_management_page(
        'AI Summary Health Check',
        'AI Summary Health',
        'manage_options',
        'ai-summary-health',
        'ai_summary_display_health_check'
    );
});
