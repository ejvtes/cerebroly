<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Chat appearance configuration page view
 * File: admin/views/chat-appearance.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get the appearance manager instance safely
$appearance_manager = CEREBROLY_Appearance_Manager::get_instance();
$config = $appearance_manager->get_config();
?>

<style>
/* Basic metabox styles */
.metabox-holder {
    margin-bottom: 20px;
    max-width: 100%;
    overflow: hidden;
    margin-right: 20px;
}

.postbox-container {
    max-width: 100%;
    overflow: hidden;
}

.postbox .inside {
    padding: 15px;
    max-width: 100%;
    overflow: hidden;
}

.description {
    margin-bottom: 15px;
    font-style: italic;
    color: #666;
}

/* THEME SELECTOR STYLES */
.cerebroly-theme-selector {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
    gap: 20px !important;
    max-width: 100% !important;
    overflow: hidden !important;
}

.cerebroly-theme-option {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    background: white;
    transition: all 0.2s ease;
}

.cerebroly-theme-option:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Native radio button styling */
.cerebroly-radio-label {
    display: flex !important;
    align-items: center !important;
    margin-bottom: 15px !important;
    cursor: pointer !important;
}

.cerebroly-native-radio {
    margin-right: 10px !important;
    margin-top: 0 !important;
}

.cerebroly-radio-text {
    font-weight: 600 !important;
    font-size: 16px !important;
    color: #1d2327 !important;
}

/* Theme image */
.cerebroly-theme-image {
    margin-bottom: 15px;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
    max-height: 340px;

}

.cerebroly-theme-image img {
    height: 100%;
}

.cerebroly-theme-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f6f7f7;
    color: #666;
}

.cerebroly-theme-placeholder .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    margin-bottom: 8px;
}

.cerebroly-theme-placeholder small {
    font-size: 12px;
    text-align: center;
}

/* Theme info */
.cerebroly-theme-description {
    font-size: 13px !important;
    color: #646970 !important;
    margin-bottom: 12px !important;
}

.cerebroly-theme-features {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}

.cerebroly-feature-tag {
    background: #f0f6fc;
    color: #2271b1;
    font-size: 11px;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cerebroly-color-preview {
    display: flex;
    gap: 8px;
    align-items: center;
}

.cerebroly-color-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
}

/* Selected state */
.cerebroly-theme-option:has(.cerebroly-native-radio:checked) {
    border-color: #2271b1 !important;
    background: #f0f6fc !important;
    box-shadow: 0 0 0 1px #2271b1 !important;
}

/* ICON SELECTOR STYLES */
.cerebroly-icon-selector {
    display: flex !important;
    gap: 15px !important;
}

.cerebroly-icon-check {
    display: none;
}

.cerebroly-icon-preview {
    width: 60px !important;
    height: 60px !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
}

.cerebroly-icon-option input[type="radio"] {
    position: absolute !important;
    width: 100% !important;
    height: 100% !important;
    opacity: 0 !important;
    cursor: pointer !important;
    z-index: 10 !important;
}

.cerebroly-icon-option:has(input:checked) .cerebroly-icon-preview {
    border-color: #2271b1 !important;
    background: #f0f6fc !important;
}

.cerebroly-icon-emoji {
    font-size: 24px !important;
    line-height: 1 !important;
}

/* SIZE SELECTOR STYLES */
.cerebroly-size-option {
    display: block;
    margin-bottom: 12px;
    cursor: pointer;
    padding: 8px 0;
}

.cerebroly-size-option input[type="radio"] {
    margin-right: 10px;
}

.cerebroly-size-option:hover {
    color: #2271b1;
}

/* WELCOME MESSAGES STYLES */
.cerebroly-welcome-messages {
    max-width: 800px;
}

.cerebroly-welcome-message-item {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 5px;
}

.cerebroly-welcome-message-item label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1d2327;
}

.cerebroly-welcome-message-textarea {
    width: 100%;
    max-width: 100%;
    min-height: 80px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.4;
    resize: vertical;
}

.cerebroly-welcome-message-textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.cerebroly-remove-message {
    margin-top: 10px;
    color: #d63638 !important;
    border-color: #d63638 !important;
}

.cerebroly-remove-message:hover {
    background: #d63638 !important;
    color: white !important;
}

.cerebroly-welcome-actions {
    margin: 20px 0;
    padding-top: 15px;
    border-top: 1px solid #e5e5e5;
}

.cerebroly-welcome-actions .button {
    margin-right: 10px;
}

#cerebroly-add-welcome-message {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

#cerebroly-add-welcome-message:hover {
    background: #135e96;
    border-color: #135e96;
}

/* CUSTOM ICON UPLOAD STYLES */
.cerebroly-custom-icon-option {
    position: relative;
}

.cerebroly-custom-icon-preview {
    position: relative;
    overflow: hidden;
}

.cerebroly-custom-icon-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.cerebroly-icon-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #666;
    background: #f8f9fa;
    border: 2px dashed #ddd;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.cerebroly-icon-placeholder:hover {
    border-color: #2271b1;
    color: #2271b1;
}

.cerebroly-icon-placeholder .dashicons {
    font-size: 16px;
    margin-bottom: 4px;
}

.cerebroly-upload-text {
    font-size: 10px;
    text-align: center;
    line-height: 1.2;
}

.cerebroly-custom-icon-controls {
    margin-top: 15px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 5px;
}

.cerebroly-image-upload-area {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cerebroly-image-upload-area .button {
    align-self: flex-start;
}

.cerebroly-remove-icon-btn {
    color: #d63638 !important;
    border-color: #d63638 !important;
}

.cerebroly-remove-icon-btn:hover {
    background: #d63638 !important;
    color: white !important;
}

.cerebroly-icon-preview-large {
    margin-top: 10px;
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-align: center;
}

.cerebroly-icon-preview-large img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Enhanced icon option selected state */
.cerebroly-icon-option:has(input:checked) .cerebroly-custom-icon-preview {
    border-color: #2271b1 !important;
    background: #f0f6fc !important;
}

.cerebroly-custom-icon-option:has(input:checked) .cerebroly-icon-placeholder {
    border-color: #2271b1 !important;
    background: #f0f6fc !important;
    color: #2271b1 !important;
}

/* ERROR MESSAGE STYLES */
.cerebroly-error-message {
    max-width: 800px;
}

.cerebroly-error-message textarea {
    width: 100%;
    min-height: 100px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    resize: vertical;
}

.cerebroly-error-message textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.cerebroly-error-actions .button {
    margin-right: 10px;
}

.cerebroly-error-actions .button .dashicons {
    margin-right: 5px;
    vertical-align: text-top;
}

/* Responsive */
@media (max-width: 768px) {
    .cerebroly-theme-selector {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    .metabox-holder,
    .postbox-container,
    .postbox .inside {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }
    
    .cerebroly-icon-selector {
        gap: 10px !important;
    }
    
    .cerebroly-icon-preview {
        width: 50px !important;
        height: 50px !important;
    }
    
    .cerebroly-icon-emoji {
        font-size: 20px !important;
    }
    
    .cerebroly-welcome-message-textarea,
    .cerebroly-error-message textarea {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .cerebroly-welcome-actions .button,
    .cerebroly-error-actions .button {
        display: block;
        width: 100%;
        margin-bottom: 10px;
        margin-right: 0;
    }
}
</style>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('cerebroly_chat_appearance');
        do_settings_sections('cerebroly_chat_appearance');
        ?>
        
        <div class="metabox-holder">
            <div class="postbox-container" style="width: 100%;">
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Visual Theme', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Select the visual theme that best fits your website.', 'cerebroly'); ?>
                        </p>
                        
                        <?php $appearance_manager->render_theme_selector(); ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Chat Icon', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Customize the icon shown when the chat is closed.', 'cerebroly'); ?>
                        </p>
                        
                        <?php $appearance_manager->render_icon_selector(); ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Welcome Messages', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Configure the initial messages displayed when the chat loads. These messages will appear with a typing effect.', 'cerebroly'); ?>
                        </p>
                        
                        <?php $appearance_manager->render_welcome_messages(); ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Error Message', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('This message will be displayed when the chat encounters an error or fails to respond.', 'cerebroly'); ?>
                        </p>
                        
                        <?php $appearance_manager->render_error_message(); ?>
                    </div>
                </div>
                
            </div>
        </div>
        
        
        <?php submit_button(); ?>
    </form>
</div>
