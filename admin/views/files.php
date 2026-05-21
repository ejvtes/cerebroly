<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap">
    <h1><?php esc_html_e('cerebroly - Files', 'cerebroly'); ?></h1>
    
    <?php
if (isset($_GET['upload_success'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('File uploaded and saved successfully!', 'cerebroly') . '</p></div>';
} elseif (isset($_GET['delete_success'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('File deleted successfully!', 'cerebroly') . '</p></div>';
} elseif (isset($_GET['upload_error'])) {
    // Decodificar FUERA de la función de traducción
    $upload_error_message = urldecode($_GET['upload_error']);
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($upload_error_message) . '</p></div>';
} elseif (isset($_GET['delete_error'])) {
    // Decodificar FUERA de la función de traducción
    $delete_error_message = urldecode($_GET['delete_error']);
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($delete_error_message) . '</p></div>';
}
?>

    
    <?php settings_errors('cerebroly_file_upload'); ?>

    

    <div class="metabox-holder">
        <div class="postbox-container" style="width: 100%;">
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <span><?php esc_html_e('Upload File', 'cerebroly'); ?></span>
                    </h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Text files to include in your knowledge base.', 'cerebroly'); ?></p>
                    
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('cerebroly_upload_file'); ?>
                        <input type="hidden" name="action" value="cerebroly_upload_file">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('File', 'cerebroly'); ?></th>
                                <td>
                                    <input type="file" name="cerebroly_file" accept=".txt" required>
                                    <p class="description"><?php esc_html_e('Allowed formats: TXT', 'cerebroly'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Upload File', 'cerebroly'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <span><?php esc_html_e('Uploaded Files', 'cerebroly'); ?></span>
                    </h2>
                </div>
                <div class="inside">
                    <?php if (empty($files)): ?>
                        <p><?php esc_html_e('No files have been uploaded yet.', 'cerebroly'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'cerebroly'); ?></th>
                                    <th><?php esc_html_e('Type', 'cerebroly'); ?></th>
                                    <th><?php esc_html_e('Size', 'cerebroly'); ?></th>
                                    <th><?php esc_html_e('Upload Date', 'cerebroly'); ?></th>
                                    <th><?php esc_html_e('Actions', 'cerebroly'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td><?php echo esc_html($file['filename']); ?></td>
                                        <td><?php echo esc_html($file['filetype']); ?></td>
                                        <td><?php echo esc_html(size_format($file['filesize'])); ?></td>
                                        <td><?php echo esc_html($file['uploaded']); ?></td>
                                        <td>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                <?php wp_nonce_field('cerebroly_delete_file'); ?>
                                                <input type="hidden" name="action" value="cerebroly_delete_file">
                                                <input type="hidden" name="file_id" value="<?php echo intval($file['id']); ?>">
                                                <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this file?', 'cerebroly'); ?>');"><?php esc_html_e('Delete', 'cerebroly'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <span><?php esc_html_e('What files should I upload?', 'cerebroly'); ?></span>
                    </h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Upload files containing relevant information that you want the chatbot to use when answering questions, such as:', 'cerebroly'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Policy documents', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('User manuals', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('Frequently asked questions', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('Technical documentation', 'cerebroly'); ?></li>
                        <li><?php esc_html_e('Any information not already included in your posts and pages', 'cerebroly'); ?></li>
                    </ul>
                    
                    <div class="cerebroly-note">
                        <p><strong><?php esc_html_e('Note:', 'cerebroly'); ?></strong> <?php esc_html_e('After uploading or deleting files, you\'ll need to either:', 'cerebroly'); ?></p>
                        <ul>
                            <li><strong><?php esc_html_e('Fine-tuning mode:', 'cerebroly'); ?></strong> <?php esc_html_e('Train a new model for changes to be reflected in the chat', 'cerebroly'); ?></li>
                            <li><strong><?php esc_html_e('RAG mode:', 'cerebroly'); ?></strong> <?php esc_html_e('Reindex your content through the RAG configuration page', 'cerebroly'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
/* Basic metabox styles */
.metabox-holder {
    margin-bottom: 20px;
    max-width: 100%;
    overflow: hidden;
    margin-right: 20px;
}

.postbox-container {
    max-width: 100%;
    overflow: hidden;
}

.postbox .inside {
    padding: 15px;
    max-width: 100%;
    overflow: hidden;
}

.cerebroly-note {
    background: #f8f8f8;
    border-left: 4px solid #0073aa;
    padding: 12px 15px;
    margin-top: 15px;
}

.postbox .inside ul {
    list-style-type: disc;
    margin-left: 20px;
}

.button-small {
    margin-right: 5px;
}

/* Responsive */
@media (max-width: 768px) {
    .metabox-holder,
    .postbox-container,
    .postbox .inside {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }
}
</style>