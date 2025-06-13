<?php
/**
 * Meta Box functionality for AI Summary plugin
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
 * MetaBox class for AI Summary plugin
 *
 * Handles meta box display and AJAX functionality
 *
 * @since 1.0.0
 */
class MetaBox {

    /**
     * Initialize meta box functionality
     */
    public function init() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_ai_summary_regenerate', array($this, 'ajax_regenerate_summary'));
    }

    /**
     * Add meta boxes to post and product edit screens
     */
    public function add_meta_boxes() {
        $post_types = array('post', 'page');
        
        // Add WooCommerce product if available
        if (class_exists('WooCommerce')) {
            $post_types[] = 'product';
        }

        foreach ($post_types as $post_type) {
            add_meta_box(
                'ai-summary-meta-box',
                __('AI Summary', 'ai-summary'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        global $post;

        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        if (!$post || !in_array($post->post_type, array('post', 'page', 'product'))) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'ai-summary-meta-box',
            AI_SUMMARY_PLUGIN_URL . 'admin/js/meta-box.js',
            array('jquery'),
            AI_SUMMARY_VERSION,
            true
        );

        // Enqueue CSS
        wp_enqueue_style(
            'ai-summary-meta-box',
            AI_SUMMARY_PLUGIN_URL . 'admin/css/meta-box.css',
            array(),
            AI_SUMMARY_VERSION
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('ai-summary-meta-box', 'aiSummaryAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_summary_meta_box'),
            'strings' => array(
                'generating' => __('Generating summary...', 'ai-summary'),
                'error' => __('Error generating summary', 'ai-summary'),
                'success' => __('Summary generated successfully!', 'ai-summary'),
            )
        ));
    }

    /**
     * Render the meta box content
     */
    public function render_meta_box($post) {
        // Get existing data
        $summary = get_post_meta($post->ID, '_ai_summary_text', true);
        $points = get_post_meta($post->ID, '_ai_summary_points', true);
        $faq = get_post_meta($post->ID, '_ai_summary_faq', true);
        $last_generated = get_post_meta($post->ID, '_ai_summary_generated', true);

        // Add nonce field
        wp_nonce_field('ai_summary_meta_box', 'ai_summary_meta_box_nonce');
        ?>
        <div class="ai-summary-container">
            <div class="ai-summary-header">
                <div class="header-info">
                    <h3><?php _e('AI-Generated Content', 'ai-summary'); ?></h3>
                    <?php if ($last_generated): ?>
                        <p class="last-generated">
                            <?php printf(__('Last generated: %s', 'ai-summary'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_generated)); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <button type="button" id="ai-summary-generate" class="button button-primary" data-post-id="<?php echo $post->ID; ?>">
                        <span class="generate-text"><?php _e('Generate Summary', 'ai-summary'); ?></span>
                        <span class="loading-spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>

            <div class="ai-summary-fields">
                <!-- Summary Text -->
                <div class="field-group">
                    <label for="ai_summary_text">
                        <strong><?php _e('Summary', 'ai-summary'); ?></strong>
                    </label>
                    <textarea 
                        id="ai_summary_text" 
                        name="ai_summary_text" 
                        rows="4" 
                        class="large-text"
                        placeholder="<?php esc_attr_e('AI-generated summary will appear here...', 'ai-summary'); ?>"
                    ><?php echo esc_textarea($summary); ?></textarea>
                </div>

                <!-- Key Points -->
                <div class="field-group">
                    <label>
                        <strong><?php _e('Key Points', 'ai-summary'); ?></strong>
                    </label>
                    <div id="ai-summary-points-container">
                        <?php if (!empty($points)): ?>
                            <?php foreach ($points as $index => $point): ?>
                                <div class="key-point-item">
                                    <input 
                                        type="text" 
                                        name="ai_summary_points[]" 
                                        value="<?php echo esc_attr($point); ?>" 
                                        class="large-text"
                                        placeholder="<?php esc_attr_e('Key point...', 'ai-summary'); ?>"
                                    />
                                    <button type="button" class="button remove-point">
                                        <span class="dashicons dashicons-minus"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="key-point-item">
                                <input 
                                    type="text" 
                                    name="ai_summary_points[]" 
                                    value="" 
                                    class="large-text"
                                    placeholder="<?php esc_attr_e('Key point...', 'ai-summary'); ?>"
                                />
                                <button type="button" class="button remove-point">
                                    <span class="dashicons dashicons-minus"></span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-key-point" class="button button-secondary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Add Key Point', 'ai-summary'); ?>
                    </button>
                </div>
            </div>

            <div id="ai-summary-status" style="display: none;">
                <div class="notice">
                    <p id="ai-summary-status-message"></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box($post_id, $post) {
        // Security checks
        if (!isset($_POST['ai_summary_meta_box_nonce']) || !wp_verify_nonce($_POST['ai_summary_meta_box_nonce'], 'ai_summary_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        $post_type_object = get_post_type_object($post->post_type);
        if (!current_user_can($post_type_object->cap->edit_post, $post_id)) {
            return;
        }

        // Save summary text
        if (isset($_POST['ai_summary_text'])) {
            update_post_meta($post_id, '_ai_summary_text', sanitize_textarea_field($_POST['ai_summary_text']));
        }

        // Save key points
        if (isset($_POST['ai_summary_points']) && is_array($_POST['ai_summary_points'])) {
            $points = array_filter(array_map('sanitize_text_field', $_POST['ai_summary_points']));
            update_post_meta($post_id, '_ai_summary_points', $points);
        } else {
            delete_post_meta($post_id, '_ai_summary_points');
        }
    }

    /**
     * AJAX handler for regenerating summary
     */
    public function ajax_regenerate_summary() {
        // Enhanced debug logging
        error_log('AI Summary AJAX: Handler started - NEW VERSION');
        error_log('AI Summary AJAX: POST data: ' . print_r($_POST, true));
        
        try {
            // Check nonce
            error_log('AI Summary AJAX: Checking nonce');
            if (!check_ajax_referer('ai_summary_meta_box', 'nonce', false)) {
                error_log('AI Summary AJAX: Nonce verification failed');
                wp_send_json_error(array(
                    'message' => __('Security check failed.', 'ai-summary')
                ));
            }
            error_log('AI Summary AJAX: Nonce verified successfully');

            $post_id = intval($_POST['post_id'] ?? 0);
            error_log('AI Summary AJAX: Post ID: ' . $post_id);
            
            if (!$post_id) {
                error_log('AI Summary AJAX: Invalid post ID');
                wp_send_json_error(array(
                    'message' => __('Invalid post ID.', 'ai-summary')
                ));
            }

            // Check permissions
            error_log('AI Summary AJAX: Checking permissions for post ID: ' . $post_id);
            if (!current_user_can('edit_post', $post_id)) {
                error_log('AI Summary AJAX: Permission denied for current user');
                wp_send_json_error(array(
                    'message' => __('You do not have permission to edit this post.', 'ai-summary')
                ));
            }
            error_log('AI Summary AJAX: Permissions verified');

            // Check if Generator class exists
            error_log('AI Summary AJAX: Checking if Generator class exists');
            if (!class_exists('NeuzaAI\\Summary\\Generator')) {
                error_log('AI Summary AJAX: Generator class not found in namespace');
                wp_send_json_error(array(
                    'message' => __('Generator class not found.', 'ai-summary')
                ));
            }

            // Generate summary - using fully qualified class name
            error_log('AI Summary AJAX: Creating Generator instance');
            $generator = new \NeuzaAI\Summary\Generator();
            error_log('AI Summary AJAX: Generator instance created successfully');
            
            error_log('AI Summary AJAX: Calling generate_summary method');
            $result = $generator->generate_summary($post_id, true); // Force regeneration
            error_log('AI Summary AJAX: generate_summary returned: ' . ($result ? 'true' : 'false'));
            
            if ($result) {
                // Get updated data
                error_log('AI Summary AJAX: Getting updated post meta data');
                $summary = get_post_meta($post_id, '_ai_summary_text', true);
                $points = get_post_meta($post_id, '_ai_summary_points', true);
                $last_generated = get_post_meta($post_id, '_ai_summary_generated', true);

                error_log('AI Summary AJAX: Sending success response');
                wp_send_json_success(array(
                    'message' => __('Summary regenerated successfully!', 'ai-summary'),
                    'data' => array(
                        'summary' => $summary,
                        'points' => $points ?: array(),
                        'last_generated' => $last_generated ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_generated) : '',
                    )
                ));
            } else {
                error_log('AI Summary AJAX: generate_summary returned false');
                wp_send_json_error(array(
                    'message' => __('Failed to generate summary. Please check your API configuration.', 'ai-summary')
                ));
            }
        } catch (Exception $e) {
            // Enhanced error logging
            error_log('AI Summary AJAX Exception: ' . $e->getMessage());
            error_log('AI Summary AJAX Exception Stack Trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => __('An error occurred while generating the summary: ', 'ai-summary') . $e->getMessage()
            ));
        } catch (Error $e) {
            // Catch PHP Fatal Errors
            error_log('AI Summary AJAX Fatal Error: ' . $e->getMessage());
            error_log('AI Summary AJAX Fatal Error Stack Trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => __('A fatal error occurred: ', 'ai-summary') . $e->getMessage()
            ));
        }
    }
}
