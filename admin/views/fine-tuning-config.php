<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
wp_register_script('cerebroly-admin-script', '', array('jquery'), '1.0', true);
wp_enqueue_script('cerebroly-admin-script');

// Luego localizar
wp_localize_script('cerebroly-admin-script', 'ftcModalStrings', array(
    'enhanceTitle' => esc_html__('Enhance Dataset with AI', 'cerebroly'),
    'processWarning' => esc_html__('This process will send your website content to OpenAI to generate variations.', 'cerebroly'),
    'warning' => esc_html__('Warning:', 'cerebroly'),
    'jsonWarning' => esc_html__('This will not use the current JSON, but the original WordPress content.', 'cerebroly'),
    'continue' => esc_html__('Do you want to continue?', 'cerebroly'),
    'startEnhancement' => esc_html__('Start Enhancement', 'cerebroly'),
    'cancel' => esc_html__('Cancel', 'cerebroly')
));
?>


<div class="wrap">
    <h1><?php esc_html_e('cerebroly - Fine-Tuning configuration', 'cerebroly'); ?></h1>
    
    <?php settings_errors(); ?>
    
    <div class="nav-tab-wrapper">
        <a href="#overview" class="nav-tab nav-tab-active"><?php esc_html_e('Overview', 'cerebroly'); ?></a>
        <a href="#editor" class="nav-tab"><?php esc_html_e('Data Editor', 'cerebroly'); ?></a>
        <a href="#preview" class="nav-tab"><?php esc_html_e('Preview', 'cerebroly'); ?></a>
        <a href="#training" class="nav-tab"><?php esc_html_e('Training', 'cerebroly'); ?></a>
    </div>

    <div class="tab-content">
        <div id="overview" class="tab-pane active">
            <div class="cerebroly-overview-container">
                
                <div class="cerebroly-overview-stats">
                    <h2><?php esc_html_e('Dataset Statistics', 'cerebroly'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <tr>
                            <th><?php esc_html_e('Training Examples', 'cerebroly'); ?></th>
                            <td><?php echo count($training_data); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Total Size', 'cerebroly'); ?></th>
                            <td><?php echo number_format($total_size / 1024, 2); ?> KB</td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Total Words', 'cerebroly'); ?></th>
                            <td><?php echo number_format($total_words); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Last Updated', 'cerebroly'); ?></th>
                            <td>
                                <?php 
                                // Display file modification time or current time as fallback
                                $cerebroly_timestamp = file_exists($preview_file) ? filemtime($preview_file) : time();
                                echo esc_html(gmdate('Y-m-d H:i:s', $cerebroly_timestamp));
                                ?>
                            </td>
                        </tr>
                    </table>
                    
                    <br>
                    
                    <?php if (!empty($active_model)): ?>
                    <div class="cerebroly-active-model">
                        <h2><?php esc_html_e('Active Model', 'cerebroly'); ?></h2>
                        <div class="cerebroly-model-card">
                            <h3>
                                <?php 
                                // Display fine-tuned model name or fallback to generic name
                                echo isset($active_model->sources['fine_tuned_model']) 
                                    ? esc_html($active_model->sources['fine_tuned_model']) 
                                    : esc_html__('Fine-Tuned Model', 'cerebroly'); 
                                ?>
                            </h3>
                            <p><strong><?php esc_html_e('ID:', 'cerebroly'); ?></strong> <?php echo esc_html($active_model->model_id); ?></p>
                            <p><strong><?php esc_html_e('Status:', 'cerebroly'); ?></strong> 
                                <span class="cerebroly-status-<?php echo esc_attr($active_model->status); ?>">
                                    <?php echo esc_html($active_model->status); ?>
                                </span>
                            </p>
                            <p><strong><?php esc_html_e('Created:', 'cerebroly'); ?></strong> <?php echo esc_html($active_model->created); ?></p>
                            <p><strong><?php esc_html_e('Updated:', 'cerebroly'); ?></strong> <?php echo esc_html($active_model->updated); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="cerebroly-overview-explanation">
                    <h2><?php esc_html_e('About Fine-Tuning', 'cerebroly'); ?></h2>
                    <p><?php esc_html_e('Fine-Tuning is the process of adapting a pre-trained AI model for a specific task, in this case, answering questions about your website\'s content.', 'cerebroly'); ?></p>
                    
                    <h3><?php esc_html_e('How It Works', 'cerebroly'); ?></h3>
                    <div class="cerebroly-process-steps">
                        
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">1</span>
                            <h4><?php esc_html_e('Data Preparation', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('The system extracts content from your site and formats it into question-answer pairs.', 'cerebroly'); ?></p>
                        </div>
                        
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">2</span>
                            <h4><?php esc_html_e('Optional Editing', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('You can manually edit the training data in the Editor to customize questions and answers.', 'cerebroly'); ?></p>
                        </div>
                        
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">3</span>
                            <h4><?php esc_html_e('Training', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('OpenAI adapts a base model with your data to create a model specialized in your content.', 'cerebroly'); ?></p>
                        </div>
                        
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">4</span>
                            <h4><?php esc_html_e('Implementation', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('Once training is complete, your model will be ready to answer queries through the chat.', 'cerebroly'); ?></p>
                        </div>
                    </div>
                    
                    <div class="cerebroly-overview-actions">
                        <button type="button" class="button button-primary js-go-to-tab" data-tab="editor"><?php esc_html_e('Edit Training Data', 'cerebroly'); ?></button>
                        <button type="button" class="button button-primary js-go-to-tab" data-tab="preview"><?php esc_html_e('Data Preview', 'cerebroly'); ?></button>
                        <button type="button" class="button button-primary js-go-to-tab" data-tab="training"><?php esc_html_e('Start Training', 'cerebroly'); ?></button>
                    </div>
                    <br>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <p><strong><?php esc_html_e('⚠️ Important Privacy & Security Alert: Do Not Share Confidential Information!', 'cerebroly'); ?></strong></p>
    <p><?php esc_html_e('This plugin sends your content to OpenAI\'s servers for processing to create informational agents. This means that any information you include in your WordPress posts, pages, or custom post types that this plugin processes will be transmitted to OpenAI.', 'cerebroly'); ?></p>
    <p><?php esc_html_e('For your security and privacy, we strongly advise against including any confidential, sensitive, or personal data (e.g., passwords, private keys, credit card numbers, confidential documents, personal identifiable information of yourself or others) in the content that this plugin is configured to use.', 'cerebroly'); ?></p>
    <p><?php esc_html_e('Once processed by OpenAI, this data is subject to their policies. Always review OpenAI\'s Terms of Service and Usage Policies to understand how they handle data. Your content used by this plugin is intended for informational purposes and may be publicly exposed through the agents created.', 'cerebroly'); ?></p>
</div>
                </div>
            </div>
        </div>

        <div id="editor" class="tab-pane">
            <div class="cerebroly-editor-container">
                <p class="description">
                    <?php esc_html_e('Edit the training JSON directly. Each element represents a question-answer pair for training.', 'cerebroly'); ?>
                    <strong><?php esc_html_e('Note:', 'cerebroly'); ?></strong> <?php esc_html_e('Changes made here will affect model training. Ensure the format is correct.', 'cerebroly'); ?>
                </p>
                
                <form method="post" action="" id="training-form">
                    <?php wp_nonce_field('cerebroly_update_training'); ?>
                    
                    <div class="cerebroly-editor-actions">
                        <button type="button" id="cerebroly-add-entry" class="button"><?php esc_html_e('Add New Pair', 'cerebroly'); ?></button>
                        <button type="button" id="cerebroly-format-json" class="button"><?php esc_html_e('Format JSON', 'cerebroly'); ?></button>
                        <button type="button" id="cerebroly-generate-dataset" class="button button-secondary"><?php esc_html_e('Improve with AI', 'cerebroly'); ?></button>
                        <span class="status-indicator"><?php esc_html_e('Editor:', 'cerebroly'); ?> <span id="editor-status"><?php esc_html_e('Ready', 'cerebroly'); ?></span></span>
                    </div>
                    
                    <div id="monaco-editor" style="width: 100%; height: 500px; border: 1px solid #ddd;"></div>
                    
                    <textarea name="cerebroly_training_data" id="cerebroly-json-value" style="display: none;"><?php echo esc_textarea($training_json_pretty); ?></textarea>
                    
                    <div class="cerebroly-validation-message"></div>
                    
                    <div class="cerebroly-editor-actions">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'cerebroly'); ?>">
                        <button type="button" class="button button-secondary" onclick="window.location.href='<?php echo esc_url(add_query_arg('regenerate', '1')); ?>'"><?php esc_html_e('Regenerate Original Data', 'cerebroly'); ?></button>
                    </div>
                </form>
                
                <div class="cerebroly-editor-help">
                    <h3><?php esc_html_e('JSON Format', 'cerebroly'); ?></h3>
                    <p><?php esc_html_e('Each entry must follow this format:', 'cerebroly'); ?></p>
                    <pre>{
  "messages": [
    {
      "role": "user",
      "content": "User's question?"
    },
    {
      "role": "assistant",
      "content": "Assistant's response."
    }
  ]
}</pre>
                    <h3><?php esc_html_e('Tips for Better Training Data', 'cerebroly'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Ensure each question is specific and relevant to your content.', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('Responses should be useful and precise, based on the actual information from your site.', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('You can add multiple question-answer pairs for each important topic.', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('Include variations of common questions to improve training effectiveness.', 'cerebroly'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div id="preview" class="tab-pane">
            <div class="cerebroly-training-preview">
                
                <div class="cerebroly-preview-actions">
                    <button type="button" class="button button-secondary" onclick="window.location.href='<?php echo esc_url(add_query_arg('regenerate', '1')); ?>'"><?php esc_html_e('Regenerate Preview', 'cerebroly'); ?></button>
                </div>
                
                <div class="cerebroly-training-content">
                    
                    <h2><?php 
                    /* translators: %s: Examples Preview (%1$d of %2$d)  */
                    printf(esc_html__('Example Preview (%1$d of %2$d)', 'cerebroly'), count($preview_examples), count($training_data)); 
                    ?></h2>
                    
                    <div class="cerebroly-scroll-container">
                        <?php foreach ($preview_examples as $cerebroly_index => $cerebroly_example): ?>
                            <div class="cerebroly-example-preview">
                                <h3><?php 
                                 /* translators: %s: Examples Example #%d  */
                                printf(esc_html__('Example #%d', 'cerebroly'), esc_html($cerebroly_index + 1)); ?>
                                </h3>
                                
                                <div class="cerebroly-qa-pair">
                                    <div class="cerebroly-question">
                                        <strong><?php esc_html_e('Question:', 'cerebroly'); ?></strong> <?php echo esc_html($cerebroly_example['messages'][0]['content']); ?>
                                    </div>
                                    <div class="cerebroly-answer">
                                        <strong><?php esc_html_e('Response:', 'cerebroly'); ?></strong> <?php echo esc_html($cerebroly_example['messages'][1]['content']); ?>
                                    </div>
                                </div>
                                
                                <pre class="cerebroly-json-preview"><?php echo esc_html(json_encode($cerebroly_example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="training" class="tab-pane">
            <div class="cerebroly-training-container">
                <h2><?php esc_html_e('Start Model Training', 'cerebroly'); ?></h2>
                
                <div class="cerebroly-training-info">
                    
                    <div class="cerebroly-info-card">
                        <h3><?php esc_html_e('OpenAI Information', 'cerebroly'); ?></h3>
                        <ul>
                            <li><strong><?php esc_html_e('Base Model:', 'cerebroly'); ?></strong> GPT-3.5 Turbo</li>
                            <li><strong><?php esc_html_e('Estimated Time:', 'cerebroly'); ?></strong> <?php esc_html_e('30-60 minutes', 'cerebroly'); ?></li>
                            <li><strong><?php esc_html_e('Approximate Cost:', 'cerebroly'); ?></strong> <?php esc_html_e('$0.008 per 1K tokens (training) +', 'cerebroly'); ?><br><?php esc_html_e('$0.012-0.016 per 1K tokens (usage)', 'cerebroly'); ?></li>
                        </ul>
                        
                        <p class="cerebroly-api-status">
                            <?php if (is_wp_error($api_status)): ?>
                                <span class="cerebroly-status-error"><?php 
                                    /* translators: %s: API Error: %s  */
                                    printf(esc_html__('API Error: %s', 'cerebroly'), esc_html($api_status->get_error_message())); ?></span>
                            <?php else: ?>
                                <span class="cerebroly-status-ok"><?php esc_html_e('OpenAI API Connected', 'cerebroly'); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="cerebroly-info-card">
                        <h3><?php esc_html_e('Dataset Summary', 'cerebroly'); ?></h3>
                        <ul>
                            <li><strong><?php esc_html_e('Examples:', 'cerebroly'); ?></strong> <?php echo count($training_data); ?></li>
                            <li><strong><?php esc_html_e('Size:', 'cerebroly'); ?></strong> <?php echo number_format($total_size / 1024, 2); ?> KB</li>
                            <li><strong><?php esc_html_e('Words:', 'cerebroly'); ?></strong> <?php echo number_format($total_words); ?></li>
                        </ul>
                        
                        <?php if (count($training_data) < 10): ?>
                            <p class="cerebroly-warning"><?php esc_html_e('At least 10 examples are recommended for effective training.', 'cerebroly'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="cerebroly-training-start">
                    <h3><?php esc_html_e('Start New Training', 'cerebroly'); ?></h3>
                    <p><?php esc_html_e('When you start training, your dataset will be sent to OpenAI to create a personalized model. This process can take between 30 minutes and several hours.', 'cerebroly'); ?></p>
                    
                    <div class="cerebroly-training-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('cerebroly_start_training'); ?>
                            <input type="hidden" name="action" value="cerebroly_start_training">
                            
                            <?php if (is_wp_error($api_status)): ?>
                                <button type="button" class="button button-primary" disabled><?php esc_html_e('API Not Configured', 'cerebroly'); ?></button>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-settings')); ?>" class="button"><?php esc_html_e('Configure API', 'cerebroly'); ?></a>
                            <?php else: ?>
                                <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to start training with the current data?', 'cerebroly'); ?>');">
                                    <?php esc_html_e('Start Training', 'cerebroly'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button" onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=cerebroly')); ?>'">
                                <?php esc_html_e('Return to Dashboard', 'cerebroly'); ?>
                            </button>
                        </form>
                    </div>
                    
                    <div class="cerebroly-note">
                        <p><strong><?php esc_html_e('Note:', 'cerebroly'); ?></strong> <?php esc_html_e('If you already have an active model, you can continue using it while training a new one. When the new training is complete, you can select which model to use.', 'cerebroly'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================================================ -->
<!-- CSS STYLES: Comprehensive styling for all interface components -->
<!-- ============================================================================ -->
<style>
    /* =========================== */
    /* General Layout and Tab Styles */
    /* =========================== */
    .tab-content {
        margin-top: 20px;
        margin-right: 20px;

    }
    
    .tab-pane {
        display: none;
    }
    
    .tab-pane.active {
        display: block;
    }
    
    /* =========================== */
    /* Overview Tab Specific Styles */
    /* =========================== */
    .cerebroly-overview-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;

    }
    
    /* Card-style containers for overview sections */
    .cerebroly-overview-stats, 
    .cerebroly-overview-explanation, 
    .cerebroly-active-model {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
        flex: 1;
        min-width: 300px;
    }
    
    /* Active model section spans full width */
    .cerebroly-active-model {
        flex: 0 0 100%;
    }
    
    /* Process steps visualization */
    .cerebroly-process-steps {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin: 20px 0;
    }
    
    .cerebroly-process-step {
        flex: 1;
        min-width: 200px;
        background: #f9f9f9;
        border-radius: 5px;
        padding: 15px;
        position: relative;
    }
    
    /* Numbered step indicators */
    .cerebroly-step-number {
        position: absolute;
        top: -10px;
        left: -10px;
        background: #2271b1;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    /* Model information card styling */
    .cerebroly-model-card {
        background: #f9f9f9;
        border-radius: 5px;
        padding: 15px;
    }
    
    /* Status indicator colors */
    .cerebroly-status-active {
        color: #46b450;
        font-weight: bold;
    }
    
    .cerebroly-status-processing {
        color: #f0b849;
        font-weight: bold;
    }
    
    .cerebroly-status-failed {
        color: #dc3232;
        font-weight: bold;
    }
    
    /* Action buttons layout */
    .cerebroly-overview-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    /* =========================== */
    /* Editor Tab Specific Styles */
    /* =========================== */
    .cerebroly-editor-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    /* Editor toolbar styling */
    .cerebroly-editor-actions {
        margin: 15px 0;
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    /* Status indicator in editor toolbar */
    .status-indicator {
        margin-left: auto;
        padding: 5px 10px;
        background: #f0f0f0;
        border-radius: 3px;
        font-size: 12px;
    }
    
    /* Dynamic status colors for editor */
    #editor-status.saving {
        color: #0073aa;
    }
    
    #editor-status.error {
        color: #dc3232;
    }
    
    #editor-status.success {
        color: #46b450;
    }
    
    /* Validation message styling */
    .cerebroly-validation-message {
        padding: 10px;
        margin: 10px 0;
        display: none;
    }
    
    .cerebroly-validation-success {
        background-color: #f0fff0;
        border-left: 4px solid #46b450;
        display: block;
    }
    
    .cerebroly-validation-error {
        background-color: #fff0f0;
        border-left: 4px solid #dc3232;
        display: block;
    }
    
    /* Editor help documentation styling */
    .cerebroly-editor-help {
        background: #f9f9f9;
        border: 1px solid #ccd0d4;
        padding: 15px;
    }
    
    .cerebroly-editor-help pre {
        background: #f1f1f1;
        padding: 10px;
        overflow-x: auto;
        border: 1px solid #ddd;
    }
    
    .cerebroly-editor-help ul {
        list-style-type: disc;
        margin-left: 20px;
    }
    
    /* =========================== */
    /* Preview Tab Specific Styles */
    /* =========================== */
    .cerebroly-training-preview {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
    }
    
    .cerebroly-preview-actions {
        margin-bottom: 20px;
    }
    
    /* Scrollable container for training examples */
    .cerebroly-scroll-container {
        max-height: 500px;
        overflow-y: auto;
        border: 1px solid #e2e4e7;
        padding: 10px;
        background: #f8f9fa;
    }
    
    /* Individual example preview styling */
    .cerebroly-example-preview {
        margin-bottom: 20px;
        border-bottom: 1px solid #e2e4e7;
        padding-bottom: 15px;
    }
    
    /* Question-answer pair display */
    .cerebroly-qa-pair {
        margin-bottom: 10px;
        padding: 10px;
        background: #fff;
        border: 1px solid #e2e4e7;
        border-radius: 4px;
    }
    
    /* Question styling with blue background */
    .cerebroly-question {
        padding: 8px;
        background: #f0f7ff;
        border-radius: 4px;
        margin-bottom: 8px;
    }
    
    /* Answer styling with green background */
    .cerebroly-answer {
        padding: 8px;
        background: #f8fff0;
        border-radius: 4px;
    }
    
    /* JSON code preview styling */
    .cerebroly-json-preview {
        background: #f8f9fa;
        border: 1px solid #e2e4e7;
        padding: 10px;
        max-height: 200px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-size: 12px;
    }
    
    /* =========================== */
    /* Training Tab Specific Styles */
    /* =========================== */
    .cerebroly-training-container {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
    }
    
    /* Information cards layout */
    .cerebroly-training-info {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    /* Individual information card styling */
    .cerebroly-info-card {
        flex: 1;
        min-width: 300px;
        background: #f9f9f9;
        border-radius: 5px;
        padding: 15px;
    }
    
    /* Training initiation section */
    .cerebroly-training-start {
        border-top: 1px solid #eee;
        padding-top: 20px;
    }
    
    .cerebroly-training-actions {
        margin: 20px 0;
    }
    
    /* Important notes styling */
    .cerebroly-note {
        background: #f0f7ff;
        border-left: 4px solid #0073aa;
        padding: 15px;
        margin-top: 20px;
    }
    
    /* =========================== */
    /* Status and State Indicators */
    /* =========================== */
    .cerebroly-status-ok {
        color: #46b450;
    }
    
    .cerebroly-status-error {
        color: #dc3232;
    }
    
    .cerebroly-warning {
        color: #f0b849;
        font-weight: bold;
    }
</style>


<!-- ============================================================================ -->
<!-- JAVASCRIPT: Advanced functionality for editor, tabs, and AI enhancement -->
<!-- ============================================================================ -->

<!-- Load Monaco Editor for advanced JSON editing -->
<?php 
wp_enqueue_script(
    'monaco-editor-loader',
    CEREBROLY_PLUGIN_URL . 'assets/js/libs/loader.min.js',
    array(),
    '0.36.1',
    true
);
?>

<script>
jQuery(document).ready(function($) {
    
    /* ============================= */
    /* Tab Navigation System */
    /* ============================= */
    
    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update active tab styling
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding tab content
        const targetId = $(this).attr('href');
        $('.tab-pane').removeClass('active');
        $(targetId).addClass('active');
        
        // Save active tab to localStorage for persistence
        localStorage.setItem('cerebroly_finetuning_active_tab', targetId);
    });
    
    // Handle tab navigation buttons within content
    $('.js-go-to-tab').on('click', function() {
        const targetTab = $(this).data('tab');
        $(`.nav-tab[href="#${targetTab}"]`).trigger('click');
    });
    
    // Restore previously active tab from localStorage
    const savedTab = localStorage.getItem('cerebroly_finetuning_active_tab');
    if (savedTab) {
        $(`.nav-tab[href="${savedTab}"]`).trigger('click');
    }
    
    /* ============================= */
    /* Monaco Editor Initialization */
    /* ============================= */
    
    // Prevent multiple Monaco Editor loads
    if (window.monacoLoaded) return;
    window.monacoLoaded = true;

    let editor; // Monaco editor instance
    
    // Configure Monaco Editor loader
    if (typeof require !== 'undefined') {
        require.config({ 
            paths: { 
                'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.36.1/min/vs'
            },
            catchError: true
        });

        require(['vs/editor/editor.main'], function() {
            try {
                
                /* ============================= */
                /* JSON Schema Configuration for Validation */
                /* ============================= */
                
                // Define JSON schema for training data validation
                monaco.languages.json.jsonDefaults.setDiagnosticsOptions({
                    validate: true,
                    schemas: [{
                        uri: "http://myschema/training-data.json",
                        fileMatch: ["*"],
                        schema: {
                            type: "array",
                            items: {
                                type: "object",
                                properties: {
                                    messages: {
                                        type: "array",
                                        items: {
                                            type: "object",
                                            properties: {
                                                role: {
                                                    type: "string",
                                                    enum: ["user", "assistant"]
                                                },
                                                content: {
                                                    type: "string"
                                                }
                                            },
                                            required: ["role", "content"]
                                        },
                                        minItems: 2
                                    }
                                },
                                required: ["messages"]
                            }
                        }
                    }]
                });
                
                /* ============================= */
                /* Editor Initialization */
                /* ============================= */
                
                // Create Monaco Editor instance with configuration
                editor = monaco.editor.create(document.getElementById('monaco-editor'), {
                    value: $('#cerebroly-json-value').val(),
                    language: 'json',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: {
                        enabled: true
                    },
                    formatOnPaste: true,
                    formatOnType: true,
                    scrollBeyondLastLine: false,
                    wordWrap: 'on'
                });
                
                /* ============================= */
                /* Editor Event Handlers */
                /* ============================= */
                
                // Handle content changes in editor
                editor.onDidChangeModelContent(function() {
                    // Sync editor content with hidden form textarea
                    $('#cerebroly-json-value').val(editor.getValue());
                    $('#editor-status').text('Modified').removeClass('saving success error');
                });
                
                /* ============================= */
                /* Editor Toolbar Functions */
                /* ============================= */
                
                // JSON formatting functionality
                $('#cerebroly-format-json').on('click', function() {
                    try {
                        let value = editor.getValue();
                        let parsed = JSON.parse(value);
                        let formatted = JSON.stringify(parsed, null, 2);
                        editor.setValue(formatted);
                        
                        // Show success message
                        $('.cerebroly-validation-message').html('JSON formatted successfully.')
                            .addClass('cerebroly-validation-success')
                            .removeClass('cerebroly-validation-error')
                            .show();
                    } catch (e) {
                        // Show error message
                        $('.cerebroly-validation-message').html('Formatting error: ' + e.message)
                            .addClass('cerebroly-validation-error')
                            .removeClass('cerebroly-validation-success')
                            .show();
                    }
                });
                
                // Add new question-answer pair functionality
                $('#cerebroly-add-entry').on('click', function() {
                    try {
                        let value = editor.getValue();
                        let parsed = JSON.parse(value);
                        
                        // Add new training example template
                        parsed.push({
                            "messages": [
                                {
                                    "role": "user",
                                    "content": "New question"
                                },
                                {
                                    "role": "assistant",
                                    "content": "New answer"
                                }
                            ]
                        });
                        
                        let formatted = JSON.stringify(parsed, null, 2);
                        editor.setValue(formatted);
                        
                        // Show success message
                        $('.cerebroly-validation-message').html('New pair added. Edit the content with your question and answer.')
                            .addClass('cerebroly-validation-success')
                            .removeClass('cerebroly-validation-error')
                            .show();
                    } catch (e) {
                        // Show error message
                        $('.cerebroly-validation-message').html('Error adding entry: ' + e.message)
                            .addClass('cerebroly-validation-error')
                            .removeClass('cerebroly-validation-success')
                            .show();
                    }
                });
            } catch (error) {
                console.error('Error initializing Monaco Editor:', error);
            }
        });
    }

    /* ============================= */
    /* AI Dataset Enhancement System */
    /* ============================= */

    /**
     * Initialize and manage the AI-powered dataset enhancement process
     * This function handles the entire workflow of improving training data using AI
     */
    function startDatasetEnhancement() {
        
        // Create progress modal for real-time feedback
        const progressModal = $(`
            <div id="cerebroly-enhancement-progress-modal" class="cerebroly-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:10000;">
                <div style="background:white; padding:20px; border-radius:5px; width:600px; max-width:90%; max-height:80%; overflow-y:auto;">
                    <h2>Enhancing Dataset with AI</h2>
                    
                    <!-- Progress Bar Section -->
                    <div style="background:#f8f8f8; padding:15px; margin-bottom:15px; border-radius:4px;">
                        <div style="height:20px; background:#e9ecef; border-radius:4px; margin-bottom:10px;">
                            <div id="cerebroly-progress-bar" style="height:100%; background-color:#28a745; width:0%; border-radius:4px; transition:width 0.5s ease;"></div>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span id="cerebroly-progress-text">Preparing enhancement...</span>
                            <span id="cerebroly-progress-percent">0%</span>
                        </div>
                    </div>
                    
                    <!-- Real-time Log Container -->
                    <div id="cerebroly-log-container" style="max-height:300px; overflow-y:auto; background:#f4f4f4; padding:10px; border-radius:4px;">
                        <div id="cerebroly-log-entries"></div>
                    </div>
                    
                    <!-- Modal Actions -->
                    <div style="margin-top:15px; text-align:right;">
                        <button id="cerebroly-cancel-enhancement" class="button">Cancel Process</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(progressModal);

        // Enhancement process variables
        let currentIndex = 0;
        let enhancedItems = [];
        const batchSize = 5; // Process items in small batches to prevent timeouts
        let isCancelled = false;
        let totalItems = 0;

        /**
         * Update the progress bar and text indicators
         * @param {number} processed - Number of processed items
         * @param {number} total - Total number of items
         */
        function updateProgress(processed, total) {
            const progressPercent = Math.floor((processed / total) * 100);
            $('#cerebroly-progress-bar').css('width', `${progressPercent}%`);
            $('#cerebroly-progress-text').text(`Processed ${processed} of ${total} items`);
            $('#cerebroly-progress-percent').text(`${progressPercent}%`);
        }

        /**
         * Add a log entry to the real-time log display
         * @param {string} message - Log message to display
         * @param {string} type - Log type: 'info', 'success', 'error'
         */
        function addLogEntry(message, type = 'info') {
            console.log(`Log [${type}]: ${message}`);
            const logEntry = $(`<p style="margin:5px 0; color:${
                type === 'success' ? '#28a745' : 
                type === 'error' ? '#dc3232' : 
                '#0073a7'
            };">${message}</p>`);
            $('#cerebroly-log-entries').append(logEntry);
            
            // Auto-scroll to latest log entry
            const logContainer = $('#cerebroly-log-container')[0];
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        /**
         * Process a batch of training data through AI enhancement
         * Uses recursive calls to handle large datasets without timeout issues
         */
        function processBatch() {
            // Check for cancellation
            if (isCancelled) {
                addLogEntry('Process cancelled by user.', 'error');
                $('#cerebroly-cancel-enhancement').prop('disabled', true);
                return;
            }

            // Make AJAX request to process current batch
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cerebroly_generate_enhanced_dataset',
                    security: '<?php echo esc_js(wp_create_nonce('cerebroly_generate_dataset')); ?>',
                    batch_size: batchSize,
                    start_index: currentIndex
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Server response:', response);

                    if (response.success) {
                        // Set total items count on first batch
                        if (currentIndex === 0) {
                            totalItems = response.data.total_items;
                            addLogEntry(`Total items to process: ${totalItems}`, 'info');
                        }

                        // Accumulate enhanced items
                        enhancedItems = enhancedItems.concat(response.data.enhanced_items);
                        
                        // Update progress tracking
                        currentIndex = response.data.next_index;
                        updateProgress(currentIndex, totalItems);
                        
                        addLogEntry(`Processed ${response.data.total_processed} items`, 'success');
                        
                        // Continue processing or finalize
                        if (!response.data.is_completed) {
                            processBatch(); // Recursive call for next batch
                        } else {
                            finalizeEnhancement(); // Complete the process
                        }
                    } else {
                        addLogEntry(`Error: ${response.data}`, 'error');
                        isCancelled = true;
                        $('#cerebroly-cancel-enhancement').prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.log('Server response:', xhr.responseText);
                    
                    addLogEntry(`Connection error: ${error}`, 'error');
                    isCancelled = true;
                    $('#cerebroly-cancel-enhancement').prop('disabled', true);
                }
            });
        }

        /**
         * Finalize the enhancement process by saving the improved dataset
         * Updates the editor with the new data and provides user feedback
         */
        function finalizeEnhancement() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cerebroly_finalize_enhanced_dataset',
                    security: '<?php echo esc_js( wp_create_nonce('cerebroly_generate_dataset')); ?>',
                    enhanced_dataset: JSON.stringify(enhancedItems)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        addLogEntry(`Enhanced dataset saved. New entries: ${response.data.count}`, 'success');
                        
                        // Update both Monaco editor and hidden textarea
                        if (editor) {
                            const formattedJson = JSON.stringify(enhancedItems, null, 2);
                            editor.setValue(formattedJson);
                            
                            // CRITICAL: Update the hidden textarea that stores form data
                            $('#cerebroly-json-value').val(formattedJson);
                            
                            // Update editor status indicator
                            $('#editor-status').text('Updated').addClass('success').removeClass('saving error');
                            
                            // Show validation success message
                            $('.cerebroly-validation-message').html('The dataset has been enhanced and updated successfully.')
                                .addClass('cerebroly-validation-success')
                                .removeClass('cerebroly-validation-error')
                                .show();
                        }
                        
                        // Close modal with delay to show completion
                        setTimeout(() => {
                            progressModal.remove();
                            
                            // Show confirmation to user
                            alert('The dataset has been enhanced successfully. To save changes permanently, click "Save Changes".');
                        }, 2000);
                    } else {
                        addLogEntry(`Error saving: ${response.data}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error finalizing:', status, error);
                    addLogEntry(`Connection error while saving: ${error}`, 'error');
                }
            });
        }

        // Handle cancellation button
        $('#cerebroly-cancel-enhancement').on('click', function() {
            if (confirm('Are you sure you want to cancel the enhancement process?')) {
                isCancelled = true;
                $(this).prop('disabled', true);
                addLogEntry('Cancelling process...', 'info');
            }
        });

        // Start the enhancement process
        processBatch();
    }

    /* ============================= */
    /* AI Enhancement Trigger */
    /* ============================= */

    /**
     * Handle the "Improve with AI" button click
     * Shows confirmation modal and initiates enhancement process
     */
    $(document).on('click', '#cerebroly-generate-dataset', function() {
        
        const confirmModal = $(`
        <div id="cerebroly-enhancement-confirm-modal" class="cerebroly-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:10000;">
            <div style="background:white; padding:20px; border-radius:5px; width:500px; max-width:90%;">
                <h2>${ftcModalStrings.enhanceTitle}</h2>
                
                <!-- Warning and Information -->
                <div style="background:#f8f8f8; padding:15px; margin:10px 0; border-radius:4px;">
                    <p>⚠️ ${ftcModalStrings.processWarning}</p>
                    <p><strong>${ftcModalStrings.warning}</strong> ${ftcModalStrings.jsonWarning}</p>
                    <p>${ftcModalStrings.continue}</p>
                </div>
                
                <!-- Modal Actions -->
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button id="cerebroly-confirm-enhancement" class="button button-primary">${ftcModalStrings.startEnhancement}</button>
                    <button id="cerebroly-cancel-modal" class="button">${ftcModalStrings.cancel}</button>
                </div>
            </div>
        </div>
    `);

        

        $('body').append(confirmModal);

        // Handle confirmation
        $('#cerebroly-confirm-enhancement').on('click', function() {
            confirmModal.remove();
            startDatasetEnhancement();
        });

        // Handle cancellation
        $('#cerebroly-cancel-modal').on('click', function() {
            confirmModal.remove();
        });
    });
});
</script>
