<?php
/**
 * Summary Generator
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
 * Generator class for AI Summary plugin
 *
 * Handles AI summary generation and storage
 *
 * @since 1.0.0
 */
class Generator {

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Maximum content length for API calls (in characters)
     *
     * @var int
     */
    private $max_content_length = 8000;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize settings only when needed to avoid WordPress function issues
        $this->settings = null;
    }

    /**
     * Get settings instance
     * 
     * @return Settings
     */
    private function get_settings() {
        if ($this->settings === null) {
            $this->settings = new Settings();
        }
        return $this->settings;
    }

    /**
     * Initialize generator
     */
    public function init() {
        // Hook into post save if auto-generate is enabled
        // This is handled in the main plugin class
    }

    /**
     * Maybe generate summary on post save
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function maybe_generate_on_save($post_id, $post) {
        // Skip if not the right context
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!in_array($post->post_type, array('post', 'page', 'product'))) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        // Check if already processed
        $already_done = get_post_meta($post_id, '_ai_summary_done', true);
        if ($already_done) {
            return;
        }

        // Generate summary
        $this->generate_summary($post_id);
    }

    /**
     * Generate AI summary for a post
     *
     * @param int $post_id Post ID
     * @param bool $force Force regeneration even if already exists
     * @return bool Success status
     */
    public function generate_summary($post_id, $force = false) {
        $post = get_post($post_id);
        
        if (!$post) {
            error_log('AI Summary: Post not found for ID: ' . $post_id);
            return false;
        }

        // Check if summary already exists and not forcing
        if (!$force) {
            $existing_summary = get_post_meta($post_id, '_ai_summary', true);
            if (!empty($existing_summary)) {
                error_log('AI Summary: Summary already exists for post ' . $post_id);
                return true;
            }
        }

        // Get post content
        $content = $this->prepare_content($post);
        if (empty($content)) {
            error_log('AI Summary: No content to summarize for post ' . $post_id);
            return false;
        }

        // Get AI provider and generate summary
        $provider = $this->get_settings()->get_option('ai_provider', 'openrouter');
        $summary = $this->call_ai_api($content, $provider, $post_id);

        if (!$summary) {
            error_log('AI Summary: Failed to generate summary for post ' . $post_id);
            return false;
        }

        // Store the summary
        update_post_meta($post_id, '_ai_summary', sanitize_textarea_field($summary));
        update_post_meta($post_id, '_ai_summary_done', true);
        update_post_meta($post_id, '_ai_summary_date', current_time('mysql'));
        update_post_meta($post_id, '_ai_summary_provider', $provider);

        error_log('AI Summary: Successfully generated summary for post ' . $post_id);
        return true;
    }

    /**
     * Prepare post content for AI processing
     *
     * @param \WP_Post $post Post object
     * @return string Prepared content
     */
    private function prepare_content($post) {
        // Get post content
        $content = $post->post_content;
        
        // Apply content filters to parse shortcodes, etc.
        $content = apply_filters('the_content', $content);
        
        // Strip HTML tags but keep some formatting
        $content = wp_strip_all_tags($content, true);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Truncate if too long
        if (strlen($content) > $this->max_content_length) {
            $content = substr($content, 0, $this->max_content_length);
            // Try to break at word boundary
            $last_space = strrpos($content, ' ');
            if ($last_space !== false && $last_space > ($this->max_content_length * 0.8)) {
                $content = substr($content, 0, $last_space);
            }
            $content .= '...';
        }

        return $content;
    }

    /**
     * Call AI API to generate summary
     *
     * @param string $content Content to summarize
     * @param string $provider AI provider
     * @param int $post_id Post ID for logging
     * @return string|false Generated summary or false on failure
     */
    private function call_ai_api($content, $provider, $post_id) {
        $api_key = $this->get_settings()->get_option('ai_api_key');
        
        if (empty($api_key)) {
            error_log('AI Summary: API key not configured');
            return false;
        }

        switch ($provider) {
            case 'openrouter':
                return $this->call_openrouter_api($content, $api_key, $post_id);
                
            case 'gemini':
                return $this->call_gemini_api($content, $api_key, $post_id);
                
            default:
                error_log('AI Summary: Unknown AI provider: ' . $provider);
                return false;
        }
    }

    /**
     * Call OpenRouter API
     *
     * @param string $content Content to summarize
     * @param string $api_key API key
     * @param int $post_id Post ID for logging
     * @return string|false Generated summary or false on failure
     */
    private function call_openrouter_api($content, $api_key, $post_id) {
        $model = $this->get_settings()->get_option('openrouter_model', 'meta-llama/llama-3.1-8b-instruct:free');
        $max_tokens = intval($this->get_settings()->get_option('summary_max_tokens', 150));
        
        $prompt = "Please provide a concise summary of the following text in " . 
                 $this->get_settings()->get_option('summary_language', 'English') . ". " .
                 "Focus on the main points and key information:\n\n" . $content;

        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => 0.7
        );

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('AI Summary: OpenRouter API error for post ' . $post_id . ': ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('AI Summary: OpenRouter API returned code ' . $response_code . ' for post ' . $post_id . ': ' . $response_body);
            return false;
        }

        $result = json_decode($response_body, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            error_log('AI Summary: Invalid OpenRouter API response for post ' . $post_id . ': ' . $response_body);
            return false;
        }

        return trim($result['choices'][0]['message']['content']);
    }

    /**
     * Call Gemini API
     *
     * @param string $content Content to summarize
     * @param string $api_key API key
     * @param int $post_id Post ID for logging
     * @return string|false Generated summary or false on failure
     */
    private function call_gemini_api($content, $api_key, $post_id) {
        $model = $this->get_settings()->get_option('gemini_model', 'gemini-1.5-flash');
        $max_tokens = intval($this->get_settings()->get_option('summary_max_tokens', 150));
        
        $prompt = "Please provide a concise summary of the following text in " . 
                 $this->get_settings()->get_option('summary_language', 'English') . ". " .
                 "Focus on the main points and key information:\n\n" . $content;

        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'maxOutputTokens' => $max_tokens,
                'temperature' => 0.7
            )
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('AI Summary: Gemini API error for post ' . $post_id . ': ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('AI Summary: Gemini API returned code ' . $response_code . ' for post ' . $post_id . ': ' . $response_body);
            return false;
        }

        $result = json_decode($response_body, true);
        
        if (!$result || !isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('AI Summary: Invalid Gemini API response for post ' . $post_id . ': ' . $response_body);
            return false;
        }

        return trim($result['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * Get summary for a post
     *
     * @param int $post_id Post ID
     * @return string|false Summary text or false if not found
     */
    public function get_summary($post_id) {
        return get_post_meta($post_id, '_ai_summary', true);
    }

    /**
     * Delete summary for a post
     *
     * @param int $post_id Post ID
     * @return bool Success status
     */
    public function delete_summary($post_id) {
        delete_post_meta($post_id, '_ai_summary');
        delete_post_meta($post_id, '_ai_summary_done');
        delete_post_meta($post_id, '_ai_summary_date');
        delete_post_meta($post_id, '_ai_summary_provider');
        
        error_log('AI Summary: Deleted summary for post ' . $post_id);
        return true;
    }

    /**
     * Get summary statistics
     *
     * @return array Statistics array
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Count posts with summaries
        $stats['total_summaries'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ai_summary_done' AND meta_value = '1'"
        );
        
        // Count by provider
        $providers = $wpdb->get_results(
            "SELECT meta_value as provider, COUNT(*) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ai_summary_provider' 
             GROUP BY meta_value"
        );
        
        $stats['by_provider'] = array();
        foreach ($providers as $provider) {
            $stats['by_provider'][$provider->provider] = $provider->count;
        }
        
        return $stats;
    }

    /**
     * Bulk generate summaries
     *
     * @param array $post_ids Array of post IDs
     * @param bool $force Force regeneration
     * @return array Results array
     */
    public function bulk_generate($post_ids, $force = false) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        foreach ($post_ids as $post_id) {
            // Check if already has summary and not forcing
            if (!$force) {
                $existing = get_post_meta($post_id, '_ai_summary', true);
                if (!empty($existing)) {
                    $results['skipped']++;
                    continue;
                }
            }

            // Generate summary
            $success = $this->generate_summary($post_id, $force);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = 'Failed to generate summary for post ' . $post_id;
            }
            
            // Small delay to avoid overwhelming the API
            sleep(1);
        }

        error_log('AI Summary: Bulk generation completed - Success: ' . $results['success'] . 
                 ', Failed: ' . $results['failed'] . ', Skipped: ' . $results['skipped']);
        
        return $results;
    }
}
