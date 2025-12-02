<?php
require_once get_stylesheet_directory() . '/includes/Parsedown.php';

// List child pages for the current page shortcode
function wpb_list_child_pages() { 
  
    global $post; 
      
    if ( is_page() && $post->post_parent )
      
        $childpages = wp_list_pages( 'sort_column=post_date&title_li=&child_of=' . $post->post_parent . '&echo=0' );
    else
        $childpages = wp_list_pages( 'sort_column=post_date&title_li=&child_of=' . $post->ID . '&echo=0' );
      
    if ( $childpages ) {
      
        $string = '<ul class="wpb_page_list">' . $childpages . '</ul>';
    }
      
    return $string;
      
}
      
add_shortcode('wpb_childpages', 'wpb_list_child_pages');

add_action('wp_enqueue_scripts', function() {
    // Enqueue parent theme styles first
    wp_enqueue_style(
        'twentytwentyfour-style',
        get_template_directory_uri() . '/style.css'
    );
    
    // Enqueue child theme's main style.css
    wp_enqueue_style(
        'twentytwentyfour-spenpo-style',
        get_stylesheet_uri(),
        ['twentytwentyfour-style']
    );
    
    // Enqueue chat-specific styles
    wp_enqueue_style(
        'twentytwentyfour-spenpo-chat',
        get_stylesheet_directory_uri() . '/assets/css/chat-styles.css',
        ['twentytwentyfour-spenpo-style'],
        '0.0.6'
    );
});

function render_chat_bubbles($atts, $content = '') {
    // match post tags with slug of one of the possible assistant names
    $assistant_names = [
        (object) ['name' => 'Claude', 'slug' => 'claude'],
        (object) ['name' => 'ChatGPT', 'slug' => 'chatgpt'],
        (object) ['name' => 'Grok', 'slug' => 'grok']
    ];

    $assistant_name = 'AI';
    if (has_tag()) {
        $tags = get_the_tags();
        foreach ($assistant_names as $assistant) {
            foreach ($tags as $tag) {
                if ($tag->slug === $assistant->slug) {
                    $assistant_name = $assistant->name;
                    break;
                }
            }
        }
    }

    $parsedown = new Parsedown();
    
    // Decode the entire content first
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = str_replace(['&#8217;', '&#8220;', '&#8221;'], ["'", '"', '"'], $content);
    
    error_log("Content after initial decode: " . $content);
    
    // Pre-process Gutenberg code blocks
    $content = preg_replace_callback(
        '/<!-- wp:code -->\s*<pre class="wp-block-code"><code>(.*?)<\/code><\/pre>\s*<!-- \/wp:code -->/s',
        function($matches) {
            error_log("Found code block: " . $matches[1]);
            // Convert HTML entities in code block
            $code = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Wrap in markdown-style code block with language if specified
            if (preg_match('/^```(\w+)/', $code, $lang_matches)) {
                return "\n" . $code . "\n";
            }
            return "\n```\n" . $code . "\n```\n";
        },
        $content
    );
    
    // error_log("Content after code block processing: " . $content);
    
    // Split content into sections based on code blocks
    $sections = preg_split('/(```.*?```)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $output = '<div class="chat-container">';
    
    foreach ($sections as $section) {
        // error_log("Processing section: " . $section);
        
        // If this is a code block, add it directly
        if (preg_match('/^```.*?```$/s', $section)) {
            $output .= $parsedown->text($section);
            continue;
        }
        
        // Process non-code sections
        $lines = explode("\n", trim($section));
        $markdown_buffer = '';
        
        $first_human = 1;
        $first_assistant = 1;

        foreach ($lines as $line) {
            $line = strip_tags($line);
            
            if (preg_match('/^\*\*Human\*\*: (.+)$/', $line, $matches)) {
                if (!empty($markdown_buffer)) {
                    $parsed = $parsedown->text($markdown_buffer);
                    $output .= $parsed;
                    $markdown_buffer = '';
                }
                $text = str_replace(['<br>', '</br>', '<br/>', '<br />'], '', $matches[1]);
                $output .= '<div class="chat-bubble human">' . esc_html($text) . '</div>';
                $output .= $first_human ? '<div class="first-chat-nametag human">Spenpo</div>' : '';
                $first_human = 0;
            } elseif (preg_match('/^\*\*Assistant\*\*: (.+)$/', $line, $matches)) {
                if (!empty($markdown_buffer)) {
                    $parsed = $parsedown->text($markdown_buffer);
                    $output .= $parsed;
                    $markdown_buffer = '';
                }
                $text = str_replace(['<br>', '</br>', '<br/>', '<br />'], '', $matches[1]);
                $output .= '<div class="chat-bubble assistant first-chat">' . esc_html($text) . '</div>';
                $output .= $first_assistant ? '<div class="first-chat-nametag assistant">' . $assistant_name . '</div>' : '';
                $first_assistant = 0;
            } else {
                $markdown_buffer .= $line . "\n";
            }
        }
        
        if (!empty($markdown_buffer)) {
            $parsed = $parsedown->text($markdown_buffer);
            $output .= $parsed;
        }
    }
    
    $output .= '</div>';
    
    // error_log("Final output: " . $output);
    return $output;
}

// Keep these filters removed
remove_filter('the_content', 'wptexturize');
remove_filter('the_content', 'wpautop');

add_shortcode('chat', 'render_chat_bubbles');

// Add Prism.js for syntax highlighting
function add_prism_jsx_support() {
    wp_enqueue_style('prism-css', get_stylesheet_directory_uri() . '/includes/prism.css');
    wp_enqueue_script('prism-js', get_stylesheet_directory_uri() . '/includes/prism.js', [], null, true);
}
add_action('wp_enqueue_scripts', 'add_prism_jsx_support');
