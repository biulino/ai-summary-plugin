/**
 * AI Summary Settings JavaScript
 *
 * Handles the settings page functionality
 *
 * @package NeuzaAI\Summary
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initSettings();
    });

    /**
     * Initialize settings functionality
     */
    function initSettings() {
        // Toggle API key visibility
        $('#toggle-api-key').on('click', toggleApiKeyVisibility);

        // Maintenance tools
        $('#flush-rewrite-rules').on('click', flushRewriteRules);
        $('#regenerate-all-summaries').on('click', regenerateAllSummaries);
    }

    /**
     * Toggle API key visibility
     */
    function toggleApiKeyVisibility(e) {
        e.preventDefault();
        
        const $apiKeyField = $('#ai_summary_api_key');
        const $toggleButton = $(this);
        
        if ($apiKeyField.attr('type') === 'password') {
            $apiKeyField.attr('type', 'text');
            $toggleButton.text('Hide');
        } else {
            $apiKeyField.attr('type', 'password');
            $toggleButton.text('Show');
        }
    }

    /**
     * Flush rewrite rules
     */
    function flushRewriteRules(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text(aiSummaryAdmin.strings.flushingRules);
        showStatus(aiSummaryAdmin.strings.flushingRules, 'info');
        
        // Make AJAX request
        $.ajax({
            url: aiSummaryAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_summary_flush_rules',
                nonce: aiSummaryAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showStatus(response.data.message || aiSummaryAdmin.strings.rulesFlush, 'success');
                } else {
                    showStatus(response.data ? response.data.message : aiSummaryAdmin.strings.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showStatus(aiSummaryAdmin.strings.error, 'error');
            },
            complete: function() {
                // Restore button
                $button.prop('disabled', false).text(originalText);
                
                // Auto-hide success message
                setTimeout(function() {
                    $('#ai-summary-status').fadeOut();
                }, 3000);
            }
        });
    }

    /**
     * Regenerate all summaries with batch processing
     */
    function regenerateAllSummaries(e) {
        e.preventDefault();
        
        // Confirm action
        if (!confirm('Are you sure you want to regenerate all summaries? This may take a while and will consume API credits.')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        let offset = 0;
        let totalProcessed = 0;
        let totalErrors = 0;
        
        // Show loading state
        $button.prop('disabled', true);
        showStatus(aiSummaryAdmin.strings.regenerating, 'info');
        
        function processBatch() {
            $button.text(`Processing... (${totalProcessed} processed)`);
            
            $.ajax({
                url: aiSummaryAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_summary_regenerate_all',
                    nonce: aiSummaryAdmin.nonce,
                    batch_size: 5, // Process 5 at a time
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        totalProcessed += response.data.processed;
                        totalErrors += response.data.errors;
                        
                        if (response.data.completed) {
                            // All done
                            showStatus(`Completed! Processed ${totalProcessed} posts with ${totalErrors} errors.`, 
                                     totalErrors > 0 ? 'warning' : 'success');
                            $button.prop('disabled', false).text(originalText);
                        } else {
                            // Continue with next batch
                            offset = response.data.next_offset;
                            setTimeout(processBatch, 1000); // Wait 1 second between batches
                        }
                    } else {
                        showStatus(response.data ? response.data.message : aiSummaryAdmin.strings.error, 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showStatus(`Error: ${error}`, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
        
        // Start batch processing
        processBatch();
    }
        
        const $button = $(this);
        const originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true).text(aiSummaryAdmin.strings.regenerating);
        showStatus(aiSummaryAdmin.strings.regenerating, 'info');
        
        // Make AJAX request
        $.ajax({
            url: aiSummaryAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_summary_regenerate_all',
                nonce: aiSummaryAdmin.nonce
            },
            timeout: 300000, // 5 minutes timeout
            success: function(response) {
                if (response.success) {
                    showStatus(response.data.message || aiSummaryAdmin.strings.regenerateComplete, 'success');
                } else {
                    showStatus(response.data ? response.data.message : aiSummaryAdmin.strings.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                if (status === 'timeout') {
                    showStatus('Request timed out. The process may still be running in the background.', 'warning');
                } else {
                    showStatus(aiSummaryAdmin.strings.error, 'error');
                }
            },
            complete: function() {
                // Restore button
                $button.prop('disabled', false).text(originalText);
                
                // Auto-hide success message
                setTimeout(function() {
                    $('#ai-summary-status').fadeOut();
                }, 5000);
            }
        });
    }

    /**
     * Show status message
     */
    function showStatus(message, type) {
        const $statusDiv = $('#ai-summary-status');
        const $statusMessage = $('#ai-summary-status-message');
        
        // Update message and type
        $statusMessage.text(message);
        $statusDiv.find('.notice')
            .removeClass('notice-info notice-success notice-error notice-warning')
            .addClass('notice-' + type);
        
        // Show the status div
        $statusDiv.show();
    }

})(jQuery);
