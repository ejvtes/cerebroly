<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class CEREBROLY_File_Handler
{
    /**
     * @param array $file  Entry from $_FILES.
     * @return array|WP_Error
     */
    public function upload_file($file)
    {
        // Check if the file exists and there are no errors
        if (!isset($file) || $file['error'] != UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('Error uploading the file: ', 'cerebroly') . $this->get_upload_error_message($file['error']));
        }

        // Validate file type (only plain text files allowed)
        $allowed_types = array('text/plain');
        $file_type = $this->get_file_mime_type($file);

        if (!in_array($file_type, $allowed_types)) {
            add_settings_error('cerebroly_file_upload', 'invalid_file_type', __('Invalid file type. Only .txt files are allowed.', 'cerebroly'), 'error');
            return new WP_Error('invalid_type', __('Invalid file type. Only .txt files are allowed.', 'cerebroly'));
        }

        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $cerebroly_dir = $upload_dir['basedir'] . '/cerebroly-files';

        if (!file_exists($cerebroly_dir)) {
            wp_mkdir_p($cerebroly_dir);
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $wp_filesystem->put_contents($cerebroly_dir . '/.htaccess', "deny from all\n");
            $wp_filesystem->put_contents($cerebroly_dir . '/index.php', '<?php // Silence is golden.');
        }

        // Sanitize file name
        $filename = sanitize_file_name($file['name']);
        $target_path = $cerebroly_dir . '/' . $filename;

        // Move the uploaded file using WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if (!$wp_filesystem->move($file['tmp_name'], $target_path)) {
            return new WP_Error('move_error', __('Error moving the uploaded file.', 'cerebroly'));
        }

        // Extract content from the text file
        $content = $this->extract_text_content($target_path);

        // Save to the database
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_files');

        $result = $wpdb->insert(
            $table_name,
            array(
                'filename' => $filename,
                'filetype' => $file_type,
                'filepath' => str_replace($upload_dir['basedir'], '', $target_path),
                'filesize' => $file['size'],
                'content' => $content,
                'uploaded' => current_time('mysql')
            )
        );

        if (!$result) {
            return new WP_Error('db_error', __('Error saving file information to the database.', 'cerebroly'));
        }

        $file_id = $wpdb->insert_id;

        // Cache the file information
        $file_data = array(
            'id' => $file_id,
            'filename' => $filename,
            'filetype' => $file_type,
            'filepath' => str_replace($upload_dir['basedir'], '', $target_path),
            'filesize' => $file['size'],
            'content' => $content,
            'uploaded' => current_time('mysql')
        );

        wp_cache_set('cerebroly_file_' . $file_id, $file_data, 'cerebroly_files', HOUR_IN_SECONDS);

        // Invalidate files list cache
        wp_cache_delete('cerebroly_files_list', 'cerebroly_files');

        return array(
            'id' => $file_id,
            'filename' => $filename,
            'filetype' => $file_type,
            'filesize' => size_format($file['size']),
            'content_length' => strlen($content)
        );
    }


    /**
     * Get the real MIME type of the file.
     *
     * @param array $file File information
     * @return string MIME type
     */
    private function get_file_mime_type($file)
    {
        // First we try with fileinfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            return $mime_type;
        }

        // If not available, we use the provided type
        return $file['type'];
    }

    public static function handle_cerebroly_file_upload()
    {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'cerebroly_upload_file')) {
            wp_die(esc_html__('Security check failed', 'cerebroly'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(message: esc_html__('You do not have permission to upload files', 'cerebroly'));
        }

        if (empty($_FILES['cerebroly_file'])) {
            wp_safe_redirect(add_query_arg('error', 'no_file', wp_get_referer()));
            exit;
        }

        $file_handler = new CEREBROLY_File_Handler();

        $result = $file_handler->upload_file($_FILES['cerebroly_file']);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            wp_safe_redirect(add_query_arg('upload_error', urlencode($error_message), wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg('upload_success', '1', wp_get_referer()));
        }
        exit;
    }



    public static function handle_cerebroly_file_delete()
    {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'cerebroly_delete_file')) {
            wp_die(esc_html__('Security check failed', 'cerebroly'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete files', 'cerebroly'));
        }

        if (empty($_POST['file_id'])) {
            wp_safe_redirect(add_query_arg('error', 'no_file_id', wp_get_referer()));
            exit;
        }

        $file_id = intval($_POST['file_id']);

        $file_handler = new CEREBROLY_File_Handler();

        $result = $file_handler->delete_file($file_id);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            wp_safe_redirect(add_query_arg('delete_error', urlencode($error_message), wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg('delete_success', '1', wp_get_referer()));
        }
        exit;
    }


    /**
     * Extract content from a text file.
     *
     * @param string $file_path Path to the text file
     * @return string Extracted content
     */
    private function extract_text_content($file_path)
    {
        if (is_readable($file_path)) {
            $content = file_get_contents($file_path);

            // Clean and normalize the content
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            return $content;
        }

        return __('Could not read the text file.', 'cerebroly');
    }

    /**
     * Get upload error message according to code.
     *
     * @param int $error_code Error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code)
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The file exceeds the maximum size allowed by PHP.', 'cerebroly');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The file exceeds the maximum size allowed by the form.', 'cerebroly');
            case UPLOAD_ERR_PARTIAL:
                return __('The file was only partially uploaded.', 'cerebroly');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded.', 'cerebroly');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing temporary folder.', 'cerebroly');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Error writing file to disk.', 'cerebroly');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload.', 'cerebroly');
            default:
                return __('Unknown file upload error.', 'cerebroly');
        }
    }

    /**
     * Get the list of uploaded files.
     *
     * @return array List of files
     */
    public function get_files()
    {
        // Try to get from cache first
        $files = wp_cache_get('cerebroly_files_list', 'cerebroly_files');

        if (false === $files) {
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'cerebroly_files');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $files = $wpdb->get_results(
                "SELECT id, filename, filetype, filesize, uploaded 
                FROM $table_name 
                ORDER BY uploaded DESC",
                ARRAY_A
            );

            // Cache the results for 1 hour
            wp_cache_set('cerebroly_files_list', $files, 'cerebroly_files', HOUR_IN_SECONDS);
        }

        return $files;
    }

    /**
     * Delete a file.
     *
     * @param int $file_id File ID
     * @return bool|WP_Error True if deleted successfully, WP_Error on error
     */
    public function delete_file($file_id)
    {
        // Try to get file info from cache first
        $file_data = wp_cache_get('cerebroly_file_' . $file_id, 'cerebroly_files');

        if (false === $file_data) {
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'cerebroly_files');

            // Get file information
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $file = $wpdb->get_row($wpdb->prepare(
                "SELECT filepath FROM $table_name WHERE id = %d",
                $file_id
            ));

            if (!$file) {
                return new WP_Error('not_found', __('File not found.', 'cerebroly'));
            }

            $filepath = $file->filepath;
        } else {
            $filepath = $file_data['filepath'];
        }

        // Delete physical file
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . $filepath;

        wp_delete_file($file_path);

        // Delete from database
        global $wpdb;
        $table_name = esc_sql($wpdb->prefix . 'cerebroly_files');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->delete(
            $table_name,
            array('id' => $file_id),
            array('%d')
        );

        if (!$result) {
            return new WP_Error('db_error', __('Error deleting file from database.', 'cerebroly'));
        }

        // Clear caches
        wp_cache_delete('cerebroly_file_' . $file_id, 'cerebroly_files');
        wp_cache_delete('cerebroly_files_list', 'cerebroly_files');

        return true;
    }
}