/**
 * WP Form Data Collector Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Toggle custom date range
    $('input[name="date_range"]').on('change', function() {
        var customRange = $('#custom-date-range');
        if ($(this).val() === 'custom') {
            customRange.show();
        } else {
            customRange.hide();
        }
    });
    
    // Bulk actions confirmation
    $('.bulkactions button').on('click', function(e) {
        var action = $('select[name="action"]').val();
        var action2 = $('select[name="action2"]').val();
        var selectedAction = action !== '-1' ? action : action2;
        
        if (selectedAction === 'delete') {
            if (!confirm(wpfdc_admin.confirm_delete_bulk)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Single delete confirmation
    $('.row-actions .delete a').on('click', function(e) {
        if (!confirm(wpfdc_admin.confirm_delete)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Quick status update
    $('.wpfdc-quick-status').on('change', function() {
        var submissionId = $(this).data('id');
        var newStatus = $(this).val();
        var nonce = wpfdc_admin.nonce;
        
        $.ajax({
            url: wpfdc_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpfdc_update_status',
                id: submissionId,
                status: newStatus,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update status display
                    var statusCell = $('#submission-' + submissionId + ' .status');
                    statusCell.html(response.data.status_html);
                    
                    // Show success message
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data.message, 'error');
                }
            }
        });
    });
    
    // Export button handler
    $('.wpfdc-export-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        
        button.text(wpfdc_admin.exporting);
        button.prop('disabled', true);
        
        $.ajax({
            url: wpfdc_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpfdc_export',
                nonce: wpfdc_admin.nonce
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function(data, status, xhr) {
                // Create download link
                var blob = new Blob([data], { type: 'text/csv' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'form-submissions-' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                // Show success message
                showNotice(wpfdc_admin.export_success, 'success');
            },
            error: function() {
                showNotice(wpfdc_admin.export_error, 'error');
            },
            complete: function() {
                button.text(originalText);
                button.prop('disabled', false);
            }
        });
    });
    
    // Show admin notice
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $(
            '<div class="notice ' + noticeClass + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss">' +
            '<span class="screen-reader-text">Dismiss this notice.</span>' +
            '</button>' +
            '</div>'
        );
        
        $('.wrap h1').after(notice);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Dismiss button
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Toggle all checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('click', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="submission[]"]').prop('checked', isChecked);
    });
    
    // Real-time search
    var searchTimeout;
    $('input[name="s"]').on('keyup', function() {
        clearTimeout(searchTimeout);
        var searchQuery = $(this).val();
        
        searchTimeout = setTimeout(function() {
            if (searchQuery.length >= 2 || searchQuery.length === 0) {
                $('.search-box').submit();
            }
        }, 500);
    });
});