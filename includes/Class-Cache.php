<?php
/**
 * Performance and Caching for AI Summary Plugin
 *
 * @package NeuzaAI\Summary
 * @since 1.0.1
 */

namespace NeuzaAI\Summary;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache class for AI Summary plugin
 *
 * Handles caching of API responses and optimizations
 *
 * @since 1.0.1
 */
class Cache {

    /**
     * Cache group name
     */
    const CACHE_GROUP = 'ai_summary';

    /**
     * Default cache expiration (24 hours)
     */
    const DEFAULT_EXPIRATION = 86400;

    /**
     * Get cached summary data
     *
     * @param int $post_id Post ID
     * @return array|false Cached data or false
     */
    public static function get_summary($post_id) {
        $cache_key = self::get_cache_key('summary', $post_id);
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Set cached summary data
     *
     * @param int $post_id Post ID
     * @param array $data Summary data
     * @param int $expiration Cache expiration in seconds
     * @return bool
     */
    public static function set_summary($post_id, $data, $expiration = self::DEFAULT_EXPIRATION) {
        $cache_key = self::get_cache_key('summary', $post_id);
        
        // Also set as transient for persistent caching
        set_transient($cache_key, $data, $expiration);
        
        return wp_cache_set($cache_key, $data, self::CACHE_GROUP, $expiration);
    }

    /**
     * Delete cached summary data
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function delete_summary($post_id) {
        $cache_key = self::get_cache_key('summary', $post_id);
        
        // Delete transient
        delete_transient($cache_key);
        
        return wp_cache_delete($cache_key, self::CACHE_GROUP);
    }

    /**
     * Get cached API response
     *
     * @param string $content_hash Hash of content
     * @return string|false Cached response or false
     */
    public static function get_api_response($content_hash) {
        $cache_key = self::get_cache_key('api_response', $content_hash);
        return get_transient($cache_key);
    }

    /**
     * Set cached API response
     *
     * @param string $content_hash Hash of content
     * @param string $response API response
     * @param int $expiration Cache expiration in seconds
     * @return bool
     */
    public static function set_api_response($content_hash, $response, $expiration = 3600) {
        $cache_key = self::get_cache_key('api_response', $content_hash);
        return set_transient($cache_key, $response, $expiration);
    }

    /**
     * Generate cache key
     *
     * @param string $type Cache type
     * @param mixed $identifier Identifier
     * @return string
     */
    private static function get_cache_key($type, $identifier) {
        return self::CACHE_GROUP . '_' . $type . '_' . md5($identifier);
    }

    /**
     * Clear all plugin caches
     *
     * @return bool
     */
    public static function clear_all() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_GROUP . '_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_GROUP . '_%'
            )
        );
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        return true;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        
        $stats = array(
            'transients' => 0,
            'size' => 0
        );
        
        // Count transients
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, LENGTH(option_value) as size 
                 FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_GROUP . '_%'
            )
        );
        
        $stats['transients'] = count($transients);
        $stats['size'] = array_sum(wp_list_pluck($transients, 'size'));
        
        return $stats;
    }

    /**
     * Preload critical summaries
     *
     * @param array $post_ids Post IDs to preload
     */
    public static function preload_summaries($post_ids) {
        if (empty($post_ids)) {
            return;
        }
        
        foreach ($post_ids as $post_id) {
            // Check if already cached
            if (self::get_summary($post_id) === false) {
                // Load from database and cache
                $summary = get_post_meta($post_id, '_ai_summary_text', true);
                $points = get_post_meta($post_id, '_ai_summary_points', true);
                $faq = get_post_meta($post_id, '_ai_summary_faq', true);
                
                if ($summary || $points || $faq) {
                    $data = array(
                        'summary' => $summary,
                        'key_points' => $points ?: array(),
                        'faq' => $faq ?: array(),
                        'generated_at' => get_post_meta($post_id, '_ai_summary_generated_at', true)
                    );
                    
                    self::set_summary($post_id, $data);
                }
            }
        }
    }
}

/**
 * Performance optimization utilities
 */
class Performance {

    /**
     * Initialize performance optimizations
     */
    public static function init() {
        // Preload summaries for front page posts
        add_action('wp', array(__CLASS__, 'maybe_preload_summaries'));
        
        // Clean expired caches daily
        if (!wp_next_scheduled('ai_summary_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ai_summary_cache_cleanup');
        }
        add_action('ai_summary_cache_cleanup', array(__CLASS__, 'cleanup_expired_caches'));
        
        // Optimize database queries
        add_action('pre_get_posts', array(__CLASS__, 'optimize_queries'));
    }

    /**
     * Preload summaries for current page
     */
    public static function maybe_preload_summaries() {
        if (is_admin() || !is_main_query()) {
            return;
        }
        
        global $wp_query;
        
        if (is_home() || is_front_page() || is_archive()) {
            $post_ids = wp_list_pluck($wp_query->posts, 'ID');
            if (!empty($post_ids)) {
                Cache::preload_summaries($post_ids);
            }
        }
    }

    /**
     * Clean up expired caches
     */
    public static function cleanup_expired_caches() {
        global $wpdb;
        
        // Clean expired transients
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_%'
             AND a.option_name NOT LIKE '_transient_timeout_%'
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < UNIX_TIMESTAMP()"
        );
        
        \AI_Summary_Plugin::log('Cache cleanup completed', 'info');
    }

    /**
     * Optimize database queries
     *
     * @param \WP_Query $query
     */
    public static function optimize_queries($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Only select necessary fields when we just need IDs for caching
        if ($query->get('ai_summary_preload')) {
            $query->set('fields', 'ids');
        }
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public static function get_metrics() {
        $metrics = array(
            'cache_stats' => Cache::get_stats(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'db_queries' => get_num_queries(),
        );
        
        if (function_exists('wp_cache_get_stats')) {
            $metrics['object_cache'] = wp_cache_get_stats();
        }
        
        return $metrics;
    }

    /**
     * Log performance metrics
     */
    public static function log_metrics() {
        $metrics = self::get_metrics();
        \AI_Summary_Plugin::log('Performance Metrics: ' . wp_json_encode($metrics), 'info');
    }
}
