<?php
/**
 * AI Summary Template
 *
 * Template for displaying AI summary data as JSON-LD
 *
 * @package NeuzaAI\Summary
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get the current post
$post_name = get_query_var('name');
if (empty($post_name)) {
    status_header(404);
    wp_die('Post not found', 'Not Found', array('response' => 404));
}

// Find the post
$post = get_page_by_path($post_name, OBJECT, array('post', 'page', 'product'));
if (!$post || $post->post_status !== 'publish') {
    status_header(404);
    wp_die('Post not found', 'Not Found', array('response' => 404));
}

// Get the endpoint handler
$endpoint = new NeuzaAI\Summary\Endpoint();
$json_ld_data = $endpoint->get_template_data($post);

if (!$json_ld_data) {
    status_header(404);
    wp_die('No AI summary available for this content', 'Not Found', array('response' => 404));
}

// Set headers
header('Content-Type: application/ld+json; charset=UTF-8');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('X-Robots-Tag: noindex, follow'); // Don't index these pages

// Add CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Validate the JSON-LD data
if (!$endpoint->validate_json_ld($json_ld_data)) {
    status_header(500);
    wp_die('Invalid summary data', 'Server Error', array('response' => 500));
}

// Output the JSON-LD
echo wp_json_encode($json_ld_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Log the access for analytics
\AI_Summary_Plugin::log(sprintf(
    'AI summary accessed for post %d (%s) from %s',
    $post->ID,
    $post->post_title,
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
), 'info');

exit;
