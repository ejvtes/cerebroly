<?php
/**
 * Plugin Name: Cerebroly
 * Plugin URI: https://cerebroly.com
 * Description:Transform your website into an AI-powered assistant. Train your custom ChatGPT-like bot with your own content, automate support, and improve conversions
 * Version: 1.5.3
 * Author: Eduardo Vega
 * Author URI: https://eduardovega.net
 * Text Domain: cerebroly
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


/**4
 * Get OpenAI API key from various sources with proper fallback
 */
if (!function_exists('cerebroly_get_openai_api_key')) {
    function cerebroly_get_openai_api_key()
    {
        // Priority 1: Environment variable
        $env_key = getenv('OPENAI_API_KEY');
        if ($env_key !== false && $env_key !== '') {
            // Security cleanup: remove from database if exists
            $db_key = get_option('cerebroly_openai_api_key', '');
            if (!empty($db_key)) {
                delete_option('cerebroly_openai_api_key');
            }
            return $env_key;
        }

        // Priority 2: wp-config.php constant
        if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') {
            // Security cleanup: remove from database if exists
            $db_key = get_option('cerebroly_openai_api_key', '');
            if (!empty($db_key)) {
                delete_option('cerebroly_openai_api_key');
            }
            return OPENAI_API_KEY;
        }

        // Priority 3: Database option (fallback)
        return get_option('cerebroly_openai_api_key', '');
    }
}

// Define constants
define('CEREBROLY_VERSION', '1.5.3');
define('CEREBROLY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CEREBROLY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CEREBROLY_ADMIN_URL', admin_url('admin.php?page=cerebroly'));

// Define environment variable for asset loading
if (!defined('CEREBROLY_DEBUG')) {
    // Priority order:
    // 1. Check for custom constant in wp-config.php
    if (defined('cerebroly_DEVELOPMENT')) {
        define('CEREBROLY_DEBUG', cerebroly_DEVELOPMENT);
    }
    // 2. Fallback to WP_DEBUG
    else {
        define('CEREBROLY_DEBUG', WP_DEBUG);
    }
}

// Activation and deactivation
register_activation_hook(__FILE__, 'cerebroly_activate');
register_deactivation_hook(__FILE__, 'cerebroly_deactivate');


add_action('admin_post_cerebroly_upload_file', array('CEREBROLY_File_Handler', 'handle_cerebroly_file_upload'));
add_action('admin_post_cerebroly_delete_file', array('CEREBROLY_File_Handler', 'handle_cerebroly_file_delete'));

/**
 * Code that runs during plugin activation.
 */
function cerebroly_activate()
{
    // Create necessary tables
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table for files
    $table_name = esc_sql($wpdb->prefix . 'cerebroly_files');
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        filename varchar(255) NOT NULL,
        filetype varchar(100) NOT NULL,
        filepath varchar(255) NOT NULL,
        filesize int(11) NOT NULL,
        content longtext,
        uploaded datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table for models
    $models_table = esc_sql($wpdb->prefix . 'cerebroly_models');
    $sql_models = "CREATE TABLE $models_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        model_id varchar(255) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        sources text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_models);

    // Add default options
    add_option('cerebroly_openai_api_key', '');
    add_option('cerebroly_extract_posts', 1);
    add_option('cerebroly_extract_media', 0);

    // Table for training history
    $training_history_table = esc_sql($wpdb->prefix . 'cerebroly_training_history');
    $sql_training_history = "CREATE TABLE IF NOT EXISTS $training_history_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        model_id varchar(255) NOT NULL,
        start_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        end_time datetime DEFAULT NULL,
        duration int(11) DEFAULT 0,
        status varchar(50) DEFAULT 'pending',
        content_length int(11) DEFAULT 0,
        sources text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql_training_history);

    // Add training options
    add_option('cerebroly_training_history', array());
    add_option('cerebroly_current_training', array());
    // Create cache directory
    $cache_dir = CEREBROLY_PLUGIN_DIR . 'cache';

    // Initialize WP_Filesystem
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }

    if (!$wp_filesystem->exists($cache_dir)) {
        // Create directory with proper permissions
        if ($wp_filesystem->mkdir($cache_dir, 0755)) {
            // Create index.php file for security
            $wp_filesystem->put_contents(
                $cache_dir . '/index.php',
                '<?php // Silence is golden.',
                0644
            );
        }
    }
    // Set RAG option to false by default (only create tables, don't initialize)
    add_option('cerebroly_use_rag', false);

    // Create tables for RAG (but don't initialize yet)
    cerebroly_create_rag_tables();
}

/**
 * Create necessary tables for RAG.
 */
function cerebroly_create_rag_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table for embeddings
    $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
    $sql_embeddings = "CREATE TABLE IF NOT EXISTS $embedding_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        content_id varchar(255) NOT NULL,
        content_type varchar(50) NOT NULL,
        content_title text NOT NULL,
        content_chunk longtext NOT NULL,
        embedding longtext NOT NULL,
        created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY content_id (content_id(191)),
        KEY content_type (content_type)
    ) $charset_collate;";

    // Table for indexing status
    $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');
    $sql_indexing = "CREATE TABLE IF NOT EXISTS $indexing_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        job_id varchar(255) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        progress int(3) DEFAULT 0,
        total_items int(11) DEFAULT 0,
        processed_items int(11) DEFAULT 0,
        started datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        completed datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY job_id (job_id(191))
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_embeddings);
    dbDelta($sql_indexing);

    return true;
}

/**
 * Centralized function to initialize RAG if enabled.
 * Returns true if RAG is enabled and was successfully initialized.
 */
function cerebroly_initialize_rag_if_enabled()
{
    if (get_option('cerebroly_use_rag', false)) {
        if (!class_exists('CEREBROLY_RAG_Manager')) {
            require_once CEREBROLY_PLUGIN_DIR . 'includes/RagManager.php';
        }

        $rag_manager = new CEREBROLY_RAG_Manager();
        $result = $rag_manager->initialize_rag();
        return $result;
    }

    return false;
}

/**
 * Code that runs during plugin deactivation.
 */
function cerebroly_deactivate()
{
    // Clean cron and scheduled tasks if any
    wp_clear_scheduled_hook('cerebroly_process_training');
    wp_clear_scheduled_hook('cerebroly_check_model_status');
    wp_clear_scheduled_hook('cerebroly_check_training_progress');
    wp_clear_scheduled_hook('cerebroly_process_indexing_batch');
    wp_clear_scheduled_hook('cerebroly_update_post_embedding');
    wp_clear_scheduled_hook('cerebroly_process_manual_indexing_batch_hook');
}

/**
 * Include necessary files.
 * 
 * We only load the RAG Manager class when needed to avoid initialization issues
 */
require_once CEREBROLY_PLUGIN_DIR . 'includes/ContentExtractor.php';
require_once CEREBROLY_PLUGIN_DIR . 'includes/FileHandler.php';
require_once CEREBROLY_PLUGIN_DIR . 'includes/OpenaiApi.php';
require_once CEREBROLY_PLUGIN_DIR . 'includes/RagManager.php';
require_once CEREBROLY_PLUGIN_DIR . 'includes/AppearanceManager.php';

// Admin
if (is_admin()) {
    require_once CEREBROLY_PLUGIN_DIR . 'admin/Admin.php';
    $cerebroly_admin = new CEREBROLY_Admin();
}

// REST API
require_once CEREBROLY_PLUGIN_DIR . 'api/RestApi.php';
$cerebroly_api = new CEREBROLY_REST_API();
/**
 * Updated shortcode function - replace in intelli.php around line 200
 */
function cerebroly_chat_shortcode($atts)
{
    // Basic shortcode that loads the container for the chat
    $atts = shortcode_atts(array(
        'placeholder' => __('Type your question...', 'cerebroly'),
        'button_text' => __('Send', 'cerebroly'),
    ), $atts, 'cerebroly_chat');

    // Check if there is an active model (for fine-tuning) or RAG is enabled
    $rag_enabled = get_option('cerebroly_use_rag', false);
    $model_available = false;

    if ($rag_enabled) {
        // Verify if RAG is configured using cache
        $cache_key = 'cerebroly_rag_table_exists';
        $model_available = wp_cache_get($cache_key, 'cerebroly_shortcode');
        
        if ($model_available === false) {
            global $wpdb;
            $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $embedding_table));
            $model_available = ($table_exists === $embedding_table);
            wp_cache_set($cache_key, $model_available, 'cerebroly_shortcode', HOUR_IN_SECONDS);
        }

        if ($model_available) {
            // Verify that there is at least one embedding in the table using cache
            $count_cache_key = 'cerebroly_rag_embeddings_count';
            $count = wp_cache_get($count_cache_key, 'cerebroly_shortcode');
            
            if ($count === false) {
                global $wpdb;
                $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
                // Use WordPress prepared statement for COUNT query
                $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$embedding_table}`"));
                wp_cache_set($count_cache_key, $count, 'cerebroly_shortcode', HOUR_IN_SECONDS);
            }
            
            $model_available = $count > 0;
        }
    } else {
        // Check if there is an active fine-tuned model using cache
        $model_cache_key = 'cerebroly_active_model';
        $active_model = wp_cache_get($model_cache_key, 'cerebroly_shortcode');
        
        if ($active_model === false) {
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');
            // Use WordPress prepared statement for SELECT query
            $active_model = $wpdb->get_var($wpdb->prepare("SELECT model_id FROM `{$table_name}` WHERE status = %s ORDER BY updated DESC LIMIT 1", 'active'));
            wp_cache_set($model_cache_key, $active_model ? $active_model : 'none', 'cerebroly_shortcode', HOUR_IN_SECONDS);
        }
        
        $model_available = !empty($active_model) && $active_model !== 'none';
    }

    if (!$model_available) {
        if ($rag_enabled) {
            return '<div class="cerebroly-chat-error">' . __('The RAG system is not properly configured. Please go to the admin to initialize and configure RAG.', 'cerebroly') . '</div>';
        } else {
            return '<div class="cerebroly-chat-error">' . __('There is no trained model available.', 'cerebroly') . '</div>';
        }
    }

    // Get appearance configuration first
    $appearance_manager = CEREBROLY_Appearance_Manager::get_instance();
    $appearance_config = $appearance_manager->get_config();

    //  cerebroly_chat_shortcode() -  ES modules
    if (defined('CEREBROLY_DEBUG') && CEREBROLY_DEBUG) {
        // DEVELOPMENT - Load individual files
        wp_enqueue_script('cerebroly-chat-container', CEREBROLY_PLUGIN_URL . 'assets/components/ChatContainer.js', array(), CEREBROLY_VERSION, true);
        wp_enqueue_script('cerebroly-chat-messages', CEREBROLY_PLUGIN_URL . 'assets/components/ChatMessages.js', array(), CEREBROLY_VERSION, true);
        wp_enqueue_script('cerebroly-chat-message', CEREBROLY_PLUGIN_URL . 'assets/components/ChatMessage.js', array(), CEREBROLY_VERSION, true);
        wp_enqueue_script('cerebroly-chat-input', CEREBROLY_PLUGIN_URL . 'assets/components/ChatInput.js', array(), CEREBROLY_VERSION, true);
        wp_enqueue_style('cerebroly-chat-style', CEREBROLY_PLUGIN_URL . 'assets/css/chat.css', array(), CEREBROLY_VERSION);
        
        // Load active theme CSS in debug mode - now using properly initialized config
        $selected_theme = $appearance_config['selected_theme'] ?? 'cerebroly-theme';
        wp_enqueue_style('cerebroly-theme-style', CEREBROLY_PLUGIN_URL . 'assets/css/themes/' . $selected_theme . '.css', array('cerebroly-chat-style'), CEREBROLY_VERSION);

    } else {
        // PRODUCTION - Load as ES module
        wp_enqueue_script('cerebroly-chat-bundle', CEREBROLY_PLUGIN_URL . 'dist/chat-bundle.js', array(), CEREBROLY_VERSION, true);
        wp_enqueue_style('cerebroly-chat-style', CEREBROLY_PLUGIN_URL . 'dist/chat-bundle.css', array(), CEREBROLY_VERSION);
        // Load active theme CSS in debug mode - now using properly initialized config
        $selected_theme = $appearance_config['selected_theme'] ?? 'cerebroly-theme';
        wp_enqueue_style('cerebroly-theme-style', CEREBROLY_PLUGIN_URL . 'assets/css/themes/' . $selected_theme . '.css', array('cerebroly-chat-style'), CEREBROLY_VERSION);

    }

    // Determine script handle for localization
    $localize_handle = (defined('CEREBROLY_DEBUG') && CEREBROLY_DEBUG)
        ? 'cerebroly-chat-input'
        : 'cerebroly-chat-bundle';

    // Pass data to JavaScript
    wp_localize_script($localize_handle, 'cerebrolData', array(
        'apiUrl' => rest_url('cerebroly/v1/chat'),
        'nonce' => wp_create_nonce('wp_rest'),
        'placeholder' => $atts['placeholder'],
        'buttonText' => $atts['button_text'],
        'useRag' => $rag_enabled,
        'welcomeMessages' => $appearance_config['welcome_messages'],
        'customIcon' => $appearance_config['custom_icon'],
        'customIconUrl' => $appearance_config['custom_icon_url'],
        'selectedTheme' => $appearance_config['selected_theme']
    ));



    // Load theme-specific styles via appearance manager (which handles custom icons)
    $appearance_manager->enqueue_theme_styles();

    // Chat container with the correct structure
    $output = '<!-- This chat is generated by Cerebroly plugin https://cerebroly.com/  -->';
    $output .= '<chat-container>';
    $output .= '<div class="container-messages">';
    $output .= '<chat-messages id="chat-messages"></chat-messages>';
    $output .= '<div id="status-indicator"></div>';
    $output .= '</div>';
    $output .= '<chat-input id="chat-input" ';
    $output .= 'endpoint="' . esc_url(rest_url('cerebroly/v1/chat')) . '" ';
    $output .= 'placeholder="' . esc_attr($atts['placeholder']) . '">';
    $output .= '</chat-input>';
    $output .= '</chat-container>';

   

    return $output;
}

add_shortcode('cerebroly_chat', 'cerebroly_chat_shortcode');

// Register hook to check training progress
add_action('cerebroly_check_model_status', 'cerebroly_check_status_callback');
function cerebroly_check_status_callback($model_id)
{
    $openai_api = new CEREBROLY_OpenAI_API();
    $openai_api->check_model_status($model_id);
}

// Add hooks for background processing of embeddings
add_action('cerebroly_update_post_embedding', 'cerebroly_update_post_embedding_callback');
function cerebroly_update_post_embedding_callback($post_id)
{
    if (get_option('cerebroly_use_rag', false)) {
        // Load RAG Manager class when needed
        if (!class_exists('CEREBROLY_RAG_Manager')) {
            require_once CEREBROLY_PLUGIN_DIR . 'includes/RagManager.php';
        }

        $rag_manager = new CEREBROLY_RAG_Manager();
        $rag_manager->process_post_embedding($post_id);
    }
}

// Add hooks for background processing of embeddings
add_action('cerebroly_process_indexing_batch', 'cerebroly_process_indexing_batch_callback', 10, 3);
function cerebroly_process_indexing_batch_callback($job_id, $content_items, $start_index)
{

    // Ensure the RAG_Manager class is available
    if (!class_exists('CEREBROLY_RAG_Manager')) {
        require_once CEREBROLY_PLUGIN_DIR . 'includes/RagManager.php';
    }

    try {
        $rag_manager = new CEREBROLY_RAG_Manager();
        $result = $rag_manager->process_indexing_batch($job_id, $content_items, $start_index);
        set_transient('cerebroly_indexing_callback_ran', time(), 300); // Set a transient for debugging
        return $result;
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('cerebroly indexing batch error: ' . $e->getMessage());
        }
        return false;
    }
}

// Add necessary scripts for the dashboard
function cerebroly_admin_scripts($hook)
{
    // For the main plugin page
    if ('toplevel_page_cerebroly' === $hook) {
        wp_enqueue_script('cerebroly-admin-script', CEREBROLY_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), CEREBROLY_VERSION, true);
        wp_localize_script('cerebroly-admin-script', 'cerebrolAdminData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cerebroly_admin_nonce')
        ));
    }


}
add_action('admin_enqueue_scripts', 'cerebroly_admin_scripts');

// Add RAG settings to the configuration
function cerebroly_register_rag_settings()
{
    // Main plugin option
    register_setting('cerebroly_settings', 'cerebroly_use_rag', array(
        'sanitize_callback' => 'absint'
    ));

    // Initialize RAG when the option is enabled (this happens on settings save)
    if (isset($_POST['cerebroly_use_rag']) && $_POST['cerebroly_use_rag'] == 1) {
        cerebroly_initialize_rag_if_enabled();
    }

    // Register API access control settings
    register_setting('cerebroly_settings', 'cerebroly_allowed_domains', array(
        'sanitize_callback' => 'cerebroly_sanitize_allowed_domains'
    ));
    register_setting('cerebroly_settings', 'cerebroly_enable_cors', array(
        'sanitize_callback' => 'absint'
    ));

    // Register option group for embeddings
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_embedding_model', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_chunk_size', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_chunk_overlap', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_posts', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_products', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_files', array(
        'sanitize_callback' => 'absint'
    ));

    // Register option group for retrieval
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_top_k', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_similarity_threshold', array(
        'sanitize_callback' => 'floatval'
    ));
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_search_method', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_query_rewriting', array(
        'sanitize_callback' => 'absint'
    ));

    // Register option group for generation
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_llm_model', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_temperature', array(
        'sanitize_callback' => 'floatval'
    ));
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_max_tokens', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_system_prompt', array(
        'sanitize_callback' => 'wp_kses_post'
    ));
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_cite_sources', array(
        'sanitize_callback' => 'absint'
    ));

    // Register settings for fixed post types only
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_post', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_page', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_product', array(
        'sanitize_callback' => 'absint'
    ));
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_files', array(
        'sanitize_callback' => 'absint'
    ));

}
add_action('admin_init', 'cerebroly_register_rag_settings');

// Register handler for manual RAG indexing
function cerebroly_register_manual_rag_indexing()
{
    // Only load the RAG Manager class when needed
    if (get_option('cerebroly_use_rag', false)) {
        if (!class_exists('CEREBROLY_RAG_Manager')) {
            require_once CEREBROLY_PLUGIN_DIR . 'includes/RagManager.php';
        }

        $rag_manager = new CEREBROLY_RAG_Manager();
        add_action('admin_post_cerebroly_manual_rag_indexing', array($rag_manager, 'handle_manual_rag_indexing'));
    }
}
add_action('plugins_loaded', 'cerebroly_register_manual_rag_indexing');

// Render RAG configuration page
function cerebroly_render_rag_config_page()
{
    // Check permissions
    if (!current_user_can('manage_options')) {
        return;
    }

    // Initialize RAG if enabled
    if (get_option('cerebroly_use_rag', false)) {
        cerebroly_initialize_rag_if_enabled();
    }

    // Include view
    include CEREBROLY_PLUGIN_DIR . 'admin/views/rag-config.php';
}

// Add RAG option to the settings
add_action('cerebroly_settings_after_api_section', 'cerebroly_add_rag_setting_option');
function cerebroly_add_rag_setting_option()
{
    ?>
    <div class="cerebroly-settings-section">
        <h2><?php esc_html_e('RAG System Configuration', 'cerebroly'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable RAG', 'cerebroly'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="cerebroly_use_rag" value="1" <?php checked(1, get_option('cerebroly_use_rag', 0)); ?>>
                        <?php esc_html_e('Use Retrieval-Augmented Generation (RAG) instead of fine-tuning', 'cerebroly'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('RAG is a more dynamic approach that doesn\'t require retraining the model when content changes.', 'cerebroly'); ?>
                        <?php if (get_option('cerebroly_use_rag', 0)): ?>
                            <a
                                href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-rag-config')); ?>"><?php esc_html_e('Configure RAG', 'cerebroly'); ?></a>

                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}


add_action('cerebroly_test_cron_event', function () {
    $now = current_time('mysql');
    update_option('cerebroly_last_cron_run', $now);
    update_option('cerebroly_last_cron_message', 'Cron executed successfully at ' . $now);
});


if (!function_exists('cerebroly_get_openai_key_source')) {
    function cerebroly_get_openai_key_source()
    {
        // Check external sources first - this determines if field should be disabled
        $env_key = getenv('OPENAI_API_KEY');
        if ($env_key !== false && $env_key !== '') {
            return __('Environment Variable', 'cerebroly');
        }

        if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '') {
            return __('wp-config.php', 'cerebroly');
        }

        // Only return 'Option' if no external source exists
        // This ensures the field is enabled when no external key is configured
        return __('Option', 'cerebroly');
    }
}

/**
 * Sanitize allowed domains input
 */
function cerebroly_sanitize_allowed_domains($input)
{
    $sanitized = array();
    $max_domains = apply_filters('cerebroly_max_allowed_domains', 5);

    if (!is_array($input)) {
        return $sanitized;
    }

    foreach ($input as $domain) {
        $domain = trim($domain);

        // Skip empty domains
        if (empty($domain)) {
            continue;
        }

        // Validate URL format
        if (filter_var($domain, FILTER_VALIDATE_URL)) {
            $parsed = wp_parse_url($domain);

            // Ensure we have a valid scheme and host
            if (
                isset($parsed['scheme']) && isset($parsed['host']) &&
                in_array($parsed['scheme'], array('http', 'https'))
            ) {

                // Reconstruct the domain to ensure consistency
                $clean_domain = $parsed['scheme'] . '://' . $parsed['host'];
                if (isset($parsed['port'])) {
                    $clean_domain .= ':' . $parsed['port'];
                }

                $sanitized[] = $clean_domain;
            }
        }

        // Limit to max domains
        if (count($sanitized) >= $max_domains) {
            break;
        }
    }

    return $sanitized;
}

/**
 * Check if a domain is allowed to access the API
 */
function cerebroly_is_domain_allowed($origin = null)
{
    // Get origin from request if not provided
    if ($origin === null) {
        $origin = isset($_SERVER['HTTP_ORIGIN'])  ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN']))  : '';
        if (empty($origin)) {
            $origin = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        }
    }

    if (empty($origin)) {
        return false;
    }

    // Parse the origin URL
    $parsed_origin = wp_parse_url($origin);
    if (!$parsed_origin || !isset($parsed_origin['host'])) {
        return false;
    }

    // Reconstruct origin domain
    $origin_domain = $parsed_origin['scheme'] . '://' . $parsed_origin['host'];
    if (isset($parsed_origin['port'])) {
        $origin_domain .= ':' . $parsed_origin['port'];
    }

    // Get allowed domains
    $allowed_domains = get_option('cerebroly_allowed_domains', array());

    return in_array($origin_domain, $allowed_domains);
}

/**
 * Load plugin textdomain for translations
 */

 function cerebroly_load_textdomain() {
    $domain = 'cerebroly';
    $locale = apply_filters('plugin_locale', get_locale(), $domain);
    
    // Intentar cargar desde el directorio global de WordPress primero
    $global_file = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
    if (file_exists($global_file)) {
        load_textdomain($domain, $global_file);
        return;
    }
    
    // Luego desde el directorio del plugin
    $plugin_file = plugin_dir_path(__FILE__) . 'languages/' . $domain . '-' . $locale . '.mo';
    if (file_exists($plugin_file)) {
        load_textdomain($domain, $plugin_file);
        return;
    }
    
    // Fallback: WP 4.6+ loads translations automatically for plugins in the directory.
    load_textdomain($domain, plugin_dir_path(__FILE__) . 'languages/' . $domain . '.mo');
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        error_log("cerebroly: loading locale '$locale' — global: $global_file / plugin: $plugin_file");
    }
}

// Cambiar el hook a 'init'
remove_action('plugins_loaded', 'cerebroly_load_textdomain');
add_action('init', 'cerebroly_load_textdomain');

/**
 * One-time data migration from intelliWP (ftc_ prefix) to cerebroly (cerebroly_ prefix).
 * Runs on plugins_loaded. Uses cerebroly_db_migrated flag to execute only once.
 */
function cerebroly_migrate_from_ftc() {
    if (get_option('cerebroly_db_migrated') === '2.0') {
        return;
    }

    global $wpdb;

    // --- Migrate DB tables ---
    $table_map = array(
        $wpdb->prefix . 'ftc_files'          => $wpdb->prefix . 'cerebroly_files',
        $wpdb->prefix . 'ftc_models'         => $wpdb->prefix . 'cerebroly_models',
        $wpdb->prefix . 'ftc_training_history' => $wpdb->prefix . 'cerebroly_training_history',
        $wpdb->prefix . 'ftc_embeddings'     => $wpdb->prefix . 'cerebroly_embeddings',
        $wpdb->prefix . 'ftc_indexing_status' => $wpdb->prefix . 'cerebroly_indexing_status',
    );

    foreach ($table_map as $old_table => $new_table) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old_table));
        $already_new = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $new_table));
        if ($exists && !$already_new) {
            $wpdb->query("RENAME TABLE `{$old_table}` TO `{$new_table}`");
        }
    }

    // --- Migrate wp_options (ftc_ → cerebroly_) ---
    $old_options = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'ftc_%'",
        ARRAY_A
    );

    foreach ($old_options as $row) {
        $new_name = 'cerebroly_' . substr($row['option_name'], 4); // strip 'ftc_' (4 chars)
        if (!get_option($new_name)) {
            add_option($new_name, maybe_unserialize($row['option_value']));
        } else {
            update_option($new_name, maybe_unserialize($row['option_value']));
        }
        delete_option($row['option_name']);
    }

    update_option('cerebroly_db_migrated', '2.0');
}
add_action('plugins_loaded', 'cerebroly_migrate_from_ftc', 5);