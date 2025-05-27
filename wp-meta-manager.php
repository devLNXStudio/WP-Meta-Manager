<?php
/**
 * Plugin Name: devLNX Meta & OG Tags Manager
 * Plugin URI: https://devlnx.pl
 * Description: Allows you to manage page titles, meta descriptions, keywords, and Open Graph tags for all content types. Fully compatible with WPML.
 * Version: 1.1.0
 * Author: devLNX Studio Artur Myszka
 * Author URI: https://devlnx.pl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-meta-og-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH'))
{
    exit;
}

add_action('plugins_loaded', function ()
{
    load_plugin_textdomain('wp-meta-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

class WP_Meta_OG_Manager
{
    
    /**
     * Store WPML instance if available
     */
    private $sitepress = null;
    
    /**
     * Class initialization
     */
    public function __construct()
    {
        // Load translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Check if WPML is active
        add_action('plugins_loaded', array($this, 'check_wpml'), 20);
        
        // Add meta box to posts, pages, and products
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Save meta data
        add_action('save_post', array($this, 'save_meta_data'));
        
        // Add meta tags to <head>
        add_action('wp_head', array($this, 'output_meta_tags'), 5);
        
        // Change page title
        add_filter('pre_get_document_title', array($this, 'custom_document_title'), 999);
        add_filter('wp_title', array($this, 'custom_document_title'), 999);
    }
    
    /**
     * Load translation files
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('wp-meta-og-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Check if WPML is active and save reference to SitePress object
     */
    public function check_wpml()
    {
        if (defined('ICL_SITEPRESS_VERSION'))
        {
            global $sitepress;
            if ($sitepress && is_object($sitepress))
            {
                $this->sitepress = $sitepress;
            }
        }
    }
    
    /**
     * Register meta boxes for different post types
     */
    public function add_meta_boxes()
    {
        // Get all public post types
        $post_types = get_post_types(array('public' => true));
        
        // Add meta box to each type
        foreach ($post_types as $post_type)
        {
            add_meta_box(
                'wp_meta_og_manager',
                __('Meta & OG Tags Manager', 'wp-meta-og-manager'),
                array($this, 'meta_box_callback'),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render meta box content
     */
    public function meta_box_callback($post)
    {
        // Add nonce for security
        wp_nonce_field('wp_meta_og_manager_nonce', 'wp_meta_og_manager_nonce');
        
        // Get saved values
        $meta_title = get_post_meta($post->ID, '_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_meta_description', true);
        $meta_keywords = get_post_meta($post->ID, '_meta_keywords', true);
        $og_title = get_post_meta($post->ID, '_og_title', true);
        $og_description = get_post_meta($post->ID, '_og_description', true);
        $og_image = get_post_meta($post->ID, '_og_image', true);
        $og_type = get_post_meta($post->ID, '_og_type', true);
        
        // Default Open Graph type
        if (empty($og_type))
        {
            if ($post->post_type == 'post')
            {
                $og_type = 'article';
            }
            elseif ($post->post_type == 'product')
            {
                $og_type = 'product';
            }
            else
            {
                $og_type = 'website';
            }
        }
        
        // Add language info if WPML is active
        $wpml_info = '';
        $multi_domain_info = '';
        
        if ($this->sitepress && method_exists($this->sitepress, 'get_current_language') && method_exists($this->sitepress, 'get_language_details'))
        {
            $current_lang = $this->sitepress->get_current_language();
            if ($current_lang)
            {
                $current_language = $this->sitepress->get_language_details($current_lang);
                $language_name = isset($current_language['display_name']) ? $current_language['display_name'] : $current_lang;
                
                $wpml_info = '<div class="wpml-language-info" style="margin-bottom: 15px; padding: 8px; background: #f8f8f8; border-left: 4px solid #46b450;">
                    <strong>' . esc_html__('WPML Language', 'wp-meta-og-manager') . ':</strong> ' . esc_html($language_name) . '
                    <p>' . esc_html__('Meta tags will be applied for this language version. Each translation can have its own meta tags.', 'wp-meta-og-manager') . '</p>
                </div>';
                
                // Check if using different domains configuration
                if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') &&
                    method_exists($this->sitepress, 'get_setting') &&
                    $this->sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN)
                {
                    
                    $language_domains = $this->sitepress->get_setting('language_domains', array());
                    $current_domain = isset($language_domains[$current_lang]) ? $language_domains[$current_lang] : '';
                    
                    if (!empty($current_domain))
                    {
                        $multi_domain_info = '<div class="wpml-domain-info" style="margin-bottom: 15px; padding: 8px; background: #f0f8ff; border-left: 4px solid #007cba;">
                            <strong>' . esc_html__('WPML Domain', 'wp-meta-og-manager') . ':</strong> ' . esc_html($current_domain) . '
                            <p>' . esc_html__('This domain is used for this language. Meta tags and OG tags will take this domain into account when generating URLs.', 'wp-meta-og-manager') . '</p>
                        </div>';
                    }
                }
            }
        }
        
        ?>
        <style>
            .meta-og-row
            {
                margin-bottom: 15px;
            }

            .meta-og-row label
            {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .meta-og-row input[type="text"],
            .meta-og-row textarea
            {
                width: 100%;
                padding: 8px;
            }

            .meta-og-row textarea
            {
                height: 80px;
            }

            .meta-og-section
            {
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }

            .meta-og-hint
            {
                color: #666;
                font-style: italic;
                margin-top: 5px;
                font-size: 12px;
            }

            .wpml-language-info
            {
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f8f8;
                border-left: 4px solid #46b450;
            }
        </style>

        <div class="meta-og-wrapper">
            <?php
            // Display WPML language info if available
            if (!empty($wpml_info))
            {
                echo $wpml_info;
            }
            ?>

            <!-- Meta Section -->
            <div class="meta-og-section">
                <h3><?php
                    _e('Meta Tags', 'wp-meta-og-manager'); ?></h3>
                <p><?php
                    _e('This information will be used by search engines to index your site.', 'wp-meta-og-manager'); ?></p>

                <div class="meta-og-row">
                    <label for="meta_title"><?php
                        _e('Meta Title:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="meta_title" name="meta_title" value="<?php
                    echo esc_attr($meta_title); ?>">
                    <p class="meta-og-hint"><?php
                        _e('If left empty, the default post title will be used.', 'wp-meta-og-manager'); ?></p>
                </div>

                <div class="meta-og-row">
                    <label for="meta_description"><?php
                        _e('Meta Description:', 'wp-meta-og-manager'); ?></label>
                    <textarea id="meta_description" name="meta_description"><?php
                        echo esc_textarea($meta_description); ?></textarea>
                    <p class="meta-og-hint"><?php
                        _e('Short content description (ideally 150-160 characters).', 'wp-meta-og-manager'); ?></p>
                </div>

                <div class="meta-og-row">
                    <label for="meta_keywords"><?php
                        _e('Meta Keywords:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="meta_keywords" name="meta_keywords" value="<?php
                    echo esc_attr($meta_keywords); ?>">
                    <p class="meta-og-hint"><?php
                        _e('List of keywords separated by commas.', 'wp-meta-og-manager'); ?></p>
                </div>
            </div>

            <!-- Open Graph Section -->
            <div class="meta-og-section">
                <h3><?php
                    _e('Open Graph Tags', 'wp-meta-og-manager'); ?></h3>
                <p><?php
                    _e('This information will be used when sharing content on social media.', 'wp-meta-og-manager'); ?></p>

                <div class="meta-og-row">
                    <label for="og_title"><?php
                        _e('OG Title:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="og_title" name="og_title" value="<?php
                    echo esc_attr($og_title); ?>">
                    <p class="meta-og-hint"><?php
                        _e('If left empty, Meta Title or post title will be used.', 'wp-meta-og-manager'); ?></p>
                </div>

                <div class="meta-og-row">
                    <label for="og_description"><?php
                        _e('OG Description:', 'wp-meta-og-manager'); ?></label>
                    <textarea id="og_description" name="og_description"><?php
                        echo esc_textarea($og_description); ?></textarea>
                    <p class="meta-og-hint"><?php
                        _e('If left empty, Meta Description will be used.', 'wp-meta-og-manager'); ?></p>
                </div>

                <div class="meta-og-row">
                    <label for="og_image"><?php
                        _e('OG Image URL:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="og_image" name="og_image" value="<?php
                    echo esc_attr($og_image); ?>">
                    <button type="button" class="button" id="og_image_button"><?php
                        _e('Select image', 'wp-meta-og-manager'); ?></button>
                    <div id="og_image_preview" style="margin-top: 10px;">
                        <?php
                        if (!empty($og_image)) : ?>
                            <img src="<?php
                            echo esc_url($og_image); ?>" style="max-width: 300px; height: auto;">
                        <?php
                        endif; ?>
                    </div>
                    <p class="meta-og-hint"><?php
                        _e('If left empty, the post thumbnail will be used.', 'wp-meta-og-manager'); ?></p>
                </div>

                <div class="meta-og-row">
                    <label for="og_type"><?php
                        _e('OG Type:', 'wp-meta-og-manager'); ?></label>
                    <select id="og_type" name="og_type">
                        <option value="website" <?php
                        selected($og_type, 'website'); ?>><?php
                            _e('Website', 'wp-meta-og-manager'); ?></option>
                        <option value="article" <?php
                        selected($og_type, 'article'); ?>><?php
                            _e('Article', 'wp-meta-og-manager'); ?></option>
                        <option value="product" <?php
                        selected($og_type, 'product'); ?>><?php
                            _e('Product', 'wp-meta-og-manager'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($)
            {
                // Image selection function
                $('#og_image_button').click(function (e)
                {
                    e.preventDefault();

                    var image = wp.media({
                        title: '<?php _e('Select image for Open Graph', 'wp-meta-og-manager'); ?>',
                        multiple: false
                    }).open()
                        .on('select', function (e)
                        {
                            var uploaded_image = image.state().get('selection').first();
                            var image_url = uploaded_image.toJSON().url;
                            $('#og_image').val(image_url);
                            $('#og_image_preview').html('<img src="' + image_url + '" style="max-width: 300px; height: auto;">');
                        });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Save meta data
     */
    public function save_meta_data($post_id)
    {
        // Check nonce
        if (!isset($_POST['wp_meta_og_manager_nonce']) || !wp_verify_nonce($_POST['wp_meta_og_manager_nonce'], 'wp_meta_og_manager_nonce'))
        {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id))
        {
            return;
        }
        
        // Save meta data
        $meta_fields = array(
            'meta_title' => '_meta_title',
            'meta_description' => '_meta_description',
            'meta_keywords' => '_meta_keywords',
            'og_title' => '_og_title',
            'og_description' => '_og_description',
            'og_image' => '_og_image',
            'og_type' => '_og_type'
        );
        
        foreach ($meta_fields as $field => $meta_key)
        {
            if (isset($_POST[$field]))
            {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Store URLs for different languages
     */
    public $language_urls = array();
    
    /**
     * Output meta tags in the head section
     */
    public function output_meta_tags()
    {
        global $post;
        
        if (!is_singular())
        {
            return;
        }
        
        if (!$post)
        {
            return;
        }
        
        // Get saved values
        $meta_title = get_post_meta($post->ID, '_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_meta_description', true);
        $meta_keywords = get_post_meta($post->ID, '_meta_keywords', true);
        $og_title = get_post_meta($post->ID, '_og_title', true);
        $og_description = get_post_meta($post->ID, '_og_description', true);
        $og_image = get_post_meta($post->ID, '_og_image', true);
        $og_type = get_post_meta($post->ID, '_og_type', true);
        
        // Default values
        $default_title = get_the_title($post->ID);
        $default_description = wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 30, '...');
        
        // Meta tags
        if (!empty($meta_description))
        {
            echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
        }
        
        if (!empty($meta_keywords))
        {
            echo '<meta name="keywords" content="' . esc_attr($meta_keywords) . '" />' . "\n";
        }
        
        // WPML Hreflang tags - call earlier to fill the language_urls array
        $this->output_hreflang_tags($post->ID);
        
        // Check if we have multi-domain configuration
        $using_different_domains = false;
        if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') &&
            $this->sitepress &&
            method_exists($this->sitepress, 'get_setting') &&
            $this->sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN)
        {
            $using_different_domains = true;
        }
        
        // Get correct URL for Open Graph considering multi-domain configuration
        $og_url = get_permalink($post->ID);
        if ($using_different_domains && $this->sitepress && method_exists($this->sitepress, 'get_current_language'))
        {
            $current_lang = $this->sitepress->get_current_language();
            if (isset($this->language_urls[$current_lang]['url']))
            {
                $og_url = $this->language_urls[$current_lang]['url'];
            }
        }
        
        // Open Graph tags
        echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
        
        // OG Title
        $final_og_title = !empty($og_title) ? $og_title : (!empty($meta_title) ? $meta_title : $default_title);
        echo '<meta property="og:title" content="' . esc_attr($final_og_title) . '" />' . "\n";
        
        // OG Description
        $final_og_description = !empty($og_description) ? $og_description : (!empty($meta_description) ? $meta_description : $default_description);
        echo '<meta property="og:description" content="' . esc_attr($final_og_description) . '" />' . "\n";
        
        // OG Type
        if (empty($og_type))
        {
            if ($post->post_type == 'post')
            {
                $og_type = 'article';
            }
            elseif ($post->post_type == 'product')
            {
                $og_type = 'product';
            }
            else
            {
                $og_type = 'website';
            }
        }
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
        
        // OG Image
        // For multi-domains, we must ensure absolute image URL
        if (!empty($og_image))
        {
            // Check if image URL is absolute
            if (strpos($og_image, 'http') !== 0)
            {
                // If not, convert to absolute
                $og_image = site_url($og_image);
            }
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
        elseif (has_post_thumbnail($post->ID))
        {
            $thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            if (is_array($thumbnail_src) && !empty($thumbnail_src[0]))
            {
                echo '<meta property="og:image" content="' . esc_url($thumbnail_src[0]) . '" />' . "\n";
            }
        }
        
        // Additional OG meta tags
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        
        // OG Locale
        $locale = get_locale();
        if ($this->sitepress && method_exists($this->sitepress, 'get_current_language') && method_exists($this->sitepress, 'get_language_details'))
        {
            $current_lang = $this->sitepress->get_current_language();
            if ($current_lang)
            {
                $language_details = $this->sitepress->get_language_details($current_lang);
                if (isset($language_details['default_locale']))
                {
                    $locale = $language_details['default_locale'];
                }
            }
        }
        echo '<meta property="og:locale" content="' . esc_attr(str_replace('_', '-', $locale)) . '" />' . "\n";
        
        // OG Locale alternate for all available languages
        if ($this->language_urls && is_array($this->language_urls))
        {
            $current_lang = $this->sitepress ? $this->sitepress->get_current_language() : '';
            foreach ($this->language_urls as $lang => $data)
            {
                if ($lang !== $current_lang && isset($data['hreflang']))
                {
                    echo '<meta property="og:locale:alternate" content="' . esc_attr(str_replace('-', '_', $data['hreflang'])) . '" />' . "\n";
                }
            }
        }
        
        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($final_og_title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($final_og_description) . '" />' . "\n";
        
        if (!empty($og_image))
        {
            echo '<meta name="twitter:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
        elseif (has_post_thumbnail($post->ID))
        {
            $thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            if (is_array($thumbnail_src) && !empty($thumbnail_src[0]))
            {
                echo '<meta name="twitter:image" content="' . esc_url($thumbnail_src[0]) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Generate hreflang tags for WPML with multi-domain support
     */
    public function output_hreflang_tags($post_id)
    {
        // Check if WPML is active
        if (!$this->sitepress || !method_exists($this->sitepress, 'get_element_trid') || !method_exists($this->sitepress, 'get_element_translations'))
        {
            return;
        }
        
        $post_type = get_post_type($post_id);
        if (!$post_type)
        {
            return;
        }
        
        // Get element type and its ID
        $element_type = 'post_' . $post_type;
        $trid = $this->sitepress->get_element_trid($post_id, $element_type);
        
        if (!$trid)
        {
            return;
        }
        
        // Get all element translations
        $translations = $this->sitepress->get_element_translations($trid, $element_type);
        
        if (!$translations || !is_array($translations))
        {
            return;
        }
        
        // Check if using different domains configuration
        $using_different_domains = false;
        if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') &&
            method_exists($this->sitepress, 'get_setting') &&
            $this->sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN)
        {
            $using_different_domains = true;
        }
        
        // Array to store full URLs for each language
        $language_urls = array();
        
        // For each language, generate hreflang tag
        foreach ($translations as $lang => $translation)
        {
            if (!isset($translation->element_id) || empty($translation->element_id))
            {
                continue;
            }
            
            // Get URL for translation
            if ($using_different_domains)
            {
                // Special handling for multi-domains
                if (function_exists('wpml_get_permalink'))
                {
                    // Use WPML API function which handles domains automatically
                    $url = apply_filters('wpml_permalink', get_permalink($translation->element_id), $lang);
                }
                else
                {
                    // Fallback if API function is not available
                    $language_domains = $this->sitepress->get_setting('language_domains', array());
                    $home_url = get_home_url();
                    $parsed_home = parse_url($home_url);
                    $host = isset($parsed_home['host']) ? $parsed_home['host'] : '';
                    
                    // Base URL
                    $url = get_permalink($translation->element_id);
                    
                    if (isset($language_domains[$lang]) && !empty($language_domains[$lang]))
                    {
                        // Replace domain for specific language
                        $domain = $language_domains[$lang];
                        $url = str_replace($host, $domain, $url);
                    }
                }
            }
            else
            {
                // Standard method for other WPML configurations
                $url = get_permalink($translation->element_id);
            }
            
            if (!$url)
            {
                continue;
            }
            
            
            $hreflang = $lang;
            if (method_exists($this->sitepress, 'get_language_details'))
            {
                $language_details = $this->sitepress->get_language_details($lang);
                
                if (isset($language_details['default_locale']))
                {
                    
                    $hreflang = strtolower(str_replace('_', '-', $language_details['default_locale']));
                }
            }
            
            // Save URL for later use
            $language_urls[$lang] = array(
                'url' => $url,
                'hreflang' => $hreflang
            );
            
            // Generate hreflang tag
            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($url) . '" />' . "\n";
        }
        
        // Add hreflang x-default tag for main language
        if (method_exists($this->sitepress, 'get_default_language'))
        {
            $default_lang = $this->sitepress->get_default_language();
            if (isset($language_urls[$default_lang]))
            {
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($language_urls[$default_lang]['url']) . '" />' . "\n";
            }
        }
        
        // Save URLs for use in OG meta for different languages
        $this->language_urls = $language_urls;
    }
    
    /**
     * Change document title
     */
    public function custom_document_title($title)
    {
        global $post;
        
        if (!is_singular() || !$post)
        {
            return $title;
        }
        
        $meta_title = get_post_meta($post->ID, '_meta_title', true);
        
        if (!empty($meta_title))
        {
            return esc_html($meta_title);
        }
        
        return $title;
    }
}

/**
 * Plugin initialization
 */
function wp_meta_og_manager_init()
{
    
    $lang_dir = plugin_dir_path(__FILE__) . 'languages';
    if (!file_exists($lang_dir))
    {
        wp_mkdir_p($lang_dir);
    }
    
    
    global $wp_meta_og_manager;
    $wp_meta_og_manager = new WP_Meta_OG_Manager();
}

/**
 * Check if WPML is properly configured for multi-domains
 */
function wp_meta_og_manager_admin_notice()
{
    // Check if WPML is active
    if (!defined('ICL_SITEPRESS_VERSION'))
    {
        return;
    }
    
    global $sitepress;
    if (!$sitepress || !is_object($sitepress) || !method_exists($sitepress, 'get_setting'))
    {
        return;
    }
    
    // Check if using multi-domain configuration
    if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') &&
        $sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN)
    {
        
        // Check if all domains are configured
        $language_domains = $sitepress->get_setting('language_domains');
        $active_languages = $sitepress->get_active_languages();
        
        if (empty($language_domains) || count($language_domains) < count($active_languages) - 1)
        {
            // At least one language does not have a configured domain
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php
                        _e('WordPress Meta & OG Tags Manager - Notice:', 'wp-meta-og-manager'); ?></strong>
                    <?php
                    _e('WPML multi-domain configuration detected, but not all languages have assigned domains. To ensure proper operation of Open Graph and hreflang tags, configure domains for all active languages in WPML settings.', 'wp-meta-og-manager'); ?>
                </p>
                <p>
                    <a href="<?php
                    echo admin_url('admin.php?page=sitepress-multilingual-cms/menu/languages.php'); ?>" class="button button-primary">
                        <?php
                        _e('Configure WPML domains', 'wp-meta-og-manager'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

// Add admin notice for WPML multi-domain configuration
add_action('admin_notices', 'wp_meta_og_manager_admin_notice');


add_action('init', 'wp_meta_og_manager_init');