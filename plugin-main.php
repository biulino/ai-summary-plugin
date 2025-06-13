<?php
/**
 * Plugin Name: AI Summary
 * Plugin URI: https://github.com/neuzamar/ai-summary
 * Description: Generate AI-powered summaries, key points, and FAQs for posts and WooCommerce products using OpenRouter or Gemini APIs.
 * Version: 1.0.0
 * Author: Neuza Mar
 * Author URI: https://neuzamar.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-summary
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 *
 * @package NeuzaAI\Summary
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_SUMMARY_VERSION', '1.0.0');
define('AI_SUMMARY_PLUGIN_FILE', __FILE__);
define('AI_SUMMARY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_SUMMARY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_SUMMARY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main AI Summary Plugin Class
 *
 * @since 1.0.0
 */
class AI_Summary_Plugin {

    /**
     * Single instance of the plugin
     *
     * @var AI_Summary_Plugin
     */
    private static $instance = null;

    /**
     * Plugin settings
     *
     * @var NeuzaAI\Summary\Settings
     */
    public $settings;

    /**
     * Plugin meta box handler
     *
     * @var NeuzaAI\Summary\MetaBox
     */
    public $meta_box;

    /**
     * Plugin summary generator
     *
     * @var NeuzaAI\Summary\Generator
     */
    public $generator;

    /**
     * Plugin endpoint handler
     *
     * @var NeuzaAI\Summary\Endpoint
     */
    public $endpoint;

    /**
     * Get single instance
     *
     * @return AI_Summary_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_autoloader();
        $this->init_hooks();
    }

    /**
     * Initialize autoloader
     */
    private function init_autoloader() {
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * PSR-4 autoloader
     *
     * @param string $class_name Class name to load
     */
    public function autoload($class_name) {
        $namespace = 'NeuzaAI\\Summary\\';
        
        if (strpos($class_name, $namespace) !== 0) {
            return;
        }

        $class_name = substr($class_name, strlen($namespace));
        $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
        
        $file_path = AI_SUMMARY_PLUGIN_DIR . 'includes/Class-' . $class_name . '.php';
        
        // Debug logging
        error_log('AI Summary Autoloader: Trying to load class: ' . $class_name);
        error_log('AI Summary Autoloader: File path: ' . $file_path);
        error_log('AI Summary Autoloader: File exists: ' . (file_exists($file_path) ? 'YES' : 'NO'));
        
        if (file_exists($file_path)) {
            require_once $file_path;
            error_log('AI Summary Autoloader: Successfully loaded: ' . $file_path);
        } else {
            // Log missing class file with improved logging
            self::log("Could not load class file: " . $file_path, 'warning');
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Check if WordPress is ready
        if (!did_action('wp_loaded')) {
            add_action('wp_loaded', array($this, 'init'));
            return;
        }

        try {
            // Initialize components
            $this->settings = new NeuzaAI\Summary\Settings();
            $this->meta_box = new NeuzaAI\Summary\MetaBox();
            $this->generator = new NeuzaAI\Summary\Generator();
            $this->endpoint = new NeuzaAI\Summary\Endpoint();

            // Initialize components
            $this->settings->init();
            $this->meta_box->init();
            $this->generator->init();
            $this->endpoint->init();

            // Initialize enhanced features if available
            if (class_exists('NeuzaAI\\Summary\\Performance')) {
                NeuzaAI\Summary\Performance::init();
            }

            // Load health check in admin
            if (is_admin()) {
                require_once AI_SUMMARY_PLUGIN_DIR . 'includes/health-check.php';
                $this->init_admin_notices();
            }

            // Hook into post save if auto-generate is enabled
            if ($this->settings->get_option('auto_generate', false)) {
                add_action('save_post', array($this->generator, 'maybe_generate_on_save'), 20, 2);
            }
        } catch (Exception $e) {
            // Log error and show admin notice
            self::log('Plugin initialization failed: ' . $e->getMessage(), 'error');
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>AI Summary Plugin Error:</strong> ' . 
                     esc_html($e->getMessage()) . '</p></div>';
            });
        }

        // Add template override
        add_filter('template_include', array($this, 'template_include'));

        // Add rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Register REST API routes
        add_action('rest_api_init', array($this->endpoint, 'register_routes'));

        // Add shortcode
        add_shortcode('ai_summary', array($this, 'shortcode_handler'));

        // Robots.txt integration
        add_filter('robots_txt', array($this, 'add_robots_txt_rules'), 10, 2);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create log directory
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/ai-summary-logs/';
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
        
        self::log('Plugin activated successfully', 'info');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        self::log('Plugin deactivated', 'info');
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-summary',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Add rewrite rules for AI summary endpoints
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^([^/]+)/ai-summary/?$',
            'index.php?name=$matches[1]&ai_summary=1',
            'top'
        );
    }

    /**
     * Add query vars
     *
     * @param array $vars Query vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'ai_summary';
        return $vars;
    }

    /**
     * Template include override
     *
     * @param string $template Template path
     * @return string
     */
    public function template_include($template) {
        if (get_query_var('ai_summary')) {
            $custom_template = AI_SUMMARY_PLUGIN_DIR . 'template-ai-summary.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Shortcode handler
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'show_summary' => 'yes',
            'show_keypoints' => 'yes',
            'show_faq' => 'yes'
        ), $atts, 'ai_summary');

        if (!$atts['post_id']) {
            return '';
        }

        ob_start();
        ?>
        <div class="ai-summary-shortcode">
            <?php
            $summary = get_post_meta($atts['post_id'], '_ai_summary', true);
            $keypoints = get_post_meta($atts['post_id'], '_ai_keypoints', true);
            $faq = get_post_meta($atts['post_id'], '_ai_faq', true);

            if ($atts['show_summary'] === 'yes' && !empty($summary)): ?>
                <div class="ai-summary-section">
                    <h3><?php _e('Summary', 'ai-summary'); ?></h3>
                    <p><?php echo esc_html($summary); ?></p>
                </div>
            <?php endif;

            if ($atts['show_keypoints'] === 'yes' && !empty($keypoints)):
                $keypoints_array = json_decode($keypoints, true);
                if (is_array($keypoints_array)): ?>
                    <div class="ai-keypoints-section">
                        <h3><?php _e('Key Points', 'ai-summary'); ?></h3>
                        <ul>
                            <?php foreach ($keypoints_array as $point): ?>
                                <li><?php echo esc_html($point); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif;
            endif;

            if ($atts['show_faq'] === 'yes' && !empty($faq)):
                $faq_array = json_decode($faq, true);
                if (is_array($faq_array)): ?>
                    <div class="ai-faq-section">
                        <h3><?php _e('FAQ', 'ai-summary'); ?></h3>
                        <?php foreach ($faq_array as $qa): ?>
                            <div class="faq-item">
                                <h4><?php echo esc_html($qa['question']); ?></h4>
                                <p><?php echo esc_html($qa['answer']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif;
            endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add robots.txt rules for AI crawlers
     *
     * @param string $output Robots.txt output
     * @param bool $public Whether site is public
     * @return string
     */
    public function add_robots_txt_rules($output, $public) {
        if (!$public || !$this->settings->get_option('robots_txt_integration', true)) {
            return $output;
        }

        $ai_rules = "\n# AI Summary Plugin Rules\n";
        $ai_rules .= "User-agent: GPTBot\n";
        $ai_rules .= "User-agent: Google-Extended\n";
        $ai_rules .= "User-agent: PerplexityBot\n";
        $ai_rules .= "Allow: /*/ai-summary/\n";
        $ai_rules .= "Allow: /wp-json/ai/v1/\n\n";

        return $output . $ai_rules;
    }

    /**
     * Log messages to plugin log file
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/ai-summary-logs/';
        $log_file = $log_dir . 'ai-summary.log';

        // Create log directory if it doesn't exist
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Check if we can write to the log file
        if (!is_writable($log_dir)) {
            // Fallback to WordPress debug log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Summary: ' . $message);
            }
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);

        error_log($log_entry, 3, $log_file);
    }

    /**
     * Initialize admin notices
     */
    private function init_admin_notices() {
        // Show setup notice if API key is not configured
        $api_key = $this->settings->get_option('api_key');
        if (empty($api_key)) {
            add_action('admin_notices', array($this, 'show_setup_notice'));
        }
    }

    /**
     * Show setup notice for first-time users
     */
    public function show_setup_notice() {
        if (get_option('ai_summary_setup_notice_dismissed')) {
            return;
        }
        ?>
        <div class="notice notice-info is-dismissible" data-dismissible="ai_summary_setup_notice">
            <h3><?php _e('AI Summary Plugin Setup', 'ai-summary'); ?></h3>
            <p><?php _e('Welcome to AI Summary! To get started, please configure your API settings.', 'ai-summary'); ?></p>
            <p>
                <a href="<?php echo admin_url('options-general.php?page=ai-summary'); ?>" class="button button-primary">
                    <?php _e('Configure Settings', 'ai-summary'); ?>
                </a>
                <button type="button" class="button dismiss-notice" data-notice="ai_summary_setup_notice">
                    <?php _e('Dismiss', 'ai-summary'); ?>
                </button>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.dismiss-notice', function() {
                var notice = $(this).data('notice');
                $.post(ajaxurl, {
                    action: 'dismiss_admin_notice',
                    notice: notice,
                    _ajax_nonce: '<?php echo wp_create_nonce('dismiss_admin_notice'); ?>'
                });
                $(this).closest('.notice').fadeOut();
            });
        });
        </script>
        <?php
    }
}

// Initialize the plugin
AI_Summary_Plugin::get_instance();

// Handle admin notice dismissal
add_action('wp_ajax_dismiss_admin_notice', function() {
    check_ajax_referer('dismiss_admin_notice');
    
    $notice = sanitize_text_field($_POST['notice']);
    if ($notice === 'ai_summary_setup_notice') {
        update_option('ai_summary_setup_notice_dismissed', true);
    }
    
    wp_die();
});
