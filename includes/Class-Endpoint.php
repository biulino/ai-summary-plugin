<?php
/**
 * Endpoint Handler
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
 * Endpoint class for AI Summary plugin
 *
 * Handles REST API endpoints and URL rewrite handling
 *
 * @since 1.0.0
 */
class Endpoint {

    /**
     * Generator instance
     *
     * @var Generator
     */
    private $generator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->generator = new Generator();
    }

    /**
     * Initialize endpoint functionality
     */
    public function init() {
        // REST API routes are registered via the main plugin hook
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('ai/v1', '/summary/(?P<slug>[\w-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_summary_endpoint'),
            'permission_callback' => '__return_true',
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_title',
                ),
            ),
        ));

        register_rest_route('ai/v1', '/summary/id/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_summary_by_id_endpoint'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }

    /**
     * REST API endpoint for getting summary by slug
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_summary_endpoint($request) {
        $slug = $request->get_param('slug');
        
        // Try to find post by slug
        $post = get_page_by_path($slug, OBJECT, array('post', 'page', 'product'));
        
        if (!$post) {
            return new \WP_REST_Response(
                array(
                    'error' => 'Post not found',
                    'message' => 'No post found with the specified slug.',
                ),
                404
            );
        }

        return $this->get_summary_response($post);
    }

    /**
     * REST API endpoint for getting summary by ID
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_summary_by_id_endpoint($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);
        
        if (!$post || !in_array($post->post_type, array('post', 'page', 'product'))) {
            return new \WP_REST_Response(
                array(
                    'error' => 'Post not found',
                    'message' => 'No post found with the specified ID.',
                ),
                404
            );
        }

        return $this->get_summary_response($post);
    }

    /**
     * Generate summary response for a post
     *
     * @param \WP_Post $post Post object
     * @return \WP_REST_Response
     */
    private function get_summary_response($post) {
        try {
            // Check if post is published
            if ($post->post_status !== 'publish') {
                return new \WP_REST_Response(
                    array(
                        'error' => 'Post not available',
                        'message' => 'The requested post is not published.',
                    ),
                    404
                );
            }

            // Get summary data
            $summary_data = $this->generator->get_summary_data($post->ID);
            
            if (!$summary_data) {
                return new \WP_REST_Response(
                    array(
                        'error' => 'No summary available',
                        'message' => 'No AI summary has been generated for this post.',
                    ),
                    404
                );
            }

            // Build JSON-LD response
            $json_ld = $this->build_json_ld($post, $summary_data);

            return new \WP_REST_Response($json_ld, 200);

        } catch (Exception $e) {
            \AI_Summary_Plugin::log('Error in summary endpoint: ' . $e->getMessage(), 'error');
            
            return new \WP_REST_Response(
                array(
                    'error' => 'Server error',
                    'message' => 'An error occurred while retrieving the summary.',
                ),
                500
            );
        }
    }

    /**
     * Build JSON-LD structured data
     *
     * @param \WP_Post $post Post object
     * @param array $summary_data Summary data
     * @return array
     */
    public function build_json_ld($post, $summary_data) {
        $base_data = array(
            '@context' => 'https://schema.org',
            '@type' => $post->post_type === 'product' ? 'Product' : 'Article',
            'name' => $post->post_title,
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'description' => $summary_data['summary'],
        );

        // Add author information
        $author = get_userdata($post->post_author);
        if ($author) {
            $base_data['author'] = array(
                '@type' => 'Person',
                'name' => $author->display_name,
                'url' => get_author_posts_url($author->ID),
            );
        }

        // Add publisher information
        $base_data['publisher'] = array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
        );

        // Add key points
        if (!empty($summary_data['key_points'])) {
            $base_data['keyPoints'] = $summary_data['key_points'];
        }

        // Add FAQ
        if (!empty($summary_data['faq'])) {
            $faq_items = array();
            foreach ($summary_data['faq'] as $item) {
                $faq_items[] = array(
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ),
                );
            }
            $base_data['mainEntity'] = $faq_items;
        }

        // Add featured image if available
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if ($featured_image_id) {
            $image_data = wp_get_attachment_image_src($featured_image_id, 'full');
            if ($image_data) {
                $base_data['image'] = array(
                    '@type' => 'ImageObject',
                    'url' => $image_data[0],
                    'width' => $image_data[1],
                    'height' => $image_data[2],
                );
            }
        }

        // Add product-specific data
        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                // Add price information
                if ($product->get_price()) {
                    $base_data['offers'] = array(
                        '@type' => 'Offer',
                        'price' => $product->get_price(),
                        'priceCurrency' => get_woocommerce_currency(),
                        'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        'url' => get_permalink($post->ID),
                    );
                }

                // Add brand if available
                $brand_terms = wp_get_post_terms($post->ID, 'pa_brand', array('fields' => 'names'));
                if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
                    $base_data['brand'] = array(
                        '@type' => 'Brand',
                        'name' => $brand_terms[0],
                    );
                }

                // Add category
                $categories = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names'));
                if (!empty($categories) && !is_wp_error($categories)) {
                    $base_data['category'] = $categories[0];
                }

                // Add SKU if available
                if ($product->get_sku()) {
                    $base_data['sku'] = $product->get_sku();
                }
            }
        } else {
            // Add article-specific data
            $categories = wp_get_post_terms($post->ID, 'category', array('fields' => 'names'));
            if (!empty($categories) && !is_wp_error($categories)) {
                $base_data['articleSection'] = $categories;
            }

            $tags = wp_get_post_terms($post->ID, 'post_tag', array('fields' => 'names'));
            if (!empty($tags) && !is_wp_error($tags)) {
                $base_data['keywords'] = implode(', ', $tags);
            }

            // Add word count
            $word_count = str_word_count(wp_strip_all_tags($post->post_content));
            if ($word_count > 0) {
                $base_data['wordCount'] = $word_count;
            }
        }

        // Add AI summary specific metadata
        $base_data['aiSummary'] = array(
            '@type' => 'DigitalDocument',
            'name' => 'AI Generated Summary',
            'description' => 'Machine-generated summary of the content',
            'dateCreated' => date('c', get_post_meta($post->ID, '_ai_summary_generated', true) ?: time()),
            'creator' => array(
                '@type' => 'SoftwareApplication',
                'name' => 'AI Summary Plugin',
                'version' => AI_SUMMARY_VERSION,
            ),
        );

        return $base_data;
    }

    /**
     * Get JSON-LD data for template use
     *
     * @param \WP_Post $post Post object
     * @return array|false JSON-LD data or false if no summary
     */
    public function get_template_data($post) {
        $summary_data = $this->generator->get_summary_data($post->ID);
        
        if (!$summary_data) {
            return false;
        }

        return $this->build_json_ld($post, $summary_data);
    }

    /**
     * Validate JSON-LD structure
     *
     * @param array $data JSON-LD data
     * @return bool
     */
    public function validate_json_ld($data) {
        // Check required fields
        $required_fields = array('@context', '@type', 'name', 'url');
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        // Validate @context
        if ($data['@context'] !== 'https://schema.org') {
            return false;
        }

        // Validate @type
        $allowed_types = array('Article', 'Product', 'BlogPosting', 'WebPage');
        if (!in_array($data['@type'], $allowed_types)) {
            return false;
        }

        // Validate URL format
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        // Validate dates if present
        if (isset($data['datePublished']) && !$this->validate_iso_date($data['datePublished'])) {
            return false;
        }

        if (isset($data['dateModified']) && !$this->validate_iso_date($data['dateModified'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate ISO 8601 date format
     *
     * @param string $date Date string
     * @return bool
     */
    private function validate_iso_date($date) {
        $dt = \DateTime::createFromFormat(\DateTime::ISO8601, $date);
        return $dt !== false && $dt->format(\DateTime::ISO8601) === $date;
    }
}
