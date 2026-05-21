<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * @package cerebroly
 */
class CEREBROLY_REST_API {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'handle_preflight_requests'));
    }

    public function register_routes() {
        register_rest_route('cerebroly/v1', '/chat', array(
            'methods'             => 'POST, OPTIONS',
            'callback'            => array($this, 'handle_chat'),
            'permission_callback' => array($this, 'chat_permission_check'),
        ));

        register_rest_route('cerebroly/v1', '/chat-embed', array(
            'methods'             => 'GET, OPTIONS',
            'callback'            => array($this, 'handle_chat_embed'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('cerebroly/v1', '/chat-iframe', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_chat_iframe'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'site' => array(
                    'required'          => false,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_url',
                ),
            ),
        ));

        register_rest_route('cerebroly/v1', '/training/start', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_start_training'),
            'permission_callback' => array($this, 'admin_permissions_check'),
        ));

        register_rest_route('cerebroly/v1', '/training/status', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_training_status'),
            'permission_callback' => array($this, 'admin_permissions_check'),
        ));

        register_rest_route('cerebroly/v1', '/files/upload', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_file_upload'),
            'permission_callback' => array($this, 'admin_permissions_check'),
        ));

        register_rest_route('cerebroly/v1', '/files', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_get_files'),
            'permission_callback' => array($this, 'admin_permissions_check'),
        ));

        register_rest_route('cerebroly/v1', '/files/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array($this, 'handle_delete_file'),
            'permission_callback' => array($this, 'admin_permissions_check'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        // OpenAI posts status updates here after a fine-tuning job completes.
        register_rest_route('cerebroly/v1', '/webhook/training', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_training_webhook'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('cerebroly/v1', '/verify-domain', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_verify_domain'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'origin'   => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_url',
                ),
                'referrer' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_url',
                ),
            ),
        ));

        register_rest_route('cerebroly/v1', '/appearance-config', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_appearance_config'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_preflight_requests() {
        if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            $this->add_cors_headers();
            header('Content-Length: 0');
            header('Content-Type: text/plain');
            exit(0);
        }
    }

    public function admin_permissions_check() {
        return current_user_can('manage_options');
    }

    public function chat_permission_check() {
        if (is_user_logged_in()) {
            return true;
        }

        $origin  = isset($_SERVER['HTTP_ORIGIN'])  ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN']))  : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

        // Same-origin requests from the shortcode won't send an Origin header.
        if (empty($origin)) {
            return true;
        }

        // Allow the site's own domain (e.g., shortcode used on a public page).
        $parsed_origin = wp_parse_url($origin);
        $parsed_site   = wp_parse_url(get_site_url());

        if (
            isset($parsed_origin['host'], $parsed_site['host']) &&
            $parsed_origin['host'] === $parsed_site['host']
        ) {
            return true;
        }

        // External embeds: must be in the allowed-domains list.
        $allowed_domains = get_option('cerebroly_allowed_domains', array());

        foreach ($allowed_domains as $domain) {
            if (!empty($domain) && $origin === rtrim($domain, '/')) {
                return true;
            }
        }

        foreach ($allowed_domains as $domain) {
            if (!empty($domain) && strpos($referer, rtrim($domain, '/')) === 0) {
                return true;
            }
        }

        return false;
    }
    
    public function handle_chat($request) {
        if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            $this->add_cors_headers();
            exit(0);
        }

        $this->add_cors_headers();

        $body    = $request->get_json_params();
        $message = '';

        if (isset($body['messages']) && is_array($body['messages'])) {
            foreach (array_reverse($body['messages']) as $msg) {
                if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                    $message = $msg['content'];
                    break;
                }
            }
        } elseif (isset($body['message'])) {
            $message = $body['message'];
        }

        if (empty($message)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Incorrect or empty message format',
            ), 400);
        }

        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000);
        }

        $rag_enabled = get_option('cerebroly_use_rag', false);

        if ($rag_enabled) {
            if (!class_exists('CEREBROLY_RAG_Manager')) {
                require_once CEREBROLY_PLUGIN_DIR . 'includes/RagManager.php';
            }

            $rag_manager = new CEREBROLY_RAG_Manager();
            $rag_result  = $rag_manager->generate_rag_response($message);

            if (isset($rag_result['success']) && !$rag_result['success']) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error'   => $rag_result['message'] ?? 'RAG processing failed',
                ), 400);
            }

            $response = $rag_result['response'] ?? $rag_result;

            if (is_wp_error($response)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error'   => $response->get_error_message(),
                ), 400);
            }

            return new WP_REST_Response(array(
                'choices' => array(
                    array('message' => array('content' => $response)),
                ),
            ), 200);
        }

        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');
        $model      = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT model_id, sources, status FROM `{$table_name}` WHERE status = %s ORDER BY updated DESC LIMIT 1",
                'active'
            )
        );

        if (!$model) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'No active model available.',
            ), 400);
        }

        $sources         = maybe_unserialize($model->sources);
        $fine_tuned_model = '';

        if (is_array($sources)) {
            if (isset($sources['fine_tuned_model'])) {
                $fine_tuned_model = $sources['fine_tuned_model'];
            } elseif (isset($sources['result']['fine_tuned_model'])) {
                $fine_tuned_model = $sources['result']['fine_tuned_model'];
            }
        }

        if (empty($fine_tuned_model)) {
            $fine_tuned_model = $model->model_id;
        }

        $openai_api = new CEREBROLY_OpenAI_API();
        $response   = $openai_api->chat($message, $fine_tuned_model);

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => $response->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'choices' => array(
                array('message' => array('content' => $response)),
            ),
        ), 200);
    }
    
    public function handle_start_training($request) {
        $content_extractor = new CEREBROLY_Content_Extractor();
        $training_content  = $content_extractor->prepare_for_training();

        $openai_api = new CEREBROLY_OpenAI_API();
        $result     = $openai_api->create_fine_tuning($training_content);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => $result->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success'  => true,
            'model_id' => $result['model_id'],
            'status'   => $result['status'],
            'message'  => $result['message'],
        ), 200);
    }

    public function handle_training_status($request) {
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');

        $models = $wpdb->get_results(
            "SELECT * FROM `{$table_name}` ORDER BY updated DESC LIMIT 5",
            ARRAY_A
        );

        foreach ($models as &$model) {
            $model['sources'] = maybe_unserialize($model['sources']);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'models'  => $models,
        ), 200);
    }

    public function handle_file_upload($request) {
        $files = $request->get_file_params();

        if (empty($files) || !isset($files['file'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'No file provided.',
            ), 400);
        }

        $file_handler = new CEREBROLY_File_Handler();
        $result       = $file_handler->upload_file($files['file']);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => $result->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'file'    => $result,
        ), 200);
    }

    public function handle_get_files($request) {
        $file_handler = new CEREBROLY_File_Handler();
        $files        = $file_handler->get_files();

        return new WP_REST_Response(array(
            'success' => true,
            'files'   => $files,
        ), 200);
    }

    public function handle_delete_file($request) {
        $file_id      = $request->get_param('id');
        $file_handler = new CEREBROLY_File_Handler();
        $result       = $file_handler->delete_file($file_id);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => $result->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'File deleted successfully.',
        ), 200);
    }

    public function handle_training_webhook($request) {
        $body = $request->get_body();
        $event = json_decode($body, true);
        
      
        // Validate webhook event format
        if (!isset($event['object']) || $event['object'] !== 'fine_tuning.job') {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Unsupported event type',
            ), 400);
        }
        
        $model_id = $event['id'];
        $status = $event['status'];
        
        // Update model status in local database
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_models');
        
        // Handle successful training completion
        if ($status === 'succeeded') {
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'active',
                    'updated' => current_time('mysql'),
                    'sources' => maybe_serialize($event)
                ),
                array('model_id' => $model_id),
                array('%s', '%s', '%s'),
                array('%s')
            );
        } 
        // Handle training failure or cancellation
        elseif ($status === 'failed' || $status === 'cancelled') {
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'updated' => current_time('mysql'),
                    'sources' => maybe_serialize($event)
                ),
                array('model_id' => $model_id),
                array('%s', '%s', '%s'),
                array('%s')
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Event processed successfully',
        ), 200);
    }
    
    public function handle_chat_embed($request) {
        if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            $this->add_cors_headers();
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            exit(0);
        }

        $site_url = get_site_url();

        $cors_added = $this->add_cors_headers();

        if (!$cors_added) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        
        $debug_param = (defined('CEREBROLY_DEBUG') && CEREBROLY_DEBUG) ? '&debug=true' : '';
        $js_content  = sprintf(
            '(function() {
    const iframe = document.createElement("iframe");
    iframe.src = "%s/wp-content/plugins/cerebroly/assets/chat-iframe.html?site=%s%s";
    iframe.width = "450";
    iframe.height = "650";
    iframe.frameBorder = "0";
    iframe.style.border = "0px solid #ddd";
    iframe.style.borderRadius = "0px";
    iframe.style.position = "fixed";
    iframe.style.bottom = "10px";
    iframe.style.right = "10px";
    iframe.style.zIndex = "9999";
    iframe.style.padding = "0px";
    document.body.appendChild(iframe);
})();',
            $site_url,
            rawurlencode($site_url),
            $debug_param
        );

        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $js_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JS file served with Content-Type: application/javascript
        exit;
    }

    public function handle_chat_iframe($request) {
        
        $site_url = $request->get_param('site') ?: get_site_url();
        
        // Generate complete HTML page for iframe content
        $html_content = sprintf('<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cerebroly Chat</title>
    <style>
        /* Reset and base styles */
        body {
            margin: 0;
            padding: 10px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Chat container layout */
        chat-container {
            display: block;
            width: 100%%;
            height: calc(100vh - 20px);
            position: relative;
        }
        
        /* Messages area styling */
        .container-messages {
            height: calc(100%% - 60px);
            overflow-y: auto;
            padding: 10px;
        }
        
        chat-messages {
            display: block;
        }
        
        /* Input area positioning */
        chat-input {
            display: block;
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            height: 40px;
        }
    </style>
</head>
<body>
    <div id="cerebroly-chat">
        <!-- Chat interface generated by cerebroly plugin https://cerebroly.com/ -->
        <chat-container>
            <div class="container-messages">
                <chat-messages id="chat-messages"></chat-messages>
                <div id="status-indicator"></div>
            </div>
            <chat-input id="chat-input" 
                       endpoint="%s/wp-json/cerebroly/v1/chat" 
                       placeholder="Type your question...">
            </chat-input>
            <div class="disclaimer"></div>
        </chat-container>
    </div>
    
    <script>
    // Configure chat widget with site-specific settings (mirrors wp_localize_script behavior)
    window.cerebrolData = {
        apiUrl: "%s/wp-json/cerebroly/v1/chat",
        nonce: "",
        placeholder: "Type your question...",
        buttonText: "Send",
        useRag: %s,
        welcomeMessages: %s,
        customIcon: "",
        customIconUrl: ""
    };
    
    console.log("cerebroly Chat iframe initialized for:", "%s");
    </script>
</body>
</html>',
            $site_url,
            $site_url,
            get_option('cerebroly_use_rag', 0) ? 'true' : 'false',
            json_encode(get_option('cerebroly_welcome_messages', [])),
            $site_url
        );
        
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        // $html_content is built from controlled values only — esc_html would break the HTML output.
        echo $html_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function handle_verify_domain($request) {
        $origin   = $request->get_param('origin');
        $referrer = $request->get_param('referrer');

        $allowed_domains = get_option('cerebroly_allowed_domains', array());

        if (empty($allowed_domains)) {
            return new WP_REST_Response(array(
                'success' => false,
                'allowed' => false,
                'message' => 'No domains are configured for external access',
            ), 200);
        }

        $is_allowed = false;

        foreach ($allowed_domains as $domain) {
            if (!empty($domain) && rtrim($origin, '/') === rtrim($domain, '/')) {
                $is_allowed = true;
                break;
            }
        }

        return new WP_REST_Response(array(
            'success'         => true,
            'allowed'         => $is_allowed,
            'origin'          => $origin,
            'allowed_domains' => $allowed_domains,
            'message'         => $is_allowed ? 'Domain is allowed' : 'Domain is not in the allowed list',
        ), 200);
    }

    public function handle_appearance_config($request) {
        if (class_exists('CEREBROLY_Appearance_Manager')) {
            $appearance_manager = CEREBROLY_Appearance_Manager::get_instance();
            $config             = $appearance_manager->get_config();

            $custom_icon_url = $config['custom_icon_url'] ?? '';
            if (!empty($custom_icon_url) && !filter_var($custom_icon_url, FILTER_VALIDATE_URL)) {
                $custom_icon_url = get_site_url() . '/' . ltrim($custom_icon_url, '/');
            }

            return new WP_REST_Response(array(
                'success'        => true,
                'theme'          => $config['selected_theme'] ?? 'cerebroly-theme',
                'welcomeMessages' => $config['welcome_messages'] ?? array(),
                'customIcon'     => $config['custom_icon'] ?? '',
                'customIconUrl'  => $custom_icon_url,
                'errorMessage'   => $config['error_message'] ?? __("I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.", 'cerebroly'),
                'config'         => $config,
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success'        => true,
                'theme'          => 'cerebroly-theme',
                'welcomeMessages' => array(),
                'customIcon'     => '',
                'customIconUrl'  => '',
                'errorMessage'   => __("I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.", 'cerebroly'),
            ), 200);
        }
    }

    private function add_cors_headers() {
        $allowed_domains = get_option('cerebroly_allowed_domains', array());

        $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
        if (empty($origin)) {
            $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
            if (!empty($referer)) {
                $parsed = wp_parse_url($referer);
                if ($parsed && isset($parsed['scheme'], $parsed['host'])) {
                    $origin = $parsed['scheme'] . '://' . $parsed['host'];
                    if (isset($parsed['port']) && 80 !== (int) $parsed['port'] && 443 !== (int) $parsed['port']) {
                        $origin .= ':' . $parsed['port'];
                    }
                }
            }
        }

        $is_allowed = false;
        if (!empty($origin)) {
            foreach ($allowed_domains as $domain) {
                if (!empty($domain) && $origin === rtrim($domain, '/')) {
                    $is_allowed = true;
                    break;
                }
            }
        }

        if ($is_allowed && get_option('cerebroly_enable_cors', 0)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            return true;
        }

        return false;
    }

}
