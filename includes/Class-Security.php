<?php
/**
 * Security Utilities for AI Summary Plugin
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
 * Security class for AI Summary plugin
 *
 * Handles security validations and sanitizations
 *
 * @since 1.0.1
 */
class Security {

    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @param string $provider API provider
     * @return bool
     */
    public static function validate_api_key($api_key, $provider = 'openrouter') {
        if (empty($api_key)) {
            return false;
        }

        switch ($provider) {
            case 'openrouter':
                // OpenRouter keys typically start with 'sk-or-'
                return strlen($api_key) >= 20 && preg_match('/^[a-zA-Z0-9\-_]+$/', $api_key);
            
            case 'gemini':
                // Gemini keys are typically 39 characters long
                return strlen($api_key) === 39 && preg_match('/^[a-zA-Z0-9\-_]+$/', $api_key);
            
            default:
                return strlen($api_key) >= 10;
        }
    }

    /**
     * Sanitize post content for API processing
     *
     * @param string $content Content to sanitize
     * @return string
     */
    public static function sanitize_content_for_api($content) {
        // Remove dangerous content
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);
        
        // Remove multiple spaces and normalize
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Limit length to prevent API overuse
        if (strlen($content) > 8000) {
            $content = substr($content, 0, 8000) . '...';
        }
        
        return $content;
    }

    /**
     * Validate JSON response from API
     *
     * @param string $json JSON string
     * @return array|false Parsed data or false
     */
    public static function validate_api_response($json) {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Check required fields
        if (!isset($data['summary']) || !is_string($data['summary'])) {
            return false;
        }
        
        if (!isset($data['key_points']) || !is_array($data['key_points'])) {
            $data['key_points'] = array();
        }
        
        if (!isset($data['faq']) || !is_array($data['faq'])) {
            $data['faq'] = array();
        }
        
        // Sanitize content
        $data['summary'] = sanitize_textarea_field($data['summary']);
        
        // Sanitize key points
        $sanitized_points = array();
        foreach ($data['key_points'] as $point) {
            if (is_string($point) && !empty(trim($point))) {
                $sanitized_points[] = sanitize_text_field($point);
            }
        }
        $data['key_points'] = $sanitized_points;
        
        // Sanitize FAQ
        $sanitized_faq = array();
        foreach ($data['faq'] as $item) {
            if (is_array($item) && isset($item['question']) && isset($item['answer'])) {
                $sanitized_faq[] = array(
                    'question' => sanitize_text_field($item['question']),
                    'answer' => sanitize_textarea_field($item['answer'])
                );
            }
        }
        $data['faq'] = $sanitized_faq;
        
        return $data;
    }

    /**
     * Check if user can manage AI summaries
     *
     * @param int $post_id Post ID (optional)
     * @return bool
     */
    public static function can_manage_summaries($post_id = 0) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if ($post_id && current_user_can('edit_post', $post_id)) {
            return true;
        }
        
        return false;
    }

    /**
     * Rate limit API calls per user
     *
     * @param int $user_id User ID
     * @param int $limit Calls per hour
     * @return bool
     */
    public static function check_rate_limit($user_id = 0, $limit = 50) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $transient_key = 'ai_summary_rate_limit_' . $user_id;
        $calls = get_transient($transient_key);
        
        if ($calls === false) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($calls >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $calls + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Log security events
     *
     * @param string $event Event description
     * @param array $context Additional context
     */
    public static function log_security_event($event, $context = array()) {
        $user_id = get_current_user_id();
        $ip = self::get_client_ip();
        
        $log_data = array(
            'event' => $event,
            'user_id' => $user_id,
            'ip' => $ip,
            'timestamp' => current_time('mysql'),
            'context' => $context
        );
        
        \AI_Summary_Plugin::log('Security Event: ' . wp_json_encode($log_data), 'security');
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}
