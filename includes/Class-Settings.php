<?php
/**
 * Settings Page Handler
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
 * Settings class for AI Summary plugin
 *
 * Handles admin settings page, options storage, and validation
 *
 * @since 1.0.0
 */
class Settings {

    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'ai-summary-settings';

    /**
     * Option group name
     *
     * @var string
     */
    private $option_group = 'ai_summary_settings';

    /**
     * Initialize settings
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ai_summary_flush_rules', array($this, 'ajax_flush_rules'));
        add_action('wp_ajax_ai_summary_regenerate_all', array($this, 'ajax_regenerate_all'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('AI Summary Settings', 'ai-summary'),
            __('AI Summaries', 'ai-summary'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register setting group
        register_setting(
            $this->option_group,
            'ai_summary_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        register_setting(
            $this->option_group,
            'ai_summary_provider',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_provider'),
                'default' => 'openrouter',
            )
        );

        register_setting(
            $this->option_group,
            'ai_summary_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o-mini',
            )
        );

        register_setting(
            $this->option_group,
            'ai_summary_temperature',
            array(
                'type' => 'number',
                'sanitize_callback' => array($this, 'sanitize_temperature'),
                'default' => 0.7,
            )
        );

        register_setting(
            $this->option_group,
            'ai_summary_auto_generate',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        register_setting(
            $this->option_group,
            'ai_summary_robots_txt_integration',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            )
        );

        // Add settings sections
        add_settings_section(
            'ai_summary_api_section',
            __('API Configuration', 'ai-summary'),
            array($this, 'render_api_section'),
            $this->page_slug
        );

        add_settings_section(
            'ai_summary_behavior_section',
            __('Behavior Settings', 'ai-summary'),
            array($this, 'render_behavior_section'),
            $this->page_slug
        );

        add_settings_section(
            'ai_summary_tools_section',
            __('Tools', 'ai-summary'),
            array($this, 'render_tools_section'),
            $this->page_slug
        );

        // Add settings fields
        add_settings_field(
            'ai_summary_api_key',
            __('API Key', 'ai-summary'),
            array($this, 'render_api_key_field'),
            $this->page_slug,
            'ai_summary_api_section'
        );

        add_settings_field(
            'ai_summary_provider',
            __('Provider', 'ai-summary'),
            array($this, 'render_provider_field'),
            $this->page_slug,
            'ai_summary_api_section'
        );

        add_settings_field(
            'ai_summary_model',
            __('Model', 'ai-summary'),
            array($this, 'render_model_field'),
            $this->page_slug,
            'ai_summary_api_section'
        );

        add_settings_field(
            'ai_summary_temperature',
            __('Temperature', 'ai-summary'),
            array($this, 'render_temperature_field'),
            $this->page_slug,
            'ai_summary_api_section'
        );

        add_settings_field(
            'ai_summary_auto_generate',
            __('Auto-generate on Save', 'ai-summary'),
            array($this, 'render_auto_generate_field'),
            $this->page_slug,
            'ai_summary_behavior_section'
        );

        add_settings_field(
            'ai_summary_robots_txt_integration',
            __('Robots.txt Integration', 'ai-summary'),
            array($this, 'render_robots_txt_field'),
            $this->page_slug,
            'ai_summary_behavior_section'
        );

        add_settings_field(
            'ai_summary_tools',
            __('Maintenance Tools', 'ai-summary'),
            array($this, 'render_tools_field'),
            $this->page_slug,
            'ai_summary_tools_section'
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }

        wp_enqueue_script(
            'ai-summary-admin',
            AI_SUMMARY_PLUGIN_URL . 'admin/js/settings.js',
            array('jquery'),
            AI_SUMMARY_VERSION,
            true
        );

        wp_localize_script('ai-summary-admin', 'aiSummaryAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_summary_admin'),
            'strings' => array(
                'flushingRules' => __('Flushing rewrite rules...', 'ai-summary'),
                'rulesFlush' => __('Rewrite rules flushed successfully!', 'ai-summary'),
                'regenerating' => __('Regenerating summaries...', 'ai-summary'),
                'regenerateComplete' => __('Summaries regenerated successfully!', 'ai-summary'),
                'error' => __('An error occurred. Please try again.', 'ai-summary'),
            ),
        ));

        wp_enqueue_style(
            'ai-summary-admin',
            AI_SUMMARY_PLUGIN_URL . 'admin/css/settings.css',
            array(),
            AI_SUMMARY_VERSION
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-summary'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>

            <div id="ai-summary-status" style="display: none;">
                <div class="notice notice-info">
                    <p id="ai-summary-status-message"></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render API section description
     */
    public function render_api_section() {
        echo '<p>' . __('Configure your AI provider settings to enable summary generation.', 'ai-summary') . '</p>';
    }

    /**
     * Render behavior section description
     */
    public function render_behavior_section() {
        echo '<p>' . __('Control how and when summaries are generated.', 'ai-summary') . '</p>';
    }

    /**
     * Render tools section description
     */
    public function render_tools_section() {
        echo '<p>' . __('Maintenance and diagnostic tools.', 'ai-summary') . '</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = $this->get_option('api_key', '');
        $masked = !empty($value);
        ?>
        <input type="<?php echo $masked ? 'password' : 'text'; ?>" 
               id="ai_summary_api_key" 
               name="ai_summary_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <?php if ($masked): ?>
            <button type="button" id="toggle-api-key" class="button button-secondary">
                <?php _e('Show', 'ai-summary'); ?>
            </button>
        <?php endif; ?>
        <p class="description">
            <?php _e('Your API key for the selected provider.', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Render provider field
     */
    public function render_provider_field() {
        $value = $this->get_option('provider', 'openrouter');
        $providers = array(
            'openrouter' => __('OpenRouter', 'ai-summary'),
            'gemini' => __('Google Gemini', 'ai-summary'),
        );
        ?>
        <select id="ai_summary_provider" name="ai_summary_provider">
            <?php foreach ($providers as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Select your AI provider.', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Render model field
     */
    public function render_model_field() {
        $value = $this->get_option('model', 'gpt-4o-mini');
        ?>
        <input type="text" 
               id="ai_summary_model" 
               name="ai_summary_model" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Model name to use for generation (e.g., gpt-4o-mini, gemini-pro).', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Render temperature field
     */
    public function render_temperature_field() {
        $value = $this->get_option('temperature', 0.7);
        ?>
        <input type="number" 
               id="ai_summary_temperature" 
               name="ai_summary_temperature" 
               value="<?php echo esc_attr($value); ?>" 
               min="0" 
               max="1" 
               step="0.1" 
               class="small-text" />
        <p class="description">
            <?php _e('Controls randomness in the output (0.0 = deterministic, 1.0 = very random).', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Render auto-generate field
     */
    public function render_auto_generate_field() {
        $value = $this->get_option('auto_generate', false);
        ?>
        <label for="ai_summary_auto_generate">
            <input type="checkbox" 
                   id="ai_summary_auto_generate" 
                   name="ai_summary_auto_generate" 
                   value="1" 
                   <?php checked($value); ?> />
            <?php _e('Automatically generate summaries when posts/products are saved', 'ai-summary'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, summaries will be generated automatically on post save.', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Render robots.txt integration field
     */
    public function render_robots_txt_field() {
        $value = $this->get_option('robots_txt_integration', true);
        ?>
        <label for="ai_summary_robots_txt_integration">
            <input type="checkbox" 
                   id="ai_summary_robots_txt_integration" 
                   name="ai_summary_robots_txt_integration" 
                   value="1" 
                   <?php checked($value); ?> />
            <?php _e('Add AI crawler rules to robots.txt', 'ai-summary'); ?>
        </label>
        <p class="description">
            <?php _e('Allows AI crawlers (GPTBot, Google-Extended, PerplexityBot) to access summary endpoints.', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Render tools field
     */
    public function render_tools_field() {
        ?>
        <p>
            <button type="button" id="flush-rewrite-rules" class="button button-secondary">
                <?php _e('Flush Rewrite Rules', 'ai-summary'); ?>
            </button>
            <button type="button" id="regenerate-all-summaries" class="button button-secondary">
                <?php _e('Regenerate All Summaries', 'ai-summary'); ?>
            </button>
        </p>
        <p class="description">
            <?php _e('Use these tools to maintain your AI summaries and URL structure.', 'ai-summary'); ?>
        </p>
        <?php
    }

    /**
     * Get plugin option
     *
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_option($key, $default = null) {
        return get_option('ai_summary_' . $key, $default);
    }

    /**
     * Update plugin option
     *
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool
     */
    public function update_option($key, $value) {
        return update_option('ai_summary_' . $key, $value);
    }

    /**
     * Sanitize provider field
     *
     * @param string $value Input value
     * @return string
     */
    public function sanitize_provider($value) {
        $allowed = array('openrouter', 'gemini');
        return in_array($value, $allowed) ? $value : 'openrouter';
    }

    /**
     * Sanitize temperature field
     *
     * @param float $value Input value
     * @return float
     */
    public function sanitize_temperature($value) {
        $value = floatval($value);
        return max(0.0, min(1.0, $value));
    }

    /**
     * AJAX handler for flushing rewrite rules
     */
    public function ajax_flush_rules() {
        check_ajax_referer('ai_summary_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'ai-summary'));
        }

        flush_rewrite_rules();
        
        wp_send_json_success(array(
            'message' => __('Rewrite rules flushed successfully!', 'ai-summary')
        ));
    }

    /**
     * AJAX handler for regenerating all summaries
     */
    public function ajax_regenerate_all() {
        check_ajax_referer('ai_summary_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'ai-summary'));
        }

        // Set time limit for batch processing
        set_time_limit(300); // 5 minutes

        // Get batch parameters
        $batch_size = intval($_POST['batch_size'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);

        // Get all posts and products that need regeneration
        $posts = get_posts(array(
            'post_type' => array('post', 'page', 'product'),
            'post_status' => 'publish',
            'numberposts' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
        ));

        if (empty($posts)) {
            wp_send_json_success(array(
                'message' => __('All summaries have been processed!', 'ai-summary'),
                'completed' => true,
                'processed' => 0,
                'errors' => 0
            ));
        }

        $generator = new Generator();
        $processed = 0;
        $errors = 0;

        foreach ($posts as $post_id) {
            try {
                $result = $generator->generate_summary($post_id);
                if ($result) {
                    $processed++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                AI_Summary_Plugin::log('Regeneration error for post ' . $post_id . ': ' . $e->getMessage(), 'error');
                $errors++;
            }
            
            // Small delay to prevent API rate limiting
            usleep(100000); // 0.1 seconds
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Processed %d posts, %d errors', 'ai-summary'), $processed, $errors),
            'completed' => count($posts) < $batch_size,
            'processed' => $processed,
            'errors' => $errors,
            'next_offset' => $offset + $batch_size
        ));
    }
}
