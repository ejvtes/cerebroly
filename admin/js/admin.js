/**
 * JavaScript for the Cerebroly Training Dashboard
 */
jQuery(document).ready(function($) {
    // If there is a training session in progress, start automatic status checks
    if ($('.cerebroly-training-progress').length > 0) {
        // Initial status check on page load
        checkTrainingStatus();
        
        // Check status every 30 seconds
        var statusInterval = setInterval(checkTrainingStatus, 30000);
        
        // Manual status check button
        $('.cerebroly-check-status').on('click', function() {
            $(this).prop('disabled', true).text('Checking...');
            checkTrainingStatus();
        });
    }
    
    /**
     * Function to check the training status via AJAX
     */
    function checkTrainingStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cerebroly_check_training_status',
                security: cerebrolAdminData.nonce
            },
            success: function(response) {
                console.log('Server response:', response);
                
                if (response.success) {
                    var data = response.data;
                    
                    // Update progress bar
                    $('.cerebroly-progress-bar').css('width', data.progress + '%');
                    $('.cerebroly-progress-percent').text(Math.round(data.progress));
                    $('.cerebroly-elapsed-time').text(data.elapsed_time);
                    
                    // If training is completed, reload the page after a short delay
                    if (data.is_completed) {
                        $('.cerebroly-progress-status').html('<strong style="color:#46b450">Training completed!</strong>');
                        clearInterval(statusInterval);
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    }
                } else {
                    console.error('Error checking status:', response.data);
                    
                    // Display error message
                    $('.cerebroly-progress-status').html('<span style="color:#dc3232">Error checking status</span>');
                }
                
                // Re-enable the manual check button
                $('.cerebroly-check-status').prop('disabled', false).text('Update Status');
            },
            error: function(xhr, status, error) {
                console.error('Connection error while checking status:', error);
                console.log('XHR:', xhr);
                
                // Display connection error message
                $('.cerebroly-progress-status').html('<span style="color:#dc3232">Connection error</span>');
                $('.cerebroly-check-status').prop('disabled', false).text('Update Status');
            }
        });
    }
    
    // Initialize elapsed time counter for ongoing training
    var startTime = $('.cerebroly-training-progress').data('start-time');
    if (startTime) {
        var elapsedSeconds = Math.floor(Date.now() / 1000) - startTime;
        updateElapsedTime(elapsedSeconds);
        
        // Update elapsed time every second
        setInterval(function() {
            elapsedSeconds++;
            updateElapsedTime(elapsedSeconds);
        }, 1000);
    }
    
    /**
     * Function to update the elapsed time display
     * @param {number} seconds - Total elapsed seconds
     */
    function updateElapsedTime(seconds) {
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds - (hours * 3600)) / 60);
        var secs = seconds - (hours * 3600) - (minutes * 60);
        
        // Format with leading zeros if necessary
        hours = (hours < 10) ? "0" + hours : hours;
        minutes = (minutes < 10) ? "0" + minutes : minutes;
        secs = (secs < 10) ? "0" + secs : secs;
        
        $('.cerebroly-elapsed-time').text(hours + ":" + minutes + ":" + secs);
    }
    
    // Confirmation prompt before starting a new training session
    $('button[type="submit"]').on('click', function(e) {
        var form = $(this).closest('form');
        
        // Check if it's the training form (verify by nonce and action)
        if (form.find('input[name="_wpnonce"]').val() && 
            (form.find('input[name="action"]').val() === 'cerebroly_start_training' || 
             form.find('input[name="action"]').val() === 'cerebroly_auto_train_now')) {
            
            if (!confirm('Are you sure you want to start a new training session?\n\nThis process may take several minutes and cannot be canceled once started.')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Chat Appearance - Icon Upload Functionality
    var mediaUploader;
    
    // Handle icon selection change
    $('.cerebroly-icon-radio').on('change', function() {
        if (this.value === 'custom_image') {
            $('.cerebroly-custom-icon-controls').show();
        } else {
            $('.cerebroly-custom-icon-controls').hide();
        }
    });
    
    // Upload button click handler
    $('#cerebroly-upload-icon-btn').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Choose Chat Icon',
            button: {
                text: 'Use This Image'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Update hidden input with the selected image URL
            $('#cerebroly-custom-icon-url').val(attachment.url);
            
            // Update preview in the selector
            $('.cerebroly-custom-icon-preview').html(
                '<img src="' + attachment.url + '" alt="Custom icon" class="cerebroly-custom-icon-image">' +
                '<div class="cerebroly-icon-check"><span class="dashicons dashicons-yes"></span></div>'
            );
            
            // Update large preview
            $('#cerebroly-icon-preview-img').attr('src', attachment.url);
            $('.cerebroly-icon-preview-large').show();
            
            // Select the custom image option
            $('#cerebroly-custom-icon-radio').prop('checked', true);
            $('.cerebroly-custom-icon-controls').show();
            
            // Show the remove button
            $('#cerebroly-remove-icon-btn').show();
        });
        
        mediaUploader.open();
    });
    
    // Remove image button handler
    $('#cerebroly-remove-icon-btn').on('click', function(e) {
        e.preventDefault();
        
        // Clear the hidden input
        $('#cerebroly-custom-icon-url').val('');
        
        // Reset preview in the selector
        $('.cerebroly-custom-icon-preview').html(
            '<span class="cerebroly-icon-placeholder">' +
                '<span class="dashicons dashicons-plus-alt2"></span>' +
                '<span class="cerebroly-upload-text">Upload Image</span>' +
            '</span>' +
            '<div class="cerebroly-icon-check"><span class="dashicons dashicons-yes"></span></div>'
        );
        
        // Hide the large preview
        $('.cerebroly-icon-preview-large').hide();
        
        // Hide the remove button
        $(this).hide();
        
        // Select the default icon option
        $('input[name="cerebroly_chat_appearance_config[custom_icon]"][value="default"]').prop('checked', true);
        $('.cerebroly-custom-icon-controls').hide();
    });
});