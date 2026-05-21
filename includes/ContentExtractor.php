<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class CEREBROLY_Content_Extractor
{
    /**
     * Returns published posts/pages/CPTs as structured items for training.
     *
     * @return array
     */
    public function extract_content()
    {
        $content = [];

        $enabled_post_types = [];
        foreach (get_post_types(['public' => true], 'names') as $post_type) {
            if (get_option('cerebroly_rag_include_' . $post_type, ($post_type === 'post' ? 1 : 0))) {
                $enabled_post_types[] = $post_type;
            }
        }


        // Post types to extract (customizable)
        $post_types = get_post_types(['public' => true], 'names');


        $args = [
            'post_type' => $enabled_post_types,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];


        $query = new WP_Query($args);


        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $post_id = get_the_ID();
                $post_title = get_the_title();
                $post_content = get_the_content();
                $post_excerpt = get_the_excerpt();
                $post_type = get_post_type();
                $post_url = get_permalink($post_id);

                // Extract additional metadata
                $meta_data = $this->extract_post_metadata($post_id);

                // Create a structured object with the content
                $content[] = [
                    'id' => $post_id,
                    'type' => $post_type,
                    'title' => $post_title,
                    'excerpt' => $post_excerpt,
                    'content' => $post_content,
                    'url' => $post_url,
                    'metadata' => $meta_data,
                    'keywords' => $this->extract_keywords($post_title, $post_content, $post_excerpt),
                    // Format for training - contains all text in a single field
                    'full_content' => $this->format_content_for_training($post_title, $post_type, $post_excerpt, $post_content, $meta_data, $post_url)
                ];
            }
            wp_reset_postdata();
        }

        return $content;
    }

    /**
     * Extract relevant metadata from a post
     * 
     * @param int $post_id Post ID
     * @return array Structured metadata
     */
    private function extract_post_metadata($post_id)
    {
        $meta_data = [];

        // Get all metadata
        $custom_fields = get_post_meta($post_id);
        if (!empty($custom_fields)) {
            foreach ($custom_fields as $key => $values) {
                // Filter private fields and very long ones
                if (!str_starts_with($key, '_') && is_array($values) && isset($values[0])) {
                    // Limit the length of the value
                    $value = $values[0];
                    if (is_string($value) && strlen($value) < 500) {
                        $meta_data[$key] = $value;
                    }
                }
            }
        }

        // Add categories and tags if applicable
        $categories = wp_get_post_categories($post_id, ['fields' => 'names']);
        if (!empty($categories)) {
            $meta_data['categories'] = implode(', ', $categories);
        }

        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
        if (!empty($tags)) {
            $meta_data['tags'] = implode(', ', $tags);
        }

        return $meta_data;
    }

    /**
     * Extract keywords from content using simple NLP techniques
     * 
     * @param string $title Content title
     * @param string $content Main content
     * @param string $excerpt Content excerpt
     * @return array List of keywords
     */
    private function extract_keywords($title, $content, $excerpt)
    {
        // Combine all text
        $text = $title . ' ' . $excerpt . ' ' . $content;

        // Clean HTML and normalize
        $text = wp_strip_all_tags($text);
        $text = strtolower($text);

        // Remove common words (basic stopwords in English and Spanish)
        $stopwords = [
            'a',
            'about',
            'above',
            'after',
            'again',
            'against',
            'all',
            'am',
            'an',
            'and',
            'any',
            'are',
            'as',
            'at',
            'be',
            'because',
            'been',
            'before',
            'being',
            'below',
            'between',
            'both',
            'but',
            'by',
            'could',
            'did',
            'do',
            'does',
            'doing',
            'down',
            'during',
            'each',
            'few',
            'for',
            'from',
            'further',
            'had',
            'has',
            'have',
            'having',
            'he',
            'her',
            'here',
            'hers',
            'herself',
            'him',
            'himself',
            'his',
            'how',
            'i',
            'if',
            'in',
            'into',
            'is',
            'it',
            'its',
            'itself',
            'me',
            'more',
            'most',
            'my',
            'myself',
            'no',
            'nor',
            'not',
            'of',
            'off',
            'on',
            'once',
            'only',
            'or',
            'other',
            'ought',
            'our',
            'ours',
            'ourselves',
            'out',
            'over',
            'own',
            'same',
            'she',
            'should',
            'so',
            'some',
            'such',
            'than',
            'that',
            'the',
            'their',
            'theirs',
            'them',
            'themselves',
            'then',
            'there',
            'these',
            'they',
            'this',
            'those',
            'through',
            'to',
            'too',
            'under',
            'until',
            'up',
            'very',
            'was',
            'we',
            'were',
            'what',
            'when',
            'where',
            'which',
            'while',
            'who',
            'whom',
            'why',
            'with',
            'would',
            'you',
            'your',
            'yours',
            'yourself',
            'yourselves'
        ];

        // Split into words and count frequencies
        $words = preg_split('/\s+/', $text);
        $word_counts = [];

        foreach ($words as $word) {
            // Clean the word of non-alphanumeric characters
            $word = preg_replace('/[^\p{L}\p{N}]/u', '', $word);

            // Check that it's not a stopword and has minimum length
            if (!empty($word) && strlen($word) > 3 && !in_array($word, $stopwords)) {
                if (isset($word_counts[$word])) {
                    $word_counts[$word]++;
                } else {
                    $word_counts[$word] = 1;
                }
            }
        }

        // Sort by descending frequency
        arsort($word_counts);

        // Take the 10 most frequent words
        return array_slice(array_keys($word_counts), 0, 10);
    }

    /**
     * Format content for training in a structured format
     */
    private function format_content_for_training($title, $type, $excerpt, $content, $meta_data, $post_url)
    {
        // Clean HTML
        $content = wp_strip_all_tags($content);

        // Create structured content
        $formatted = __('Title: ', 'cerebroly') . $title . "\n";
        $formatted .= __('Type: ', 'cerebroly') . $type . "\n";
        $formatted .= __('URL: ', 'cerebroly') . $post_url . "\n";

        if (!empty($excerpt)) {
            $formatted .= __('Summary: ', 'cerebroly') . $excerpt . "\n";
        }

        // Add relevant metadata
        if (!empty($meta_data)) {
            $formatted .= __('Metadata:', 'cerebroly') . "\n";
            foreach ($meta_data as $key => $value) {
                $formatted .= "- $key: $value\n";
            }
        }

        $formatted .= __('Content: ', 'cerebroly') . $content . "\n";

        return $formatted;
    }

    /**
     * Extract content from uploaded files
     */
    private function extract_file_content()
    {
        $content = [];

        // Get files from database
        global $wpdb;
        $files_table = esc_sql($wpdb->prefix . 'cerebroly_files');

        // Modify query to include text files specifically
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT id, filename, filetype, content FROM {$files_table} 
            WHERE content IS NOT NULL AND filetype = %s",
            'text/plain'
        ));


        foreach ($files as $file) {
            // Extract keywords from file content
            $keywords = $this->extract_keywords($file->filename, $file->content, '');

            $content[] = array(
                'id' => 'file-' . $file->id,
                'type' => 'uploaded-file',
                'title' => $file->filename,
                'content' => $file->content,
                'keywords' => $keywords,
                'full_content' => __('File: ', 'cerebroly') . $file->filename . "\n" . __('Type: ', 'cerebroly') . $file->filetype . "\n" . __('Content: ', 'cerebroly') . $file->content
            );
        }

        return $content;
    }

    /**
     * Prepares content for training and converts it to JSONL format
     * 
     * @return string Content in JSONL format ready for training
     */
    public function prepare_for_training()
    {
        // Get content from all sources
        $content_items = $this->extract_content();

        // Get content from uploaded files
        $file_content = $this->extract_file_content();

        // Combine all content
        $all_content = array_merge($content_items, $file_content);


        // Check AI processing limit
        $enhancement_limit = intval(get_option('cerebroly_ai_enhancement_limit', 100));
        $use_api_processing = get_option('cerebroly_use_ai_enhancement', 0) &&
            ($enhancement_limit === 0 || count($all_content) <= $enhancement_limit);

        // Limit elements if necessary
        if ($use_api_processing && $enhancement_limit > 0 && count($all_content) > $enhancement_limit) {
            // Randomly shuffle
            shuffle($all_content);
            // Take only the limited number of items
            $all_content = array_slice($all_content, 0, $enhancement_limit);
        }

        // Generate training entries
        $training_data = [];
        $processed_count = 0;
        $total_count = count($all_content);

        foreach ($all_content as $item) {
            $processed_count++;

            // Determine processing method
            if ($use_api_processing) {
                // Advanced processing with OpenAI
                $entries = $this->process_content_with_ai($item);
            } else {
                // Processing based on local content analysis
                $entries = $this->generate_content_based_qa_pairs($item);
            }

            // Add generated entries to the dataset
            $training_data = array_merge($training_data, $entries);
        }

        // Verify we have enough examples
        if (count($training_data) < 5) {

            // Generate some generic examples if not enough
            $generic_examples = $this->generate_generic_examples();
            $training_data = array_merge($training_data, $generic_examples);
        }

        // Convert to JSONL
        $jsonl = '';
        foreach ($training_data as $entry) {
            $jsonl .= json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
        }

        return $jsonl;
    }

    /**
     * Process content with OpenAI to generate high-quality question-answer pairs
     * 
     * @param array $item Content item
     * @return array Training entries
     */
    private function process_content_with_ai($item)
    {
        // Get OpenAI API instance
        $openai_api = new CEREBROLY_OpenAI_API();

        // Extract relevant information
        $title = $item['title'];
        $content = isset($item['full_content']) ? $item['full_content'] : $item['content'];
        $keywords = isset($item['keywords']) ? implode(', ', $item['keywords']) : '';

        // Limit content to not exceed API tokens
        $content = $this->clean_and_truncate_content($content, 2500);

        // Create a more advanced prompt for the OpenAI API
        $prompt = __('Your task is to generate high-quality question-answer pairs based on the provided content. Thoroughly analyze the text and generate 5-7 question-answer pairs that are relevant, specific, and directly based on the content. Questions should be varied and cover different aspects of the content. Answers should be extracted directly from the provided text. Return the result in JSON format with the structure: [{"question": "question 1", "answer": "answer 1"}, ...]', 'cerebroly') . "\n\n";

        $prompt .= __('Title: ', 'cerebroly') . $title . "\n";
        if (!empty($keywords)) {
            $prompt .= __('Keywords: ', 'cerebroly') . $keywords . "\n";
        }
        $prompt .= __('Content: ', 'cerebroly') . $content . "\n\n";

        // Make request to OpenAI
        $response = $openai_api->generate_qa_pairs($prompt);

        // Handle errors
        if (is_wp_error($response)) {
            // Use alternative method as fallback
            return $this->generate_content_based_qa_pairs($item);
        }

        // Try to decode the JSON
        $qa_pairs = json_decode($response, true);

        if (!$qa_pairs || !is_array($qa_pairs)) {
            return $this->generate_content_based_qa_pairs($item);
        }

        // Convert to training format
        $training_entries = [];
        foreach ($qa_pairs as $pair) {
            if (isset($pair['question']) && isset($pair['answer'])) {
                $training_entry = [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $pair['question']
                        ],
                        [
                            'role' => 'assistant',
                            'content' => $pair['answer']
                        ]
                    ]
                ];
                $training_entries[] = $training_entry;
            }
        }

        return $training_entries;
    }

    /**
     * Generate question-answer pairs based on content analysis
     * 
     * @param array $item Content item
     * @return array Training entries
     */
    private function generate_content_based_qa_pairs($item)
    {
        $title = $item['title'];
        $content = isset($item['full_content']) ? $item['full_content'] : $item['content'];
        $type = $item['type'];
        $keywords = isset($item['keywords']) ? $item['keywords'] : [];

        // Clean and prepare content
        $clean_content = $this->clean_and_truncate_content($content);

        // Basic content analysis to identify sections or topics
        $sections = $this->analyze_content_sections($clean_content);

        // Generate questions based on content and keywords
        $questions = [];

        // 1. General questions based on title
        /* translators: %s: Title of the content */
        $questions[] = sprintf(__('What is %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $questions[] = sprintf(__('Tell me more about %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $questions[] = sprintf(__('Explain in detail about %s', 'cerebroly'), $title);

        // 2. Questions based on keywords
        foreach ($keywords as $index => $keyword) {
            if ($index < 5) {  // Limit to 5 keywords to avoid redundancy
                /* translators: %1$s: Keyword, %2$s: Title of the content */
                $questions[] = sprintf(__('What is the relationship between %1$s and %2$s?', 'cerebroly'), $keyword, $title);
                /* translators: %1$s: Keyword, %2$s: Title of the content */
                $questions[] = sprintf(__('What information is there about %1$s in %2$s?', 'cerebroly'), $keyword, $title);
            }
        }

        // 3. Specific questions for each identified section
        foreach ($sections as $section => $section_content) {
            if (!empty($section)) {
                /* translators: %1$s: Section name, %2$s: Title of the content */
                $questions[] = sprintf(__('What information is there about %1$s in %2$s?', 'cerebroly'), $section, $title);
                /* translators: %1$s: Section name, %2$s: Title of the content */
                $questions[] = sprintf(__('Can you explain the %1$s section in %2$s?', 'cerebroly'), $section, $title);
            }
        }

        // 4. Questions based on content type
        if ($type == 'post') {
            /* translators: %s: Title of the post */
            $questions[] = sprintf(__('What is the article %s about?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $questions[] = sprintf(__('What are the main points of the post %s?', 'cerebroly'), $title);
        } elseif ($type == 'page') {
            /* translators: %s: Title of the page */
            $questions[] = sprintf(__('What information does the page %s contain?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $questions[] = sprintf(__('What is the main purpose of the page %s?', 'cerebroly'), $title);
        } elseif ($type == 'uploaded-file') {
            /* translators: %s: Name of the uploaded file */
            $questions[] = sprintf(__('What information does the file %s contain?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $questions[] = sprintf(__('What is the content of the document %s?', 'cerebroly'), $title);
        }

        // Remove duplicates and limit to 10 questions
        $questions = array_unique($questions);
        $questions = array_slice($questions, 0, 10);

        // Create training entries
        $training_entries = [];
        foreach ($questions as $question) {
            $training_entry = [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $question
                    ],
                    [
                        'role' => 'assistant',
                        'content' => $clean_content
                    ]
                ]
            ];
            $training_entries[] = $training_entry;
        }

        return $training_entries;
    }

    /**
     * Analyze content to identify possible sections or topics
     * 
     * @param string $content Content to analyze
     * @return array Identified sections/topics
     */
    private function analyze_content_sections($content)
    {
        $sections = [];

        // Look for lines that might be section headers
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);

            // Detect possible headers (e.g., lines ending with a colon)
            if (preg_match('/^([A-Za-z\s]+):/', $line, $matches) && strlen($matches[1]) < 50) {
                $section_name = trim($matches[1]);

                // Add section only if not empty and not too long
                $excluded_sections = [
                    __('Title', 'cerebroly'),
                    __('Type', 'cerebroly'),
                    __('Summary', 'cerebroly'),
                    __('Content', 'cerebroly'),
                    __('Metadata', 'cerebroly')
                ];

                if (!empty($section_name) && !in_array($section_name, $excluded_sections)) {
                    $sections[$section_name] = $line;
                }
            }
        }

        return $sections;
    }

    /**
     * Generate generic examples to ensure there's enough training content
     * 
     * @return array Generic training examples
     */
    private function generate_generic_examples()
    {
        $examples = [];

        // Generic examples about the website
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');

        $generic_qa_pairs = [
            [
                
                'question' => __('What is this website about?', 'cerebroly'),
                /* translators: %1$s: Site name, %2$s: Site description */
                'answer' => sprintf(
    /* translators: %1$s: Site name, %2$s: Site description */
    __('This website is %1$s, which focuses on %2$s', 'cerebroly'),
    $site_name,
    ($site_description ? $site_description : __('providing relevant information and resources to our visitors.', 'cerebroly'))
)
            ],
            [
                /* translators: %s: Site name */
                'question' => sprintf(__('What type of content can I find on %s?', 'cerebroly'), $site_name),
                /* translators: %s: Site name */
                'answer' => sprintf(__('On %s you can find a variety of content including articles, informational pages, and resources related to our subject matter.', 'cerebroly'), $site_name)
            ],
            [
                /* translators: %s: Site name */
                'question' => sprintf(__('How can I contact %s?', 'cerebroly'), $site_name),
                'answer' => __('You can contact us through our contact form on the corresponding page, or through the social media channels you\'ll find on our website.', 'cerebroly')
            ]
        ];

        // Convert to training format
        foreach ($generic_qa_pairs as $pair) {
            $examples[] = [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $pair['question']
                    ],
                    [
                        'role' => 'assistant',
                        'content' => $pair['answer']
                    ]
                ]
            ];
        }

        return $examples;
    }

    /**
     * Generate question variations based on title and content type
     * 
     * @param string $title Content title
     * @param string $type Content type
     * @return array List of question variations
     */
    public function generate_variations($title, $type)
    {
        $variations = [];

        // Standard variations based on the title
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What is %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Tell me more about %s.', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Explain in detail about %s.', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('How can I use %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What key information should I know about %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What does %s offer?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Why is %s important?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Where can I get more information about %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What do I need to know about %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Why should I care about %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What are the benefits of %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Explain how %s works.', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What features does %s have?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What makes %s unique?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('How can I learn more about %s?', 'cerebroly'), $title);

        // Custom variations based on content type
        if ($type == 'post') {
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What is the post about %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What are the key points of the post %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('How does the post %s help me?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What problems does the post %s solve?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('Who benefits from the post %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('When was the post %s published?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What additional resources does the post %s mention?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What is the main purpose of the post %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('Who is the post %s targeted to?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What do experts think about %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What are the conclusions of the post %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What examples are cited in the post %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('Why is the post %s relevant?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What format does the post %s have?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('How does the post %s impact my area of interest?', 'cerebroly'), $title);
            /* translators: %s: Title of the post */
            $variations[] = sprintf(__('What advice does the post %s give?', 'cerebroly'), $title);
        } elseif ($type == 'page') {
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What information does the page %s contain?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What is the main purpose of the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What benefits can I get from the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('How can the page %s help me?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What key details does the page %s provide?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What type of information can I find on the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What are the goals of the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What actions can I take from the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('Who can benefit from the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What recent changes have been made to the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What is the main content of the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What format does the page %s have?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What are the most notable elements of the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('In which situations is the page %s useful?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('What additional information can you find on the page %s?', 'cerebroly'), $title);
            /* translators: %s: Title of the page */
            $variations[] = sprintf(__('How is the page %s organized?', 'cerebroly'), $title);
        } elseif ($type == 'uploaded-file') {
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What information does the file %s contain?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('How can I use the file %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What type of file is %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('Why should I check the file %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What important details does the file %s contain?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('In which situations can I use the file %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What additional resources does the file %s provide?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('How is the file %s structured?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What topics does the file %s cover?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What methodology does the file %s use?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What key data can be extracted from the file %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What critical information does the file %s contain?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('How does the file %s impact practice?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What tools do I need to work with the file %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What are the risks or limitations of the file %s?', 'cerebroly'), $title);
            /* translators: %s: Name of the uploaded file */
            $variations[] = sprintf(__('What versions of the file %s exist?', 'cerebroly'), $title);
        }

        // Variations for Menu and URL
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What pages are available in the menu for %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('How is the navigation menu structured on %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What is the URL for the page %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('Where can I find the page %s in the menu?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What URL does the page %s lead to?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What can I expect to find at the URL of %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('How can I access the page %s through the menu?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What is the URL address of %s?', 'cerebroly'), $title);

        // Variations for Custom Post Types
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What custom content types are available on the site for %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What type of custom post is %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What special content can I find in %s?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('How can I find more information about %s on the site?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('What differences are there between %s and other content types on the site?', 'cerebroly'), $title);
        /* translators: %s: Title of the content */
        $variations[] = sprintf(__('How do I interact with the custom post %s?', 'cerebroly'), $title);

        return $variations;
    }

    /**
     * Clean and truncate content for training use
     * 
     * @param string $content Content to clean
     * @param int $max_length Maximum length
     * @return string Cleaned content
     */
    public function clean_and_truncate_content($content, $max_length = 3000)
    {
        // Clean HTML
        $content = wp_strip_all_tags($content);

        // Normalize spaces
        $content = preg_replace('/\s+/', ' ', $content);

        // Truncate if exceeds maximum length
        if (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length - 3) . '...';
        }

        return trim($content);
    }
}