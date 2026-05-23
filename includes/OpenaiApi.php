<?php
 // Prevent direct access
 if (!defined('ABSPATH')) {
    exit;
}
class CEREBROLY_OpenAI_API
{
    /**
     * @var string
     */
    private $api_key;

    /**
     * OpenAI API base URL.
     *
     * @var string
     */
    private $api_url = 'https://api.openai.com/v1';

    /**
     * Base model for fine-tuning.
     *
     * @var string
     */
    private $base_model = 'gpt-3.5-turbo';

    // Rate limiting enabled by default
    public $rate_limit_enabled; 

    /**
     * Constructor.
     */
    public function __construct()
    {
        // API key will be loaded lazily when needed
        $this->rate_limit_enabled = true;
    }

    /**
     * Get API key with lazy loading
     */
    private function get_api_key()
    {
        if ($this->api_key === null) {
            $this->api_key = cerebroly_get_openai_api_key();
        }
        return $this->api_key;
    }

    private function check_basic_rate_limit($operation = 'api')
{
    if (!$this->rate_limit_enabled) {
        return true;
    }
    
    $key = 'cerebroly_rate_' . $operation . '_' . floor(time() / 60); // Por minuto
    $count = get_transient($key) ?: 0;
    $limit = get_option('cerebroly_rate_limit_per_minute', 50); // Default 50/minuto
    
    if ($count >= $limit) {
        return false;
    }
    
    set_transient($key, $count + 1, 60);
    return true;
}

    /**
     * Verify that the API key is valid.
     *
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function verify_api_key()
    {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Make a simple request to verify the API key
        $response = wp_remote_get(
            $this->api_url . '/models',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Unknown error when verifying API key.', 'cerebroly');

            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        return true;
    }

    /**
     * Generate embeddings for a text using the specified model.
     *
     * @param string $text The text to process.
     * @param string $model_name The embedding model name (e.g. 'text-embedding-ada-002').
     * @return array|WP_Error The embedding vector as array, or WP_Error on failure.
     */
    public function create_embedding($text, $model_name)
    {
        if (!$this->check_basic_rate_limit('embedding')) {
            return new WP_Error('rate_limit', __('Too many requests. Please wait a minute.', 'cerebroly'));
        }

        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }
        if (empty(trim($text))) {
            return new WP_Error('empty_input', __('Input text for embedding cannot be empty.', 'cerebroly'));
        }
        if (empty($model_name)) {
            // You could set a default value if you prefer
            return new WP_Error('no_model', __('Embedding model name must be specified.', 'cerebroly'));
        }

        $endpoint = $this->api_url . '/embeddings';
        $body_args = array(
            'input' => $text,
            'model' => $model_name,
            // Consider adding 'encoding_format' => 'float' if using v3+ models
            // 'encoding_format' => 'float',
            // Consider adding 'dimensions' if using v3 models and want specific size
            // 'dimensions' => 1536, // Example for Ada-002 or text-embedding-3-small default
        );
        $body = json_encode($body_args);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_encode_error', __('Error encoding request body: ', 'cerebroly') . json_last_error_msg());
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => $body,
            'timeout' => 60, // Embeddings can take time, give more time than 'models'
        ));

        // Error handling and response (similar to get_models)
        if (is_wp_error($response)) {
            // WordPress connection error (timeout, DNS, etc.)
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($status_code !== 200) {
            // Error returned by OpenAI API (invalid key, rate limit, etc.)
            $error_message = __('Unknown API error during embedding.', 'cerebroly');
            if (isset($response_data['error']['message'])) {
                $error_message = $response_data['error']['message'];
            } elseif (isset($response_data['message'])) {
                $error_message = $response_data['message']; // Sometimes the error comes here
            }
            return new WP_Error('api_error', $error_message, array('status' => $status_code, 'response' => $response_data));
        }

        // Extract embedding from successful result
        if (isset($response_data['data'][0]['embedding']) && is_array($response_data['data'][0]['embedding'])) {
            return $response_data['data'][0]['embedding']; // Success! Return the vector array
        } else {
            // Response structure was not as expected
            return new WP_Error('unexpected_response', __('Unexpected response structure from OpenAI API.', 'cerebroly'), array('response' => $response_data));
        }
    }

    /**
     * Upload training file to OpenAI.
     *
     * @param string $content_jsonl Content in JSONL format
     * @return array|WP_Error File information or error
     */
    public function upload_training_file($content_jsonl)
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Create temporary file with JSONL content
        $temp_file = wp_tempnam('cerebroly_training');
        file_put_contents($temp_file, $content_jsonl);

        // Prepare data for the request
        $boundary = wp_generate_password(24, false);
        $headers = array(
            'Authorization' => 'Bearer ' . $this->get_api_key(),
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );

        $payload = '';

        // Add purpose parameter
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="purpose"' . "\r\n\r\n";
        $payload .= 'fine-tune' . "\r\n";

        // Add file
        $payload .= '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file"; filename="training_data.jsonl"' . "\r\n";
        $payload .= 'Content-Type: application/json' . "\r\n\r\n";
        $payload .= file_get_contents($temp_file) . "\r\n";
        $payload .= '--' . $boundary . '--';

        // Delete temporary file
        wp_delete_file($temp_file);


        // Send request to OpenAI to upload file
        $response = wp_remote_post(
            $this->api_url . '/files',
            array(
                'headers' => $headers,
                'body' => $payload,
                'timeout' => 60,
            )
        );


        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code !== 200 && $status_code !== 201) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : __('Unknown error uploading the file.', 'cerebroly');
            return new WP_Error('api_error', $error_message, array(
                'status' => $status_code,
                'response' => $body
            ));
        }

        return $response_data;
    }

    /**
     * Create a fine-tuning model.
     *
     * @param string $content Content for training
     * @return array|WP_Error Model information or error
     */
    public function create_fine_tuning($content)
    {

        if (!$this->check_basic_rate_limit('training')) {
            return new WP_Error('rate_limit', __('Training rate limit reached. Please wait.', 'cerebroly'));
        }
        
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        if (empty($content)) {
            return new WP_Error('no_content', __('No content available for training the model.', 'cerebroly'));
        }

        $model_base = get_option('cerebroly_finetuning_base_model', 'gpt-3.5-turbo');

        // Format the content as JSONL for OpenAI
        $jsonl_content = $content;

        // Verify there are enough examples
        $line_count = substr_count($jsonl_content, "\n") + 1;
        if ($line_count < 3) {
            // translators: %d is the number of examples found
            return new WP_Error('insufficient_examples', sprintf(__('At least 3 examples are needed for fine-tuning, only %d found', 'cerebroly'), $line_count));
        }

        // upload training file
        $file_upload = $this->upload_training_file($jsonl_content);

        if (is_wp_error($file_upload)) {
            return $file_upload;
        }

        $file_id = $file_upload['id'];

        // Create the fine-tuning job
        $data = array(
            'training_file' => $file_id,
            'model' => $model_base,
            'suffix' => 'wp-chat-' . substr(md5(site_url()), 0, 8)
        );


        // Send request to OpenAI to start fine-tuning
        $response = wp_remote_post(
            $this->api_url . '/fine_tuning/jobs',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($data),
                'timeout' => 60,
            )
        );


        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code !== 200 && $status_code !== 201) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : __('Unknown error creating the fine-tuning.', 'cerebroly');
            return new WP_Error('api_error', $error_message, array(
                'status' => $status_code,
                'response' => $body
            ));
        }

        // Save model information in the database
        $model_id = $response_data['id'];
        $sources = array(
            'posts' => get_option('cerebroly_extract_posts', 1) ? 'yes' : 'no',
            'media' => get_option('cerebroly_extract_media', 0) ? 'yes' : 'no',
            'files' => 'yes' // We always include uploaded files
        );

        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');

        $wpdb->insert(
            $table_name,
            array(
                'model_id' => $model_id,
                'status' => 'processing',
                'created' => current_time('mysql'),
                'updated' => current_time('mysql'),
                'sources' => maybe_serialize($sources)
            )
        );

        // Schedule status check
        wp_schedule_single_event(time() + 300, 'cerebroly_check_model_status', array($model_id));

        return array(
            'model_id' => $model_id,
            'status' => 'processing',
            'message' => __('Model training has started.', 'cerebroly'),
            'raw_response' => $body
        );
    }

    public function get_available_models()
    {
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
            )
        ));

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['data'])) {
            return [];
        }

        return $body['data']; // Model list (base + finetuned)
    }



    /**
     * Send a message to the trained model.
     *
     * @param string $message User message
     * @param string $fine_tuned_model ID of the fine-tuned model to use (optional)
     * @return string|WP_Error Model response or error
     */
    /**
     * Modified chat() function to use RAG
     * 
     * Add this modified function to the CEREBROLY_OpenAI_API class
     */
    public function chat($message, $fine_tuned_model = '')
    {

        if (!$this->check_basic_rate_limit('chat')) {
            return new WP_Error('rate_limit', __('Too many requests. Please wait a minute.', 'cerebroly'));
        }


        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Check if RAG is enabled and configured
        $use_rag = get_option('cerebroly_use_rag', false);
        $rag_initialized = false;

        global $wpdb;
        $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $embedding_table)) == $embedding_table) {
            $rag_initialized = true;
        }

        // If RAG is enabled and configured, use it for generation
        if ($use_rag && $rag_initialized) {
            $rag_manager = new CEREBROLY_RAG_Manager();
            $result = $rag_manager->generate_rag_response($message);

            if ($result['success']) {
                return $result['response'];
            }
        }

        // Use fine-tuned model as default method or fallback
        // If no model provided, try to use a default model
        if (empty($fine_tuned_model)) {
            global $wpdb;

            $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');
            
            $model = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT model_id, sources, status FROM {$table_name} WHERE status = %s ORDER BY updated DESC LIMIT 1",
                    'active'
                )
            );
            
            if ($model) {
                // Directly deserialize using maybe_unserialize
                $sources = maybe_unserialize($model->sources);

                // Extract the fine-tuned model directly from the serialized format
                $fine_tuned_model = $sources['fine_tuned_model'] ?? 'gpt-3.5-turbo';

            } else {
                // Fallback
                $fine_tuned_model = 'gpt-3.5-turbo';
            }
        }

        // Get configuration for generation
        $model = $fine_tuned_model; // Keep the fine-tuned model 
        $temperature = floatval(get_option('cerebroly_rag_temperature', 0.3)); // Use configured value
        $max_tokens = intval(get_option('cerebroly_rag_max_tokens', 200)); // Use configured value
        $system_message = get_option('cerebroly_rag_system_prompt', __('You are a helpful assistant, you can only answer based on the content you were trained on. If the question cant be answered with that content, it indicates that you dont have enough information. Format your responses with HTML tags like <p>, <ul>, <li>, <strong>, and <br> for better readability. Keep responses concise and to the point.', 'cerebroly'));

        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_message
                ),
                array(
                    'role' => 'user',
                    'content' => $message
                )
            ),
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'top_p' => 0,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );

        // Send request to OpenAI
        $response = wp_remote_post(
            $this->api_url . '/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($data),
                'timeout' => 30,
            )
        );

        // Check for request errors
        if (is_wp_error($response)) {
            return $response;
        }

        // Process response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);


        $response_data = json_decode($body, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message'])
                ? $response_data['error']['message']
                : __('Error getting response from model.', 'cerebroly');

            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        // Extract response
        if (isset($response_data['choices'][0]['message']['content'])) {
            $response_text = $response_data['choices'][0]['message']['content'];
            return $response_text;
        }

        // Handle unknown response
        return new WP_Error('unknown_response', __('Unknown response from OpenAI.', 'cerebroly'));
    }

    /**
     * Get only the completion response from OpenAI.
     * 
     * @param string $prompt Text to complete
     * @return string|WP_Error OpenAI response or error
     */
    public function get_completion_only($prompt)
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Prepare data for the request
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => __('You are an expert assistant specialized in improving training datasets for chatbots. Your goal is to generate high-quality, diverse, and relevant training examples that cover a wide range of potential user queries and scenarios.', 'cerebroly')
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 800,
            'top_p' => 1.0
        );

        // Send request to OpenAI
        $response = wp_remote_post(
            $this->api_url . '/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($data),
                'timeout' => 30,
            )
        );

        // Check for errors in the request
        if (is_wp_error($response)) {
            return $response;
        }

        // Process response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message'])
                ? $response_data['error']['message']
                : __('Error getting model response.', 'cerebroly');

            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        // Extract response
        if (isset($response_data['choices'][0]['message']['content'])) {
            return $response_data['choices'][0]['message']['content'];
        }

        // Handle unknown response
        return new WP_Error('unknown_response', __('Unknown response from OpenAI.', 'cerebroly'));
    }

    /**
     * Generate question-answer pairs from a prompt
     * 
     * @param string $prompt The prompt to generate QA pairs
     * @return string|WP_Error JSON string with pairs or error
     */
    public function generate_qa_pairs($prompt)
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Prepare data for the request
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => __('You are an AI training data specialist with expertise in natural language processing and conversational AI. Your task is to analyze the provided content thoroughly and generate high-quality question-answer pairs that:

1. Cover the full spectrum of information presented in the content
2. Include both straightforward factual questions and more complex inferential questions
3. Represent different question types (what, how, why, when, where, who, etc.)
4. Anticipate real user queries by incorporating common phrasing variations
5. Include edge cases and less obvious but relevant questions users might ask
6. Prioritize specificity and contextual relevance over generic questions
7. Ensure answers are accurate, comprehensive, and directly extracted from the source material
8. Format output exclusively as valid JSON with no surrounding text

Return the result as an array of JSON objects with "question" and "answer" fields only, like:
[{"question": "detailed question 1", "answer": "comprehensive answer 1"}, {...}]', 'cerebroly')
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 1500,
            'top_p' => 1.0
        );


        // Send request to OpenAI
        $response = wp_remote_post(
            $this->api_url . '/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($data),
                'timeout' => 30,
            )
        );

        // Check for errors in the request
        if (is_wp_error($response)) {
            return $response;
        }

        // Process response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $response_data = json_decode($body, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message'])
                ? $response_data['error']['message']
                : __('Error getting model response.', 'cerebroly');

            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        // Extract response
        if (isset($response_data['choices'][0]['message']['content'])) {
            $qa_content = $response_data['choices'][0]['message']['content'];
            return $qa_content;
        }

        // Handle unknown response
        return new WP_Error('unknown_response', __('Unknown response from OpenAI.', 'cerebroly'));
    }

    /**
     * Format data for OpenAI training.
     * 
     * @param string $content
     * @return string JSONL formatted for training
     */
    private function format_training_data($content)
    {
        // Split content by 'Document End' separator
        $documents = explode('Document End', $content);
        $formatted_data = [];

        foreach ($documents as $doc) {
            $doc = trim($doc);
            if (empty($doc))
                continue;

            // Extract title and content
            $title_pattern = '/Document Title:\s*(.*?)(?:\n|$)/';
            $content_pattern = '/Document Content:(.*?)(?:\n\n|$)/s';

            preg_match($title_pattern, $doc, $title_matches);
            preg_match($content_pattern, $doc, $content_matches);

            $title = isset($title_matches[1]) ? trim($title_matches[1]) : '';
            $doc_content = isset($content_matches[1]) ? trim($content_matches[1]) : '';

            if (empty($title) || empty($doc_content))
                continue;

            // Extract additional details from content
            $content_lines = explode("\n", $doc_content);
            $main_content = '';

            foreach ($content_lines as $line) {
                if (strpos($line, 'Content:') !== false) {
                    $main_content = trim(str_replace('Content:', '', $line));
                    break;
                }
            }

            if (empty($main_content)) {
                $main_content = $doc_content;
            }

            // Create training examples (question and answer)
            $example = array(
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => "What is $title?"
                    ),
                    array(
                        'role' => 'assistant',
                        'content' => $main_content
                    )
                )
            );

            $formatted_data[] = json_encode($example, JSON_UNESCAPED_UNICODE);

            // Create additional examples if possible
            if (strlen($main_content) > 30) {
                // Example 2: Question about details
                $example2 = array(
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => "Tell me more information about $title"
                        ),
                        array(
                            'role' => 'assistant',
                            'content' => $main_content
                        )
                    )
                );
                $formatted_data[] = json_encode($example2, JSON_UNESCAPED_UNICODE);
            }
        }

        // Ensure there are enough examples
        while (count($formatted_data) < 3 && !empty($formatted_data)) {
            $formatted_data[] = $formatted_data[0];
        }

        return implode("\n", $formatted_data);
    }

    /**
     * Check the status of a model.
     *
     * @param string $model_id Model ID
     * @return array|WP_Error Model status or error
     */
    public function check_model_status($model_id)
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Request status from OpenAI
        $response = wp_remote_get(
            $this->api_url . '/fine_tuning/jobs/' . $model_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : __('Error checking model status.', 'cerebroly');
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        // Update status in the database
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');

        $openai_status = $response_data['status'];
        $model_status = 'processing';
        $fine_tuned_model = isset($response_data['fine_tuned_model']) ? $response_data['fine_tuned_model'] : '';

        if ($openai_status === 'success') {
            $model_status = 'active';

            // Save the fine-tuned model ID in metadata
            $meta = maybe_serialize(array(
                'fine_tuned_model' => $fine_tuned_model,
                'result' => $response_data
            ));

            $wpdb->update(
                $table_name,
                array(
                    'status' => $model_status,
                    'updated' => current_time('mysql'),
                    'sources' => $meta
                ),
                array('model_id' => $model_id),
                array('%s', '%s', '%s'),
                array('%s')
            );
        } elseif ($openai_status === 'failed' || $openai_status === 'cancelled') {
            $model_status = 'failed';

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
        } elseif ($openai_status === 'pending' || $openai_status === 'running') {
            // Reschedule verification
            wp_schedule_single_event(time() + 300, 'cerebroly_check_model_status', array($model_id));

            $wpdb->update(
                $table_name,
                array(
                    'updated' => current_time('mysql')
                ),
                array('model_id' => $model_id),
                array('%s'),
                array('%s')
            );
        }

        return array(
            'model_id' => $model_id,
            'status' => $model_status,
            'openai_status' => $openai_status,
            'fine_tuned_model' => $fine_tuned_model,
            'updated' => current_time('mysql')
        );
    }

    /**
     * Get the user's models from OpenAI.
     *
     * @return array|WP_Error List of models or error
     */
    public function get_user_models()
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Request fine-tunes from OpenAI
        $response = wp_remote_get(
            $this->api_url . '/fine_tuning/jobs',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->get_api_key(),
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : __('Error getting models.', 'cerebroly');
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }

        // Process and return results
        $models = isset($response_data['data']) ? $response_data['data'] : array();

        // Convert to common format for the interface
        $formatted_models = array();
        foreach ($models as $model) {
            $formatted_models[] = array(
                'id' => $model['id'],
                'name' => isset($model['fine_tuned_model']) ? $model['fine_tuned_model'] : __('Model in training', 'cerebroly'),
                'version' => $model['model'],
                // translators: %s is the current status of the model (e.g., 'completed', 'training', 'failed')
                'description' => sprintf(__('Status: %s', 'cerebroly'), $model['status']),
                'status' => $model['status']
            );
}

        return $formatted_models;
    }

    public function get_models_for_select()
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        $models = [];

        // Fine-tunable base models from API
        $finetune_whitelist = [
            'gpt-4o-2024-08-06',
            'gpt-4o-mini-2024-07-18',
            'gpt-4-0613',
            'gpt-3.5-turbo-0125',
            'gpt-3.5-turbo-1106',
            'gpt-3.5-turbo-0613',
        ];

        $base_response = wp_remote_get($this->api_url . '/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $base = [];
        if (!is_wp_error($base_response)) {
            $base_data = json_decode(wp_remote_retrieve_body($base_response), true);
            if (!empty($base_data['data'])) {
                $matched = array_filter($base_data['data'], function ($m) use ($finetune_whitelist) {
                    return in_array($m['id'], $finetune_whitelist, true);
                });
                usort($matched, function ($a, $b) {
                    return $b['created'] - $a['created'];
                });
                foreach (array_slice($matched, 0, 5) as $m) {
                    $base[] = ['id' => $m['id']];
                }
            }
        }
        if (empty($base)) {
            $base = [
                ['id' => 'gpt-4o-mini-2024-07-18'],
                ['id' => 'gpt-3.5-turbo-0125'],
            ];
        }
        $models['base'] = $base;

        // Last 5 completed fine-tuned models
        $response = wp_remote_get($this->api_url . '/fine_tuning/jobs?limit=100', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $fine_tuned = [];

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['data'])) {
                usort($data['data'], function ($a, $b) {
                    return $b['created_at'] - $a['created_at'];
                });

                $completed = array_filter($data['data'], function ($m) {
                    return !empty($m['fine_tuned_model']);
                });

                foreach (array_slice($completed, 0, 10) as $model) {
                    $fine_tuned[] = [
                        'id'     => $model['fine_tuned_model'],
                        'status' => $model['status'],
                    ];
                }
            }
        }

        $models['fine_tuned'] = $fine_tuned;

        return $models;
    }

    public function get_finetuning_models_for_settings()
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key has not been configured.', 'cerebroly'));
        }

        // Fine-tunable models: fetch from /v1/models and filter by known fine-tunable IDs
        $finetune_whitelist = [
            'gpt-4o-2024-08-06'      => 'GPT-4o (2024-08-06)',
            'gpt-4o-mini-2024-07-18' => 'GPT-4o Mini (2024-07-18)',
            'gpt-4-0613'             => 'GPT-4 (0613)',
            'gpt-3.5-turbo-0125'     => 'GPT-3.5 Turbo (0125)',
            'gpt-3.5-turbo-1106'     => 'GPT-3.5 Turbo (1106)',
            'gpt-3.5-turbo-0613'     => 'GPT-3.5 Turbo (0613)',
        ];

        $models_response = wp_remote_get($this->api_url . '/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $base_models = [];

        if (!is_wp_error($models_response)) {
            $models_data = json_decode(wp_remote_retrieve_body($models_response), true);
            if (!empty($models_data['data'])) {
                // Keep only whitelisted fine-tunable models
                $matched = array_filter($models_data['data'], function ($m) use ($finetune_whitelist) {
                    return isset($finetune_whitelist[$m['id']]);
                });
                // Sort by created date descending
                usort($matched, function ($a, $b) {
                    return $b['created'] - $a['created'];
                });
                foreach (array_slice($matched, 0, 5) as $m) {
                    $base_models[] = [
                        'id'    => $m['id'],
                        'label' => $finetune_whitelist[$m['id']],
                    ];
                }
            }
        }

        // Fallback if API call failed or returned no matching models
        if (empty($base_models)) {
            $base_models = [
                ['id' => 'gpt-4o-mini-2024-07-18', 'label' => 'GPT-4o Mini (2024-07-18)'],
                ['id' => 'gpt-3.5-turbo-0125',     'label' => 'GPT-3.5 Turbo (0125)'],
            ];
        }

        // Get fine-tuned models (limit=100 to cover jobs with failed/running status)
        $response = wp_remote_get($this->api_url . '/fine_tuning/jobs?limit=100', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $fine_tuned = [];

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['data'])) {
                usort($data['data'], function ($a, $b) {
                    return $b['created_at'] - $a['created_at'];
                });

                $completed = array_filter($data['data'], function ($m) {
                    return !empty($m['fine_tuned_model']);
                });

                foreach (array_slice($completed, 0, 10) as $model) {
                    $fine_tuned[] = [
                        'id'    => $model['fine_tuned_model'],
                        'label' => $model['fine_tuned_model'] . ' (' . $model['status'] . ')',
                    ];
                }
            }
        }

        return [
            'base' => $base_models,
            'fine_tuned' => $fine_tuned,
        ];
    }

    public function get_fine_tuning_status($model_id)
    {
        if (empty($this->get_api_key())) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'cerebroly'));
        }

        $response = wp_remote_get($this->api_url . '/fine_tuning/jobs/' . $model_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['status'])) {
            return new WP_Error('no_status', __('No status found in API response.', 'cerebroly'));
        }

        return [
            'status' => $data['status'],
            'fine_tuned_model' => $data['fine_tuned_model'] ?? null,
        ];
    }
}