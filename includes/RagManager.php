<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class CEREBROLY_RAG_Manager
{


    public $openai_api_client;

    public function __construct()
    {

        // Create API client instance
        if (class_exists('CEREBROLY_OpenAI_API')) {
            $this->openai_api_client = new CEREBROLY_OpenAI_API();
        } else {
            // Handle case where API class doesn't exist or isn't loaded
            $this->openai_api_client = null; // Ensure it's null if it doesn't exist
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('FTC RAG Plugin Error:', 'cerebroly') . '</strong> ' . esc_html__('CEREBROLY_OpenAI_API class not found. Communication with OpenAI will not work.', 'cerebroly') . '</p></div>';

            });
        }
        // Register admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        //add_action('admin_post_cerebroly_index_content', array($this, 'handle_index_content'));

        // Register AJAX endpoints
        add_action('wp_ajax_cerebroly_ajax_manual_indexing', array($this, 'ajax_manual_indexing'));
        add_action('wp_ajax_cerebroly_ajax_check_manual_indexing_progress', array($this, 'ajax_check_manual_indexing_progress'));

        add_action('wp_ajax_cerebroly_initialize_rag', array($this, 'ajax_initialize_rag'));
        add_action('wp_ajax_cerebroly_test_rag', array($this, 'ajax_test_rag'));
        add_action('wp_ajax_cerebroly_reset_rag', array($this, 'ajax_reset_rag'));
        add_action('wp_ajax_cerebroly_start_indexing', array($this, 'ajax_start_indexing'));
        add_action('wp_ajax_cerebroly_check_indexing_progress', array($this, 'ajax_check_indexing_progress'));
        add_action('wp_ajax_cerebroly_direct_process_indexing', array($this, 'ajax_direct_process_indexing')); // New endpoint
        add_action('wp_ajax_cerebroly_direct_file_indexing', array($this, 'ajax_direct_file_indexing'));

        add_action('cerebroly_process_manual_indexing_batch_hook', array($this, 'process_manual_indexing_batch_hook_callback'));


        // AJAX endpoints for content preview
        add_action('wp_ajax_cerebroly_get_rag_content_stats', array($this, 'ajax_get_rag_content_stats'));
        add_action('wp_ajax_cerebroly_get_rag_content_preview', array($this, 'ajax_get_rag_content_preview'));

        // Hooks for content updates
        add_action('save_post', array($this, 'update_post_embedding'), 10, 3);
        add_action('delete_post', array($this, 'delete_post_embedding'));
    }

    // --- process_manual_indexing_batch function (The robust version I gave you before) ---
    // Make sure it's here and uses $this->generate_embeddings() that we just modified
    public function process_manual_indexing_batch()
    {
        global $wpdb;

        // 1. Lock
        $lock_transient_name = 'cerebroly_manual_indexing_lock';
        if (get_transient($lock_transient_name)) {
            return;
        }
        set_transient($lock_transient_name, time(), 5 * MINUTE_IN_SECONDS);

        // Variables
        $queue_transient = 'cerebroly_manual_indexing_queue';
        $status_transient = 'cerebroly_manual_indexing_status';
        $processed_transient = 'cerebroly_manual_indexing_processed';
        $errors_transient = 'cerebroly_manual_indexing_errors';
        $error_log_transient = 'cerebroly_manual_indexing_error_log'; // Optional for details

        // 2. Get Queue
        $post_ids_queue = get_transient($queue_transient);
        if (empty($post_ids_queue) || !is_array($post_ids_queue)) {
            set_transient($status_transient, 'completed');
            delete_transient($queue_transient);
            delete_transient($error_log_transient); // Clean log if you use it
            delete_transient($lock_transient_name);
            return;
        }

        // 3. Batch Size
        $batch_size = apply_filters('cerebroly_rag_manual_indexing_batch_size', 5);

        // 4. Get Current Batch
        $current_batch_ids = array_slice($post_ids_queue, 0, $batch_size);

        // 5. Process Batch
        $processed_in_batch = 0;
        $errors_in_batch = 0;
        $error_details = get_transient($error_log_transient) ?: [];

        foreach ($current_batch_ids as $post_id) {
            try {
                $post = get_post($post_id);
                if (!$post || !in_array($post->post_status, ['publish', 'private'])) { // Adjust statuses if needed
                    throw new Exception("Post ID $post_id not found or not public/private.");
                }

                $content_to_index = apply_filters('cerebroly_rag_manual_content_for_post', $post->post_content, $post);
                if (empty(trim($content_to_index))) {
                    // Consider whether to skip or mark as error if empty
                    continue; // Skip this post, don't count as error necessarily
                }

                // a. Chunking (Make sure $this->chunk_content exists)
                $chunks = $this->chunk_content($content_to_index);
                if ($chunks === false || !is_array($chunks)) { // If chunk_content can return false
                    throw new Exception("Failed to chunk Post ID $post_id.");
                }
                if (empty($chunks)) {
                    continue; // Skip if no chunks, not a fatal post error
                }

                // b. Embeddings and Insertion per Chunk
                $post_has_error = false; // Flag to know if any chunk failed
                foreach ($chunks as $index => $chunk) {
                    // Generate Embeddings (USE THE MODIFIED FUNCTION)
                    $embedding = $this->generate_embeddings($chunk); // <--- CALLS THE NEW FUNCTION

                    if ($embedding === false) {
                        // Error already logged inside generate_embeddings
                        $error_message = "Failed to generate embedding for chunk $index of Post ID $post_id.";
                        $error_details[] = $error_message; // Add to optional log
                        $post_has_error = true; // Mark that this post had error
                        break; // Skip to next post, don't try more chunks of this one
                    }

                    // Save to DB
                    $table_name = esc_sql($wpdb->prefix . 'cerebroly_rag_index'); // Check table name!
                    $data = array(
                        'content_id' => $post_id,
                        'content_type' => $post->post_type, // Use real post_type
                        'content_title' => $post->post_title,
                        'content_chunk' => $chunk,
                        'embedding' => json_encode($embedding),
                        'created' => current_time('mysql', 1)
                    );
                    $format = array('%d', '%s', '%s', '%s', '%s', '%s');
                    $inserted = $wpdb->insert($table_name, $data, $format);

                    if ($inserted === false) {
                        $db_error = $wpdb->last_error;
                        $error_message = "DB insert failed for chunk $index, Post ID $post_id.";
                        $error_details[] = $error_message;
                        $post_has_error = true;
                        break; // Skip to next post
                    }
                } // End foreach $chunks

                if (!$post_has_error) {
                    $processed_in_batch++;
                } else {
                    $errors_in_batch++; // Count the post as error if any of its chunks failed
                }

            } catch (Exception $e_post) {
                $errors_in_batch++;
                $error_message = "Error processing Post ID $post_id: " . $e_post->getMessage();
                $error_details[] = $error_message;
            }
        } // End foreach $current_batch_ids

        // 6. Update Global Counters
        $total_processed = (int) get_transient($processed_transient) + $processed_in_batch;
        $total_errors = (int) get_transient($errors_transient) + $errors_in_batch;
        set_transient($processed_transient, $total_processed);
        set_transient($errors_transient, $total_errors);
        if (!empty($error_details)) { // Save detailed log if you use it
            set_transient($error_log_transient, $error_details);
        }


        // 7. Update Queue and Reschedule
        $remaining_ids = array_slice($post_ids_queue, count($current_batch_ids)); // Remaining IDs

        if (!empty($remaining_ids)) {
            set_transient($queue_transient, $remaining_ids);
            set_transient($status_transient, 'processing');
            wp_schedule_single_event(time() + 10, 'cerebroly_process_manual_indexing_batch_hook'); // Reschedule hook
        } else {
            // Finished
            set_transient($status_transient, 'completed');
            delete_transient($queue_transient);
        }

        // 8. Release Lock
        delete_transient($lock_transient_name);

    } // End process_manual_indexing_batch

    /**
     * Hook to call the batch processing method.
     * Necessary because wp_schedule_single_event can't directly call object methods.
     */
    public function process_manual_indexing_batch_hook_callback()
    {
        // You could add a check here if the API client is ready
        if ($this->openai_api_client) {
            $this->process_manual_indexing_batch();
        } else {
            // Consider how to handle/notify this serious error
            // Maybe try to restart the process or mark it as failed
            set_transient('cerebroly_manual_indexing_status', 'failed_api_init');
            delete_transient('cerebroly_manual_indexing_lock'); // Release lock if we fail here
        }
    }


    /**
     * Generates embeddings for given text. (Example implementation)
     * @param string $text
     * @return array|false Vector embedding or false on error.
     */
    private function generate_embeddings($text)
    {
        // Check if API client was initialized correctly
        if (!$this->openai_api_client) {
            return false;
        }

        if (empty(trim($text))) {
            return false;
        }

        // Get embedding model name from options
        $embedding_model = get_option('cerebroly_rag_embedding_model', 'text-embedding-ada-002');

        // Call the custom API class method
        $result = $this->openai_api_client->create_embedding($text, $embedding_model);

        // Handle the result returned by CEREBROLY_OpenAI_API
        if (is_wp_error($result)) {
            // Error returned by API class (already logged inside API class)
            // We log here too to know it failed at this point in the RAG process
            return false; // Return false so process_manual_indexing_batch counts it as error
        } elseif (is_array($result)) {
            // Success! Return the vector array
            return $result;
        } else {
            // Unexpected result (API class should return WP_Error or array)
            return false;
        }
    } // --- End of generate_embeddings MODIFIED ---


    public function ajax_manual_indexing()
    {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'cerebroly')]);
        }
        check_ajax_referer('cerebroly_rag_nonce', 'security');

        // Collect parameters   
        $sources = isset($_POST['sources']) ? array_map('sanitize_text_field', $_POST['sources']) : [];
        $force_reindex = isset($_POST['force_reindex']) && $_POST['force_reindex'] === 'true';

        // Configure options based on sources
        $update_options = [];
        $update_options['cerebroly_rag_include_posts'] = in_array('posts', $sources) ? 1 : 0;
        $update_options['cerebroly_rag_include_products'] = in_array('products', $sources) ? 1 : 0;
        $update_options['cerebroly_rag_include_files'] = in_array('files', $sources) ? 1 : 0;

        // Update options
        foreach ($update_options as $option => $value) {
            update_option($option, $value);
        }

        // Start indexing
        $result = $this->start_indexing($force_reindex);

        if ($result['success']) {
            wp_send_json_success([
                'job_id' => $result['job_id'],
                'sources' => $sources
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    public function ajax_check_manual_indexing_progress()
    {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'cerebroly')]);
        }
        check_ajax_referer('cerebroly_rag_nonce', 'security');

        // Get indexing job
        global $wpdb;
        $indexing_table = $wpdb->prefix . 'cerebroly_indexing_status';

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE job_id = %s",
            $indexing_table,
            $job_id
        ));

        if (!$job) {
            wp_send_json_error(['message' => __('Job not found', 'cerebroly')]);
        }

        // Retrieve active sources
        $sources = [];
        if (get_option('cerebroly_rag_include_posts'))
            $sources[] = __('Posts and pages', 'cerebroly');
        if (get_option('cerebroly_rag_include_products'))
            $sources[] = __('WooCommerce products', 'cerebroly');
        if (get_option('cerebroly_rag_include_files'))
            $sources[] = __('Uploaded files', 'cerebroly');

        wp_send_json_success([
            'job_id' => $job->job_id,
            'progress' => (int) $job->progress,
            'total_items' => (int) $job->total_items,
            'processed_items' => (int) $job->processed_items,
            /* translators: %1$d: Processed items count, %2$d: Total items count */
            'status' => sprintf(__('Processing %1$d of %2$d items', 'cerebroly'), $job->processed_items, $job->total_items),
            'completed' => $job->status === 'completed',
            'sources' => $sources
        ]);
    }

    /** 
     * Start a new indexing job.
     *
     * @param bool $force_reindex Whether to clear previous embeddings.
     * @return array Result of the operation (success, job_id, message).
     */
    public function start_indexing($force_reindex = false)
    {
        global $wpdb;

        $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');

        // Ensure required tables exist
        $this->create_rag_tables();

        // Force reindex: truncate the embeddings table
        if ($force_reindex) {
            $wpdb->query("TRUNCATE TABLE $embedding_table");
        }

        // Get items to index
        $content_items = $this->get_content_for_indexing();
        $total_items = count($content_items);

        if ($total_items === 0) {
            return array(
                'success' => false,
                'message' => __('No content found to index. Check your content sources.', 'cerebroly')
            );
        }

        // Create new indexing job
        $job_id = 'job_' . time() . '_' . wp_rand(1000, 9999);

        $inserted = $wpdb->insert($indexing_table, array(
            'job_id' => $job_id,
            'status' => 'processing',
            'progress' => 0,
            'total_items' => $total_items,
            'processed_items' => 0,
            'started' => current_time('mysql')
        ));

        if (!$inserted) {
            return array(
                'success' => false,
                /* translators: %s: Database error message */
                'message' => sprintf(__('Failed to create indexing job: %s', 'cerebroly'), $wpdb->last_error)
            );
        }

        // Schedule indexing batch (WP-Cron)
        wp_schedule_single_event(time() + 5, 'cerebroly_process_indexing_batch', array($job_id, $content_items, 0));

        return array(
            'success' => true,
            'message' => __('Indexing started successfully.', 'cerebroly'),
            'job_id' => $job_id
        );
    }



    /**
     * AJAX handler to start indexing.
     */
    public function ajax_start_indexing()
    {

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
            exit;
        }

        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cerebroly_rag_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'cerebroly')));
            exit;
        }

        $force_reindex = isset($_POST['force_reindex']) && $_POST['force_reindex'] === 'true';

        // Start indexing
        $result = $this->start_indexing($force_reindex);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        exit;
    }

    /**
 * Register settings for RAG system with proper sanitization.
 */
public function register_settings()
{
    // Embedding settings
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_embedding_model', array(
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'text-embedding-ada-002'
    ));
    
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_chunk_size', array(
        'sanitize_callback' => array($this, 'sanitize_positive_integer'),
        'default' => 1000
    ));
    
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_chunk_overlap', array(
        'sanitize_callback' => array($this, 'sanitize_positive_integer'),
        'default' => 200
    ));
    
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_files', array(
        'sanitize_callback' => array($this, 'sanitize_boolean_option'),
        'default' => 1
    ));

    // Register settings for fixed post types only
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_post', array(
        'sanitize_callback' => array($this, 'sanitize_boolean_option'),
        'default' => 1
    ));
    
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_page', array(
        'sanitize_callback' => array($this, 'sanitize_boolean_option'),
        'default' => 1
    ));
    
    register_setting('cerebroly_rag_settings', 'cerebroly_rag_include_product', array(
        'sanitize_callback' => array($this, 'sanitize_boolean_option'),
        'default' => 0
    ));

    // Retrieval settings
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_top_k', array(
        'sanitize_callback' => array($this, 'sanitize_positive_integer'),
        'default' => 5
    ));
    
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_similarity_threshold', array(
        'sanitize_callback' => array($this, 'sanitize_float_range'),
        'default' => 0.75
    ));
    
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_search_method', array(
        'sanitize_callback' => array($this, 'sanitize_search_method'),
        'default' => 'cosine'
    ));
    
    register_setting('cerebroly_rag_retrieval_settings', 'cerebroly_rag_query_rewriting', array(
        'sanitize_callback' => array($this, 'sanitize_boolean_option'),
        'default' => 0
    ));

    // Generation settings
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_llm_model', array(
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'gpt-3.5-turbo'
    ));
    
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_temperature', array(
        'sanitize_callback' => array($this, 'sanitize_temperature'),
        'default' => 0.3
    ));
    
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_max_tokens', array(
        'sanitize_callback' => array($this, 'sanitize_max_tokens'),
        'default' => 1000
    ));
    
    register_setting('cerebroly_rag_generation_settings', 'cerebroly_rag_system_prompt', array(
        'sanitize_callback' => array($this, 'sanitize_system_prompt'),
        'default' => __('You are a specialized assistant for the website. Answer queries based only on the information from the following context. If the information is not in the context, honestly indicate that you do not have that information.', 'cerebroly')
    ));
}

/**
 * Sanitize positive integer values.
 * 
 * @param mixed $value Input value
 * @return int Sanitized positive integer
 */
public function sanitize_positive_integer($value)
{
    $sanitized = absint($value);
    return max(1, $sanitized); // Ensure minimum value of 1
}

/**
 * Sanitize boolean option (0 or 1).
 * 
 * @param mixed $value Input value
 * @return int 0 or 1
 */
public function sanitize_boolean_option($value)
{
    return $value ? 1 : 0;
}

/**
 * Sanitize float values within a range (0.0 to 1.0).
 * 
 * @param mixed $value Input value
 * @return float Sanitized float between 0.0 and 1.0
 */
public function sanitize_float_range($value)
{
    $sanitized = floatval($value);
    return max(0.0, min(1.0, $sanitized));
}

/**
 * Sanitize search method (only allow specific values).
 * 
 * @param mixed $value Input value
 * @return string Valid search method
 */
public function sanitize_search_method($value)
{
    $allowed_methods = array('cosine', 'dot_product', 'euclidean');
    $sanitized = sanitize_text_field($value);
    
    return in_array($sanitized, $allowed_methods, true) ? $sanitized : 'cosine';
}

/**
 * Sanitize temperature value (0.0 to 2.0 for OpenAI).
 * 
 * @param mixed $value Input value
 * @return float Sanitized temperature
 */
public function sanitize_temperature($value)
{
    $sanitized = floatval($value);
    return max(0.0, min(2.0, $sanitized));
}

/**
 * Sanitize max tokens (positive integer with reasonable limits).
 * 
 * @param mixed $value Input value
 * @return int Sanitized max tokens
 */
public function sanitize_max_tokens($value)
{
    $sanitized = absint($value);
    return max(10, min(4000, $sanitized)); // Between 10 and 4000 tokens
}

/**
 * Sanitize system prompt (strip tags but preserve line breaks).
 * 
 * @param mixed $value Input value
 * @return string Sanitized system prompt
 */
public function sanitize_system_prompt($value)
{
    // Remove HTML tags but preserve basic formatting
    $sanitized = wp_strip_all_tags($value);
    
    // Ensure it's not empty
    if (empty(trim($sanitized))) {
        return __('You are a specialized assistant for the website. Answer queries based only on the information from the following context. If the information is not in the context, honestly indicate that you do not have that information.', 'cerebroly');
    }
    
    // Limit length (reasonable for system prompts)
    return substr($sanitized, 0, 2000);
}
    /**
     * Initialize RAG system.
     */
    public function initialize_rag()
    {

        // Create necessary tables
        $this->create_rag_tables();

        // Set default options
        if (!get_option('cerebroly_rag_embedding_model')) {
            add_option('cerebroly_rag_embedding_model', 'text-embedding-ada-002');
        }

        if (!get_option('cerebroly_rag_chunk_size')) {
            add_option('cerebroly_rag_chunk_size', 1000);
        }

        if (!get_option('cerebroly_rag_chunk_overlap')) {
            add_option('cerebroly_rag_chunk_overlap', 200);
        }

        // Set default options for fixed post types
        if (!get_option('cerebroly_rag_include_post')) {
            add_option('cerebroly_rag_include_post', 1);
        }

        if (!get_option('cerebroly_rag_include_page')) {
            add_option('cerebroly_rag_include_page', 1);
        }

        if (!get_option('cerebroly_rag_include_product')) {
            add_option('cerebroly_rag_include_product', 0);
        }

        if (!get_option('cerebroly_rag_include_files')) {
            add_option('cerebroly_rag_include_files', 1);
        }

        if (!get_option('cerebroly_rag_top_k')) {
            add_option('cerebroly_rag_top_k', 5);
        }

        if (!get_option('cerebroly_rag_similarity_threshold')) {
            add_option('cerebroly_rag_similarity_threshold', 0.75);
        }

        if (!get_option('cerebroly_rag_search_method')) {
            add_option('cerebroly_rag_search_method', 'cosine');
        }

        if (!get_option('cerebroly_rag_llm_model')) {
            add_option('cerebroly_rag_llm_model', 'gpt-3.5-turbo');
        }

        if (!get_option('cerebroly_rag_temperature')) {
            add_option('cerebroly_rag_temperature', 0.3);
        }

        if (!get_option('cerebroly_rag_max_tokens')) {
            add_option('cerebroly_rag_max_tokens', 1000);
        }

        if (!get_option('cerebroly_rag_system_prompt')) {
            add_option('cerebroly_rag_system_prompt', __('You are a specialized assistant for the website. Answer queries based only on the information from the following context. If the information is not in the context, honestly indicate that you do not have that information.', 'cerebroly'));
        }


        // Create tables if they don't exist
        $this->create_rag_tables();

        return true;
    }

    /**
     * Create necessary tables for RAG.
     */
    private function create_rag_tables()
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

    }

    /**
     * Verify OpenAI API connection and embedding permissions.
     * 
     * @return bool|string True if everything is correct, error message otherwise
     */
    private function verify_openai_embedding_api()
    {
        // Get API key
        $api_key = cerebroly_get_openai_api_key();

        if (empty($api_key)) {
            return __('OpenAI API key has not been configured. Please configure the API key in the settings page.', 'cerebroly');
        }

        // Embedding model
        $model = get_option('cerebroly_rag_embedding_model', 'text-embedding-ada-002');

        // Data to test the embeddings API
        $data = array(
            'model' => $model,
            'input' => 'Test text to verify the embeddings API.'
        );

        // Make request to OpenAI API
        $response = wp_remote_post(
            'https://api.openai.com/v1/embeddings',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($data),
                'timeout' => 15,
            )
        );

        // Check response
        if (is_wp_error($response)) {
            /* translators: %s: Error message from OpenAI connection */
            return sprintf(__('Connection error with OpenAI: %s', 'cerebroly'), $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : __('Unknown error', 'cerebroly');
            /* translators: %s: Error message from OpenAI API */
            return sprintf(__('OpenAI API error: %s', 'cerebroly'), $error_message);
        }

        // Check that response contains an embedding
        if (!isset($response_data['data'][0]['embedding']) || !is_array($response_data['data'][0]['embedding'])) {
            return __('OpenAI API did not return a valid embedding. Check your API key and permissions.', 'cerebroly');
        }

        return true;
    }
    private function get_content_for_indexing()
    {
        $content_items = array();
        global $wpdb;

        // Fixed post types - no more dynamic detection
        $post_types_to_include = [];

        $include_posts = get_option('cerebroly_rag_include_post', 1);
        $include_pages = get_option('cerebroly_rag_include_page', 1);
        $include_products = get_option('cerebroly_rag_include_product', 0);

        if ($include_posts == 1) {
            $post_types_to_include[] = 'post';
        }
        if ($include_pages == 1) {
            $post_types_to_include[] = 'page';
        }
        if ($include_products == 1) {
            $post_types_to_include[] = 'product';
        }

        // Get selected posts/pages/CPTs
        if (!empty($post_types_to_include)) {
            $args = array(
                'post_type' => $post_types_to_include,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
            );

            $query = new WP_Query($args);
            if ($query->have_posts()) {
                foreach ($query->posts as $post_id) {
                    $content_items[] = array(
                        'id' => $post_id,
                        'type' => 'post',
                    );
                }
            }
            wp_reset_postdata();
        }

        // Process files if enabled
        $include_files = get_option('cerebroly_rag_include_files', 0);
        if ($include_files == 1 || $include_files === '1') {
            $files_table = esc_sql($wpdb->prefix . 'cerebroly_files');
            if ($wpdb->get_var("SHOW TABLES LIKE '$files_table'") == $files_table) {
                $files = $wpdb->get_results("SELECT id FROM $files_table WHERE content IS NOT NULL");
                foreach ($files as $file) {
                    $content_items[] = array(
                        'id' => $file->id,
                        'type' => 'file',
                    );
                }
            }
        }


        return $content_items;
    }


    /**
     * Process an indexing batch.
     *
     * @param string $job_id Indexing job ID
     * @param array $content_items Items to process
     * @param int $start_index Batch start index
     * @return bool Processing result
     */
    public function process_indexing_batch($job_id, $content_items, $start_index)
    {
        global $wpdb;
        $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');


        // Check that job exists and is in process
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $indexing_table WHERE job_id = %s AND status = %s LIMIT 1",
            $job_id,
            'processing'
        ));

        if (!$job) {
            return false;
        }

        // Number of items to process in this batch
        $batch_size = 5; // Reduce to 3 for better management
        $end_index = min($start_index + $batch_size, count($content_items));


        $successful_items = 0;

        // Process batch items
        for ($i = $start_index; $i < $end_index; $i++) {
            $item = $content_items[$i];
            $item_type = isset($item['type']) ? $item['type'] : 'unknown';
            $item_id = isset($item['id']) ? $item['id'] : 'unknown';

            $process_result = false;

            try {
                // Process by type
                if ($item_type === 'post') {
                    $process_result = $this->process_post_embedding($item_id);
                } elseif ($item_type === 'file') {
                    $process_result = $this->process_file_embedding($item_id);
                }

                if ($process_result) {
                    $successful_items++;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions
                    error_log('cerebroly indexing error: ' . $e->getMessage());
                }
            }

            // Update progress
            $processed = $job->processed_items + 1;
            $progress = round(($processed / $job->total_items) * 100);

            $wpdb->update(
                $indexing_table,
                array(
                    'processed_items' => $processed,
                    'progress' => $progress
                ),
                array('id' => $job->id)
            );

        }


        // If items remain, schedule next batch
        if ($end_index < count($content_items)) {
            $scheduled = wp_schedule_single_event(
                time() + 5, // Reduce wait time to 5 seconds
                'cerebroly_process_indexing_batch',
                array($job_id, $content_items, $end_index)
            );

            if (!$scheduled) {

                // Try to execute directly if can't schedule
                $this->process_indexing_batch($job_id, $content_items, $end_index);
            }
        } else {
            // Mark job as completed
            $wpdb->update(
                $indexing_table,
                array(
                    'status' => 'completed',
                    'progress' => 100,
                    'completed' => current_time('mysql')
                ),
                array('id' => $job->id)
            );
        }

        return true;
    }

    /**
     * Process post embedding with improved error handling.
     *
     * @param int $post_id Post ID to process
     * @return bool Processing result
     */
    private function process_post_embedding($post_id)
    {
        global $wpdb;
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');



        // Check that table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") != $embedding_table) {
            $this->create_rag_tables();
        }

        // Get post information
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        if ($post->post_status !== 'publish') {
            return false;
        }

        try {
            // Remove existing embeddings for this post
            $wpdb->delete(
                $embedding_table,
                array(
                    'content_id' => $post_id,
                    'content_type' => 'post'
                )
            );


            // Prepare content
            $title = $post->post_title;
            $content = wp_strip_all_tags($post->post_content);


            // If it's a WooCommerce product, add additional information
            if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $content .= "\n\n" . __('Price:', 'cerebroly') . " " . $product->get_price();
                    $content .= "\n\n" . __('SKU:', 'cerebroly') . " " . $product->get_sku();
                    $content .= "\n\n" . __('Short description:', 'cerebroly') . " " . wp_strip_all_tags($product->get_short_description());
                }
            }

            // Content verification
            if (empty($content) || strlen($content) < 10) {
                return false;
            }

            // Split into chunks
            $chunks = $this->chunk_content($content);

            $success_count = 0;

            // Process each chunk
            foreach ($chunks as $index => $chunk) {
                // Check minimum chunk size
                if (strlen($chunk) < 10) {
                    continue;
                }

                // Prepare text for embedding
                /* translators: %1$d: Current part number, %2$d: Total number of parts */
                $chunk_title = $title . " " . sprintf(__('(Part %1$d of %2$d)', 'cerebroly'), ($index + 1), count($chunks));
                $chunk_text = $chunk_title . "\n\n" . $chunk;

                // Generate embedding
                $embedding = $this->generate_embeddings($chunk_text);

                if (!$embedding) {
                    continue;
                }

                // Verify embedding 
                if (!is_array($embedding)) {
                    continue;
                }

                // Convert embedding to JSON
                $embedding_json = json_encode($embedding);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                // Save to database
                $insert_result = $wpdb->insert(
                    $embedding_table,
                    array(
                        'content_id' => $post_id,
                        'content_type' => 'post',
                        'content_title' => $chunk_title,
                        'content_chunk' => $chunk_text,
                        'embedding' => $embedding_json,
                        'created' => current_time('mysql'),
                        'updated' => current_time('mysql')
                    )
                );

                if ($insert_result === false) {
                } else {
                    $success_count++;
                }

                // Free memory
                unset($embedding);
                unset($embedding_json);

                if ($index % 3 === 0) {
                    gc_collect_cycles();
                }
            }


            return $success_count > 0;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions
                error_log('cerebroly post embedding error (post ' . $post_id . '): ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Execute indexing process directly without depending on WP-Cron.
     * Useful for environments where WP-Cron doesn't work correctly.
     *
     * @param string $job_id Indexing job ID
     * @return array Processing result
     */
    public function direct_process_indexing($job_id)
    {
        global $wpdb;
        $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');


        // Get job information
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $indexing_table WHERE job_id = %s LIMIT 1",
            $job_id
        ));

        if (!$job) {
            return array(
                'success' => false,
                'message' => __('Job not found', 'cerebroly')
            );
        }

        // Get all items to index
        $content_items = $this->get_content_for_indexing();
        $total_items = count($content_items);

        if ($total_items === 0) {
            $wpdb->update(
                $indexing_table,
                array(
                    'status' => 'completed',
                    'progress' => 100,
                    'processed_items' => 0,
                    'total_items' => 0,
                    'completed' => current_time('mysql')
                ),
                array('id' => $job->id)
            );

            return array(
                'success' => true,
                'message' => __('No items to index', 'cerebroly'),
                'processed' => 0,
                'total' => 0
            );
        }

        // Update job total items
        $wpdb->update(
            $indexing_table,
            array(
                'total_items' => $total_items,
                'status' => 'processing'
            ),
            array('id' => $job->id)
        );

        $successful_items = 0;

        // Process all items sequentially
        foreach ($content_items as $index => $item) {
            $item_type = isset($item['type']) ? $item['type'] : 'unknown';
            $item_id = isset($item['id']) ? $item['id'] : 'unknown';


            $process_result = false;

            try {
                // Process by type
                if ($item_type === 'post') {
                    $process_result = $this->process_post_embedding($item_id);
                } elseif ($item_type === 'file') {
                    $process_result = $this->process_file_embedding($item_id);
                }

                if ($process_result) {
                    $successful_items++;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions
                    error_log('cerebroly direct indexing error: ' . $e->getMessage());
                }
            }

            // Update progress
            $progress = round((($index + 1) / $total_items) * 100);

            $wpdb->update(
                $indexing_table,
                array(
                    'processed_items' => $index + 1,
                    'progress' => $progress
                ),
                array('id' => $job->id)
            );

            // Give time for request to process and not block server
            if ($index % 5 === 0) {
                usleep(250000); // 250ms
            }
        }

        // Mark job as completed
        $wpdb->update(
            $indexing_table,
            array(
                'status' => 'completed',
                'progress' => 100,
                'completed' => current_time('mysql')
            ),
            array('id' => $job->id)
        );


        return array(
            'success' => true,
            'message' => __('Processing completed', 'cerebroly'),
            'processed' => $successful_items,
            'total' => $total_items
        );
    }

    /**
     * AJAX handler for direct indexing processing.
     */
    public function ajax_direct_process_indexing()
    {

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
            exit;
        }

        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cerebroly_rag_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'cerebroly')));
            exit;
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';

        if (empty($job_id)) {
            wp_send_json_error(array('message' => __('No job ID provided', 'cerebroly')));
            exit;
        }

        // Execute direct processing
        $result = $this->direct_process_indexing($job_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        exit;
    }

    public function direct_file_indexing($job_id)
    {
        global $wpdb;
        $files_table = esc_sql($wpdb->prefix . 'cerebroly_files');
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
        $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');


        // Get only plain text files
        $files = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, filename, content, filetype FROM `{$files_table}` WHERE filetype = %s AND content IS NOT NULL",
                'text/plain'
            )
        );


        $total_files = count($files);
        $processed_files = 0;

        // Update job status
        $wpdb->update(
            $indexing_table,
            [
                'total_items' => $total_files,
                'status' => 'processing'
            ],
            ['job_id' => $job_id]
        );

        // Delete existing file embeddings
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$embedding_table}` WHERE content_type = %s",
                'file'
            )
        );

        foreach ($files as $index => $file) {

            // Split content into chunks
            $chunks = $this->chunk_content($file->content);

            foreach ($chunks as $chunk_index => $chunk) {

                // Generate embedding for each chunk
                $embedding = $this->generate_embeddings($chunk);

                if ($embedding) {
                    $insert_data = [
                        'content_id' => 'file_' . $file->id,
                        'content_type' => 'file',
                        /* translators: %d: (Part %d) */
                        'content_title' => $file->filename . " " . sprintf(__('(Part %d)', 'cerebroly'), ($chunk_index + 1)),
                        'content_chunk' => $chunk,
                        'embedding' => json_encode($embedding),
                        'created' => current_time('mysql'),
                        'updated' => current_time('mysql')
                    ];
                    $insert_result = $wpdb->insert(
                        $embedding_table,
                        $insert_data
                    );


                }
            }

            $processed_files++;

            // Update progress
            $progress = round(($processed_files / $total_files) * 100);
            $wpdb->update(
                $indexing_table,
                [
                    'processed_items' => $processed_files,
                    'progress' => $progress
                ],
                ['job_id' => $job_id]
            );
        }

        // Mark job as completed
        $wpdb->update(
            $indexing_table,
            [
                'status' => 'completed',
                'progress' => 100,
                'completed' => current_time('mysql')
            ],
            ['job_id' => $job_id]
        );

        return [
            'success' => true,
            'processed' => $processed_files,
            'total' => $total_files
        ];
    }

    /**
     * AJAX handler for direct file processing
     */
    public function ajax_direct_file_indexing()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
            exit;
        }

        // Check nonce
        check_ajax_referer('cerebroly_rag_nonce', 'security');

        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';

        // Execute direct file processing
        $result = $this->direct_file_indexing($job_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        exit;
    }

    /**
     * Process file embedding.
     */
    private function process_file_embedding($file_id)
    {
        global $wpdb;
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
        $files_table = esc_sql($wpdb->prefix . 'cerebroly_files');


        // Get file information
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d",
            $file_id
        ));



        // Remove existing embeddings for this file
        $wpdb->delete(
            $embedding_table,
            array(
                'content_id' => 'file_' . $file_id,
                'content_type' => 'file'
            )
        );

        // Split into chunks
        $chunks = $this->chunk_content($file->content);


        // Process each chunk
        foreach ($chunks as $index => $chunk) {
            // Only process if it has sufficient content
            if (strlen($chunk) < 10) {
                continue;
            }

            // Prepare text for embedding
            $chunk_title = $file->filename . " " .
                /* translators: %1$d: Current part number, %2$d: Total number of parts */
                sprintf(__('(Part %1$d of %2$d)', 'cerebroly'), ($index + 1), count($chunks));
            $chunk_text = $chunk_title . "\n\n" . $chunk;

            // Generate embedding
            $embedding = $this->generate_embeddings($chunk_text);

            if ($embedding) {
                // Save to database
                $result = $wpdb->insert(
                    $embedding_table,
                    array(
                        'content_id' => 'file_' . $file_id,
                        'content_type' => 'file',
                        'content_title' => $chunk_title,
                        'content_chunk' => $chunk_text,
                        'embedding' => json_encode($embedding),
                        'created' => current_time('mysql'),
                        'updated' => current_time('mysql')
                    )
                );


            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions
                    error_log('cerebroly: could not generate embedding for chunk');
                }
            }
        }

        return true;
    }

    /**
     * Splits content into chunks. (Example implementation)
     * @param string $content
     * @return array Array of strings (chunks)
     */
    private function chunk_content($content)
    {
        // Implement your chunking logic here. It must be robust!
        // Very basic example: split by paragraphs, clean HTML and spaces
        $content = wp_strip_all_tags($content); // Remove HTML
        $content = preg_replace('/\s+/', ' ', $content); // Replace multiple spaces/breaks with one
        $content = trim($content);

        if (empty($content)) {
            return []; // Return empty array if no content after cleaning
        }

        // Split by sentences (more robust than paragraphs sometimes) or use a library if you need more precision
        $sentences = preg_split('/(?<=[.?!])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        // Group sentences into chunks of reasonable size (e.g. approx 3-5 sentences or X tokens)
        $chunks = [];
        $current_chunk = '';
        $max_chunk_length = 1000; // MAXIMUM APPROXIMATE length in characters (adjust according to model/tokens)

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence))
                continue;

            if (empty($current_chunk)) {
                $current_chunk = $sentence;
            } elseif (strlen($current_chunk) + strlen($sentence) + 1 < $max_chunk_length) {
                $current_chunk .= ' ' . $sentence;
            } else {
                // Current chunk is full, save it and start a new one
                $chunks[] = $current_chunk;
                $current_chunk = $sentence;
            }
        }
        // Add the last chunk if not empty
        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Buscar documentos relevantes para una consulta.
     */
    public function search_documents($query, $top_k = null, $threshold = null)
    {
        global $wpdb;
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');

        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") != $embedding_table) {
            return array();
        }

        // Obtener configuración
        $top_k = $top_k ?: intval(get_option('cerebroly_rag_top_k', 5));
        $threshold = $threshold ?: floatval(get_option('cerebroly_rag_similarity_threshold', 0.75));
        $search_method = get_option('cerebroly_rag_search_method', 'cosine');

        // Generar embedding para la consulta
        $query_embedding = $this->generate_embeddings($query);

        if (!$query_embedding) {
            return array();
        }

        // Obtener todos los embeddings de la base de datos
        $embeddings = $wpdb->get_results("SELECT id, content_id, content_type, content_title, content_chunk, embedding FROM $embedding_table");

        // Calcular similitud para cada documento
        $similarities = array();

        foreach ($embeddings as $doc) {
            $doc_embedding = json_decode($doc->embedding, true);

            if (!$doc_embedding)
                continue;

            // Calcular similitud según el método seleccionado
            $similarity = $this->calculate_similarity($query_embedding, $doc_embedding, $search_method);

            // Solo considerar documentos con similitud por encima del umbral
            if ($similarity >= $threshold) {
                $similarities[] = array(
                    'id' => $doc->id,
                    'content_id' => $doc->content_id,
                    'content_type' => $doc->content_type,
                    'title' => $doc->content_title,
                    'content' => $doc->content_chunk,
                    'similarity' => $similarity
                );
            }
        }

        // Ordenar por similitud descendente
        usort($similarities, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Devolver los top_k documentos más relevantes
        return array_slice($similarities, 0, $top_k);
    }
    /**
     * Calculate similarity between two embeddings.
     */
    private function calculate_similarity($embedding1, $embedding2, $method = 'cosine')
    {
        // Check that embeddings have the same dimension
        if (count($embedding1) != count($embedding2)) {
            return 0;
        }

        if ($method === 'cosine') {
            // Cosine similarity: dot(a, b) / (||a|| * ||b||)
            $dot_product = 0;
            $norm_a = 0;
            $norm_b = 0;

            for ($i = 0; $i < count($embedding1); $i++) {
                $dot_product += $embedding1[$i] * $embedding2[$i];
                $norm_a += $embedding1[$i] * $embedding1[$i];
                $norm_b += $embedding2[$i] * $embedding2[$i];
            }

            $norm_a = sqrt($norm_a);
            $norm_b = sqrt($norm_b);

            if ($norm_a == 0 || $norm_b == 0) {
                return 0;
            }

            return $dot_product / ($norm_a * $norm_b);
        } elseif ($method === 'dot_product') {
            // Simple dot product
            $dot_product = 0;

            for ($i = 0; $i < count($embedding1); $i++) {
                $dot_product += $embedding1[$i] * $embedding2[$i];
            }

            return $dot_product;
        } elseif ($method === 'euclidean') {
            // Inverse Euclidean distance (converted to similarity)
            $sum = 0;

            for ($i = 0; $i < count($embedding1); $i++) {
                $sum += pow($embedding1[$i] - $embedding2[$i], 2);
            }

            $distance = sqrt($sum);

            // Convert distance to similarity (1 / (1 + distance))
            return 1 / (1 + $distance);
        }

        // Default, use cosine
        return 0;
    }


    /**
     * Generate response using RAG with improved citations.
     */
    public function generate_rag_response($query)
    {
        // Get relevant documents
        $relevant_docs = $this->search_documents($query);

        if (empty($relevant_docs)) {
            return array(
                'success' => false,
                'message' => __('No relevant documents found to answer the query.', 'cerebroly'),
                'documents' => array(),
                'response' => __('Sorry, I don\'t have enough information to answer this query.', 'cerebroly')
            );
        }



        // Build context with retrieved documents
        $context = "";
        $sources_list = "";



        // Build enhanced system prompt with citation instructions
        $base_system_prompt = get_option(
            'cerebroly_rag_system_prompt',
            __('You are a specialized assistant. Answer based only on the information from the provided context.', 'cerebroly')
        );

        $system_prompt = $base_system_prompt;

        // Get configuration for generation
        $model = get_option('cerebroly_rag_llm_model', 'gpt-3.5-turbo');
        $temperature = floatval(get_option('cerebroly_rag_temperature', 0.3));
        $max_tokens = intval(get_option('cerebroly_rag_max_tokens', 1000));

        // Prepare data for OpenAI request
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => __('Context:', 'cerebroly') . "\n" . $context . "\n\n" . __('Question:', 'cerebroly') . " " . $query
                )
            ),
            'temperature' => $temperature,
            'max_tokens' => $max_tokens
        );

        // Get API key
        $api_key = cerebroly_get_openai_api_key();

        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('OpenAI API key has not been configured.', 'cerebroly'),
                'documents' => $relevant_docs,
                'response' => __('Error: OpenAI API not configured.', 'cerebroly')
            );
        }

        // Make request to OpenAI API
        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($data),
                'timeout' => 60,
            )
        );

        // Check response
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                /* translators: %s: Error message describing the connection issue */
                'message' => sprintf(__('Connection error: %s', 'cerebroly'), $response->get_error_message()),
                'documents' => $relevant_docs,
                'response' => __('Error generating response.', 'cerebroly')
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : __('Unknown error generating response.', 'cerebroly');

            return array(
                'success' => false,
                /* translators: %s: Error message from the API */
                'message' => sprintf(__('API error: %s', 'cerebroly'), $error_message),
                'documents' => $relevant_docs,
                'response' => __('Error generating response.', 'cerebroly')
            );
        }

        // Extract response
        if (isset($response_data['choices'][0]['message']['content'])) {
            $ai_response = $response_data['choices'][0]['message']['content'];


            return array(
                'success' => true,
                'documents' => $relevant_docs,
                'processed_query' => $query,
                'response' => $ai_response
            );
        }

        return array(
            'success' => false,
            'message' => __('Unknown response format.', 'cerebroly'),
            'documents' => $relevant_docs,
            'response' => __('Error processing response.', 'cerebroly')
        );
    }

    /**
     * Update post embedding when post is updated.
     */
    public function update_post_embedding($post_id, $post, $update)
    {
        // Ignore autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Ignore revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }

        // Only process post types included in configuration
        $include_posts = get_option('cerebroly_rag_include_posts', 1);
        $include_products = get_option('cerebroly_rag_include_products', 0);

        if (!$include_posts && $post->post_type !== 'product') {
            return;
        }

        if (!$include_products && $post->post_type === 'product') {
            return;
        }

        // Schedule embedding update in background
        wp_schedule_single_event(time() + 5, 'cerebroly_update_post_embedding', array($post_id));
    }

    /**
     * Delete post embeddings when post is deleted.
     */
    public function delete_post_embedding($post_id)
    {
        global $wpdb;
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") != $embedding_table) {
            return;
        }

        // Delete post embeddings
        $wpdb->delete(
            $embedding_table,
            array(
                'content_id' => $post_id,
                'content_type' => 'post'
            )
        );
    }

    /**
     * Reset RAG system (delete all embeddings).
     */
    public function reset_rag()
    {
        global $wpdb;
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
        $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');

        // Truncate tables
        if ($wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") == $embedding_table) {
            $wpdb->query("TRUNCATE TABLE $embedding_table");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$indexing_table'") == $indexing_table) {
            $wpdb->query("TRUNCATE TABLE $indexing_table");
        }

        return true;
    }



    /**
     * AJAX handler to initialize RAG system.
     */
    public function ajax_initialize_rag()
    {
        // Entry log

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
            exit;
        }

        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cerebroly_rag_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'cerebroly')));
            exit;
        }

        // Initialize system
        $result = $this->initialize_rag();

        if ($result) {
            // Ensure necessary tables are created
            $this->create_rag_tables();

            wp_send_json_success(array('message' => __('RAG system initialized correctly', 'cerebroly')));
        } else {
            wp_send_json_error(array('message' => __('Error initializing RAG system', 'cerebroly')));
        }
        exit;
    }



    /**
     * AJAX handler to test RAG system.
     */
    public function ajax_test_rag()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
        }

        // Check nonce
        check_ajax_referer('cerebroly_rag_test_nonce', 'security');

        // Get query
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (empty($query)) {
            wp_send_json_error(array('message' => __('Empty query', 'cerebroly')));
        }

        // Generate response
        $result = $this->generate_rag_response($query);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['message'], 'documents' => $result['documents']));
        }
    }

    /**
     * AJAX handler to reset RAG system.
     */
    public function ajax_reset_rag()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
            exit;
        }

        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cerebroly_rag_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'cerebroly')));
            exit;
        }

        // Reset system
        $result = $this->reset_rag();

        if ($result) {
            wp_send_json_success(array('message' => __('RAG system reset correctly', 'cerebroly')));
        } else {
            wp_send_json_error(array('message' => __('Error resetting RAG system', 'cerebroly')));
        }
        exit;
    }



    /**
     * AJAX handler to check indexing progress.
     */
    public function ajax_check_indexing_progress()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'cerebroly')));
            exit;
        }

        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'cerebroly_rag_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed', 'cerebroly')));
            exit;
        }

        // Get active job
        global $wpdb;
        $indexing_table = esc_sql($wpdb->prefix . 'cerebroly_indexing_status');

        if ($wpdb->get_var("SHOW TABLES LIKE '$indexing_table'") != $indexing_table) {
            wp_send_json_error(array('message' => __('RAG system not initialized', 'cerebroly')));
            exit;
        }

        // Get job ID if provided
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';

        if (!empty($job_id)) {
            $active_job = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$indexing_table}` WHERE status IN ('processing', 'completed') AND job_id = %s ORDER BY started DESC LIMIT 1",
                    $job_id
                )
            );
        } else {
            $active_job = $wpdb->get_row(
                "SELECT * FROM `{$indexing_table}` WHERE status IN ('processing', 'completed') ORDER BY started DESC LIMIT 1"
            );
        }

        if (!$active_job) {
            wp_send_json_error(array('message' => __('No active indexing jobs', 'cerebroly')));
            exit;
        }

        // Prepare response
        $response = array(
            'job_id' => $active_job->job_id,
            'progress' => (int) $active_job->progress,
            'total_items' => (int) $active_job->total_items,
            'processed_items' => (int) $active_job->processed_items,
            /* translators: %1$d: number of processed items, %2$d: total number of items */
            'status' => sprintf(__('Processing %1$d of %2$d items.', 'cerebroly'), $active_job->processed_items, $active_job->total_items),

            'completed' => $active_job->status === 'completed'
        );

        wp_send_json_success($response);
        exit;
    }

    /**
     * AJAX function to get RAG content statistics
     */
    public function ajax_get_rag_content_stats()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cerebroly'));
        }

        // Check nonce
        check_ajax_referer('cerebroly_rag_nonce', 'security');

        global $wpdb;
        $embedding_table = $wpdb->prefix . 'cerebroly_embeddings';

        // Check that table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $embedding_table)) !== $embedding_table) {
            wp_send_json_error(array('message' => __('RAG system is not initialized', 'cerebroly')));
        }

        // Filters
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'all';
        $search_query = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';

        // Count total using direct prepare() per filter combination
        $like = !empty($search_query) ? '%' . $wpdb->esc_like($search_query) . '%' : null;

        if ($content_type !== 'all' && $like !== null) {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE content_type = %s AND (content_title LIKE %s OR content_chunk LIKE %s)",
                $embedding_table, $content_type, $like, $like
            ));
        } elseif ($content_type !== 'all') {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE content_type = %s",
                $embedding_table, $content_type
            ));
        } elseif ($like !== null) {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE (content_title LIKE %s OR content_chunk LIKE %s)",
                $embedding_table, $like, $like
            ));
        } else {
            $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $embedding_table));
        }

        // Count by type
        $post_count     = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE content_type = %s", $embedding_table, 'post'));
        $file_count     = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE content_type = %s", $embedding_table, 'file'));
        $training_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE content_type = %s", $embedding_table, 'training'));

        wp_send_json_success(array(
            'total'          => $total,
            'post_count'     => $post_count,
            'file_count'     => $file_count,
            'training_count' => $training_count,
        ));
    }

    /**
     * AJAX function to get RAG content preview
     */
    public function ajax_get_rag_content_preview()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cerebroly'));
        }

        // Check nonce
        check_ajax_referer('cerebroly_rag_nonce', 'security');

        global $wpdb;
        $embedding_table = $wpdb->prefix . 'cerebroly_embeddings';

        // Check that table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $embedding_table)) !== $embedding_table) {
            wp_send_json_error(array('message' => __('RAG system is not initialized', 'cerebroly')));
        }

        // Parameters
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : 'all';
        $search_query = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';
        $page         = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page     = 10;
        $offset       = ($page - 1) * $per_page;

        // Build query using direct prepare() per filter combination
        $like = !empty($search_query) ? '%' . $wpdb->esc_like($search_query) . '%' : null;

        if ($content_type !== 'all' && $like !== null) {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE content_type = %s AND (content_title LIKE %s OR content_chunk LIKE %s)",
                $embedding_table, $content_type, $like, $like
            ));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT id, content_id, content_type, content_title, content_chunk, created FROM %i WHERE content_type = %s AND (content_title LIKE %s OR content_chunk LIKE %s) ORDER BY created DESC LIMIT %d OFFSET %d",
                $embedding_table, $content_type, $like, $like, $per_page, $offset
            ));
        } elseif ($content_type !== 'all') {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE content_type = %s",
                $embedding_table, $content_type
            ));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT id, content_id, content_type, content_title, content_chunk, created FROM %i WHERE content_type = %s ORDER BY created DESC LIMIT %d OFFSET %d",
                $embedding_table, $content_type, $per_page, $offset
            ));
        } elseif ($like !== null) {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE (content_title LIKE %s OR content_chunk LIKE %s)",
                $embedding_table, $like, $like
            ));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT id, content_id, content_type, content_title, content_chunk, created FROM %i WHERE (content_title LIKE %s OR content_chunk LIKE %s) ORDER BY created DESC LIMIT %d OFFSET %d",
                $embedding_table, $like, $like, $per_page, $offset
            ));
        } else {
            $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $embedding_table));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT id, content_id, content_type, content_title, content_chunk, created FROM %i ORDER BY created DESC LIMIT %d OFFSET %d",
                $embedding_table, $per_page, $offset
            ));
        }

        // Format results
        $formatted_items = array();
        foreach ($items as $item) {
            // Determine type label
            $type_label = __('Unknown', 'cerebroly');
            switch ($item->content_type) {
                case 'post':
                    $type_label = __('Post/Page', 'cerebroly');
                    break;
                case 'file':
                    $type_label = __('File', 'cerebroly');
                    break;
                case 'training':
                    $type_label = __('Training', 'cerebroly');
                    break;
            }

            // Format content for display (escape HTML)
            $content = esc_html($item->content_chunk);
            $content = nl2br($content);

            // Highlight search term if exists
            if (!empty($search_query)) {
                $escaped_search_query = esc_html($search_query);
                $content = preg_replace('/(' . preg_quote($escaped_search_query, '/') . ')/i', '<mark>$1</mark>', $content);
            }

            $formatted_items[] = array(
                'id' => $item->id,
                'content_id' => $item->content_id,
                'type' => $item->content_type,
                'type_label' => $type_label,
                'title' => esc_html($item->content_title),
                'content' => $content,
                'created' => $item->created
            );
        }

        wp_send_json_success(array(
            'items' => $formatted_items,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }
}