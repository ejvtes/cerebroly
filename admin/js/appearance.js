jQuery(document).ready(function($) {
    console.log('Cerebroly Appearance JS loaded');
    console.log('wp.media available:', typeof wp !== 'undefined' && typeof wp.media !== 'undefined');
    
    var mediaUploader;

    // Upload custom icon button click
    $(document).on('click', '#cerebroly-upload-icon-btn', function(e) {
        e.preventDefault();
        console.log('Upload button clicked');
        
        // Check if wp.media is available
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            console.error('wp.media is not available');
            alert('Media library is not available. Please refresh the page.');
            return;
        }
        
        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Extend the wp.media object
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
        
        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function() {
            console.log('Image selected');
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Set the custom icon URL
            $('#cerebroly-custom-icon-url').val(attachment.url);
            
            // Update the preview
            $('#cerebroly-icon-preview').html('<img src="' + attachment.url + '" alt="Custom icon preview" style="max-width: 60px; max-height: 60px; border-radius: 50%; border: 2px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><p><small><strong>Custom icon preview</strong></small></p>').show();
            
            // Show remove button
            $('#cerebroly-remove-icon-btn').show();
            
            // Automatically select the custom_image radio button if it exists
            $('input[name="cerebroly_chat_appearance_config[custom_icon]"][value="custom_image"]').prop('checked', true).trigger('change');
            
            console.log('Custom icon set to:', attachment.url);
        });
        
        // Open the uploader dialog
        mediaUploader.open();
    });

    // Remove custom icon button click
    $(document).on('click', '#cerebroly-remove-icon-btn', function(e) {
        e.preventDefault();
        console.log('Remove custom icon clicked');
        
        // Clear the custom icon URL
        $('#cerebroly-custom-icon-url').val('');
        
        // Hide the preview
        $('#cerebroly-icon-preview').html('').hide();
        
        // Hide remove button
        $(this).hide();
        
        // Optionally select the first default icon
        $('input[name="cerebroly_chat_appearance_config[custom_icon]"]:first').prop('checked', true).trigger('change');
        
        console.log('Custom icon removed');
    });

    // Icon selector change handler
    $('input[name="cerebroly_chat_appearance_config[icon]"]').on('change', function() {
        var selectedIcon = $(this).val();
        
        if (selectedIcon === 'custom_image') {
            $('#cerebroly-custom-icon-upload').show();
        } else {
            $('#cerebroly-custom-icon-upload').hide();
        }
        
        // Update live preview if needed
        updateIconPreview(selectedIcon);
    });

    // Function to update icon preview
    function updateIconPreview(iconType) {
        var previewContainer = $('#cerebroly-icon-live-preview');
        if (previewContainer.length) {
            if (iconType === 'custom_image') {
                var customUrl = $('#cerebroly-custom-icon-url').val();
                if (customUrl) {
                    previewContainer.html('<img src="' + customUrl + '" style="width: 40px; height: 40px; border-radius: 50%;">');
                } else {
                    previewContainer.html('Upload an image');
                }
            } else {
                previewContainer.html('<span style="font-size: 24px;">' + iconType + '</span>');
            }
        }
    }

    // Initialize on page load
    var selectedIcon = $('input[name="cerebroly_chat_appearance_config[icon]"]:checked').val();
    if (selectedIcon === 'custom_image') {
        $('#cerebroly-custom-icon-upload').show();
        var customUrl = $('#cerebroly-custom-icon-url').val();
        if (customUrl) {
            $('#cerebroly-icon-preview').html('<img src="' + customUrl + '" style="max-width: 40px; max-height: 40px; border-radius: 50%;">');
            $('#cerebroly-remove-icon-btn').show();
        }
    } else {
        $('#cerebroly-custom-icon-upload').hide();
    }
});