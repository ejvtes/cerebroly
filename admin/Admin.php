<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Class for plugin administration.
 */
class CEREBROLY_Admin
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Process admin actions
        add_action('admin_post_cerebroly_start_training', array($this, 'handle_start_training'));
        add_action('admin_post_cerebroly_upload_file', array($this, 'handle_file_upload'));
        add_action('admin_post_cerebroly_delete_file', array($this, 'handle_file_delete'));

        // Add hook to check model status
        add_action('cerebroly_check_model_status', array($this, 'check_model_status'));

        add_action('admin_post_cerebroly_select_model', array($this, 'handle_select_model'));

        add_action('admin_post_cerebroly_auto_train_now', array($this, 'handle_auto_train_now'));
        add_action('wp_ajax_cerebroly_check_training_status', array($this, 'ajax_check_training_status'));
        add_action('wp_ajax_cerebroly_simple_api_test', array($this, 'ajax_simple_api_test'));

        add_action('wp_ajax_cerebroly_check_specific_model_status', array($this, 'ajax_check_specific_model_status'));
        add_action('admin_post_cerebroly_delete_model', array($this, 'handle_delete_model'));

        add_action('wp_ajax_cerebroly_generate_enhanced_dataset', array($this, 'ajax_generate_enhanced_dataset'));
        add_action('wp_ajax_cerebroly_finalize_enhanced_dataset', array($this, 'ajax_finalize_enhanced_dataset'));
    }

    /**
     * Handle training start.
     */
    public function handle_start_training()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You don\'t have permission to perform this action.', 'cerebroly'), esc_html__('Error', 'cerebroly'), array('response' => 403));
        }

        // Verify nonce
        check_admin_referer('cerebroly_start_training');

        $preview_file = CEREBROLY_PLUGIN_DIR . 'cache/training-preview.json';

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ($wp_filesystem->exists($preview_file)) {
            $training_content = $wp_filesystem->get_contents($preview_file);

            // Check if JSON is an array (editor format) and convert to JSONL if needed
            $check_format = json_decode($training_content);
            if (json_last_error() === JSON_ERROR_NONE && is_array($check_format)) {
                // Convert from JSON to JSONL
                $jsonl = '';
                foreach ($check_format as $entry) {
                    $jsonl .= json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
                }
                $training_content = $jsonl;
            }
        } else {
            // If file doesn't exist, generate content as before
            $content_extractor = new CEREBROLY_Content_Extractor();
            $training_content = $content_extractor->prepare_for_training();
        }

        // Validate that there is content
        if (empty(trim($training_content))) {
            add_settings_error(
                'cerebroly_training',
                'no_content',
                esc_html__('No content found for training. Check your extraction settings.', 'cerebroly'),
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
            exit;
        }

        // Save content information to display later
        $content_stats = [
            'total_size' => strlen($training_content),
            'total_words' => str_word_count($training_content),
            'document_count' => substr_count($training_content, 'Document Title:'),
        ];

        // Start training
        $openai_api = new CEREBROLY_OpenAI_API();
        $result = $openai_api->create_fine_tuning($training_content);

        // Save result to display in an alert
        if (!is_wp_error($result)) {
            set_transient('cerebroly_training_details', [
                'success' => true,
                'model_id' => $result['model_id'],
                'status' => $result['status'],
                'content_stats' => $content_stats,
                'raw_response' => isset($result['raw_response']) ? $result['raw_response'] : esc_html__('Not available', 'cerebroly')
            ], 60);
        } else {
            set_transient('cerebroly_training_details', [
                'success' => false,
                'error_message' => $result->get_error_message(),
                'error_data' => $result->get_error_data(),
                'content_stats' => $content_stats
            ], 60);
        }

        // Redirect
        wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
        exit;
    }

    /**
     * AJAX handler to check the status of a specific model.
     */
    public function ajax_check_specific_model_status()
    {
        // Security checks (same as before)

        // Get model ID
        $model_id = isset($_POST['model_id']) ? sanitize_text_field($_POST['model_id']) : '';
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';

        if (empty($model_id)) {
            wp_send_json_error(esc_html__('Model ID not provided', 'cerebroly'));
        }

        // Check model status
        $openai_api = new CEREBROLY_OpenAI_API();
        $status_check = $openai_api->check_model_status($model_id);

        if (is_wp_error($status_check)) {
            wp_send_json_error($status_check->get_error_message());
        }

        // If forced or status has changed to completed or failed
        if ($force_update || in_array($status_check['openai_status'], ['succeeded', 'failed', 'cancelled'])) {
            // Update database immediately
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');

            $model_status = ($status_check['openai_status'] === 'succeeded') ? 'active' : 'failed';

            $wpdb->update(
                $table_name,
                array(
                    'status' => $model_status,
                    'updated' => current_time('mysql')
                ),
                array('model_id' => $model_id),
                array('%s', '%s'),
                array('%s')
            );
            
            // Clear the active model cache
            wp_cache_delete('cerebroly_active_model', 'cerebroly_models');
        }

        wp_send_json_success($status_check);
    }

    /**
     * Start automatic training.
     */
    public function handle_auto_train_now()
    {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You don\'t have permission to perform this action.', 'cerebroly'), esc_html__('Error', 'cerebroly'), array('response' => 403));
        }

        check_admin_referer('cerebroly_auto_train_now');

        // Prepare content
        $content_extractor = new CEREBROLY_Content_Extractor();
        $training_content = $content_extractor->prepare_for_training();

        // Content statistics
        $content_stats = [
            'total_size' => strlen($training_content),
            'total_words' => str_word_count($training_content),
            'document_count' => substr_count($training_content, 'Document Title:'),
        ];

        // Start training
        $openai_api = new CEREBROLY_OpenAI_API();
        $result = $openai_api->create_fine_tuning($training_content);

        // At the end of the function, before wp_redirect:
        if (!is_wp_error($result)) {
            set_transient('cerebroly_training_details', [
                'success' => true,
                'model_id' => $result['model_id'],
                'status' => $result['status'],
                'content_stats' => $content_stats,
                'raw_response' => isset($result['raw_response']) ? $result['raw_response'] : esc_html__('Not available', 'cerebroly')
            ], 60);
        } else {
            set_transient('cerebroly_training_details', [
                'success' => false,
                'error_message' => $result->get_error_message(),
                'error_data' => $result->get_error_data(),
                'content_stats' => $content_stats
            ], 60);
        }

        wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
        exit;
    }

    /**
     * Updated function for add_admin_menu() in class-admin.php
     * Replaces the existing function with this one to have an organized structure
     */
    public function add_admin_menu()
    {
        add_menu_page(
            esc_html__('cerebroly', 'cerebroly'),
            esc_html__('cerebroly', 'cerebroly'),
            'manage_options',
            'cerebroly',
            array($this, 'render_main_page'),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'cerebroly',
            esc_html__('cerebroly - Dashboard', 'cerebroly'),
            esc_html__('Dashboard', 'cerebroly'),
            'manage_options',
            'cerebroly',
            array($this, 'render_main_page')
        );

        add_submenu_page(
            'cerebroly',
            esc_html__('cerebroly - Files', 'cerebroly'),
            esc_html__('Files', 'cerebroly'),
            'manage_options',
            'cerebroly-files',
            array($this, 'render_files_page')
        );

        // New unified page for Fine-Tuning
        add_submenu_page(
            'cerebroly',
            esc_html__('cerebroly - Fine-Tuning', 'cerebroly'),
            esc_html__('Fine-Tuning', 'cerebroly'),
            'manage_options',
            'cerebroly-fine-tuning',
            array($this, 'render_fine_tuning_page')
        );

        // Page for RAG
        add_submenu_page(
            'cerebroly',
            esc_html__('cerebroly - RAG System', 'cerebroly'),
            esc_html__('RAG System', 'cerebroly'),
            'manage_options',
            'cerebroly-rag-config',
            array($this, 'render_rag_config_page')
        );
    }

    /**
     * Render the new unified fine-tuning page
     */
    public function render_fine_tuning_page()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if form has been submitted
        $updated = false;
        if (isset($_POST['cerebroly_training_data']) && check_admin_referer('cerebroly_update_training')) {
            $training_json = stripslashes($_POST['cerebroly_training_data']);

            // Verify that JSON is valid
            $decoded = json_decode($training_json);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Convert to JSONL if received as JSON array
                if (is_array($decoded)) {
                    // Convert to JSONL if received as JSON array
                    $jsonl = '';
                    foreach ($decoded as $entry) {
                        $jsonl .= json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
                    }
                    $training_content = $jsonl;
                } else {
                    // If already in JSONL format, use as is
                    $training_content = $training_json;
                }

                // Path to cache file
                $cache_dir = CEREBROLY_PLUGIN_DIR . 'cache';
                $preview_file = $cache_dir . '/training-preview.json';

                // Create directory if it doesn't exist
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once(ABSPATH . '/wp-admin/includes/file.php');
                    WP_Filesystem();
                }
                
                if (!$wp_filesystem->exists($cache_dir)) {
                    $wp_filesystem->mkdir($cache_dir, 0755);
                    $wp_filesystem->put_contents($cache_dir . '/index.php', '<?php // Silence is golden.');
                }

                // Save to cache
                $wp_filesystem->put_contents($preview_file, $training_content);
                $updated = true;

                add_settings_error(
                    'cerebroly_editor',
                    'cerebroly_editor_updated',
                    esc_html__('Training data has been updated successfully.', 'cerebroly'),
                    'success'
                );
            } else {
                add_settings_error(
                    'cerebroly_editor',
                    'cerebroly_editor_error',
                    /* translators: %s: JSON parse error message */
                    sprintf(esc_html__('Error: The provided JSON is not valid. Error: %s', 'cerebroly'), json_last_error_msg()),
                    'error'
                );
            }

            set_transient('settings_errors', get_settings_errors(), 30);
        }

        // Check if we should regenerate the preview
        $regenerate = isset($_GET['regenerate']) && $_GET['regenerate'] == '1';

        // Path to cache file
        $cache_dir = CEREBROLY_PLUGIN_DIR . 'cache';
        $preview_file = $cache_dir . '/training-preview.json';

        // Create cache directory if it doesn't exist
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }
        
        if (!$wp_filesystem->exists($cache_dir)) {
            $wp_filesystem->mkdir($cache_dir, 0755);
            // Protect the directory with an index.php file
            $wp_filesystem->put_contents($cache_dir . '/index.php', '<?php // Silence is golden.');
        }

        // Check if cache file exists and is recent (less than 24 hours)
        $cache_exists = $wp_filesystem->exists($preview_file);
        $cache_fresh  = $cache_exists && (time() - $wp_filesystem->mtime($preview_file) < 86400);

        if ($regenerate || !$cache_exists || !$cache_fresh) {
            $content_extractor = new CEREBROLY_Content_Extractor();
            $training_content  = $this->get_preview_training_content($content_extractor);
            $wp_filesystem->put_contents($preview_file, $training_content);

            if ($regenerate) {
                add_settings_error(
                    'cerebroly_preview',
                    'cerebroly_preview_regenerated',
                    esc_html__('Preview successfully regenerated.', 'cerebroly'),
                    'success'
                );
                set_transient('settings_errors', get_settings_errors(), 30);
            }
        } else {
            $training_content = $wp_filesystem->get_contents($preview_file);
        }

        // Count documents and statistics
        $document_count = substr_count($training_content, 'Document Title:');
        $total_size = strlen($training_content);
        $total_words = str_word_count($training_content);

        // Convert to array for limited preview
        $training_data = [];
        $jsonl_lines = explode("\n", $training_content);
        foreach ($jsonl_lines as $line) {
            if (!empty(trim($line))) {
                $training_data[] = json_decode($line, true);
            }
        }

        // Limit to 10 examples for preview
        $preview_examples = array_slice($training_data, 0, 10);

        // Convert array to pretty JSON for the editor
        $training_json_pretty = json_encode($training_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Verify OpenAI API status
        $openai_api = new CEREBROLY_OpenAI_API();
        $api_status = $openai_api->verify_api_key();

        // Get active model to display
        $cache_key = 'cerebroly_active_model';
        $active_model = wp_cache_get($cache_key, 'cerebroly_models');
        
        if ($active_model === false) {
            global $wpdb;
            $active_model = $wpdb->get_row(
                "SELECT * FROM {$wpdb->prefix}cerebroly_models
                WHERE status = 'active'
                ORDER BY updated DESC
                LIMIT 1"
            );
            wp_cache_set($cache_key, $active_model, 'cerebroly_models', HOUR_IN_SECONDS);
        }

        // Include view
        include CEREBROLY_PLUGIN_DIR . 'admin/views/fine-tuning-config.php';
    }

    // New function to render RAG configuration page
    public function render_rag_config_page()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include view
        include CEREBROLY_PLUGIN_DIR . 'admin/views/rag-config.php';
    }

    public function ajax_generate_enhanced_dataset()
    {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'cerebroly'));
        }

        check_ajax_referer('cerebroly_generate_dataset', 'security');

        // Extract original content from WordPress
        $content_extractor = new CEREBROLY_Content_Extractor();
        $original_content = $content_extractor->extract_content();

        // Get uploaded files
        $cache_key = 'cerebroly_uploaded_files';
        $files = wp_cache_get($cache_key, 'cerebroly_files');
        
        if ($files === false) {
            global $wpdb;
            $files_table = esc_sql($wpdb->prefix . 'cerebroly_files');
            $files = $wpdb->get_results("SELECT filename, content FROM $files_table WHERE content IS NOT NULL");
            wp_cache_set($cache_key, $files, 'cerebroly_files', HOUR_IN_SECONDS);
        }

        // Add files to content
        foreach ($files as $file) {
            $original_content[] = array(
                'id' => 'file-' . sanitize_title($file->filename),
                'type' => 'uploaded-file',
                'title' => $file->filename,
                'content' => $file->content
            );
        }

        // Processing parameters
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 5;
        $start_index = isset($_POST['start_index']) ? intval($_POST['start_index']) : 0;

        // Get total elements
        $total_items = count($original_content);

        // Calculate end of this batch
        $end_index = min($start_index + $batch_size, $total_items);

        // Get elements for this batch
        $batch_items = array_slice($original_content, $start_index, $batch_size);

        // Instantiate OpenAI API
        $openai_api = new CEREBROLY_OpenAI_API();

        // Process each element
        $enhanced_items = [];
        foreach ($batch_items as $item) {
            // Prepare title and content
            $title = $item['title'] ?? 'Untitled content'; // This is fine, it's data
            $content = $content_extractor->clean_and_truncate_content($item['content'] ?? '');

            // Create prompt for OpenAI - THIS SHOULD NOT BE INTERNATIONALIZED
            $prompt = "Enhance this question-answer pair for training a chatbot. Generate 2 different variations based on the following content:\n\n";
            $prompt .= "Title: $title\n";
            $prompt .= "Content: $content\n\n";
            $prompt .= "For each variation, generate a specific question and a detailed answer in this JSON format:\n";
            $prompt .= '{"question": "Variation question", "answer": "Detailed variation answer"}'; // This is part of the expected JSON format

            // Call OpenAI to enhance the content
            $enhanced_response = $openai_api->get_completion_only($prompt);

            if (is_wp_error($enhanced_response)) {
                // If there's an error, keep the original
                $enhanced_items[] = [
                    'messages' => [
                        ['role' => 'user', 'content' => $title],
                        ['role' => 'assistant', 'content' => $content]
                    ]
                ];
                continue;
            }

            // Add the original item
            $enhanced_items[] = [
                'messages' => [
                    ['role' => 'user', 'content' => $title],
                    ['role' => 'assistant', 'content' => $content]
                ]
            ];

            // Try to parse the response
            try {
                // Look for JSON patterns in the response
                preg_match_all('/\{\"question\".*?\}/s', $enhanced_response, $matches);

                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $variation = json_decode($match, true);
                        if (is_array($variation) && isset($variation['question'], $variation['answer'])) {
                            // Create a new item with the correct format
                            $enhanced_items[] = [
                                'messages' => [
                                    [
                                        'role' => 'user',
                                        'content' => $variation['question']
                                    ],
                                    [
                                        'role' => 'assistant',
                                        'content' => $variation['answer']
                                    ]
                                ]
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                // Parsing error from OpenAI response — skip this variation.
            }
        }

        // Calculate progress
        $progress = ($end_index / $total_items) * 100;
        $is_completed = $end_index >= $total_items;

        // Send response
        wp_send_json_success([
            'enhanced_items' => $enhanced_items,
            'next_index' => $end_index,
            'progress' => $progress,
            'is_completed' => $is_completed,
            'total_processed' => $end_index,
            'total_items' => $total_items
        ]);
    }

    /**
     * AJAX handler to finalize dataset generation
     */
    public function ajax_finalize_enhanced_dataset()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'cerebroly'));
        }

        // Verify nonce
        check_ajax_referer('cerebroly_generate_dataset', 'security');

        // Get complete dataset
        $enhanced_dataset = isset($_POST['enhanced_dataset']) ? json_decode(stripslashes($_POST['enhanced_dataset']), true) : [];

        // Check if there's data
        if (empty($enhanced_dataset)) {
            wp_send_json_error(esc_html__('No dataset to save', 'cerebroly'));
        }

        $cache_dir    = CEREBROLY_PLUGIN_DIR . 'cache';
        $preview_file = $cache_dir . '/training-preview.json';

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $jsonl = '';
        foreach ($enhanced_dataset as $entry) {
            $jsonl .= json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
        }

        if (!$wp_filesystem->put_contents($preview_file, $jsonl)) {
            wp_send_json_error(esc_html__('Error saving enhanced dataset', 'cerebroly'));
        }

        // Send successful response
        wp_send_json_success([
            'message' => esc_html__('Enhanced dataset saved successfully', 'cerebroly'),
            'count' => count($enhanced_dataset)
        ]);
    }

    /**
     * Get training content for preview (without using the API)
     *
     * @param CEREBROLY_Content_Extractor $content_extractor Content extractor instance
     * @return string Training content in JSONL format
     */
    private function get_preview_training_content($content_extractor)
    {
        // Get content
        $content = $content_extractor->extract_content();

        // Get uploaded files
        $cache_key = 'cerebroly_uploaded_files';
        $files = wp_cache_get($cache_key, 'cerebroly_files');
        
        if ($files === false) {
            global $wpdb;
            $files_table = esc_sql($wpdb->prefix . 'cerebroly_files');
            $files = $wpdb->get_results("SELECT filename, content FROM $files_table WHERE content IS NOT NULL");
            wp_cache_set($cache_key, $files, 'cerebroly_files', HOUR_IN_SECONDS);
        }

        // Add files to content
        foreach ($files as $file) {
            $content[] = array(
                'id' => 'file-' . sanitize_title($file->filename),
                'type' => 'uploaded-file',
                'title' => $file->filename,
                'content' => $file->content
            );
        }

        // Limit to 20 random elements for preview
        if (count($content) > 20) {
            shuffle($content);
            $content = array_slice($content, 0, 20);
        }

        // Prepare training data (WITHOUT USING THE API)
        $training_data = [];

        foreach ($content as $item) {
            // Extract dynamic parameters from content
            $title = $item['title'];
            $content_text = $content_extractor->clean_and_truncate_content($item['content']);
            $type = $item['type'];

            // Generate question variations based on content type
            $variations = $content_extractor->generate_variations($title, $type);

            // Limit to 3 variations per item for the preview
            $variations = array_slice($variations, 0, 3);

            // Create training entries for each variation
            foreach ($variations as $question) {
                $training_entry = [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $question
                        ],
                        [
                            'role' => 'assistant',
                            'content' => $content_text
                        ]
                    ]
                ];
                $training_data[] = $training_entry;
            }
        }

        // Convert to JSONL format
        $jsonl = '';
        foreach ($training_data as $entry) {
            $jsonl .= json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
        }

        return $jsonl;
    }

    /**
     * AJAX function for simple API test
     */
    public function ajax_simple_api_test()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => esc_html__('Insufficient permissions', 'cerebroly')));
        }

        // Verify nonce
        check_ajax_referer('cerebroly_api_test', 'security');

        // Get API key
        $api_key = cerebroly_get_openai_api_key();
        if (empty($api_key)) {
            wp_send_json_error(array(
                'error' => esc_html__('OpenAI API key has not been configured', 'cerebroly'),
                'details' => esc_html__('Please configure an API key in the settings page', 'cerebroly')
            ));
        }

        // Make a simple request to the OpenAI API
        $response = wp_remote_get(
            'https://api.openai.com/v1/models',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'error' => esc_html__('Connection error', 'cerebroly'),
                'details' => $response->get_error_message()
            ));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            wp_send_json_error(array(
                'error' => esc_html__('API response error', 'cerebroly'),
                'status' => $status_code,
                'details' => isset($data['error']['message']) ? $data['error']['message'] : esc_html__('No additional details', 'cerebroly')
            ));
        }

        // Check if we can see a list of models
        $models_count = isset($data['data']) ? count($data['data']) : 0;

        wp_send_json_success(array(
            'message' => esc_html__('OpenAI API working correctly', 'cerebroly'),
            /* translators: %d: Found %d models */
            'models' => $models_count > 0 ? sprintf(esc_html__('Found %d models', 'cerebroly'), $models_count) : esc_html__('No models found', 'cerebroly')
        ));
    }

    /**
     * AJAX function to check training status
     */
    public function ajax_check_training_status()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'cerebroly'));
        }

        // Verify nonce
        if (!check_ajax_referer('cerebroly_status_nonce', 'security', false)) {
            wp_send_json_error(esc_html__('Security verification failed', 'cerebroly'));
        }

        // Get current training information
        $current_training = get_option('cerebroly_current_training', array());

        if (empty($current_training) || empty($current_training['model_id'])) {
            wp_send_json_error(esc_html__('No training in progress', 'cerebroly'));
        }

        // Check status in OpenAI
        $openai_api = new CEREBROLY_OpenAI_API();
        $status_check = $openai_api->check_model_status($current_training['model_id']);

        if (is_wp_error($status_check)) {
            /* translators: %s: API error message */
            wp_send_json_error(sprintf(esc_html__('Error checking status: %s', 'cerebroly'), $status_check->get_error_message()));
        }

        // Calculate elapsed time
        $elapsed_time = time() - $current_training['start_time'];
        $elapsed_formatted = sprintf(
            '%02d:%02d:%02d',
            floor($elapsed_time / 3600),
            floor(($elapsed_time % 3600) / 60),
            $elapsed_time % 60
        );

        // Calculate estimated progress (just an approximation)
        $progress = 0;
        if ($status_check['status'] === 'active') {
            $progress = 100;
        } elseif ($status_check['status'] === 'processing') {
            // Estimate progress based on time (very approximate)
            // We assume a typical training takes ~30 minutes
            $progress = min(95, ($elapsed_time / 1800) * 100);
        }

        // Update training information
        $current_training['status'] = $status_check['status'];
        $current_training['progress'] = $progress;
        $current_training['last_check'] = time();
        update_option('cerebroly_current_training', $current_training);

        // If training completed, save to history
        if ($status_check['status'] === 'active') {
            $history = get_option('cerebroly_training_history', array());
            $history[] = array(
                'model_id' => $current_training['model_id'],
                'start_time' => $current_training['start_time'],
                'end_time' => time(),
                'duration' => $elapsed_time,
                'content_length' => $current_training['content_length']
            );
            update_option('cerebroly_training_history', $history);

            // Delete current training
            delete_option('cerebroly_current_training');
        }

        // Send response
        wp_send_json_success(array(
            'model_id' => $current_training['model_id'],
            'status' => $status_check['status'],
            'progress' => $progress,
            'elapsed_time' => $elapsed_formatted,
            'is_completed' => ($status_check['status'] === 'active'),
            'start_time' => gmdate('Y-m-d H:i:s', $current_training['start_time'])
        ));
    }

    /**
     * Check training progress (for cron)
     */
    public function check_training_progress($model_id)
    {
        $openai_api = new CEREBROLY_OpenAI_API();
        $status_check = $openai_api->check_model_status($model_id);

        $current_training = get_option('cerebroly_current_training', array());

        // If the model is still processing, schedule another check
        if ($status_check['status'] === 'processing') {
            wp_schedule_single_event(time() + 60, 'cerebroly_check_training_progress', array($model_id));
        }
    }

    /**
     * Register settings.
     */
    public function register_settings()
    {
        // Always register cerebroly_openai_api_key setting to allow form submission
        register_setting('cerebroly_settings', 'cerebroly_openai_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting('cerebroly_settings', 'cerebroly_extract_posts', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('cerebroly_settings', 'cerebroly_extract_media', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('cerebroly_settings', 'cerebroly_use_ai_enhancement', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('cerebroly_settings', 'cerebroly_finetuning_base_model', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('cerebroly_settings', 'cerebroly_use_rag', array(
            'sanitize_callback' => 'absint'
        ));

        register_setting('cerebroly_settings', 'cerebroly_rate_limit_enabled', array(
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('cerebroly_settings', 'cerebroly_rate_limit_per_minute', array(
            'sanitize_callback' => 'absint'
        ));

        // When RAG is enabled
        if (isset($_POST['cerebroly_use_rag']) && $_POST['cerebroly_use_rag'] == 1) {
            $rag_manager = new CEREBROLY_RAG_Manager();
            $rag_manager->initialize_rag();
        }
    }

    /**
     * Render main page.
     */
    public function render_main_page()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Prepare dashboard data
        $dashboard_data = $this->prepare_dashboard_data();

        // Extract data
        $api_status = $dashboard_data['api_status'];
        $user_models = $dashboard_data['user_models'];
        $active_model = $dashboard_data['active_model'];
        $extract_posts = $dashboard_data['extract_posts'];
        $extract_media = $dashboard_data['extract_media'];
        $file_count = $dashboard_data['file_count'];

        // Include view
        include_once CEREBROLY_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Prepare data for the dashboard.
     *
     * @return array Dashboard data
     */
    public function prepare_dashboard_data()
    {

        // Check API status
        $openai_api = new CEREBROLY_OpenAI_API();
        $api_status = $openai_api->verify_api_key();

        // Get user models
        $user_models = [];
        if ($api_status === true) {
            $user_models = $openai_api->get_user_models();

            // If get_user_models() returns nothing, use test model
            if (empty($user_models)) {
                $user_models = [
                    [
                        'id' => 'test-model-1',
                        'name' => esc_html__('Test Model', 'cerebroly'),
                        'version' => 'gpt-3.5-turbo',
                        'description' => esc_html__('Temporary model for testing', 'cerebroly'),
                        'status' => 'available'
                    ]
                ];
            }
        }

        // Get active model
        $cache_key = 'cerebroly_active_model';
        $active_model = wp_cache_get($cache_key, 'cerebroly_models');
        
        if ($active_model === false) {
            global $wpdb;
            $active_model = $wpdb->get_row(
                "SELECT * FROM {$wpdb->prefix}cerebroly_models
        WHERE status = 'active'
        ORDER BY updated DESC
        LIMIT 1"
            );
            wp_cache_set($cache_key, $active_model, 'cerebroly_models', HOUR_IN_SECONDS);
        }

        // Settings
        $extract_posts = get_option('cerebroly_extract_posts', 1);
        $extract_media = get_option('cerebroly_extract_media', 0);

        $cache_key = 'cerebroly_file_count';
        $file_count = wp_cache_get($cache_key, 'cerebroly_files');
        
        if ($file_count === false) {
            $file_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cerebroly_files"
            );
            wp_cache_set($cache_key, $file_count, 'cerebroly_files', HOUR_IN_SECONDS);
        }

        if ($active_model) {
            $openai_api = new CEREBROLY_OpenAI_API();
            $status_check = $openai_api->get_fine_tuning_status($active_model->model_id);

            if (!is_wp_error($status_check) && isset($status_check['status'])) {
                if ($status_check['status'] === 'succeeded' && !empty($status_check['fine_tuned_model'])) {
                    $wpdb->update(
                        $wpdb->prefix . 'cerebroly_models',
                        [
                            'status' => 'active',
                            'updated' => current_time('mysql'),
                            'sources' => maybe_serialize(['fine_tuned_model' => $status_check['fine_tuned_model']]),
                        ],
                        ['model_id' => $active_model->model_id],
                        ['%s', '%s', '%s'],
                        ['%s']
                    );

                    // Actualiza también la variable en memoria
                    $active_model->status = $status_check['status'];
                    $active_model->sources = maybe_serialize(['fine_tuned_model' => $status_check['fine_tuned_model']]);
                }
            }
        }

        $result = array(
            'api_status' => $api_status,
            'user_models' => $user_models,
            'active_model' => $active_model,
            'extract_posts' => $extract_posts,
            'extract_media' => $extract_media,
            'file_count' => $file_count
        );

      

        return $result;
    }

    /**
     * Render files page.
     */
    public function render_files_page()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get files
        $file_handler = new CEREBROLY_File_Handler();
        $files = $file_handler->get_files();

        // Include view
        include CEREBROLY_PLUGIN_DIR . 'admin/views/files.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings_page()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include view
        include CEREBROLY_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Handle model selection.
     */
    public function handle_select_model()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You don\'t have permission to perform this action.', 'cerebroly'), esc_html__('Error', 'cerebroly'), array('response' => 403));
        }

        // Verify nonce
        check_admin_referer('cerebroly_select_model');

        // Get model ID
        $model_id = isset($_POST['model_id']) ? sanitize_text_field($_POST['model_id']) : '';

        if (empty($model_id)) {
            add_settings_error(
                'cerebroly_model',
                'cerebroly_model_error',
                esc_html__('Invalid model ID.', 'cerebroly'),
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
            exit;
        }

        // Check if the model exists in OpenAI
        $openai_api = new CEREBROLY_OpenAI_API();
        $status_check = $openai_api->check_model_status($model_id);

        // If there's an error checking the model
        if (is_wp_error($status_check)) {
            add_settings_error(
                'cerebroly_model',
                'cerebroly_model_error',
                /* translators: %s: API error message */
                sprintf(esc_html__('Error checking model: %s', 'cerebroly'), $status_check->get_error_message()),
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
            exit;
        }

        // Update active model
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');

        // Deactivate all models
        $wpdb->update(
            $table_name,
            array('status' => 'inactive'),
            array('status' => 'active'),
            array('%s'),
            array('%s')
        );
        
        // Clear the active model cache
        wp_cache_delete('cerebroly_active_model', 'cerebroly_models');

        // Check if model already exists in database
        $existing_model = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE model_id = %s",
            $model_id
        ));


        if ($existing_model) {
            // Activate selected model if it already exists
            $update_result = $wpdb->update(
                $table_name,
                array(
                    'status' => 'active',
                    'updated' => current_time('mysql')
                ),
                array('model_id' => $model_id),
                array('%s', '%s'),
                array('%s')
            );

        } else {
            // Insert model if it doesn't exist
            $meta = maybe_serialize(array(
                'fine_tuned_model' => $status_check['fine_tuned_model'] ?? '',
                'result' => $status_check
            ));

            $insert_result = $wpdb->insert(
                $table_name,
                array(
                    'model_id' => $model_id,
                    'status' => 'active',
                    'created' => current_time('mysql'),
                    'updated' => current_time('mysql'),
                    'sources' => $meta
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );

        }

        add_settings_error(
            'cerebroly_model',
            'cerebroly_model_success',
            esc_html__('Model selected successfully.', 'cerebroly'),
            'success'
        );
        set_transient('settings_errors', get_settings_errors(), 30);

        wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
        exit;
    }

    /**
     * Handle model deletion.
     */
    public function handle_delete_model()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You don\'t have permission to perform this action.', 'cerebroly'), esc_html__('Error', 'cerebroly'), array('response' => 403));
        }

        // Verify nonce
        check_admin_referer('cerebroly_delete_model');

        // Get model ID
        $model_id = isset($_POST['model_id']) ? sanitize_text_field($_POST['model_id']) : '';


        if (empty($model_id)) {
            add_settings_error(
                'cerebroly_model',
                'cerebroly_model_error',
                esc_html__('Invalid model ID.', 'cerebroly'),
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
            exit;
        }

        // Delete from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'cerebroly_models';

        // Check if model exists first
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE model_id = %s",
            $table_name,
            $model_id
        ));


        if ($exists > 0) {
            $result = $wpdb->delete(
                $table_name,
                array('model_id' => $model_id),
                array('%s')
            );


            if ($result !== false) {
                add_settings_error(
                    'cerebroly_model',
                    'cerebroly_model_success',
                    esc_html__('Model deleted successfully.', 'cerebroly'),
                    'success'
                );
            } else {
                add_settings_error(
                    'cerebroly_model',
                    'cerebroly_model_error',
                    /* translators: %s: Database error message */
                    sprintf(esc_html__('Error deleting model: %s', 'cerebroly'), $wpdb->last_error),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'cerebroly_model',
                'cerebroly_model_error',
                esc_html__('The model does not exist in the database.', 'cerebroly'),
                'error'
            );
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=cerebroly'));
        exit;
    }

    /**
     * Handle file upload.
     */
    public function handle_file_upload()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You don\'t have permission to perform this action.', 'cerebroly'), esc_html__('Error', 'cerebroly'), array('response' => 403));
        }

        // Verify nonce
        check_admin_referer('cerebroly_upload_file');

        // Process file
        if (!isset($_FILES['cerebroly_file'])) {
            add_settings_error(
                'cerebroly_file_upload',
                'cerebroly_no_file',
                esc_html__('No file provided.', 'cerebroly'),
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('admin.php?page=cerebroly-files'));
            exit;
        }

        $file_handler = new CEREBROLY_File_Handler();
        $result = $file_handler->upload_file($_FILES['cerebroly_file']);

        // Redirect with message
        if (is_wp_error($result)) {
            add_settings_error(
                'cerebroly_file_upload',
                'cerebroly_upload_error',
                /* translators: %s: Error uploading file: %s */
                sprintf(esc_html__('Error uploading file: %s', 'cerebroly'), $result->get_error_message()),
                'error'
            );
        } else {
            add_settings_error(
                'cerebroly_file_upload',
                'cerebroly_upload_success',
                /* translators: %s: File uploaded successfully: %s */
                sprintf(esc_html__('File uploaded successfully: %s', 'cerebroly'), $result['filename']),
                'success'
            );
            
            // Clear file-related caches
            wp_cache_delete('cerebroly_file_count', 'cerebroly_files');
            wp_cache_delete('cerebroly_uploaded_files', 'cerebroly_files');
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=cerebroly-files'));
        exit;
    }

    /**
     * Handle file deletion.
     */
    public function handle_file_delete()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            
            wp_die(esc_html__('You don\'t have permission to perform this action.', 'cerebroly'), esc_html__('Error', 'cerebroly'), array('response' => 403));
        }

        // Verify nonce
        check_admin_referer('cerebroly_delete_file');

        // Get file ID
        $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;

        if (!$file_id) {
            add_settings_error(
                'cerebroly_file_delete',
                'cerebroly_delete_error',
                esc_html__('Invalid file ID.', 'cerebroly'),
                'error'
            );
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('admin.php?page=cerebroly-files'));
            exit;
        }

        // Delete file
        $file_handler = new CEREBROLY_File_Handler();
        $result = $file_handler->delete_file($file_id);

        // Redirect with message
        if (is_wp_error($result)) {
            add_settings_error(
                'cerebroly_file_delete',
                'cerebroly_delete_error',
                /* translators: %s: Error deleting file: %s */
                sprintf(esc_html__('Error deleting file: %s', 'cerebroly'), $result->get_error_message()),
                'error'
            );
        } else {
            add_settings_error(
                'cerebroly_file_delete',
                'cerebroly_delete_success',
                esc_html__('File deleted successfully.', 'cerebroly'),
                'success'
            );
            
            // Clear file-related caches
            wp_cache_delete('cerebroly_file_count', 'cerebroly_files');
            wp_cache_delete('cerebroly_uploaded_files', 'cerebroly_files');
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=cerebroly-files'));
        exit;
    }

    /**
     * Check a model's status.
     *
     * @param string $model_id Model ID
     */
    public function check_model_status($model_id)
    {
        $openai_api = new CEREBROLY_OpenAI_API();
        $openai_api->check_model_status($model_id);
    }
}

add_action('wp_ajax_cerebroly_test_cron', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('Insufficient permissions', 'cerebroly')]);
    }
    check_ajax_referer('cerebroly_cron_test_nonce', 'security');

    // Programar evento
    if (!wp_next_scheduled('cerebroly_test_cron_event')) {
        wp_schedule_single_event(time() + 5, 'cerebroly_test_cron_event');
    }

    wp_send_json_success(['message' => esc_html__('Test cron event scheduled! Check back in 5-10 seconds.', 'cerebroly')]);
});