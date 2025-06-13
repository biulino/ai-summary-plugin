<?php
/**
 * AI Summary Plugin - Comprehensive Testing Script
 *
 * Run this script to test all plugin functionality
 *
 * @package NeuzaAI\Summary
 * @since 1.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test runner for AI Summary plugin
 */
class AI_Summary_Test_Runner {

    /**
     * Run all tests
     */
    public static function run_tests() {
        $results = array();
        
        // Test 1: Plugin activation and initialization
        $results['initialization'] = self::test_initialization();
        
        // Test 2: Settings functionality
        $results['settings'] = self::test_settings();
        
        // Test 3: API connectivity
        $results['api_connectivity'] = self::test_api_connectivity();
        
        // Test 4: Security features
        $results['security'] = self::test_security();
        
        // Test 5: Caching system
        $results['caching'] = self::test_caching();
        
        // Test 6: Performance metrics
        $results['performance'] = self::test_performance();
        
        // Test 7: Database operations
        $results['database'] = self::test_database();
        
        return $results;
    }

    /**
     * Test plugin initialization
     */
    private static function test_initialization() {
        $tests = array();
        
        // Check if main plugin class exists
        $tests['main_class'] = class_exists('AI_Summary_Plugin');
        
        // Check if all required classes can be loaded
        $required_classes = array(
            'NeuzaAI\\Summary\\Settings',
            'NeuzaAI\\Summary\\Generator',
            'NeuzaAI\\Summary\\MetaBox',
            'NeuzaAI\\Summary\\Endpoint',
            'NeuzaAI\\Summary\\Security',
            'NeuzaAI\\Summary\\Cache',
            'NeuzaAI\\Summary\\Performance'
        );
        
        foreach ($required_classes as $class) {
            $tests['class_' . str_replace('\\', '_', $class)] = class_exists($class);
        }
        
        // Check constants
        $tests['constants'] = defined('AI_SUMMARY_VERSION') && 
                             defined('AI_SUMMARY_PLUGIN_DIR') && 
                             defined('AI_SUMMARY_PLUGIN_URL');
        
        // Check hooks
        $tests['hooks'] = has_action('plugins_loaded', 'ai_summary_init');
        
        return $tests;
    }

    /**
     * Test settings functionality
     */
    private static function test_settings() {
        $tests = array();
        
        try {
            $settings = new NeuzaAI\Summary\Settings();
            
            // Test option retrieval
            $tests['option_retrieval'] = method_exists($settings, 'get_option');
            
            // Test default values
            $provider = $settings->get_option('provider', 'openrouter');
            $tests['default_provider'] = ($provider === 'openrouter');
            
            // Test admin menu
            $tests['admin_menu'] = method_exists($settings, 'add_admin_menu');
            
        } catch (Exception $e) {
            $tests['exception'] = $e->getMessage();
        }
        
        return $tests;
    }

    /**
     * Test API connectivity (without making actual calls)
     */
    private static function test_api_connectivity() {
        $tests = array();
        
        try {
            $generator = new NeuzaAI\Summary\Generator();
            
            // Test method existence
            $tests['generator_exists'] = method_exists($generator, 'generate_summary');
            
            // Test API key validation
            $tests['api_validation'] = class_exists('NeuzaAI\\Summary\\Security') && 
                                     method_exists('NeuzaAI\\Summary\\Security', 'validate_api_key');
            
            // Test content building
            $post = get_post(1); // Assume post ID 1 exists
            if ($post) {
                $reflection = new ReflectionClass($generator);
                $method = $reflection->getMethod('build_content');
                $method->setAccessible(true);
                $content = $method->invoke($generator, $post);
                $tests['content_building'] = !empty($content);
            } else {
                $tests['content_building'] = 'no_test_post';
            }
            
        } catch (Exception $e) {
            $tests['exception'] = $e->getMessage();
        }
        
        return $tests;
    }

    /**
     * Test security features
     */
    private static function test_security() {
        $tests = array();
        
        try {
            // Test security class existence
            $tests['security_class'] = class_exists('NeuzaAI\\Summary\\Security');
            
            if ($tests['security_class']) {
                // Test API key validation
                $tests['api_key_validation'] = NeuzaAI\Summary\Security::validate_api_key('test-key', 'openrouter');
                
                // Test content sanitization
                $dirty_content = '<script>alert("xss")</script>Test content';
                $clean_content = NeuzaAI\Summary\Security::sanitize_content_for_api($dirty_content);
                $tests['content_sanitization'] = (strpos($clean_content, '<script>') === false);
                
                // Test rate limiting
                $tests['rate_limiting'] = method_exists('NeuzaAI\\Summary\\Security', 'check_rate_limit');
            }
            
        } catch (Exception $e) {
            $tests['exception'] = $e->getMessage();
        }
        
        return $tests;
    }

    /**
     * Test caching system
     */
    private static function test_caching() {
        $tests = array();
        
        try {
            // Test cache class existence
            $tests['cache_class'] = class_exists('NeuzaAI\\Summary\\Cache');
            
            if ($tests['cache_class']) {
                // Test cache operations
                $test_data = array('test' => 'data');
                $set_result = NeuzaAI\Summary\Cache::set_summary(999, $test_data);
                $get_result = NeuzaAI\Summary\Cache::get_summary(999);
                $tests['cache_operations'] = ($get_result === $test_data);
                
                // Clean up
                NeuzaAI\Summary\Cache::delete_summary(999);
                
                // Test cache statistics
                $tests['cache_stats'] = method_exists('NeuzaAI\\Summary\\Cache', 'get_stats');
            }
            
        } catch (Exception $e) {
            $tests['exception'] = $e->getMessage();
        }
        
        return $tests;
    }

    /**
     * Test performance features
     */
    private static function test_performance() {
        $tests = array();
        
        try {
            // Test performance class existence
            $tests['performance_class'] = class_exists('NeuzaAI\\Summary\\Performance');
            
            if ($tests['performance_class']) {
                // Test metrics collection
                $tests['metrics_collection'] = method_exists('NeuzaAI\\Summary\\Performance', 'get_metrics');
                
                if ($tests['metrics_collection']) {
                    $metrics = NeuzaAI\Summary\Performance::get_metrics();
                    $tests['metrics_structure'] = isset($metrics['memory_usage']) && 
                                                 isset($metrics['cache_stats']);
                }
                
                // Test initialization
                $tests['performance_init'] = method_exists('NeuzaAI\\Summary\\Performance', 'init');
            }
            
        } catch (Exception $e) {
            $tests['exception'] = $e->getMessage();
        }
        
        return $tests;
    }

    /**
     * Test database operations
     */
    private static function test_database() {
        $tests = array();
        
        try {
            global $wpdb;
            
            // Test database connection
            $tests['db_connection'] = ($wpdb->get_var("SELECT 1") == 1);
            
            // Test post meta operations
            $test_post_id = 1; // Assume post ID 1 exists
            update_post_meta($test_post_id, '_ai_summary_test', 'test_value');
            $retrieved = get_post_meta($test_post_id, '_ai_summary_test', true);
            $tests['post_meta'] = ($retrieved === 'test_value');
            
            // Clean up
            delete_post_meta($test_post_id, '_ai_summary_test');
            
            // Test transient operations
            set_transient('ai_summary_test', 'test_value', 60);
            $retrieved = get_transient('ai_summary_test');
            $tests['transients'] = ($retrieved === 'test_value');
            
            // Clean up
            delete_transient('ai_summary_test');
            
        } catch (Exception $e) {
            $tests['exception'] = $e->getMessage();
        }
        
        return $tests;
    }

    /**
     * Generate test report
     */
    public static function generate_report($results) {
        $total_tests = 0;
        $passed_tests = 0;
        
        $report = "AI Summary Plugin - Test Report\n";
        $report .= "Generated: " . current_time('Y-m-d H:i:s') . "\n";
        $report .= str_repeat("=", 50) . "\n\n";
        
        foreach ($results as $category => $tests) {
            $report .= strtoupper($category) . " TESTS:\n";
            $report .= str_repeat("-", 20) . "\n";
            
            foreach ($tests as $test_name => $result) {
                $total_tests++;
                $status = $result === true ? 'PASS' : 'FAIL';
                if ($status === 'PASS') $passed_tests++;
                
                $report .= sprintf("%-30s: %s", $test_name, $status);
                if ($result !== true && $result !== false) {
                    $report .= " (" . $result . ")";
                }
                $report .= "\n";
            }
            $report .= "\n";
        }
        
        $report .= "SUMMARY:\n";
        $report .= str_repeat("-", 20) . "\n";
        $report .= "Total Tests: {$total_tests}\n";
        $report .= "Passed: {$passed_tests}\n";
        $report .= "Failed: " . ($total_tests - $passed_tests) . "\n";
        $report .= "Success Rate: " . round(($passed_tests / $total_tests) * 100, 2) . "%\n";
        
        return $report;
    }
}

// Run tests if accessed directly (for debugging)
if (defined('AI_SUMMARY_RUN_TESTS') && AI_SUMMARY_RUN_TESTS) {
    $results = AI_Summary_Test_Runner::run_tests();
    $report = AI_Summary_Test_Runner::generate_report($results);
    echo "<pre>" . esc_html($report) . "</pre>";
}
