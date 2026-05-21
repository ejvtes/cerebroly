<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap">
    <h1>cerebroly - Dashboard</h1>
    <?php settings_errors(); ?>

    <?php
    // Display training details if available
    $training_details = get_transient('cerebroly_training_details');
    if ($training_details) {
        delete_transient('cerebroly_training_details'); // Remove so it doesn't appear again on reload
    
        if ($training_details['success']) {
            // Show success alert with details
            ?>
            <div class="notice notice-success is-dismissible">
                <h3><?php esc_html_e('Training successfully initiated', 'cerebroly'); ?></h3>
                <p><strong><?php esc_html_e('Model ID:', 'cerebroly'); ?></strong>
                    <?php echo esc_html($training_details['model_id']); ?></p>
                <p><strong><?php esc_html_e('Status:', 'cerebroly'); ?></strong>
                    <?php echo esc_html($training_details['status']); ?>
                </p>
                <hr>
                <h4><?php esc_html_e('Details of submitted content:', 'cerebroly'); ?></h4>
                <ul>
                    <li><strong><?php esc_html_e('Processed documents:', 'cerebroly'); ?></strong>
                        <?php echo intval($training_details['content_stats']['document_count']); ?></li>
                    <li><strong><?php esc_html_e('Total words:', 'cerebroly'); ?></strong>
                        <?php echo number_format($training_details['content_stats']['total_words']); ?></li>
                    <li><strong><?php esc_html_e('Content size:', 'cerebroly'); ?></strong>
                        <?php echo number_format($training_details['content_stats']['total_size'] / 1024, 2); ?> KB</li>
                </ul>
                <hr>
                <p><em><?php esc_html_e('OpenAI is processing your content. Training can take anywhere from 30 minutes to several hours depending on the amount of data and system load.', 'cerebroly'); ?></em>
                </p>
            </div>
            <?php
        } else {
            // Show error alert with details
            ?>
            <div class="notice notice-error is-dismissible">
                <h3><?php esc_html_e('Error starting training', 'cerebroly'); ?></h3>
                <p><strong><?php esc_html_e('Error:', 'cerebroly'); ?></strong>
                    <?php echo esc_html($training_details['error_message']); ?></p>
                <?php if (isset($training_details['error_data']) && !empty($training_details['error_data'])): ?>
                    <p><strong><?php esc_html_e('Details:', 'cerebroly'); ?></strong></p>
                    <pre style="background:#f8f8f8; padding:10px; overflow:auto; max-height:200px;">
                    <?php esc_html($training_details['error_data'], 'cerebroly'); ?></pre>
                    <?php endif; ?>
                <hr>
                <h4><?php esc_html_e('Information about the content we attempted to submit:', 'cerebroly'); ?></h4>
                <ul>
                    <li><strong><?php esc_html_e('Processed documents:', 'cerebroly'); ?></strong>
                        <?php echo intval($training_details['content_stats']['document_count']); ?></li>
                    <li><strong><?php esc_html_e('Total words:', 'cerebroly'); ?></strong>
                        <?php echo number_format($training_details['content_stats']['total_words']); ?></li>
                    <li><strong><?php esc_html_e('Content size:', 'cerebroly'); ?></strong>
                        <?php echo number_format($training_details['content_stats']['total_size'] / 1024, 2); ?> KB</li>
                </ul>
            </div>
            <?php
        }
    }
    ?>

    <div class="cerebroly-dashboard">




        <div class="cerebroly-status-card">

            <div class="metabox-holder">
                <div class="postbox-container" style="width: 100%;">

                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span><?php esc_html_e('System Status', 'cerebroly'); ?></span>
                            </h2>
                        </div>
                        <div class="inside">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Component', 'cerebroly'); ?></th>
                                        <th><?php esc_html_e('Status', 'cerebroly'); ?></th>
                                        <th><?php esc_html_e('Details', 'cerebroly'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php esc_html_e('Operation Mode', 'cerebroly'); ?></td>
                                        <td>
                                            <?php
                                            $rag_enabled = get_option('cerebroly_use_rag', false);
                                            if ($rag_enabled):
                                                ?>
                                                <span class="cerebroly-status-ok"><?php esc_html_e('RAG', 'cerebroly'); ?></span>
                                            <?php else: ?>
                                                <span
                                                    class="cerebroly-status-ok"><?php esc_html_e('Fine-tuning', 'cerebroly'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rag_enabled): ?>
                                                <?php esc_html_e('Using Retrieval-Augmented Generation for dynamic content retrieval', 'cerebroly'); ?>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-rag-config')); ?>"
                                                    class="button button-small"><?php esc_html_e('Configure RAG', 'cerebroly'); ?></a>
                                            <?php else: ?>
                                                <?php esc_html_e('Using Fine-tuning to create a custom model based on your content', 'cerebroly'); ?>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-fine-tuning')); ?>"
                                                    class="button button-small"><?php esc_html_e('Manage Fine-tuning', 'cerebroly'); ?></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('OpenAI API', 'cerebroly'); ?></td>
                                        <td>
                                            <?php if (is_wp_error($api_status)): ?>
                                                <span
                                                    class="cerebroly-status-error"><?php esc_html_e('Error', 'cerebroly'); ?></span>
                                            <?php else: ?>
                                                <span
                                                    class="cerebroly-status-ok"><?php esc_html_e('Connected', 'cerebroly'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (is_wp_error($api_status)): ?>
                                                <?php echo esc_html($api_status->get_error_message()); ?>
                                            <?php else: ?>
                                                <?php esc_html_e('API key is valid and working properly', 'cerebroly'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Post/Page Extraction', 'cerebroly'); ?></td>
                                        <td>
                                            <?php echo $extract_posts ? '<span class="cerebroly-status-ok">' . esc_html__('Enabled', 'cerebroly') . '</span>' : '<span class="cerebroly-status-inactive">' . esc_html__('Disabled', 'cerebroly') . '</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($extract_posts): ?>
                                                <?php esc_html_e('WordPress posts and pages will be included in training', 'cerebroly'); ?>
                                            <?php else: ?>
                                                <?php esc_html_e('WordPress posts and pages will NOT be included in training', 'cerebroly'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Media Library Extraction', 'cerebroly'); ?></td>
                                        <td>
                                            <?php echo $extract_media ? '<span class="cerebroly-status-ok">' . esc_html__('Enabled', 'cerebroly') . '</span>' : '<span class="cerebroly-status-inactive">' . esc_html__('Disabled', 'cerebroly') . '</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($extract_media): ?>
                                                <?php esc_html_e('PDFs and text files from Media Library will be included in training', 'cerebroly'); ?>
                                            <?php else: ?>
                                                <?php esc_html_e('Media Library files will NOT be included in training', 'cerebroly'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Uploaded Files', 'cerebroly'); ?></td>
                                        <td>
                                            <span
                                                class="cerebroly-status-info"><?php
                                                /* translators: %s: %d files */
                                                printf(esc_html__('%d files', 'cerebroly'), intval($file_count)); ?></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-files')); ?>"
                                                class="button button-small"><?php esc_html_e('Manage Files', 'cerebroly'); ?></a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>









            <?php

            // First, check if RAG is enabled
            $rag_enabled = get_option('cerebroly_use_rag', false);

            if ($active_model && !$rag_enabled): ?>

                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 100%;">

                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">
                                    <span><?php esc_html_e('System Fine-tuned', 'cerebroly'); ?></span>
                                </h2>
                            </div>
                            <div class="inside">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Property', 'cerebroly'); ?></th>
                                            <th><?php esc_html_e('Value', 'cerebroly'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong><?php esc_html_e('Model ID', 'cerebroly'); ?></strong></td>
                                            <td><?php echo esc_html($active_model->model_id); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Created', 'cerebroly'); ?></strong></td>
                                            <td><?php echo esc_html($active_model->created); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Updated', 'cerebroly'); ?></strong></td>
                                            <td><?php echo esc_html($active_model->updated); ?></td>
                                        </tr>
                                        <?php
                                        $sources = maybe_unserialize($active_model->sources);
                                        if (is_array($sources) && isset($sources['fine_tuned_model'])):
                                            ?>
                                            <tr>
                                                <td><strong><?php esc_html_e('Fine-tuned Model', 'cerebroly'); ?></strong></td>
                                                <td><?php echo esc_html($sources['fine_tuned_model']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>




                <?php
                global $wpdb, $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . '/wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $cache_dir    = CEREBROLY_PLUGIN_DIR . 'cache';
                $preview_file = $cache_dir . '/training-preview.json';

                $cache_exists   = $wp_filesystem->exists($preview_file);
                $total_examples = 0;
                $total_size     = 0;
                $total_words    = 0;
                $last_updated   = 0;

                if ($cache_exists) {
                    $training_content = $wp_filesystem->get_contents($preview_file);
                    $jsonl_lines = explode("\n", $training_content);

                    // Count examples
                    $total_examples = 0;
                    foreach ($jsonl_lines as $line) {
                        if (!empty(trim($line))) {
                            $total_examples++;
                        }
                    }

                    // Calculate size and words
                    $total_size = strlen($training_content);
                    $total_words = str_word_count($training_content);

                    // Get last update time
                    $last_updated = filemtime($preview_file);
                }
                ?>


                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 100%;">

                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">
                                    <span><?php esc_html_e('Content Statistics', 'cerebroly'); ?></span>
                                </h2>
                            </div>
                            <div class="inside">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Metric', 'cerebroly'); ?></th>
                                            <th><?php esc_html_e('Value', 'cerebroly'); ?></th>
                                            <th><?php esc_html_e('Details', 'cerebroly'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php esc_html_e('Training Examples', 'cerebroly'); ?></td>
                                            <td><?php echo number_format($total_examples); ?></td>
                                            <td><?php esc_html_e('Total question-answer pairs for training', 'cerebroly'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e('Content Size', 'cerebroly'); ?></td>
                                            <td><?php echo number_format($total_size / 1024, 2); ?> KB</td>
                                            <td><?php esc_html_e('Size of training data in kilobytes', 'cerebroly'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e('Total Words', 'cerebroly'); ?></td>
                                            <td><?php echo number_format($total_words); ?></td>
                                            <td><?php esc_html_e('Word count in all training examples', 'cerebroly'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><?php esc_html_e('Last Updated', 'cerebroly'); ?></td>
                                            <td>
                                                <?php
                                                if ($last_updated) {
                                                    echo esc_html(gmdate('Y-m-d H:i:s', $last_updated));
                                                } else {
                                                    echo esc_html__('Never', 'cerebroly');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-training-preview&regenerate=1')); ?>"
                                                    class="button button-small"><?php esc_html_e('Regenerate Preview', 'cerebroly'); ?></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>


            <?php endif; ?>
            <?php
            // First, check if RAG is enabled
            $rag_enabled = get_option('cerebroly_use_rag', false);

            if ($rag_enabled):
                // Display RAG information
                global $wpdb;
                $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');
                $embedding_count = $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($embedding_table) . "`");
                $last_update = $wpdb->get_var("SELECT MAX(updated) FROM `" . esc_sql($embedding_table) . "`");
                ?>

                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 100%;">

                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">
                                    <span><?php esc_html_e('System RAG', 'cerebroly'); ?></span>
                                </h2>
                            </div>
                            <div class="inside">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Property', 'cerebroly'); ?></th>
                                            <th><?php esc_html_e('Value', 'cerebroly'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong><?php esc_html_e('System Type', 'cerebroly'); ?></strong></td>
                                            <td><?php esc_html_e('Retrieval-Augmented Generation (RAG)', 'cerebroly'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Embedding Model', 'cerebroly'); ?></strong></td>
                                            <td><?php echo esc_html(get_option('cerebroly_rag_embedding_model', 'text-embedding-ada-002')); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('LLM Model', 'cerebroly'); ?></strong></td>
                                            <td><?php echo esc_html(get_option('cerebroly_rag_llm_model', 'gpt-3.5-turbo')); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php
                                            esc_html_e('Indexed Content', 'cerebroly'); ?></strong></td>
                                            <td>
                                                <?php
                                                /* translators: %s: Number of content chunks */
                                                printf(esc_html__('%s chunks', 'cerebroly'), number_format($embedding_count));
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Last Updated', 'cerebroly'); ?></strong></td>
                                            <td><?php echo $last_update ? esc_html(gmdate('Y-m-d H:i:s', strtotime($last_update))) : esc_html__('Never', 'cerebroly'); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>



                <div class="metabox-holder">
                    <div class="postbox-container" style="width: 100%;">

                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">
                                    <span><?php esc_html_e('Content Statistics', 'cerebroly'); ?></span>
                                </h2>
                            </div>
                            <div class="inside">
                                <?php
                                global $wpdb;
                                $embedding_table = esc_sql($wpdb->prefix . 'cerebroly_embeddings');

                                // Check if the table exists
                                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") == $embedding_table;

                                if (!$table_exists) {
                                    // System not initialized
                                    echo '<div class="cerebroly-status-error"><p>' . esc_html__('RAG System not installed. Click "Initialize RAG System" to begin.', 'cerebroly') . '</p></div>';
                                    echo '<button id="initialize-rag" class="button button-primary">' . esc_html__('Initialize RAG System', 'cerebroly') . '</button>';
                                } else {
                                    // Count embedded documents
                                    $embedded_count = $wpdb->get_var("SELECT COUNT(*) FROM $embedding_table");
                                    $last_update = $wpdb->get_var("SELECT MAX(created) FROM $embedding_table");

                                    // Display statistics
                                    ?>
                                    <table class="wp-list-table widefat fixed striped">
                                        <tr>
                                            <th><?php esc_html_e('Indexed Documents', 'cerebroly'); ?></th>
                                            <td><?php echo number_format($embedded_count); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php
                                            /* translators: %s: 'Last Updated */
                                            esc_html_e('Last Updated', 'cerebroly'); ?></th>
                                            <td>
                                                <?php
                                                if ($last_update) {
                                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_update)));
                                                } else {
                                                    esc_html_e('Never', 'cerebroly');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e('Status', 'cerebroly'); ?></th>
                                            <td><span class="cerebroly-status-ok"><?php esc_html_e('Active', 'cerebroly'); ?></span>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>




            <?php elseif ($active_model): ?>

            <?php else: ?>
                <div class="cerebroly-warning">
                    <p><?php esc_html_e('No active model or RAG system configured. Please train a model or enable the RAG system.', 'cerebroly'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="metabox-holder">
                <div class="postbox-container" style="width: 100%;">

                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span><?php esc_html_e('Chat Shortcode', 'cerebroly'); ?></span>
                            </h2>
                        </div>
                        <div class="inside">
                            <p>
                                <?php esc_html_e('Use this shortcode to insert the chat in any page or post.', 'cerebroly'); ?>
                            </p>
                           <p><code>[cerebroly_chat]</code></p>
                            
                        </div>
                    </div>
                </div>
            </div>

            <div class="metabox-holder">
                <div class="postbox-container" style="width: 100%;">

                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <?php esc_html_e('API Diagnostics', 'cerebroly'); ?>
                            </h2>
                        </div>
                        <div class="inside">
                            <p>
                                <?php esc_html_e('Run communication tests with OpenAI.', 'cerebroly'); ?>
                            </p>
                            <button
                                class="button test-api-button"><?php esc_html_e('Test API Connection', 'cerebroly'); ?></button>
                            <div class="api-test-result" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>





            <script>
                jQuery(document).ready(function ($) {
                    $('.test-api-button').on('click', function () {
                        var $button = $(this);
                        var $result = $('.api-test-result');

                        $button.prop('disabled', true).text('Testing...');
                        $result.html(
                            '<span style="color:#666;">Verifying connection to OpenAI...</span>'
                        );

                        // Perform a simple API test
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'cerebroly_simple_api_test',
                                security: '<?php echo esc_js(wp_create_nonce('cerebroly_api_test')); ?>'
                            },
                            success: function (response) {
                                if (response.success) {
                                    $result.html(
                                        '<div style="color:#46b450; padding:10px;">' +
                                        'Successful connection to the OpenAI API.<br>' +
                                        '<strong>Response:</strong> ' + response
                                            .data.message + '<br>' +
                                        (response.data.models ?
                                            '<strong>Available models:</strong> ' +
                                            response.data.models + '<br>' : '') +
                                        '</div>');
                                } else {
                                    $result.html(
                                        '<div style="color:#dc3232; padding:10px;">' +
                                        'Error: ' + response.data.error + '<br>' +
                                        (response.data.status ?
                                            '<strong>HTTP Status:</strong> ' +
                                            response.data.status + '<br>' : '') +
                                        (response.data.details ?
                                            '<strong>Details:</strong> ' + response
                                                .data.details : '') +
                                        '</div>');
                                }
                            },
                            error: function () {
                                $result.html(
                                    '<div style="color:#dc3232; padding:10px;">Connection error with the WordPress server.</div>'
                                );
                            },
                            complete: function () {
                                $button.prop('disabled', false).text(
                                    'Test API Connection');
                            }
                        });
                    });
                });
            </script>

            <div class="metabox-holder">
                <div class="postbox-container" style="width: 100%;">

                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span><?php esc_html_e('Cron Diagnostics', 'cerebroly'); ?></span>
                            </h2>
                        </div>
                        <div class="inside">
                            <p><?php esc_html_e('Check if WP-Cron is working. Click the button to trigger a test event.', 'cerebroly'); ?>
                            </p>
                            <button
                            class="button button-secondary js-test-cron"><?php esc_html_e('Run Cron Test', 'cerebroly'); ?></button>
                            <div style="margin-top: 20px; padding: 15px; background: #f8f8f8; border-radius: 4px;">
                               
                                <div class="cron-test-result" style="margin-top: 10px;"></div>
                                <div style="">
                                    <strong><?php esc_html_e('Last Cron Run:', 'cerebroly'); ?></strong>
                                    <?php
                                    $last_cron = get_option('cerebroly_last_cron_run');
                                    $last_message = get_option('cerebroly_last_cron_message');

                                    if ($last_cron) {
                                        echo '<p>' . esc_html($last_cron) . '</p>';
                                        if ($last_message) {
                                            echo '<p style="color:green;">' . esc_html($last_message) . '</p>';
                                        }
                                    } else {
                                        echo '<p>No test cron has run yet.</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>






            <script>
                jQuery(document).ready(function ($) {
                    $('.js-test-cron').on('click', function () {
                        var $btn = $(this);
                        var $result = $('.cron-test-result');
                        $btn.prop('disabled', true).text('Running...');
                        $result.text('Triggering cron test...');

                        $.post(ajaxurl, {
                            action: 'cerebroly_test_cron',
                            security: '<?php echo esc_js(wp_create_nonce("cerebroly_cron_test_nonce")); ?>'
                        }, function (response) {
                            if (response.success) {
                                $result.html('<span style="color:green;">' + response.data
                                    .message + '</span>');
                                setTimeout(function () {
                                    location.reload();
                                }, 7000); // Espera 7 segundos para dar tiempo al cron
                            } else {
                                $result.html('<span style="color:red;">' + response.data
                                    .message + '</span>');
                                $btn.prop('disabled', false).text('Run Cron Test');
                            }
                        }).fail(function () {
                            $result.html(
                                '<span style="color:red;">AJAX request failed.</span>');
                            $btn.prop('disabled', false).text('Run Cron Test');
                        });
                    });
                });
            </script>
        </div>

        <div class="cerebroly-model-card">

            <?php if (is_wp_error($api_status)): ?>
                <div class="cerebroly-model-none">
                    <p class="cerebroly-warning"><?php echo esc_html($api_status->get_error_message()); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-settings')); ?>"
                        class="button button-primary">
                        <?php esc_html_e('Configure API Key', 'cerebroly'); ?>
                    </a>
                </div>
            <?php else: ?>

                <?php if (!empty($user_models)): ?>

                    <div class="metabox-holder">
                        <div class="postbox-container" style="width: 100%;">

                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle">
                                        <span><?php esc_html_e('Available Fine-tuned Models', 'cerebroly'); ?></span>
                                    </h2>
                                </div>
                                <div class="inside" style="height: 800px; overflow: auto;">
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Name/ID', 'cerebroly'); ?></th>
                                                <th><?php esc_html_e('Base Model', 'cerebroly'); ?></th>
                                                <th><?php esc_html_e('Status', 'cerebroly'); ?></th>
                                                <th><?php esc_html_e('Actions', 'cerebroly'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_models as $model): ?>
                                                <?php if ($model['status'] === 'succeeded' || ($model['status'] === 'running') || ($model['status'] === 'queued')): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($model['name']); ?></td>
                                                        <td><?php echo esc_html($model['version']); ?></td>
                                                        <td><?php echo esc_html($model['status']); ?></td>
                                                        <td>
                                                            <?php if ($model['status'] === 'succeeded'): ?>
                                                                <form method="post"
                                                                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                                                    style="display:inline;">
                                                                    <?php wp_nonce_field('cerebroly_select_model'); ?>
                                                                    <input type="hidden" name="action" value="cerebroly_select_model">
                                                                    <input type="hidden" name="model_id"
                                                                        value="<?php echo esc_attr($model['id']); ?>">
                                                                    <button type="submit"
                                                                        class="button button-small"><?php esc_html_e('Select', 'cerebroly'); ?></button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button class="button button-small"
                                                                    disabled><?php esc_html_e('In progress', 'cerebroly'); ?></button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="metabox-holder">
                <div class="postbox-container" style="width: 100%;">

                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle">
                                <span><?php esc_html_e('Actions', 'cerebroly'); ?></span>
                            </h2>
                        </div>
                        <div class="inside">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('cerebroly_start_training'); ?>
                                <input type="hidden" name="action" value="cerebroly_start_training">

                                <p><?php esc_html_e('Start a new training with current WordPress content and uploaded files.', 'cerebroly'); ?>
                                </p>

                                <?php if (is_wp_error($api_status)): ?>
                                    <p class="cerebroly-warning">
                                        <?php esc_html_e('You must first configure the OpenAI API key.', 'cerebroly'); ?>
                                    </p>
                                    <button type="button" class="button"
                                        disabled><?php esc_html_e('Start Training', 'cerebroly'); ?></button>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-settings')); ?>"
                                        class="button button-primary"><?php esc_html_e('Configure API Key', 'cerebroly'); ?></a>
                                <?php else: ?>
                                    <button type="submit"
                                        class="button button-primary"><?php esc_html_e('Start Training', 'cerebroly'); ?></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

<style>
    .cerebroly-dashboard {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 0px;
        margin-right: 20px;
    }

    .cerebroly-status-card,
    .cerebroly-model-card {
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        margin-bottom: 20px;
        flex: 1;
        min-width: 300px;
    }

    .cerebroly-status-ok {
        color: #46b450;
        font-weight: bold;
    }

    .cerebroly-status-error {
        color: #dc3232;
        font-weight: bold;
    }

    .cerebroly-status-inactive {
        color: #999;
    }

    .cerebroly-status-info {
        color: #0073aa;
        font-weight: bold;
    }

    .cerebroly-warning {
        color: #dc3232;
        font-style: italic;
    }

    .cerebroly-shortcode-info {
        background: #f9f9f9;
        border-left: 4px solid #46b450;
        padding: 10px 15px;
        margin: 15px 0;
    }

    .cerebroly-training-actions {
        border-top: 1px solid #eee;
    }

    .cerebroly-help-columns {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
    }

    .cerebroly-help-column {
        flex: 1;
        min-width: 300px;
    }

    .cerebroly-progress-container {
        height: 20px;
        background-color: #f3f3f3;
        border-radius: 4px;
        margin: 10px 0;
        overflow: hidden;
    }

    .cerebroly-progress-bar {
        height: 100%;
        background-color: #46b450;
        transition: width 0.3s ease-in-out;
    }

    .cerebroly-progress-status {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #666;
    }

    .cerebroly-progress-percent {
        font-weight: bold;
    }

    .cerebroly-auto-training-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }

    .cerebroly-training-history {
        margin-top: 15px;
        padding: 10px;
        background: #f9f9f9;
        border-left: 4px solid #ccc;
    }

    .cerebroly-status-processing {
        color: #f0b849;
        font-weight: bold;
    }

    .cerebroly-refreshing-status {
        font-style: italic;
        color: #666;
    }

    .cerebroly-model-info {
        font-size: 0.9em;
        color: #555;
    }

    .cerebroly-model-active {
        margin-top: 15px;
    }
</style>