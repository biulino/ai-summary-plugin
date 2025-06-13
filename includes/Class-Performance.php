<?php
/**
 * Performance monitoring and optimization class
 *
 * @package NeuzaAI\Summary
 * @since 1.0.0
 */

namespace NeuzaAI\Summary;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Class
 *
 * Handles performance monitoring, caching, and optimization
 */
class Performance {
    
    /**
     * Instance of this class
     *
     * @var Performance
     */
    private static $instance = null;
    
    /**
     * Performance metrics
     *
     * @var array
     */
    private $metrics = array();
    
    /**
     * Get singleton instance
     *
     * @return Performance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize performance monitoring
     */
    public static function init() {
        $instance = self::get_instance();
        $instance->setup_hooks();
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->metrics = array(
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(),
            'api_calls' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0
        );
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Performance monitoring hooks
        add_action('ai_summary_before_generation', array($this, 'track_generation_start'));
        add_action('ai_summary_after_generation', array($this, 'track_generation_end'));
        add_action('ai_summary_api_call', array($this, 'track_api_call'));
        add_action('ai_summary_cache_hit', array($this, 'track_cache_hit'));
        add_action('ai_summary_cache_miss', array($this, 'track_cache_miss'));
        
        // Admin performance dashboard
        if (is_admin()) {
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        }
        
        // Cleanup old logs
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Track generation start
     *
     * @param int $post_id Post ID
     */
    public function track_generation_start($post_id) {
        $this->metrics['generation_start'] = microtime(true);
        $this->log_performance('Generation started for post ID: ' . $post_id);
    }
    
    /**
     * Track generation end
     *
     * @param int $post_id Post ID
     */
    public function track_generation_end($post_id) {
        if (isset($this->metrics['generation_start'])) {
            $duration = microtime(true) - $this->metrics['generation_start'];
            $this->log_performance('Generation completed for post ID: ' . $post_id . ' in ' . round($duration, 2) . 's');
        }
    }
    
    /**
     * Track API call
     */
    public function track_api_call() {
        $this->metrics['api_calls']++;
    }
    
    /**
     * Track cache hit
     */
    public function track_cache_hit() {
        $this->metrics['cache_hits']++;
    }
    
    /**
     * Track cache miss
     */
    public function track_cache_miss() {
        $this->metrics['cache_misses']++;
    }
    
    /**
     * Get current performance metrics
     *
     * @return array Performance metrics
     */
    public function get_metrics() {
        $current_time = microtime(true);
        $current_memory = memory_get_usage();
        
        return array_merge($this->metrics, array(
            'current_time' => $current_time,
            'execution_time' => $current_time - $this->metrics['start_time'],
            'memory_current' => $current_memory,
            'memory_used' => $current_memory - $this->metrics['memory_start'],
            'memory_peak' => memory_get_peak_usage()
        ));
    }
    
    /**
     * Log performance data
     *
     * @param string $message Performance message
     */
    private function log_performance($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Summary Performance: ' . $message);
        }
    }
    
    /**
     * Add dashboard widget for performance monitoring
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'ai_summary_performance',
            'AI Summary Performance',
            array($this, 'dashboard_widget_content')
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        $metrics = $this->get_metrics();
        ?>
        <div class="ai-summary-performance">
            <h4>Performance Metrics</h4>
            <ul>
                <li><strong>Execution Time:</strong> <?php echo round($metrics['execution_time'], 2); ?>s</li>
                <li><strong>Memory Usage:</strong> <?php echo size_format($metrics['memory_used']); ?></li>
                <li><strong>Peak Memory:</strong> <?php echo size_format($metrics['memory_peak']); ?></li>
                <li><strong>API Calls:</strong> <?php echo $metrics['api_calls']; ?></li>
                <li><strong>Cache Hits:</strong> <?php echo $metrics['cache_hits']; ?></li>
                <li><strong>Cache Misses:</strong> <?php echo $metrics['cache_misses']; ?></li>
            </ul>
            
            <?php if ($metrics['cache_hits'] + $metrics['cache_misses'] > 0): ?>
                <p><strong>Cache Hit Rate:</strong> 
                    <?php echo round($metrics['cache_hits'] / ($metrics['cache_hits'] + $metrics['cache_misses']) * 100, 1); ?>%
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Cleanup old log files
     */
    public function cleanup_old_logs() {
        $log_dir = wp_upload_dir()['basedir'] . '/ai-summary-logs/';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '*.log');
        $cutoff_time = time() - (30 * DAY_IN_SECONDS); // 30 days
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get performance report
     *
     * @return array Performance report
     */
    public function get_performance_report() {
        $metrics = $this->get_metrics();
        
        return array(
            'status' => 'good',
            'metrics' => $metrics,
            'recommendations' => $this->get_recommendations($metrics)
        );
    }
    
    /**
     * Get performance recommendations
     *
     * @param array $metrics Performance metrics
     * @return array Recommendations
     */
    private function get_recommendations($metrics) {
        $recommendations = array();
        
        // Check execution time
        if ($metrics['execution_time'] > 5) {
            $recommendations[] = 'Consider optimizing long-running processes';
        }
        
        // Check memory usage
        if ($metrics['memory_used'] > 50 * 1024 * 1024) { // 50MB
            $recommendations[] = 'High memory usage detected, consider optimization';
        }
        
        // Check cache hit rate
        $total_cache_requests = $metrics['cache_hits'] + $metrics['cache_misses'];
        if ($total_cache_requests > 0) {
            $hit_rate = $metrics['cache_hits'] / $total_cache_requests;
            if ($hit_rate < 0.8) {
                $recommendations[] = 'Cache hit rate is low, consider cache optimization';
            }
        }
        
        return $recommendations;
    }
}
