<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap">
    <h1>cerebroly - Settings</h1>
    <?php
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'cerebroly') . '</p></div>';
}
    ?>
    <form method="post" action="options.php">
        <?php
        settings_fields('cerebroly_settings');
        do_settings_sections('cerebroly_settings');
        ?>

        <?php 
        
        $cerebroly_openai_api = new CEREBROLY_OpenAI_API();
        $cerebroly_models_data = $cerebroly_openai_api->get_finetuning_models_for_settings();

        // Check if models_data is WP_Error
        if (is_wp_error($cerebroly_models_data)) {
            $cerebroly_base_models = [];
            $cerebroly_fine_tuned_models = [];
            $cerebroly_models_error = $cerebroly_models_data->get_error_message();
        } else {
            $cerebroly_base_models = $cerebroly_models_data['base'] ?? [];
            $cerebroly_fine_tuned_models = $cerebroly_models_data['fine_tuned'] ?? [];
            $cerebroly_models_error = null;
        }

        $cerebroly_selected_model = get_option('cerebroly_finetuning_base_model', 'gpt-3.5-turbo');

        ?>

        <div class="metabox-holder">
            <div class="postbox-container" style="width: 100%;">

                <!-- Metabox: API Configuration -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('API Configuration', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px;">
                    <p><strong><?php esc_html_e('⚠️ Important: OpenAI Terms of Service', 'cerebroly'); ?></strong></p>
                    <p><?php esc_html_e('This plugin integrates with the OpenAI API. Please be aware that while this plugin is GPL, your use of the OpenAI API is subject to OpenAI\'s own Terms of Service and Usage Policies.', 'cerebroly'); ?></p>
                        </div>
                       
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('OpenAI API Key', 'cerebroly'); ?></th>
                                <td>
                                <?php

$cerebroly_key_source = cerebroly_get_openai_key_source();
$cerebroly_api_key_value = cerebroly_get_openai_api_key();

$cerebroly_is_fallback = ($cerebroly_key_source === __('Option', 'cerebroly'));
?>

<input type="password" 
       name="cerebroly_openai_api_key"
       value="<?php echo $cerebroly_is_fallback ? esc_attr($cerebroly_api_key_value) : ''; ?>"
       <?php if (!$cerebroly_is_fallback): ?>readonly disabled<?php endif; ?>
       style="width: 100%; max-width: 400px;"
       placeholder="<?php echo $cerebroly_is_fallback ? esc_attr__('Enter your OpenAI API key', 'cerebroly') : ''; ?>">

<p class="description">
    <?php if ($cerebroly_is_fallback): ?>
        <?php esc_html_e('API key will be stored in the database (fallback method). For better security, consider defining the key in wp-config.php or your server\'s environment.', 'cerebroly'); ?>
        <br><br>
        <strong><?php esc_html_e('To use wp-config.php instead:', 'cerebroly'); ?></strong>
        <br><code>define('OPENAI_API_KEY', 'your-api-key-here');</code>
        <br><br>
        <strong><?php esc_html_e('To use environment variable instead:', 'cerebroly'); ?></strong>
        <br><code>export OPENAI_API_KEY="your-api-key-here"</code>
    <?php else: ?>
        <strong><?php
        // translators: %s: source of the API key (e.g. Environment Variable, wp-config.php).
        printf(esc_html__('API key loaded from %s.', 'cerebroly'), esc_html($cerebroly_key_source));
        ?></strong>
        <?php if (!empty($cerebroly_api_key_value)): ?>
            <br><?php
            // translators: %s: partially masked API key preview.
            printf(esc_html__('Key preview: %s', 'cerebroly'),
                   esc_html(substr($cerebroly_api_key_value, 0, 7) . '...' . substr($cerebroly_api_key_value, -4)));
            ?>
        <?php endif; ?>
        <br><em><?php esc_html_e('This field is read-only because the API key is loaded from an external source.', 'cerebroly'); ?></em>
    <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Operation Mode', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Choose between the two available modes to answer queries:', 'cerebroly'); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Processing Method', 'cerebroly'); ?></th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text">
                                            <?php esc_html_e('Processing Method', 'cerebroly'); ?></legend>
                                        <label>
                                            <input type="radio" name="cerebroly_use_rag" value="0"
                                                <?php checked(0, get_option('cerebroly_use_rag', 0)); ?>>
                                            <strong><?php esc_html_e('Fine-tuning (Classic Mode)', 'cerebroly'); ?></strong>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Creates a model specifically trained with your content. Requires retraining when content changes.', 'cerebroly'); ?>
                                        </p>
                                        <br>
                                        <label>
                                            <input type="radio" name="cerebroly_use_rag" value="1"
                                                <?php checked(1, get_option('cerebroly_use_rag', 0)); ?>>
                                            <strong><?php esc_html_e('RAG (Retrieval-Augmented Generation)', 'cerebroly'); ?></strong>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Uses vector search to retrieve relevant content for each query. Automatically updates when content changes.', 'cerebroly'); ?>
                                            <?php if (get_option('cerebroly_use_rag', 0)): ?>
                                            <br><a
                                                href="<?php echo esc_url(admin_url('admin.php?page=cerebroly-rag-config')); ?>"
                                                class="button button-small"><?php esc_html_e('Configure RAG System', 'cerebroly'); ?></a>
                                            <?php endif; ?>
                                        </p>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Content Sources', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Posts and Pages', 'cerebroly'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cerebroly_extract_posts" value="1"
                                            <?php checked(1, get_option('cerebroly_extract_posts', 1)); ?>>
                                        <?php esc_html_e('Include posts and pages in training', 'cerebroly'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Extracts content from all published posts and pages on your site.', 'cerebroly'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Media Library', 'cerebroly'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cerebroly_extract_media" value="1"
                                            <?php checked(1, get_option('cerebroly_extract_media', 0)); ?>>
                                        <?php esc_html_e('Include files from the media library', 'cerebroly'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Extracts text from text files in your media library.', 'cerebroly'); ?>
                                        <br><strong><?php esc_html_e('Note:', 'cerebroly'); ?></strong>
                                        <?php esc_html_e('This can significantly increase training time.', 'cerebroly'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('AI Enhancement', 'cerebroly'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cerebroly_use_ai_enhancement" value="1"
                                            <?php checked(1, get_option('cerebroly_use_ai_enhancement', 0)); ?>>
                                        <?php esc_html_e('Use AI to generate enhanced training datasets', 'cerebroly'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Uses the OpenAI API to generate high-quality question and answer pairs.', 'cerebroly'); ?>
                                        <br><strong><?php esc_html_e('Note:', 'cerebroly'); ?></strong>
                                        <?php esc_html_e('This option consumes additional API tokens and may increase costs.', 'cerebroly'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Fine-Tuning Base Model', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px;">
                    <p><strong><?php esc_html_e('⚠️ Important: OpenAI Terms of Service', 'cerebroly'); ?></strong></p>
                    <p><?php esc_html_e('This plugin integrates with the OpenAI API. Please be aware that while this plugin is GPL, your use of the OpenAI API is subject to OpenAI\'s own Terms of Service and Usage Policies.', 'cerebroly'); ?></p>
                        </div>
                       
                        <?php if ($cerebroly_models_error): ?>
                        <div class="notice notice-error">
                            <p><strong><?php esc_html_e('Error loading models:', 'cerebroly'); ?></strong>
                                <?php echo esc_html($cerebroly_models_error); ?></p>
                            <p><?php esc_html_e('Please check your OpenAI API key configuration.', 'cerebroly'); ?></p>
                        </div>
                        <?php endif; ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><?php esc_html_e('Select Base Model', 'cerebroly'); ?></th>
                                <td>
                                    <select name="cerebroly_finetuning_base_model">
                                        <optgroup label="<?php esc_attr_e('Base Models', 'cerebroly'); ?>">
                                            <?php foreach ($cerebroly_base_models as $cerebroly_model): ?>
                                            <option value="<?php echo esc_attr($cerebroly_model['id']); ?>"
                                                <?php selected($cerebroly_selected_model, $cerebroly_model['id']); ?>>
                                                <?php echo esc_html($cerebroly_model['label']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="<?php esc_attr_e('Fine-Tuned Models', 'cerebroly'); ?>">
                                            <?php if (!empty($cerebroly_fine_tuned_models)): ?>
                                            <?php foreach ($cerebroly_fine_tuned_models as $cerebroly_model): ?>
                                            <option value="<?php echo esc_attr($cerebroly_model['id']); ?>"
                                                <?php selected($cerebroly_selected_model, $cerebroly_model['id']); ?>>
                                                <?php echo esc_html($cerebroly_model['label']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <option disabled>
                                                <?php esc_html_e('No fine-tuned models available', 'cerebroly'); ?>
                                            </option>
                                            <?php endif; ?>
                                        </optgroup>
                                    </select>

                                    <p class="description">
                                        <?php esc_html_e('Select the OpenAI base model for fine-tuning.', 'cerebroly'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Advanced OpenAI Configuration', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('OpenAI offers several fine-tuning and customization options:', 'cerebroly'); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Approximate fine-tuning prices', 'cerebroly'); ?></th>
                                <td>
                                    <ul style="list-style-type: disc; padding-left: 20px;">
                                        <li><?php esc_html_e('Training:', 'cerebroly'); ?> $0.008 per 1K tokens</li>
                                        <li><?php esc_html_e('Fine-tuned model usage:', 'cerebroly'); ?> $0.012 per 1K
                                            tokens
                                            (input), $0.016 per 1K tokens (output)</li>
                                    </ul>
                                    <p class="description">
                                        <?php 
                                            /* translators: %s: URL to OpenAI pricing page */
                                            printf(wp_kses(__('Prices may vary. Check the <a href="%s" target="_blank">OpenAI pricing page</a> for updated information.', 'cerebroly'), array('a' => array('href' => array(), 'target' => array()))), 'https://openai.com/pricing'); 
                                            ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Content limit for AI enhancement', 'cerebroly'); ?>
                                </th>
                                <td>
                                    <input type="number" name="cerebroly_ai_enhancement_limit"
                                        value="<?php echo esc_attr(get_option('cerebroly_ai_enhancement_limit', 100)); ?>"
                                        class="small-text">
                                    <p class="description">
                                        <?php esc_html_e('Maximum number of content items that will be processed with the OpenAI API.', 'cerebroly'); ?>
                                        <br><?php esc_html_e('A higher number improves dataset quality but increases cost.', 'cerebroly'); ?>
                                        <br><?php esc_html_e('Use 0 to process all content.', 'cerebroly'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

<div class="postbox">
    <div class="postbox-header">
        <h2 class="hndle">
            <span><?php esc_html_e('Rate Limiting Protection', 'cerebroly'); ?></span>
        </h2>
    </div>
    <div class="inside">
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Rate Limiting', 'cerebroly'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="cerebroly_rate_limit_enabled" value="1"
                            <?php checked(get_option('cerebroly_rate_limit_enabled', 1), 1); ?>>
                        <?php esc_html_e('Protect against API overuse', 'cerebroly'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Prevents excessive API calls to avoid unexpected costs.', 'cerebroly'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php esc_html_e('Requests per Minute', 'cerebroly'); ?></th>
                <td>
                    <input type="number" name="cerebroly_rate_limit_per_minute" 
                        value="<?php echo esc_attr(get_option('cerebroly_rate_limit_per_minute', 50)); ?>" 
                        min="1" max="200" class="small-text">
                    <p class="description">
                        <?php esc_html_e('Maximum API requests per minute (recommended: 50).', 'cerebroly'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>
</div>
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('API Access Control', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Configure which domains can access the chat API from external JavaScript applications.', 'cerebroly'); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Allowed Domains', 'cerebroly'); ?></th>
                                <td>
                                    <?php 
                        $cerebroly_allowed_domains = get_option('cerebroly_allowed_domains', array());
                        $cerebroly_max_domains = apply_filters('cerebroly_max_allowed_domains', 5);
                        
                        // Ensure we have at least the max number of empty slots
                        while (count($cerebroly_allowed_domains) < $cerebroly_max_domains) {
                            $cerebroly_allowed_domains[] = '';
                        }
                        ?>

                                    <div id="cerebroly-domains-container">
                                        <?php for ($cerebroly_i = 0; $cerebroly_i < $cerebroly_max_domains; $cerebroly_i++): ?>
                                        <div class="cerebroly-domain-row" style="margin-bottom: 10px;">
                                            <input type="url" name="cerebroly_allowed_domains[<?php echo esc_attr($cerebroly_i); ?>]"
                                                value="<?php echo esc_attr($cerebroly_allowed_domains[$cerebroly_i] ?? ''); ?>"
                                                placeholder="https://example.com" style="width: 300px;"
                                                pattern="https?://.*">
                                            <span class="description">
                                                <?php
                                                /* translators: %d: Domain number */
                                                printf(esc_html__('Domain %d', 'cerebroly'), esc_html($cerebroly_i + 1));
                                                ?>
                                            </span>
                                        </div>
                                        <?php endfor; ?>
                                    </div>

                                    <p class="description">
                                        <strong><?php esc_html_e('Important:', 'cerebroly'); ?></strong>
                                        <?php esc_html_e('Only domains listed here will be able to access the chat API from external JavaScript applications.', 'cerebroly'); ?>
                                        <br>•
                                        <?php esc_html_e('Include the full protocol (https:// or http://)', 'cerebroly'); ?>
                                        <br>• <?php esc_html_e('Leave empty fields for unused slots', 'cerebroly'); ?>
                                        <br>•
                                        <?php 
                                        /* translators: %d: Maximum number of domains allowed */
                                        printf(esc_html__('Maximum %d domains allowed', 'cerebroly'), (int) $cerebroly_max_domains); 
                                        ?>
                                    </p>
                                    <div id="cerebroly-domain-validation" style="margin-top: 10px;"></div>

                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const domainInputs = document.querySelectorAll(
                                            'input[name^="cerebroly_allowed_domains"]');
                                        const validationDiv = document.getElementById('cerebroly-domain-validation');

                                        function validateDomains() {
                                            let validCount = 0;
                                            let errors = [];

                                            domainInputs.forEach((input, index) => {
                                                const value = input.value.trim();
                                                if (value) {
                                                    if (!value.match(/^https?:\/\/.+/)) {
                                                        errors.push(
                                                            `Domain ${index + 1}: Must start with http:// or https://`
                                                        );
                                                    } else {
                                                        validCount++;
                                                    }
                                                }
                                            });

                                            if (errors.length > 0) {
                                                validationDiv.innerHTML = '<div style="color: #d63638;">' +
                                                    errors.join('<br>') + '</div>';
                                            } else if (validCount > 0) {
                                                validationDiv.innerHTML = '<div style="color: #00a32a;">✓ ' +
                                                    validCount + ' valid domain(s) configured</div>';
                                            } else {
                                                validationDiv.innerHTML =
                                                    '<div style="color: #dba617;">No domains configured - API will only work internally</div>';
                                            }
                                        }

                                        domainInputs.forEach(input => {
                                            input.addEventListener('input', validateDomains);
                                            input.addEventListener('blur', validateDomains);
                                        });

                                        // Initial validation
                                        validateDomains();
                                    });
                                    </script>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('CORS Headers', 'cerebroly'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cerebroly_enable_cors" value="1"
                                            <?php checked(1, get_option('cerebroly_enable_cors', 0)); ?>>
                                        <?php esc_html_e('Enable CORS headers for API responses', 'cerebroly'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Required for external JavaScript applications to access the API.', 'cerebroly'); ?>
                                        <br><?php esc_html_e('Only enables CORS for domains in the allowed list above.', 'cerebroly'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Metabox: Chat Sharing -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span><?php esc_html_e('Chat Sharing', 'cerebroly'); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <p class="description">
                            <?php esc_html_e('Embed your chat on external websites using JavaScript or iFrame. Only domains in the allowed list above can use these snippets.', 'cerebroly'); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('JavaScript Snippet', 'cerebroly'); ?></th>
                                <td>
                                    <?php 
                                            $cerebroly_site_url = get_site_url();
                                            $cerebroly_js_snippet = sprintf(
                                                '&lt;script src="%s/wp-json/cerebroly/v1/chat-embed"&gt;&lt;/script&gt;',
                                                esc_url($cerebroly_site_url)
                                            );
                                        ?>
                                   <textarea readonly
                                   style="width: 100%; height: 120px; font-family: monospace; font-size: 12px;"><?php echo esc_attr($cerebroly_js_snippet); ?></textarea>
                                    <p class="description">
                                        <strong><?php esc_html_e('Easy Integration:', 'cerebroly'); ?></strong>
                                        <?php esc_html_e('Copy this code and paste it into your external website\'s HTML.', 'cerebroly'); ?>
                                        <br>•
                                        <?php esc_html_e('Automatically creates a floating chat widget', 'cerebroly'); ?>
                                        <br>•
                                        <?php esc_html_e('Respects your WordPress theme and appearance settings', 'cerebroly'); ?>
                                        <br>•
                                        <?php esc_html_e('Positioned in bottom-right corner with responsive design', 'cerebroly'); ?>
                                    </p>
                                    <button type="button" class="button"
                                        onclick="navigator.clipboard.writeText(this.previousElementSibling.previousElementSibling.value)"><?php esc_html_e('Copy JavaScript Snippet', 'cerebroly'); ?></button>
                                </td>
                            </tr>


                            <tr>
                                <th scope="row"><?php esc_html_e('Security Notice', 'cerebroly'); ?></th>
                                <td>
                                    <div
                                        style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px;">
                                        <strong>⚠️ <?php esc_html_e('Important:', 'cerebroly'); ?></strong>
                                        <?php esc_html_e('These snippets will only work on domains added to the "Allowed Domains" list above.', 'cerebroly'); ?>
                                        <br><?php esc_html_e('Make sure to:', 'cerebroly'); ?>
                                        <ol style="margin: 10px 0 0 20px;">
                                            <li><?php esc_html_e('Add your target domain to the allowed domains list', 'cerebroly'); ?>
                                            </li>
                                            <li><?php esc_html_e('Enable CORS headers if using JavaScript snippet', 'cerebroly'); ?>
                                            </li>
                                            <li><?php esc_html_e('Test the integration on your target domain', 'cerebroly'); ?>
                                            </li>
                                        </ol>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>

<style>
/* Basic metabox styles */
.metabox-holder {
    margin-bottom: 20px;
    margin-right: 20px;
    max-width: 100%;
    overflow: hidden;
}

.postbox-container {
    max-width: 100%;
    overflow: hidden;
}


.cerebroly-dashboard {
       
        margin-right: 20px;
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

.cerebroly-comparison-table {
    margin-top: 15px;
}

.cerebroly-domain-row {
    margin-bottom: 10px;
}

/* Responsive */
@media (max-width: 768px) {

    .metabox-holder,
    .postbox-container,
    .postbox .inside {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }

    .cerebroly-domain-row input {
        width: 100% !important;
        max-width: none !important;
    }
}
</style>