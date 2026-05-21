<?php

    // Prevent direct access
    if (!defined('ABSPATH')) {
        exit;
    }
    /* Fine-Tuned Chat Appearance Manager
    *
    *  This class manages the appearance settings for the Fine-Tuned Chat plugin,
    *  including theme selection, icon customization, chat position, size, and welcome messages.
    *
    */
    class CEREBROLY_Appearance_Manager {
        
        /**
         * Single instance
         */
        private static $instance = null;
        
        /**
         * Available themes
         */
        private $available_themes = array();
        
        /**
         * Constructor
         */
        public function __construct() {
            $this->init_themes();
            $this->init_hooks();
        }
        
        /**
         * Get single instance
         */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        /**
         * Initialize hooks
         */
        private function init_hooks() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
            
            // Add theme CSS loading for frontend
            add_action('wp_enqueue_scripts', array($this, 'enqueue_theme_styles'));
        }
        
        /**
         * Initialize available themes - Only 2 themes
         */
        private function init_themes() {
            $this->available_themes = array(
                'cerebroly-theme' => array(
                    'name' => __('cerebroly Theme', 'cerebroly'),
                    'description' => __('Official cerebroly theme with modern design and smart features', 'cerebroly'),
                    'image' => CEREBROLY_PLUGIN_URL . 'assets/images/themes/cerebroly-theme.png',
                    'colors' => array(
                        'primary' => '#2563eb',
                        'background' => '#ffffff',
                        'text' => '#1f2937'
                    ),
                    'supported_features' => array('light_mode', 'typing_effect', 'emoji_support')
                ),
                
                'dark-theme' => array(
                    'name' => __('Dark Theme', 'cerebroly'),
                    'description' => __('Elegant dark theme perfect for modern websites and startups', 'cerebroly'),
                    'image' => CEREBROLY_PLUGIN_URL . 'assets/images/themes/dark-theme.png',
                    'colors' => array(
                        'primary' => '#7f5af0',
                        'background' => '#0a0709',
                        'text' => '#ffffff'
                    ),
                    'supported_features' => array('dark_mode', 'typing_effect', 'emoji_support')
                ),
                
                'sientegrowth-theme' => array(
                    'name' => __('SienteGrowth Theme', 'cerebroly'),
                    'description' => __('Corporate orange theme with modern design and growth-focused branding, This theme is a generous donation from https://sientegrowth.com', 'cerebroly'),
                    'image' => CEREBROLY_PLUGIN_URL . 'assets/images/themes/sientegrowth-theme.png',
                    'colors' => array(
                        'primary' => '#ff4802',
                        'background' => '#fffaf9',
                        'text' => '#991a00'
                    ),
                    'supported_features' => array('light_mode', 'typing_effect', 'emoji_support', 'corporate_branding')
                )
            );
        }

        public function enqueue_theme_styles() {
            // NO global CSS - everything loaded in Shadow DOM
            // Only add custom icon styles for chat container
            $this->add_custom_icon_styles();
        }
        

        /**
         * Add custom icon styles based on selected icon
         */
        public function add_custom_icon_styles() {
            $config = $this->get_config();
            $selected_icon = $config['custom_icon'];
            
            // PRIORITY 1: Custom uploaded image (if URL exists, always use it)
            if (!empty($config['custom_icon_url'])) {
                // Custom image icon - target both possible selectors
                $custom_css = "
                    chat-container.closed::after,
                    chat-container.chat-closed::after {
                        content: '' !important;
                        background-image: url('" . esc_url($config['custom_icon_url']) . "') !important;
                        background-size: cover !important;
                        background-position: center !important;
                        background-repeat: no-repeat !important;
                        width: 40px !important;
                        height: 40px !important;
                        border-radius: 50% !important;
                        display: block !important;
                    }
                ";
                
                wp_add_inline_style('cerebroly-chat-theme', $custom_css);
            } 
            // PRIORITY 2: Emoji icons (only if no custom image)
            elseif ($selected_icon !== 'default') {
                // Custom emoji icon - target both possible selectors
                $icons = array(
                    'robot' => '🤖',
                    'message' => '💭',
                    'help' => '❓',
                    'support' => '🎧'
                );
                
                if (isset($icons[$selected_icon])) {
                    $custom_css = "
                        chat-container.closed::after,
                        chat-container.chat-closed::after {
                            content: '" . $icons[$selected_icon] . "' !important;
                        }
                    ";
                    
                    wp_add_inline_style('cerebroly-chat-theme', $custom_css);
                }
            }
        }
        
        /**
         * Add admin menu
         */
        public function add_admin_menu() {
            add_submenu_page(
                'cerebroly',
                __('cerebroly - Appearance', 'cerebroly'),
                __('Appearance', 'cerebroly'),
                'manage_options',
                'cerebroly-chat-appearance',
                array($this, 'render_admin_page')
            );
            
            // Add Settings as the last menu item
            if (class_exists('CEREBROLY_Admin')) {
                $admin_instance = new CEREBROLY_Admin();
                add_submenu_page(
                    'cerebroly',
                    __('cerebroly - Settings', 'cerebroly'),
                    __('Settings', 'cerebroly'),
                    'manage_options',
                    'cerebroly-settings',
                    array($admin_instance, 'render_settings_page')
                );
            }
        }
        
        /**
 * Register settings with proper sanitization
 */
public function register_settings() {
    register_setting('cerebroly_chat_appearance', 'cerebroly_chat_appearance_config', array(
        'sanitize_callback' => array($this, 'sanitize_appearance_config'),
        'default' => array(
            'selected_theme' => 'cerebroly-theme',
            'chat_position' => 'bottom-right',
            'chat_size' => 'medium',
            'custom_icon' => 'default',
            'custom_icon_url' => '',
            'welcome_messages' => $this->get_default_welcome_messages(),
            'error_message' => $this->get_default_error_message()
        )
    ));
}

/**
 * Sanitize appearance configuration array
 * 
 * @param array $input Raw input from form
 * @return array Sanitized configuration
 */
public function sanitize_appearance_config($input) {
    $sanitized = array();
    
    // Sanitize selected theme
    if (isset($input['selected_theme'])) {
        $sanitized['selected_theme'] = $this->sanitize_theme_selection($input['selected_theme']);
    }
    
    // Sanitize chat position
    if (isset($input['chat_position'])) {
        $sanitized['chat_position'] = $this->sanitize_chat_position($input['chat_position']);
    }
    
    // Sanitize chat size
    if (isset($input['chat_size'])) {
        $sanitized['chat_size'] = $this->sanitize_chat_size($input['chat_size']);
    }
    
    // Sanitize custom icon
    if (isset($input['custom_icon'])) {
        $sanitized['custom_icon'] = $this->sanitize_custom_icon($input['custom_icon']);
    }
    
    // Sanitize custom icon URL
    if (isset($input['custom_icon_url'])) {
        $sanitized['custom_icon_url'] = $this->sanitize_icon_url($input['custom_icon_url']);
    }
    
    // Sanitize welcome messages
    if (isset($input['welcome_messages']) && is_array($input['welcome_messages'])) {
        $sanitized['welcome_messages'] = $this->sanitize_welcome_messages($input['welcome_messages']);
    }
    
    // Sanitize error message
    if (isset($input['error_message'])) {
        $sanitized['error_message'] = $this->sanitize_text_content($input['error_message']);
    }
    
    return $sanitized;
}

/**
 * Sanitize theme selection (reusing validation pattern)
 */
private function sanitize_theme_selection($theme) {
    $allowed_themes = array_keys($this->available_themes);
    $sanitized = sanitize_text_field($theme);
    
    return in_array($sanitized, $allowed_themes, true) ? $sanitized : 'cerebroly-theme';
}

/**
 * Sanitize chat position (reusing validation pattern)
 */
private function sanitize_chat_position($position) {
    $allowed_positions = array('bottom-right', 'bottom-left', 'center');
    $sanitized = sanitize_text_field($position);
    
    return in_array($sanitized, $allowed_positions, true) ? $sanitized : 'bottom-right';
}

/**
 * Sanitize chat size (reusing validation pattern)
 */
private function sanitize_chat_size($size) {
    $allowed_sizes = array('medium');
    $sanitized = sanitize_text_field($size);
    
    return in_array($sanitized, $allowed_sizes, true) ? $sanitized : 'medium';
}

/**
 * Sanitize custom icon (reusing validation pattern)
 */
private function sanitize_custom_icon($icon) {
    $allowed_icons = array('default', 'robot', 'message', 'help', 'support');
    $sanitized = sanitize_text_field($icon);
    
    return in_array($sanitized, $allowed_icons, true) ? $sanitized : 'default';
}

/**
 * Sanitize icon URL (reusing URL sanitization)
 */
private function sanitize_icon_url($url) {
    if (empty($url)) {
        return '';
    }
    
    // If numeric (attachment ID)
    if (is_numeric($url)) {
        $attachment_id = absint($url);
        return wp_attachment_is_image($attachment_id) ? wp_get_attachment_url($attachment_id) : '';
    }
    
    // Sanitize URL
    return esc_url_raw($url);
}

/**
 * Sanitize welcome messages array
 */
private function sanitize_welcome_messages($messages) {
    $sanitized = array();
    
    foreach ($messages as $message) {
        $clean_message = $this->sanitize_text_content($message);
        if (!empty(trim($clean_message))) {
            $sanitized[] = $clean_message;
        }
    }
    
    // Ensure at least one default message
    if (empty($sanitized)) {
        return $this->get_default_welcome_messages();
    }
    
    return $sanitized;
}

/**
 * Sanitize text content (reusable for messages)
 */
private function sanitize_text_content($content) {
    // Allow basic HTML tags in messages
    $allowed_tags = array(
        'br' => array(),
        'strong' => array(),
        'em' => array(),
        'b' => array(),
        'i' => array(),
        'span' => array('style' => array()),
        'p' => array()
    );
    
    $sanitized = wp_kses($content, $allowed_tags);
    
    // Limit length (reasonable for chat messages)
    return substr($sanitized, 0, 1000);
}
        
        /**
         * Enqueue admin styles and scripts
         */
        public function enqueue_admin_styles($hook) {
            // Enqueue media uploader and jQuery globally on admin
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            
            // Enqueue appearance-specific JavaScript globally
            wp_enqueue_script(
                'cerebroly-appearance-script',
                CEREBROLY_PLUGIN_URL . 'admin/js/appearance.js',
                array('jquery', 'media-upload', 'media-views'),
                CEREBROLY_VERSION,
                true
            );
            
            // Localize script data
            wp_localize_script('cerebroly-appearance-script', 'ftcAppearanceData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cerebroly_appearance_nonce')
            ));
            
            if ('cerebroly_page_cerebroly-chat-appearance' !== $hook) {
                return;
            }
            
            wp_enqueue_style(
                'cerebroly-appearance-admin',
                CEREBROLY_PLUGIN_URL . 'admin/css/appearance.css',
                array(),
                CEREBROLY_VERSION
            );
        }
        
        /**
         * Render admin page
         */
        public function render_admin_page() {
            include CEREBROLY_PLUGIN_DIR . 'admin/views/chat-appearance.php';
        }
        
        /**
 * Render theme selector with native radio buttons
 */
public function render_theme_selector() {
    $config = $this->get_config();
    // Default theme should be an existing key in $this->available_themes
    // 'dark-professional' might not exist if only 'cerebroly-theme' and 'dark-theme' are available
    $selected_theme = isset($config['selected_theme']) && isset($this->available_themes[$config['selected_theme']]) 
                      ? $config['selected_theme'] 
                      : 'cerebroly-theme'; // Fallback to 'cerebroly-theme'
    
    echo '<div class="cerebroly-theme-selector">';
    
    foreach ($this->available_themes as $theme_id => $theme) {
        $is_selected = ($selected_theme === $theme_id);
        ?>
        <div class="cerebroly-theme-option">
            <div class="cerebroly-theme-card">
                <label class="cerebroly-radio-label">
                    <input type="radio" name="cerebroly_chat_appearance_config[selected_theme]"
                        value="<?php echo esc_attr($theme_id); ?>" <?php checked($selected_theme, $theme_id); ?>
                        class="cerebroly-native-radio">
                    <span class="cerebroly-radio-text"><?php echo esc_html($theme['name']); ?></span>
                </label>

                <div class="cerebroly-theme-image">
                    <?php if (!empty($theme['image']) && filter_var($theme['image'], FILTER_VALIDATE_URL)): ?>
                        <img src="<?php echo esc_url($theme['image']); ?>" 
                             alt="<?php echo esc_attr($theme['name']); ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <?php endif; ?>
                    
                    <div class="cerebroly-theme-placeholder" style="display: none;">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <small><?php echo esc_html($theme['name']); ?></small>
                    </div>
                </div>

                <div class="cerebroly-theme-info">
                    <p class="cerebroly-theme-description"><?php echo esc_html($theme['description']); ?></p>

                    <?php if (!empty($theme['supported_features'])): ?>
                    <div class="cerebroly-theme-features">
                        <?php foreach ($theme['supported_features'] as $feature): ?>
                        <span class="cerebroly-feature-tag">
                            <?php echo esc_html($this->get_feature_label($feature)); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($theme['colors'])): ?>
                    <div class="cerebroly-color-preview">
                        <?php foreach ($theme['colors'] as $color): ?>
                        <span class="cerebroly-color-dot" style="background-color: <?php echo esc_attr($color); ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    echo '</div>';
}
        /**
         * Render icon selector
         */
        public function render_icon_selector() {
            $config = $this->get_config();
            $selected_icon = isset($config['custom_icon']) ? $config['custom_icon'] : 'default';
            $custom_icon_url = isset($config['custom_icon_url']) ? $config['custom_icon_url'] : '';
            
            $icons = array(
                'default' => '💬',
                'robot' => '🤖',
                'message' => '💭',
                'help' => '❓',
                'support' => '🎧'
            );
            
            echo '<div class="cerebroly-icon-selector">';
            
            // Default emoji icons
            foreach ($icons as $icon_id => $icon_display) {
                ?>
<label class="cerebroly-icon-option">
    <input type="radio" name="cerebroly_chat_appearance_config[custom_icon]" value="<?php echo esc_attr($icon_id); ?>"
        <?php checked($selected_icon, $icon_id); ?> class="screen-reader-text cerebroly-icon-radio">

    <div class="cerebroly-icon-preview">
        <span class="cerebroly-icon-emoji"><?php echo esc_attr( $icon_display); ?></span>
        <div class="cerebroly-icon-check">
            <span class="dashicons dashicons-yes"></span>
        </div>
    </div>
</label>
<?php
            }
            
            echo '</div>';
            
            // Hidden input for custom icon URL
            ?>
<input type="hidden" name="cerebroly_chat_appearance_config[custom_icon_url]"
    value="<?php echo esc_attr($custom_icon_url); ?>" id="cerebroly-custom-icon-url">

<!-- Custom Image Upload - Always visible -->
<div class="cerebroly-custom-icon-controls" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
    <h4><?php esc_html_e('Custom Icon Upload', 'cerebroly'); ?></h4>
    <p class="description">
        <?php esc_html_e('Upload a custom icon image (PNG, JPG, WEBP recommended). Optimal size: 60x60px or larger. After uploading, the custom image will automatically be used as your chat icon.', 'cerebroly'); ?>
    </p>

    <div class="cerebroly-image-upload-area">
        <button type="button" class="button button-primary" id="cerebroly-upload-icon-btn">
            <?php esc_html_e('Choose Custom Image', 'cerebroly'); ?>
        </button>

        <?php if ($custom_icon_url): ?>
        <button type="button" class="button" id="cerebroly-remove-icon-btn" style="margin-left: 10px;">
            <?php esc_html_e('Remove Custom Image', 'cerebroly'); ?>
        </button>
        <?php endif; ?>

        <?php if ($custom_icon_url): ?>
        <div class="cerebroly-icon-preview-large" id="cerebroly-icon-preview" style="margin-top: 15px;">
            <?php
                                if (!empty($custom_icon_url) && is_numeric($custom_icon_url)) {
                                    echo wp_get_attachment_image(
                                        $custom_icon_url,
                                        'thumbnail',
                                        false,
                                        array(
                                            'alt' => esc_attr__('Custom icon preview', 'cerebroly'),
                                            'style' => 'max-width: 60px; max-height: 60px; border-radius: 50%; border: 2px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'
                                        )
                                    );
                                } elseif (!empty($custom_icon_url) && filter_var($custom_icon_url, FILTER_VALIDATE_URL)) {
                                    // Fallback para URLs externas
                                    $attachment_id = attachment_url_to_postid($custom_icon_url);
                                    if ($attachment_id) {
                                        echo wp_get_attachment_image(
                                            $attachment_id,
                                            'thumbnail',
                                            false,
                                            array(
                                                'alt' => esc_attr__('Custom icon preview', 'cerebroly'),
                                                'style' => 'max-width: 60px; max-height: 60px; border-radius: 50%; border: 2px solid #ccc; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'
                                            )
                                        );
                                    }
                                }
                                ?>
            <p><small><strong><?php esc_html_e('Custom icon preview', 'cerebroly'); ?></strong></small></p>
        </div>
        <?php else: ?>
        <div class="cerebroly-icon-preview-large" id="cerebroly-icon-preview" style="margin-top: 15px; display: none;">
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
        }
        
        /**
         * Render position selector
         */
        public function render_position_selector() {
            $config = $this->get_config();
            $selected_position = isset($config['chat_position']) ? $config['chat_position'] : 'bottom-right';
            
            $positions = array(
                'bottom-right' => __('Bottom Right', 'cerebroly'),
                'bottom-left' => __('Bottom Left', 'cerebroly'),
                'center' => __('Center', 'cerebroly')
            );
            
            foreach ($positions as $pos_id => $label) {
                ?>
<label class="cerebroly-position-option">
    <input type="radio" name="cerebroly_chat_appearance_config[chat_position]" value="<?php echo esc_attr($pos_id); ?>"
        <?php checked($selected_position, $pos_id); ?>>
    <?php echo esc_html($label); ?>
</label><br>
<?php
            }
        }
        
        /**
         * Render size selector
         */
        public function render_size_selector() {
            $config = $this->get_config();
            $selected_size = isset($config['chat_size']) ? $config['chat_size'] : 'medium';
            
            $sizes = array(
                'medium' => __('Medium (400×500px)', 'cerebroly'),
            );
            
            foreach ($sizes as $size_id => $label) {
                ?>
<label class="cerebroly-size-option">
    <input type="radio" name="cerebroly_chat_appearance_config[chat_size]" value="<?php echo esc_attr($size_id); ?>"
        <?php checked($selected_size, $size_id); ?>>
    <?php echo esc_html($label); ?>
</label><br>
<?php
            }
        }
        
        /**
         * Render welcome messages configuration
         */
        public function render_welcome_messages() {
            $config = $this->get_config();
            $welcome_messages = isset($config['welcome_messages']) ? $config['welcome_messages'] : $this->get_default_welcome_messages();
            
            ?>
<div class="cerebroly-welcome-messages">
    <div class="cerebroly-welcome-messages-container">
        <?php for ($i = 0; $i < count($welcome_messages); $i++): ?>
        <div class="cerebroly-welcome-message-item">
            <label for="welcome_message_<?php echo esc_attr( $i); ?>">
                <?php 
                            // translators: %d is the message number
                            echo esc_attr(__('Message:', 'cerebroly'), $i + 1); 
                            ?>

            </label>
            <textarea id="welcome_message_<?php echo esc_attr( $i); ?>"
                name="cerebroly_chat_appearance_config[welcome_messages][<?php echo esc_attr( $i); ?>]" rows="3"
                class="cerebroly-welcome-message-textarea"
                placeholder="<?php esc_attr_e('Enter welcome message...', 'cerebroly'); ?>"><?php echo esc_textarea($welcome_messages[$i]); ?></textarea>
            <?php if ($i > 0): ?>
            <button type="button" class="button cerebroly-remove-message" data-index="<?php echo esc_attr( $i); ?>">
                <?php esc_html_e('Remove Message', 'cerebroly'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>

    <div class="cerebroly-welcome-actions">
        <button type="button" id="cerebroly-add-welcome-message" class="button button-secondary">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e('Add Message', 'cerebroly'); ?>
        </button>
        <button type="button" id="cerebroly-reset-welcome-messages" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php echo esc_attr( __( 'Reset to Default', 'cerebroly' )); ?>

        </button>
    </div>

    <p class="description">
        <?php esc_html_e('Messages will be displayed in order with a typing effect. You can use HTML for formatting.', 'cerebroly'); ?>
    </p>
</div>

<script>
jQuery(document).ready(function($) {
    // Add new message
    $('#cerebroly-add-welcome-message').on('click', function() {
        const container = $('.cerebroly-welcome-messages-container');
        const index = container.children().length;
        const newMessage = `
                        <div class="cerebroly-welcome-message-item">
                            <label for="welcome_message_${index}">
                                <?php esc_html_e('Message', 'cerebroly'); ?> ${index + 1}:
                            </label>
                            <textarea 
                                id="welcome_message_${index}"
                                name="cerebroly_chat_appearance_config[welcome_messages][${index}]" 
                                rows="3" 
                                class="cerebroly-welcome-message-textarea"
                                placeholder="<?php esc_attr_e('Enter welcome message...', 'cerebroly'); ?>"
                            ></textarea>
                            <button type="button" class="button cerebroly-remove-message" data-index="${index}">
                                <?php esc_html_e('Remove Message', 'cerebroly'); ?>
                            </button>
                        </div>
                    `;
        container.append(newMessage);
    });

    // Remove message
    $(document).on('click', '.cerebroly-remove-message', function() {
        $(this).closest('.cerebroly-welcome-message-item').remove();
        // Reindex remaining messages
        $('.cerebroly-welcome-message-item').each(function(index) {
            $(this).find('label').text('<?php esc_html_e('Message', 'cerebroly'); ?> ' + (
                index + 1) + ':');
            $(this).find('textarea').attr('name',
                `cerebroly_chat_appearance_config[welcome_messages][${index}]`);
            $(this).find('textarea').attr('id', `welcome_message_${index}`);
            $(this).find('label').attr('for', `welcome_message_${index}`);
        });
    });

    // Reset to default messages
    $('#cerebroly-reset-welcome-messages').on('click', function() {
        if (confirm(
                '<?php esc_js(esc_html_e('Are you sure you want to reset to default messages?', 'cerebroly')); ?>'
                )) {
            const container = $('.cerebroly-welcome-messages-container');
            const defaultMessages = <?php echo json_encode($this->get_default_welcome_messages()); ?>;

            container.empty();
            defaultMessages.forEach(function(message, index) {
                const messageItem = `
                                <div class="cerebroly-welcome-message-item">
                                    <label for="welcome_message_${index}">
                                        <?php esc_html_e('Message', 'cerebroly'); ?> ${index + 1}:
                                    </label>
                                    <textarea 
                                        id="welcome_message_${index}"
                                        name="cerebroly_chat_appearance_config[welcome_messages][${index}]" 
                                        rows="3" 
                                        class="cerebroly-welcome-message-textarea"
                                        placeholder="<?php esc_attr_e('Enter welcome message...', 'cerebroly'); ?>"
                                    >${message}</textarea>
                                    ${index > 0 ? `<button type="button" class="button cerebroly-remove-message" data-index="${index}"><?php esc_html_e('Remove Message', 'cerebroly'); ?></button>` : ''}
                                </div>
                            `;
                container.append(messageItem);
            });
        }
    });
});
</script>
<?php
        }
        
        /**
         * Get default welcome messages
         */
        public function get_default_welcome_messages() {
            return array(
                __("👋 Hey there! I’m here to help you with whatever you need. Want to learn more, ask questions, or get things done? Just tell me, and we’ll figure it out together!", 'cerebroly'),
                
            );
        }
        
        /**
         * Get default error message
         */
        public function get_default_error_message() {
            return __("I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment or contact our support team if the issue persists.", 'cerebroly');
        }
        
        /**
         * Render error message configuration
         */
        public function render_error_message() {
            $config = $this->get_config();
            $error_message = isset($config['error_message']) ? $config['error_message'] : $this->get_default_error_message();
            
            ?>
<div class="cerebroly-error-message">
    <textarea id="cerebroly_error_message" name="cerebroly_chat_appearance_config[error_message]" rows="4" cols="50"
        class="large-text"
        placeholder="<?php esc_attr_e('Enter error message...', 'cerebroly'); ?>"><?php echo esc_textarea($error_message); ?></textarea>

    <div class="cerebroly-error-actions" style="margin-top: 10px;">
        <button type="button" id="cerebroly-reset-error-message" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php echo esc_attr( __( 'Reset to Default', 'cerebroly' )); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Reset error message to default
    $('#cerebroly-reset-error-message').on('click', function() {
        if (confirm(
                '<?php esc_js(esc_html_e('Are you sure you want to reset to the default error message?', 'cerebroly')); ?>'
                )) {
            const defaultMessage = <?php echo json_encode($this->get_default_error_message()); ?>;
            $('#cerebroly_error_message').val(defaultMessage);
        }
    });
});
</script>
<?php
        }

        /**
         * Get current configuration
         */
        public function get_config() {
            $default_config = array(
                'selected_theme' => 'cerebroly-theme',  // Changed default to cerebroly
                'chat_position' => 'bottom-right',
                'chat_size' => 'medium',
                'custom_icon' => 'default',
                'custom_icon_url' => '',
                'welcome_messages' => $this->get_default_welcome_messages(),
                'error_message' => $this->get_default_error_message()
            );
            
            $saved_config = get_option('cerebroly_chat_appearance_config', array());
            return wp_parse_args($saved_config, $default_config);
        }

        /**
         * Get feature label
         */
        private function get_feature_label($feature) {
            $labels = array(
                'typing_effect' => __('Typing Effect', 'cerebroly'),
                'light_mode' => __('Light Mode', 'cerebroly'),
                'dark_mode' => __('Dark Mode', 'cerebroly'),
                'emoji_support' => __('Emoji Support', 'cerebroly')
            );
            
            return isset($labels[$feature]) ? $labels[$feature] : ucfirst(str_replace('_', ' ', $feature));
        }
        
        /**
         * Get available themes (for future extensibility)
         */
        public function get_available_themes() {
            return $this->available_themes;
        }
    }

    // Helper function to get instance
    if (!function_exists('cerebroly_appearance_manager')) {
        function cerebroly_appearance_manager() {
            return CEREBROLY_Appearance_Manager::get_instance();
        }
    }

    // Initialize only if we're in admin
    if (is_admin()) {
        add_action('plugins_loaded', 'cerebroly_appearance_manager');
    }
    ?>