<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap">
    <h1><?php esc_html_e('cerebroly - RAG configuration', 'cerebroly'); ?></h1>

    <?php settings_errors(); ?>

    <?php
$rag_manager = new CEREBROLY_RAG_Manager();
$openai_api = $rag_manager->openai_api_client;
$models = ($openai_api) ? $openai_api->get_models_for_select() : [];

$base_models = $models['base'] ?? [];
$fine_tuned_models = $models['fine_tuned'] ?? [];

$selected_model = get_option('cerebroly_rag_llm_model', 'gpt-3.5-turbo');

$embedding_models = [
    'text-embedding-ada-002' => 'OpenAI Ada 002 (Recommended)',
    'text-embedding-3-small' => 'OpenAI Embedding 3 Small',
    'text-embedding-3-large' => 'OpenAI Embedding 3 Large',
];

$selected_embedding_model = get_option('cerebroly_rag_embedding_model', 'text-embedding-ada-002');
?>

    <div class="nav-tab-wrapper">
        <a href="#overview" class="nav-tab nav-tab-active"><?php esc_html_e('Overview', 'cerebroly'); ?></a>
        <a href="#embedding" class="nav-tab"><?php esc_html_e('Content Embedding', 'cerebroly'); ?></a>
        <a href="#retrieval" class="nav-tab"><?php esc_html_e('Retrieval Settings', 'cerebroly'); ?></a>
        <a href="#generation" class="nav-tab"><?php esc_html_e('Generation Settings', 'cerebroly'); ?></a>
        <a href="#preview" class="nav-tab"><?php esc_html_e('Content Preview', 'cerebroly'); ?></a>
        <a href="#test" class="nav-tab"><?php esc_html_e('Test RAG', 'cerebroly'); ?></a>
    </div>

    <div class="tab-content">
        <div id="overview" class="tab-pane active">
            <div class="cerebroly-overview-container">
                <div class="cerebroly-overview-stats">
                    <h2><?php esc_html_e('RAG Statistics', 'cerebroly'); ?></h2>
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
                            <th><?php esc_html_e('Last Updated', 'cerebroly'); ?></th>
                            <td>
                            <?php 
                                echo $last_update ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_update))) : esc_html__('Never', 'cerebroly'); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Status', 'cerebroly'); ?></th>
                            <td><span class="cerebroly-status-ok"><?php esc_html_e('Active', 'cerebroly'); ?></span></td>
                        </tr>
                    </table>

                    <div class="cerebroly-overview-actions">
                        <button id="reset-rag" class="button"><?php esc_html_e('Reset RAG System', 'cerebroly'); ?></button>
                    </div>
                    <?php
                    }
                    ?>
                </div>

                <div class="cerebroly-overview-explanation">
                    <h2><?php esc_html_e('About RAG', 'cerebroly'); ?></h2>
                    <p><?php esc_html_e('Retrieval-Augmented Generation (RAG) is an approach that combines specific information retrieval with natural language generation. Instead of training a model with all your content (fine-tuning), RAG works differently:', 'cerebroly'); ?></p>

                    <div class="cerebroly-process-steps">
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">1</span>
                            <h4><?php esc_html_e('Index', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('Stores your content in a vector database that can be searched semantically.', 'cerebroly'); ?></p>
                        </div>
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">2</span>
                            <h4><?php esc_html_e('Retrieve', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('Finds relevant documents for a specific query using embedding similarity.', 'cerebroly'); ?></p>
                        </div>
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">3</span>
                            <h4><?php esc_html_e('Enrich', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('Enhances the model\'s context with these documents for better responses.', 'cerebroly'); ?></p>
                        </div>
                        <div class="cerebroly-process-step">
                            <span class="cerebroly-step-number">4</span>
                            <h4><?php esc_html_e('Generate', 'cerebroly'); ?></h4>
                            <p><?php esc_html_e('Creates precise responses based on the enriched context.', 'cerebroly'); ?></p>
                        </div>
                    </div>



                    <div class="cerebroly-overview-actions">
                        <button type="button" class="button button-primary js-go-to-tab" data-tab="embedding"><?php esc_html_e('Configure Embedding Settings', 'cerebroly'); ?></button>
                        <button type="button" class="button button-primary js-go-to-tab" data-tab="test"><?php esc_html_e('Test RAG System', 'cerebroly'); ?></button>
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

        <div id="embedding" class="tab-pane">
            <div class="cerebroly-section-card">
                <h2><?php esc_html_e('Content Embedding Settings', 'cerebroly'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Configure how your content is processed and indexed in the RAG system. These settings affect the quality and precision of document retrieval.', 'cerebroly'); ?>
                </p>

                <form method="post" action="options.php" class="cerebroly-settings-form">
                    <?php settings_fields('cerebroly_rag_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Embedding Model', 'cerebroly'); ?></th>
                            <td>
                                <select name="cerebroly_rag_embedding_model">
                                    <?php foreach ($embedding_models as $id => $label): ?>
                                    <option value="<?php echo esc_attr($id); ?>"
                                        <?php selected($selected_embedding_model, $id); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>


                                <p class="description"><?php esc_html_e('Model used to convert text into vectors. More advanced models offer better precision at higher cost.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Chunk Size', 'cerebroly'); ?></th>
                            <td>
                                <input type="number" name="cerebroly_rag_chunk_size"
                                    value="<?php echo esc_attr(get_option('cerebroly_rag_chunk_size', 1000)); ?>" min="100"
                                    max="8000" step="100">
                                <p class="description"><?php esc_html_e('Maximum number of characters per chunk. Smaller chunks allow more precise searches but generate more fragments.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Chunk Overlap', 'cerebroly'); ?></th>
                            <td>
                                <input type="number" name="cerebroly_rag_chunk_overlap"
                                    value="<?php echo esc_attr(get_option('cerebroly_rag_chunk_overlap', 200)); ?>" min="0"
                                    max="1000" step="50">
                                <p class="description"><?php esc_html_e('Number of characters that overlap between consecutive chunks. Helps maintain context between chunks.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Content Sources', 'cerebroly'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cerebroly_rag_include_post" value="1"
                                            <?php checked(get_option('cerebroly_rag_include_post', 1), 1); ?>>
                                        <?php esc_html_e('Include Posts', 'cerebroly'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="cerebroly_rag_include_page" value="1"
                                            <?php checked(get_option('cerebroly_rag_include_page', 1), 1); ?>>
                                        <?php esc_html_e('Include Pages', 'cerebroly'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="cerebroly_rag_include_product" value="1"
                                            <?php checked(get_option('cerebroly_rag_include_product', 0), 1); ?>>
                                        <?php esc_html_e('Include WooCommerce Products', 'cerebroly'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="cerebroly_rag_include_files" value="1"
                                            <?php checked(get_option('cerebroly_rag_include_files', 1), 1); ?>>
                                        <?php esc_html_e('Include uploaded files', 'cerebroly'); ?>
                                    </label>
                                </fieldset>
                                <p class="description"><?php esc_html_e('Select which content sources to include in the RAG index.', 'cerebroly'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(esc_html__('Save Settings', 'cerebroly')); ?>
                </form>
            </div>

            <div class="cerebroly-section-card">
                <h3><?php esc_html_e('Manual Indexing', 'cerebroly'); ?></h3>
                <p class="description">
                    <?php esc_html_e('You can manually start content indexing to update the RAG knowledge base. This process may take several minutes depending on the amount of content.', 'cerebroly'); ?>
                </p>

                <div id="manual-indexing-form">
                    <div class="indexing-options">
                        <label>
                            <input type="checkbox" id="force-reindex"> <?php esc_html_e('Force full reindexing', 'cerebroly'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('This will delete all existing embeddings and reindex all content.', 'cerebroly'); ?></p>
                    </div>

                    <div class="cerebroly-indexing-actions">
                        <button id="start-manual-indexing" class="button button-primary">
                            <?php esc_html_e('Start Custom Indexing', 'cerebroly'); ?>
                        </button>
                    </div>
                </div>

                <div id="indexing-progress" style="display:none;">
                    <h4><?php esc_html_e('Indexing Progress', 'cerebroly'); ?></h4>
                    <div class="cerebroly-progress-container">
                        <div class="cerebroly-progress-bar" style="width: 0%;"></div>
                    </div>
                    <div class="cerebroly-progress-status">
                        <span class="cerebroly-progress-text"><?php esc_html_e('Preparing indexing...', 'cerebroly'); ?></span>
                        <span class="cerebroly-progress-percent">0%</span>
                    </div>
                    <div id="indexing-details" class="cerebroly-progress-details"></div>
                </div>
            </div>
        </div>

        <div id="retrieval" class="tab-pane">
            <div class="cerebroly-section-card">
                <h2><?php esc_html_e('Retrieval Configuration', 'cerebroly'); ?></h2>
                <p class="description">
                    <?php esc_html_e('These settings control how documents are retrieved from the knowledge base when a user sends a query. They affect the relevance and number of documents included in the context.', 'cerebroly'); ?>
                </p>

                <form method="post" action="options.php" class="cerebroly-settings-form">
                    <?php settings_fields('cerebroly_rag_retrieval_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Documents to Retrieve', 'cerebroly'); ?></th>
                            <td>
                                <input type="number" name="cerebroly_rag_top_k"
                                    value="<?php echo esc_attr(get_option('cerebroly_rag_top_k', 5)); ?>" min="1" max="20">
                                <p class="description"><?php esc_html_e('Number of most relevant fragments to include in the context. More fragments provide more information but may dilute relevance.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Similarity Threshold', 'cerebroly'); ?></th>
                            <td>
                                <input type="range" name="cerebroly_rag_similarity_threshold"
                                    value="<?php echo esc_attr(get_option('cerebroly_rag_similarity_threshold', 0.75)); ?>"
                                    min="0" max="1" step="0.01" class="cerebroly-range-slider" id="similarity-threshold">
                                <span
                                    id="similarity-value"><?php echo esc_attr(get_option('cerebroly_rag_similarity_threshold', 0.75)); ?></span>
                                <p class="description"><?php esc_html_e('Minimum similarity threshold to include a document. Higher values require greater relevance.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Search Method', 'cerebroly'); ?></th>
                            <td>
                                <select name="cerebroly_rag_search_method">
                                    <option value="cosine"
                                        <?php selected(get_option('cerebroly_rag_search_method', 'cosine'), 'cosine'); ?>>
                                        <?php esc_html_e('Cosine Similarity (Recommended)', 'cerebroly'); ?></option>
                                    <option value="dot_product"
                                        <?php selected(get_option('cerebroly_rag_search_method'), 'dot_product'); ?>>
                                        <?php esc_html_e('Dot Product', 'cerebroly'); ?></option>
                                    <option value="euclidean"
                                        <?php selected(get_option('cerebroly_rag_search_method'), 'euclidean'); ?>>
                                        <?php esc_html_e('Euclidean Distance', 'cerebroly'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Mathematical method to calculate similarity between queries and documents.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Query Rewriting', 'cerebroly'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cerebroly_rag_query_rewriting" value="1"
                                        <?php checked(get_option('cerebroly_rag_query_rewriting', 0), 1); ?>>
                                    <?php esc_html_e('Enable query rewriting', 'cerebroly'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Uses AI to reformulate the user\'s query to improve retrieval. Adds an additional step but may improve results.', 'cerebroly'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(esc_html__('Save Settings', 'cerebroly')); ?>
                </form>
            </div>
        </div>

        <div id="generation" class="tab-pane">
            <div class="cerebroly-section-card">
                <h2><?php esc_html_e('Response Generation Settings', 'cerebroly'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Configure how the AI generates responses using the retrieved content. These settings affect the style, length, and characteristics of the answers provided to users.', 'cerebroly'); ?>
                </p>

                <form method="post" action="options.php" class="cerebroly-settings-form">
                    <?php settings_fields('cerebroly_rag_generation_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('LLM Model', 'cerebroly'); ?></th>
                            <td>

                                <select name="cerebroly_rag_llm_model">
                                    <optgroup label="<?php esc_attr_e('Base Models', 'cerebroly'); ?>">
                                        <?php foreach ($base_models as $model): ?>
                                        <option value="<?php echo esc_attr($model['id']); ?>"
                                            <?php selected($selected_model, $model['id']); ?>>
                                            <?php echo esc_html($model['id']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Fine-Tuned Models', 'cerebroly'); ?>">
                                        <?php foreach ($fine_tuned_models as $model): ?>
                                        <option value="<?php echo esc_attr($model['id']); ?>"
                                            <?php selected($selected_model, $model['id']); ?>>
                                            <?php echo esc_html($model['id']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <p class="description"><?php esc_html_e('Model used to generate responses. More advanced models offer better understanding but at higher cost.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Temperature', 'cerebroly'); ?></th>
                            <td>
                                <input type="range" name="cerebroly_rag_temperature"
                                    value="<?php echo esc_attr(get_option('cerebroly_rag_temperature', 0.3)); ?>" min="0"
                                    max="1" step="0.1" class="cerebroly-range-slider" id="temperature-slider">
                                <span
                                    id="temperature-value"><?php echo esc_attr(get_option('cerebroly_rag_temperature', 0.3)); ?></span>
                                <p class="description"><?php esc_html_e('Controls response creativity. Lower values generate more deterministic and precise responses.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Maximum Length', 'cerebroly'); ?></th>
                            <td>
                                <input type="number" name="cerebroly_rag_max_tokens"
                                    value="<?php echo esc_attr(get_option('cerebroly_rag_max_tokens', 1000)); ?>" min="100"
                                    max="4000" step="100">
                                <p class="description"><?php esc_html_e('Maximum token limit in the response.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('System Prompt', 'cerebroly'); ?></th>
                            <td>
                                <textarea name="cerebroly_rag_system_prompt" rows="6"
                                    cols="50"><?php echo esc_textarea(get_option('cerebroly_rag_system_prompt', esc_html__('You are a specialized assistant for the website. Respond to queries based only on the information in the following context. If the information is not in the context, honestly indicate that you don\'t have that information.', 'cerebroly'))); ?></textarea>
                                <p class="description"><?php esc_html_e('Base instructions for the model. Customize according to your specific needs.', 'cerebroly'); ?></p>
                            </td>
                        </tr>

                    </table>

                    <?php submit_button(esc_html__('Save Settings', 'cerebroly')); ?>
                </form>
            </div>
        </div>

        <div id="preview" class="tab-pane">
            <div class="cerebroly-section-card">
                <h2><?php esc_html_e('Indexed Content Preview', 'cerebroly'); ?></h2>
                <p class="description">
                    <?php esc_html_e('This section shows a preview of content that has been or will be indexed in the RAG system. Use the filters to explore different content types.', 'cerebroly'); ?>
                </p>

               

                <div class="cerebroly-preview-filters">
                    <select id="content-type-filter">
                        <option value="all"><?php esc_html_e('All types', 'cerebroly'); ?></option>
                        <option value="post"><?php esc_html_e('Posts and pages', 'cerebroly'); ?></option>
                        <option value="file"><?php esc_html_e('Uploaded files', 'cerebroly'); ?></option>
                        <option value="training"><?php esc_html_e('Training data', 'cerebroly'); ?></option>
                    </select>

                    <input type="text" id="content-search" placeholder="<?php esc_attr_e('Search in content...', 'cerebroly'); ?>" class="regular-text">

                    <button id="load-preview-content" class="button"><?php esc_html_e('Load Preview', 'cerebroly'); ?></button>
                </div>

                <div class="cerebroly-preview-stats">
                    <div class="cerebroly-stat-box">
                        <h4><?php esc_html_e('Total Elements', 'cerebroly'); ?></h4>
                        <span id="total-content-count">-</span>
                    </div>
                    <div class="cerebroly-stat-box">
                        <h4><?php esc_html_e('Posts & Pages', 'cerebroly'); ?></h4>
                        <span id="posts-count">-</span>
                    </div>
                    <div class="cerebroly-stat-box">
                        <h4><?php esc_html_e('Files', 'cerebroly'); ?></h4>
                        <span id="files-count">-</span>
                    </div>
                    <div class="cerebroly-stat-box">
                        <h4><?php esc_html_e('Training', 'cerebroly'); ?></h4>
                        <span id="training-count">-</span>
                    </div>
                </div>

                <div id="content-preview-loading" style="text-align: center; padding: 20px; display: none;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <p><?php esc_html_e('Loading content...', 'cerebroly'); ?></p>
                </div>

                <div id="content-preview-container" class="cerebroly-preview-list"></div>

                <div id="content-preview-pagination" class="cerebroly-pagination"></div>
            </div>
        </div>

        <div id="test" class="tab-pane">
            <div class="cerebroly-section-card">
                <h2><?php esc_html_e('Test RAG System', 'cerebroly'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Test the RAG system with sample queries to see how it responds. This helps you evaluate and fine-tune the system before deploying it for users.', 'cerebroly'); ?>
                </p>

                <div class="cerebroly-test-input">
                    <textarea id="rag-test-query" placeholder="<?php esc_attr_e('Enter your query here...', 'cerebroly'); ?>" rows="4"></textarea>
                    <button id="run-rag-test" class="button button-primary"><?php esc_html_e('Submit Query', 'cerebroly'); ?></button>
                </div>

                <div class="cerebroly-test-result" style="display: none;">
                    <h3><?php esc_html_e('RAG Process', 'cerebroly'); ?></h3>

                    <div class="cerebroly-test-step">
                        <h4><?php esc_html_e('1. Processed Query', 'cerebroly'); ?></h4>
                        <div id="processed-query" class="cerebroly-result-box"></div>
                    </div>

                    <div class="cerebroly-test-step">
                        <h4><?php esc_html_e('2. Retrieved Documents', 'cerebroly'); ?></h4>
                        <div id="retrieved-docs" class="cerebroly-result-box"></div>
                    </div>

                    <div class="cerebroly-test-step">
                        <h4><?php esc_html_e('3. Generated Response', 'cerebroly'); ?></h4>
                        <div id="generated-response" class="cerebroly-result-box"></div>
                    </div>
                </div>
            </div>

            <div class="cerebroly-section-card">
                <h3><?php esc_html_e('Query History', 'cerebroly'); ?></h3>
                <div id="query-history" class="cerebroly-history-list">
                    <p><?php esc_html_e('Query history will appear here.', 'cerebroly'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* General Styles */
.cerebroly-overview-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 20px;
}

.cerebroly-overview-stats,
.cerebroly-overview-explanation,
.cerebroly-section-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    padding: 20px;
    margin-bottom: 20px;
    flex: 1;
    min-width: 300px;
}

/* Process Steps */
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

/* Status Indicators */
.cerebroly-status-ok {
    color: #46b450;
    font-weight: bold;
}

.cerebroly-status-error {
    color: #dc3232;
    font-weight: bold;
}

/* Tab Content */
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

/* Action Buttons */
.cerebroly-overview-actions,
.cerebroly-indexing-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Progress Bar */
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
    font-size: 14px;
    color: #666;
}

.cerebroly-progress-details {
    margin-top: 10px;
    padding: 10px;
    background: #f8f8f8;
    border-radius: 4px;
}

/* Preview Areas */
.cerebroly-preview-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}

.cerebroly-preview-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.cerebroly-stat-box {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    border-radius: 4px;
    padding: 10px 15px;
    flex: 1;
    min-width: 120px;
    text-align: center;
}

.cerebroly-stat-box h4 {
    margin: 0 0 5px 0;
    color: #555;
}

.cerebroly-stat-box span {
    font-size: 18px;
    font-weight: bold;
}

.cerebroly-preview-list {
    border: 1px solid #e2e4e7;
    border-radius: 4px;
}

.cerebroly-pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
}

/* Test Area */
.cerebroly-test-input {
    margin-bottom: 20px;
}

.cerebroly-test-input textarea {
    width: 100%;
    margin-bottom: 10px;
}

.cerebroly-test-step {
    margin-bottom: 20px;
}

.cerebroly-result-box {
    background: #f8f8f8;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    max-height: 200px;
    overflow-y: auto;
}

.cerebroly-history-list {
    border: 1px solid #e2e4e7;
    max-height: 300px;
    overflow-y: auto;
    padding: 15px;
}

/* Range Sliders */
.cerebroly-range-slider {
    width: 300px;
    vertical-align: middle;
}

/* Settings Form */
.cerebroly-settings-form {
    margin-top: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab Navigation with localStorage persistence
    const TAB_STORAGE_KEY = 'cerebroly_rag_active_tab';

    $('.nav-tab').on('click', function(e) {
        e.preventDefault();

        // Update active tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show corresponding content
        const target = $(this).attr('href');
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');

        // Save to localStorage
        try {
            localStorage.setItem(TAB_STORAGE_KEY, target);
        } catch (e) {
            console.warn('Could not save tab state:', e);
        }
    });

    try {
        const storedTab = localStorage.getItem(TAB_STORAGE_KEY);
        if (storedTab) {
            const $targetTab = $(`.nav-tab[href="${storedTab}"]`);
            if ($targetTab.length) {
                $targetTab.click();
            }
        }
    } catch (e) {
        console.warn('Could not restore tab state:', e);
    }

    // Enable tab navigation via buttons
    $('.js-go-to-tab').on('click', function() {
        const targetTab = $(this).data('tab');
        $(`.nav-tab[href="#${targetTab}"]`).click();
    });

    // Update slider values display
    $('#temperature-slider').on('input', function() {
        $('#temperature-value').text($(this).val());
    });

    $('#similarity-threshold').on('input', function() {
        $('#similarity-value').text($(this).val());
    });

    // Initialize RAG System
    $('#initialize-rag').on('click', function() {
        if (confirm(
                'Are you sure you want to initialize the RAG system? This will create new tables in the database.'
                )) {
            const $button = $(this);
            $button.prop('disabled', true).text('Initializing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ajax_initialize_rag',
                    security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('RAG system successfully initialized. The page will reload.');
                        location.reload();
                    } else {
                        alert('Error initializing: ' + response.data.message);
                        $button.prop('disabled', false).text('Initialize RAG System');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error Details:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Full Response:', xhr.responseText);

                    alert('Connection error while initializing the RAG system.');
                    $button.prop('disabled', false).text('Initialize RAG System');
                }
            });
        }
    });

    // Reset RAG System
    $('#reset-rag').on('click', function() {
        if (confirm(
                'Are you sure you want to reset the RAG system? This will delete all existing embeddings.'
                )) {
            if (confirm('WARNING: This action cannot be undone. Do you want to continue?')) {
                const $button = $(this);
                $button.prop('disabled', true).text('Resetting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cerebroly_reset_rag',
                        security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('RAG system successfully reset. The page will reload.');
                            location.reload();
                        } else {
                            alert('Error resetting: ' + response.data.message);
                            $button.prop('disabled', false).text('Reset RAG System');
                        }
                    },
                    error: function() {
                        alert('Connection error while resetting the RAG system.');
                        $button.prop('disabled', false).text('Reset RAG System');
                    }
                });
            }
        }
    });

    // Manual Indexing
    $('#start-manual-indexing').on('click', function() {
        const forceReindex = $('#force-reindex').is(':checked');
        const $button = $(this);
        const $progress = $('#indexing-progress');

        $button.prop('disabled', true).text('Starting...');
        $progress.show();
        $('.cerebroly-progress-bar').css('width', '0%');
        $('.cerebroly-progress-text').text('Initializing indexing...');
        $('.cerebroly-progress-percent').text('0%');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cerebroly_ajax_manual_indexing',
                security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>',
                sources: getSelectedSources(),
                force_reindex: forceReindex
            },
            success: function(response) {
                if (response.success) {
                    console.log('Indexing started. Job ID:', response.data.job_id);
                    $button.hide();
                    $progress.show();

                    // Start checking progress
                    checkManualIndexingProgress(response.data.job_id);
                } else {
                    console.error('Error starting indexing:', response.data.message);
                    alert('Error starting indexing: ' + response.data.message);
                    $button.prop('disabled', false).text('Start Custom Indexing');
                    $progress.hide();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('Connection error while starting indexing.');
                $button.prop('disabled', false).text('Start Custom Indexing');
                $progress.hide();
            }
        });
    });

    // Helper function to get selected content sources
function getSelectedSources() {
    const sources = [];
    
    if ($('input[name="cerebroly_rag_include_post"]').is(':checked')) {     
        sources.push('posts');
    }
    if ($('input[name="cerebroly_rag_include_product"]').is(':checked')) {
        sources.push('products');
    }
    if ($('input[name="cerebroly_rag_include_files"]').is(':checked')) {  
        sources.push('files');
    }
    return sources;
}

    // Check indexing progress
    function checkManualIndexingProgress(jobId) {
        const progressInterval = setInterval(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cerebroly_ajax_check_manual_indexing_progress',
                    security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>',
                    job_id: jobId
                },
                success: function(response) {
                    if (response.success) {
                        // Update progress UI
                        const progress = response.data.progress;
                        $('.cerebroly-progress-bar').css('width', progress + '%');
                        $('.cerebroly-progress-text').text(response.data.status);
                        $('.cerebroly-progress-percent').text(progress + '%');

                        // Display indexing details
                        let details = "Sources: " + response.data.sources.join(', ') +
                            "<br>";
                        details += "Processing " + response.data.processed_items + " of " +
                            response.data.total_items + " items.";
                        $('#indexing-details').html(details);

                        // If completed, stop checking
                        if (response.data.completed) {
                            clearInterval(progressInterval);

                            setTimeout(function() {
                                alert('Indexing completed successfully.');
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        clearInterval(progressInterval);
                        console.error('Error checking progress:', response.data.message);
                        alert('Error checking progress: ' + response.data.message);
                        $('#start-manual-indexing').prop('disabled', false).show().text(
                            'Start Custom Indexing');
                        $('#indexing-progress').hide();
                    }
                },
                error: function() {
                    clearInterval(progressInterval);
                    console.error('Connection error while checking indexing progress');
                    alert('Connection error while checking progress.');
                    $('#start-manual-indexing').prop('disabled', false).show().text(
                        'Start Custom Indexing');
                    $('#indexing-progress').hide();
                }
            });
        }, 2000); // Check every 2 seconds
    }

    // Content Preview Functionality
    $('#load-preview-content').on('click', function() {
        const $button = $(this);
        const $container = $('#content-preview-container');
        const $loading = $('#content-preview-loading');
        const contentType = $('#content-type-filter').val();
        const searchQuery = $('#content-search').val();

        $button.prop('disabled', true);
        $container.empty();
        $loading.show();

        // Load statistics and first page
        loadContentStats();
        loadContentPage(1);
    });

    function loadContentStats() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cerebroly_get_rag_content_stats',
                security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>',
                content_type: $('#content-type-filter').val(),
                search_query: $('#content-search').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#total-content-count').text(response.data.total || 0);
                    $('#posts-count').text(response.data.post_count || 0);
                    $('#files-count').text(response.data.file_count || 0);
                    $('#training-count').text(response.data.training_count || 0);
                }
            }
        });
    }


    function createEfficientPagination(currentPage, totalPages, maxVisiblePages = 7) {
        let paginationHtml = '';
        
        // If no pages or only one page, return empty
        if (totalPages <= 1) {
            return '';
        }
        
        // First page button
        if (currentPage > 1) {
            paginationHtml += `<button class="button" data-page="1" title="First page">«« 1</button>`;
        }
        
        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<button class="button" data-page="${currentPage - 1}" title="Previous page">‹</button>`;
        }
        
        // Calculate page range to display
        let startPage, endPage;
        
        if (totalPages <= maxVisiblePages) {
            // Show all pages if total is less than max visible
            startPage = 1;
            endPage = totalPages;
        } else {
            // Calculate window centered on current page
            const halfVisible = Math.floor(maxVisiblePages / 2);
            
            if (currentPage <= halfVisible) {
                // At the beginning
                startPage = 1;
                endPage = maxVisiblePages;
            } else if (currentPage + halfVisible >= totalPages) {
                // At the end
                startPage = totalPages - maxVisiblePages + 1;
                endPage = totalPages;
            } else {
                // In the middle
                startPage = currentPage - halfVisible;
                endPage = currentPage + halfVisible;
            }
        }
        
        // Show ellipsis if there are pages before the range
        if (startPage > 1) {
            paginationHtml += `<span class="pagination-ellipsis">...</span>`;
        }
        
        // Show pages in range
        for (let i = startPage; i <= endPage; i++) {
            if (i === currentPage) {
                paginationHtml += `<button class="button button-primary current" disabled>${i}</button>`;
            } else {
                paginationHtml += `<button class="button" data-page="${i}">${i}</button>`;
            }
        }
        
        // Show ellipsis if there are pages after the range
        if (endPage < totalPages) {
            paginationHtml += `<span class="pagination-ellipsis">...</span>`;
        }
        
        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<button class="button" data-page="${currentPage + 1}" title="Next page">›</button>`;
        }
        
        // Last page button
        if (currentPage < totalPages) {
            paginationHtml += `<button class="button" data-page="${totalPages}" title="Last page">${totalPages} »»</button>`;
        }
        
        return paginationHtml;
    }

    function loadContentPage(page) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cerebroly_get_rag_content_preview',
                security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>',
                content_type: $('#content-type-filter').val(),
                search_query: $('#content-search').val(),
                page: page
            },
            success: function(response) {
                const $container = $('#content-preview-container');
                const $loading = $('#content-preview-loading');
                const $pagination = $('#content-preview-pagination');

                $loading.hide();
                $('#load-preview-content').prop('disabled', false);

                if (response.success) {
                    if (response.data.items.length === 0) {
                        $container.html('<p>No items found matching the search criteria.</p>');
                        $pagination.empty();
                        return;
                    }

                    // Show items
                    let html = '';
                    response.data.items.forEach(item => {
                        html += `
                            <div class="cerebroly-preview-item">
                                <div class="cerebroly-preview-header">
                                    <div class="cerebroly-preview-title">${item.title}</div>
                                    <div class="cerebroly-preview-type ${item.type}">${item.type_label}</div>
                                </div>
                                <div class="cerebroly-preview-content">${item.content}</div>
                            </div>
                        `;
                    });
                    $container.html(html);

                    const totalPages = Math.ceil(response.data.total / response.data.per_page);
                    const currentPage = parseInt(response.data.page);
                    const paginationHtml = createEfficientPagination(currentPage, totalPages);
                    $pagination.html(paginationHtml);

                    // Add pagination info
                    if (totalPages > 1) {
                        const pageInfo = `
                            <div class="pagination-info" style="text-align: center; margin-top: 10px; color: #666;">
                                Page ${currentPage} of ${totalPages} (${response.data.total} items total)
                            </div>
                        `;
                        $pagination.append(pageInfo);
                    }

                } else {
                    $container.html(`<p>Error: ${response.data.message}</p>`);
                    $pagination.empty();
                }
            },
            error: function() {
                $('#content-preview-loading').hide();
                $('#load-preview-content').prop('disabled', false);
                $('#content-preview-container').html(
                    '<p>Connection error while loading preview.</p>');
                $('#content-preview-pagination').empty();
            }
        });
    }

    // Handle pagination clicks
    $(document).on('click', '#content-preview-pagination button', function() {
        const page = $(this).data('page');
        if (page) {
            $('#content-preview-container').empty();
            $('#content-preview-loading').show();
            loadContentPage(page);
        }
    });

    // Search on Enter key
    $('#content-search').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $('#load-preview-content').click();
            e.preventDefault();
        }
    });

    // Test RAG System
    $('#run-rag-test').on('click', function() {
        const query = $('#rag-test-query').val().trim();

        if (query === '') {
            alert('Please enter a query.');
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true).text('Processing...');
        $('.cerebroly-test-result').hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cerebroly_test_rag',
                security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_test_nonce")); ?>',
                query: query
            },
            success: function(response) {
                if (response.success) {
                    // Show results
                    $('#processed-query').text(response.data.processed_query || query);

                    // Retrieved documents
                    let docsHtml = '';
                    if (response.data.documents && response.data.documents.length > 0) {
                        $.each(response.data.documents, function(i, doc) {
                            docsHtml += `<div class="retrieved-doc">
                                <h5>${doc.title} (Similarity: ${(doc.similarity * 100).toFixed(2)}%)</h5>
                                <p>${doc.content}</p>
                            </div>`;
                        });
                    } else {
                        docsHtml = '<p>No relevant documents found.</p>';
                    }
                    $('#retrieved-docs').html(docsHtml);

                    // Generated response
                    $('#generated-response').html(response.data.response.replace(/\n/g,
                        '<br>'));

                    // Show results section
                    $('.cerebroly-test-result').show();

                    // Add to history
                    addToHistory(query, response.data.response);
                } else {
                    alert('Error: ' + response.data.message);
                }
                $button.prop('disabled', false).text('Submit Query');
            },
            error: function() {
                alert('Connection error while testing the RAG system.');
                $button.prop('disabled', false).text('Submit Query');
            }
        });
    });

    // Add to history function
    function addToHistory(query, response) {
        const timestamp = new Date().toLocaleTimeString();
        const historyItem = `
            <div class="history-item">
                <p><strong>${timestamp} - Query:</strong> ${query}</p>
                <p><strong>Response:</strong> ${response.substring(0, 100)}${response.length > 100 ? '...' : ''}</p>
            </div>
        `;

        $('#query-history').prepend(historyItem);
    }

    // Add additional styles for content preview items
    $('<style>')
        .text(`
            .cerebroly-preview-item {
                border: 1px solid #e2e4e7;
                border-radius: 4px;
                margin-bottom: 15px;
                overflow: hidden;
            }
            
            .cerebroly-preview-header {
                background: #f8f9fa;
                padding: 10px 15px;
                border-bottom: 1px solid #e2e4e7;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .cerebroly-preview-title {
                font-weight: bold;
                flex: 1;
            }
            
            .cerebroly-preview-type {
                background: #e9ecef;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 12px;
            }
            
            .cerebroly-preview-type.post {
                background: #d1ecf1;
                color: #0c5460;
            }
            
            .cerebroly-preview-type.file {
                background: #d4edda;
                color: #155724;
            }
            
            .cerebroly-preview-type.training {
                background: #f8d7da;
                color: #721c24;
            }
            
            .cerebroly-preview-content {
                padding: 15px;
                max-height: 200px;
                overflow-y: auto;
                background: #fff;
            }
            
            .history-item {
                padding: 10px;
                border-bottom: 1px solid #e2e4e7;
            }
            
            .history-item:last-child {
                border-bottom: none;
            }
            
            /* Responsive adjustments */
            @media (max-width: 782px) {
                .cerebroly-process-steps {
                    flex-direction: column;
                }
                
                .cerebroly-range-slider {
                    width: 100%;
                    max-width: 300px;
                }
                
                .cerebroly-preview-filters {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .cerebroly-preview-filters > * {
                    margin-bottom: 10px;
                    width: 100%;
                }
            }
        `)
        .appendTo('head');

    // Initialize any additional components needed on page load
    function initializeComponents() {
        // Check for active indexing jobs on page load
        checkForActiveIndexingJobs();

        // Pre-load content stats if we're on the preview tab
        if ($('#preview').hasClass('active')) {
            loadContentStats();
            loadContentPage(1);
        }
    }

    // Check if there are any active indexing jobs
    function checkForActiveIndexingJobs() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cerebroly_check_indexing_progress',
                security: '<?php echo esc_js(wp_create_nonce("cerebroly_rag_nonce")); ?>'
            },
            success: function(response) {
                if (response.success && response.data && !response.data.completed) {
                    // Show progress bar and update it
                    $('#indexing-progress').show();
                    $('#start-manual-indexing').hide();

                    // Set initial values
                    $('.cerebroly-progress-bar').css('width', response.data.progress + '%');
                    $('.cerebroly-progress-text').text(response.data.status);
                    $('.cerebroly-progress-percent').text(response.data.progress + '%');

                    // Start monitoring progress
                    checkManualIndexingProgress(response.data.job_id);
                }
            }
        });
    }

    // Run initialization on document ready
    initializeComponents();
});
</script>