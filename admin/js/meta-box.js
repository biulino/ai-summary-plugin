/**
 * AI Summary Meta Box JavaScript
 *
 * Handles the AI Summary meta box functionality on post/product edit screens
 *
 * @package NeuzaAI\Summary
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initMetaBox();
    });

    /**
     * Initialize meta box functionality
     */
    function initMetaBox() {
        // Regenerate summary button
        $('#regenerate-summary').on('click', handleRegenerateSummary);

        // Add/remove key points
        $('#add-key-point').on('click', addKeyPoint);
        $(document).on('click', '.remove-point', removeKeyPoint);

        // Add/remove FAQ items
        $('#add-faq').on('click', addFAQ);
        $(document).on('click', '.remove-faq', removeFAQ);

        // Auto-resize textareas
        autoResizeTextareas();
    }

    /**
     * Handle regenerate summary button click
     */
    function handleRegenerateSummary(e) {
        e.preventDefault();

        // Debug logging
        console.log('AI Summary: Regenerate button clicked');
        console.log('Ajax URL:', aiSummaryMetaBox?.ajaxUrl);
        console.log('Post ID:', aiSummaryMetaBox?.postId);
        console.log('Nonce:', aiSummaryMetaBox?.nonce);

        // Check if required data is available
        if (typeof aiSummaryMetaBox === 'undefined') {
            console.error('AI Summary: aiSummaryMetaBox object not found!');
            alert('Error: Plugin JavaScript not loaded properly. Please refresh the page.');
            return;
        }

        // Confirm action
        if (!confirm(aiSummaryMetaBox.strings.confirmRegenerate)) {
            return;
        }

        const $button = $(this);
        const $statusDiv = $('#ai-summary-status');
        const $statusMessage = $('#ai-summary-status-message');

        // Store original button text
        if (!$button.data('original-text')) {
            $button.data('original-text', $button.text().trim());
        }

        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.html('<span class="dashicons dashicons-update-alt spin"></span> ' + aiSummaryMetaBox.strings.regenerating);
        
        // Show status message
        $statusMessage.text(aiSummaryMetaBox.strings.regenerating);
        $statusDiv.find('.notice').removeClass('notice-success notice-error').addClass('notice-info');
        $statusDiv.show();

        // Make AJAX request
        $.ajax({
            url: aiSummaryMetaBox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_summary_regenerate',
                post_id: aiSummaryMetaBox.postId,
                nonce: aiSummaryMetaBox.nonce
            },
            beforeSend: function() {
                console.log('AI Summary: Sending AJAX request...');
            },
            success: function(response) {
                console.log('AI Summary: AJAX success response:', response);
                if (response.success) {
                    handleRegenerateSuccess(response.data);
                } else {
                    console.error('AI Summary: Server returned error:', response.data);
                    handleRegenerateError(response.data ? response.data.message : aiSummaryMetaBox.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AI Summary: AJAX Error Details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    xhr: xhr
                });
                handleRegenerateError('AJAX Error: ' + error + ' (Check console for details)');
            },
            complete: function() {
                console.log('AI Summary: AJAX request completed');
                // Re-enable button
                $button.prop('disabled', false);
                $button.html('<span class="dashicons dashicons-update"></span> ' + ($button.data('original-text') || 'Regenerate Summary'));
            }
        });
    }

    /**
     * Handle successful regeneration
     */
    function handleRegenerateSuccess(data) {
        const $statusDiv = $('#ai-summary-status');
        const $statusMessage = $('#ai-summary-status-message');

        // Update status
        $statusMessage.text(data.message || aiSummaryMetaBox.strings.regenerateComplete);
        $statusDiv.find('.notice').removeClass('notice-info notice-error').addClass('notice-success');

        // Update form fields
        if (data.data) {
            // Update summary text
            $('#ai_summary_text').val(data.data.summary || '');

            // Update key points
            updateKeyPoints(data.data.points || []);

            // Update FAQ
            updateFAQ(data.data.faq || []);

            // Update last generated timestamp
            if (data.data.last_generated) {
                $('.ai-summary-last-generated').text('Last generated: ' + data.data.last_generated);
            }
        }

        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            $statusDiv.fadeOut();
        }, 5000);
    }

    /**
     * Handle regeneration error
     */
    function handleRegenerateError(message) {
        const $statusDiv = $('#ai-summary-status');
        const $statusMessage = $('#ai-summary-status-message');

        $statusMessage.text(message || aiSummaryMetaBox.strings.error);
        $statusDiv.find('.notice').removeClass('notice-info notice-success').addClass('notice-error');
    }

    /**
     * Update key points display
     */
    function updateKeyPoints(points) {
        const $container = $('#ai-summary-points-container');
        
        // Clear existing points
        $container.empty();

        // Add new points or at least one empty field
        if (points.length === 0) {
            points = [''];
        }

        points.forEach(function(point) {
            const $pointHtml = $(getKeyPointTemplate());
            $pointHtml.find('input').val(point);
            $container.append($pointHtml);
        });
    }

    /**
     * Update FAQ display
     */
    function updateFAQ(faqItems) {
        const $container = $('#ai-summary-faq-container');
        
        // Clear existing FAQ
        $container.empty();

        // Add new FAQ items or at least one empty item
        if (faqItems.length === 0) {
            faqItems = [{ question: '', answer: '' }];
        }

        faqItems.forEach(function(item, index) {
            const $faqHtml = $(getFAQTemplate(index));
            $faqHtml.find('input[name*="[question]"]').val(item.question || '');
            $faqHtml.find('textarea[name*="[answer]"]').val(item.answer || '');
            $container.append($faqHtml);
        });

        // Auto-resize new textareas
        autoResizeTextareas();
    }

    /**
     * Add new key point
     */
    function addKeyPoint(e) {
        e.preventDefault();
        
        const $container = $('#ai-summary-points-container');
        const $newPoint = $(getKeyPointTemplate());
        
        $container.append($newPoint);
        $newPoint.find('input').focus();
    }

    /**
     * Remove key point
     */
    function removeKeyPoint(e) {
        e.preventDefault();
        
        const $container = $('#ai-summary-points-container');
        
        // Don't remove if it's the last one
        if ($container.find('.key-point-item').length > 1) {
            $(this).closest('.key-point-item').remove();
        } else {
            // Just clear the input
            $(this).closest('.key-point-item').find('input').val('');
        }
    }

    /**
     * Add new FAQ item
     */
    function addFAQ(e) {
        e.preventDefault();
        
        const $container = $('#ai-summary-faq-container');
        const currentCount = $container.find('.faq-item').length;
        const $newFAQ = $(getFAQTemplate(currentCount));
        
        $container.append($newFAQ);
        $newFAQ.find('input').first().focus();
        
        // Auto-resize new textareas
        autoResizeTextareas();
    }

    /**
     * Remove FAQ item
     */
    function removeFAQ(e) {
        e.preventDefault();
        
        const $container = $('#ai-summary-faq-container');
        
        // Don't remove if it's the last one
        if ($container.find('.faq-item').length > 1) {
            $(this).closest('.faq-item').remove();
        } else {
            // Just clear the inputs
            const $item = $(this).closest('.faq-item');
            $item.find('input, textarea').val('');
        }
    }

    /**
     * Get key point template
     */
    function getKeyPointTemplate() {
        return $('#key-point-template').html();
    }

    /**
     * Get FAQ template with proper index
     */
    function getFAQTemplate(index) {
        let template = $('#faq-template').html();
        return template.replace(/\{\{INDEX\}\}/g, index);
    }

    /**
     * Auto-resize textareas
     */
    function autoResizeTextareas() {
        $('textarea').each(function() {
            const $textarea = $(this);
            
            // Set initial height
            $textarea.css('height', 'auto');
            $textarea.css('height', $textarea[0].scrollHeight + 'px');
            
            // Auto-resize on input
            $textarea.off('input.autoResize').on('input.autoResize', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    }

    // Store original button text
    $(document).ready(function() {
        $('#regenerate-summary').data('original-text', $('#regenerate-summary').text().trim());
    });

})(jQuery);
