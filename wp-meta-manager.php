<?php
/**
 * Plugin Name: WordPress Meta & OG Tags Manager
 * Plugin URI: https://devlnx.pl
 * Description: Umożliwia zarządzanie tytułami stron, meta opisami, słowami kluczowymi oraz tagami Open Graph dla wszystkich typów treści. W pełni kompatybilny z WPML.
 * Version: 1.1.0
 * Author: devLNX Studio Artur Myszka
 * Author URI: https://devlnx.pl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-meta-og-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Meta_OG_Manager {

    /**
     * Przechowywanie instancji WPML jeśli jest dostępny
     */
    private $sitepress = null;

    /**
     * Inicjalizacja klasy
     */
    public function __construct() {
        // Ładowanie tłumaczeń
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Sprawdzenie czy WPML jest aktywny
        add_action('plugins_loaded', array($this, 'check_wpml'), 20);
        
        // Dodanie meta boksu do edycji postów, stron i produktów
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Zapisanie danych meta
        add_action('save_post', array($this, 'save_meta_data'));
        
        // Dodanie meta tagów do <head>
        add_action('wp_head', array($this, 'output_meta_tags'), 5);
        
        // Zmiana tytułu strony
        add_filter('pre_get_document_title', array($this, 'custom_document_title'), 999);
        add_filter('wp_title', array($this, 'custom_document_title'), 999);
    }
    
    /**
     * Ładowanie plików tłumaczeń
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-meta-og-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Sprawdzenie czy WPML jest aktywny i zapisanie referencji do obiektu SitePress
     */
    public function check_wpml() {
        if (defined('ICL_SITEPRESS_VERSION')) {
            global $sitepress;
            if ($sitepress && is_object($sitepress)) {
                $this->sitepress = $sitepress;
            }
        }
    }

    /**
     * Rejestracja meta boksów dla różnych typów treści
     */
    public function add_meta_boxes() {
        // Pobieranie wszystkich publicznych typów postów
        $post_types = get_post_types(array('public' => true));
        
        // Dodanie meta boksu do każdego typu
        foreach ($post_types as $post_type) {
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
     * Renderowanie zawartości meta boksu
     */
    public function meta_box_callback($post) {
        // Dodanie nonce dla bezpieczeństwa
        wp_nonce_field('wp_meta_og_manager_nonce', 'wp_meta_og_manager_nonce');
        
        // Pobieranie zapisanych wartości
        $meta_title = get_post_meta($post->ID, '_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_meta_description', true);
        $meta_keywords = get_post_meta($post->ID, '_meta_keywords', true);
        $og_title = get_post_meta($post->ID, '_og_title', true);
        $og_description = get_post_meta($post->ID, '_og_description', true);
        $og_image = get_post_meta($post->ID, '_og_image', true);
        $og_type = get_post_meta($post->ID, '_og_type', true);
        
        // Domyślny typ Open Graph
        if (empty($og_type)) {
            if ($post->post_type == 'post') {
                $og_type = 'article';
            } elseif ($post->post_type == 'product') {
                $og_type = 'product';
            } else {
                $og_type = 'website';
            }
        }
        
        // Dodaj informację o języku jeśli WPML jest aktywny
        $wpml_info = '';
        $multi_domain_info = '';
        
        if ($this->sitepress && method_exists($this->sitepress, 'get_current_language') && method_exists($this->sitepress, 'get_language_details')) {
            $current_lang = $this->sitepress->get_current_language();
            if ($current_lang) {
                $current_language = $this->sitepress->get_language_details($current_lang);
                $language_name = isset($current_language['display_name']) ? $current_language['display_name'] : $current_lang;
                
                $wpml_info = '<div class="wpml-language-info" style="margin-bottom: 15px; padding: 8px; background: #f8f8f8; border-left: 4px solid #46b450;">
                    <strong>' . esc_html__('WPML Language', 'wp-meta-og-manager') . ':</strong> ' . esc_html($language_name) . '
                    <p>' . esc_html__('Meta tagi zostaną zastosowane dla tej wersji językowej. Każde tłumaczenie może mieć własne meta tagi.', 'wp-meta-og-manager') . '</p>
                </div>';
                
                // Sprawdź czy używamy konfiguracji różnych domen
                if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') && 
                    method_exists($this->sitepress, 'get_setting') &&
                    $this->sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN) {
                    
                    $language_domains = $this->sitepress->get_setting('language_domains', array());
                    $current_domain = isset($language_domains[$current_lang]) ? $language_domains[$current_lang] : '';
                    
                    if (!empty($current_domain)) {
                        $multi_domain_info = '<div class="wpml-domain-info" style="margin-bottom: 15px; padding: 8px; background: #f0f8ff; border-left: 4px solid #007cba;">
                            <strong>' . esc_html__('WPML Domain', 'wp-meta-og-manager') . ':</strong> ' . esc_html($current_domain) . '
                            <p>' . esc_html__('Ta domena jest używana dla tego języka. Meta tagi i OG tagi uwzględnią tę domenę przy generowaniu URL.', 'wp-meta-og-manager') . '</p>
                        </div>';
                    }
                }
            }
        }
        
        ?>
        <style>
            .meta-og-row {
                margin-bottom: 15px;
            }
            .meta-og-row label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .meta-og-row input[type="text"],
            .meta-og-row textarea {
                width: 100%;
                padding: 8px;
            }
            .meta-og-row textarea {
                height: 80px;
            }
            .meta-og-section {
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }
            .meta-og-hint {
                color: #666;
                font-style: italic;
                margin-top: 5px;
                font-size: 12px;
            }
            .wpml-language-info {
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f8f8;
                border-left: 4px solid #46b450;
            }
        </style>
        
        <div class="meta-og-wrapper">
            <?php 
            // Wyświetl informację o języku WPML jeśli jest dostępny
            if (!empty($wpml_info)) {
                echo $wpml_info;
            }
            ?>
            
            <!-- Sekcja Meta -->
            <div class="meta-og-section">
                <h3><?php _e('Meta Tagi', 'wp-meta-og-manager'); ?></h3>
                <p><?php _e('Te informacje będą używane przez wyszukiwarki do indeksowania Twojej strony.', 'wp-meta-og-manager'); ?></p>
                
                <div class="meta-og-row">
                    <label for="meta_title"><?php _e('Meta Tytuł:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="meta_title" name="meta_title" value="<?php echo esc_attr($meta_title); ?>">
                    <p class="meta-og-hint"><?php _e('Jeśli pozostawisz puste, zostanie użyty domyślny tytuł wpisu.', 'wp-meta-og-manager'); ?></p>
                </div>
                
                <div class="meta-og-row">
                    <label for="meta_description"><?php _e('Meta Opis:', 'wp-meta-og-manager'); ?></label>
                    <textarea id="meta_description" name="meta_description"><?php echo esc_textarea($meta_description); ?></textarea>
                    <p class="meta-og-hint"><?php _e('Krótki opis treści (idealnie 150-160 znaków).', 'wp-meta-og-manager'); ?></p>
                </div>
                
                <div class="meta-og-row">
                    <label for="meta_keywords"><?php _e('Meta Słowa Kluczowe:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo esc_attr($meta_keywords); ?>">
                    <p class="meta-og-hint"><?php _e('Lista słów kluczowych oddzielonych przecinkami.', 'wp-meta-og-manager'); ?></p>
                </div>
            </div>
            
            <!-- Sekcja Open Graph -->
            <div class="meta-og-section">
                <h3><?php _e('Open Graph Tagi', 'wp-meta-og-manager'); ?></h3>
                <p><?php _e('Te informacje będą używane przy udostępnianiu treści w mediach społecznościowych.', 'wp-meta-og-manager'); ?></p>
                
                <div class="meta-og-row">
                    <label for="og_title"><?php _e('OG Tytuł:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="og_title" name="og_title" value="<?php echo esc_attr($og_title); ?>">
                    <p class="meta-og-hint"><?php _e('Jeśli pozostawisz puste, zostanie użyty Meta Tytuł lub tytuł wpisu.', 'wp-meta-og-manager'); ?></p>
                </div>
                
                <div class="meta-og-row">
                    <label for="og_description"><?php _e('OG Opis:', 'wp-meta-og-manager'); ?></label>
                    <textarea id="og_description" name="og_description"><?php echo esc_textarea($og_description); ?></textarea>
                    <p class="meta-og-hint"><?php _e('Jeśli pozostawisz puste, zostanie użyty Meta Opis.', 'wp-meta-og-manager'); ?></p>
                </div>
                
                <div class="meta-og-row">
                    <label for="og_image"><?php _e('OG Obraz URL:', 'wp-meta-og-manager'); ?></label>
                    <input type="text" id="og_image" name="og_image" value="<?php echo esc_attr($og_image); ?>">
                    <button type="button" class="button" id="og_image_button"><?php _e('Wybierz obraz', 'wp-meta-og-manager'); ?></button>
                    <div id="og_image_preview" style="margin-top: 10px;">
                        <?php if (!empty($og_image)) : ?>
                            <img src="<?php echo esc_url($og_image); ?>" style="max-width: 300px; height: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="meta-og-hint"><?php _e('Jeśli pozostawisz puste, zostanie użyte miniatura wpisu.', 'wp-meta-og-manager'); ?></p>
                </div>
                
                <div class="meta-og-row">
                    <label for="og_type"><?php _e('OG Typ:', 'wp-meta-og-manager'); ?></label>
                    <select id="og_type" name="og_type">
                        <option value="website" <?php selected($og_type, 'website'); ?>><?php _e('Website', 'wp-meta-og-manager'); ?></option>
                        <option value="article" <?php selected($og_type, 'article'); ?>><?php _e('Article', 'wp-meta-og-manager'); ?></option>
                        <option value="product" <?php selected($og_type, 'product'); ?>><?php _e('Product', 'wp-meta-og-manager'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Funkcja wyboru obrazka
            $('#og_image_button').click(function(e) {
                e.preventDefault();
                
                var image = wp.media({ 
                    title: '<?php _e('Wybierz obraz dla Open Graph', 'wp-meta-og-manager'); ?>',
                    multiple: false
                }).open()
                .on('select', function(e){
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
     * Zapisywanie danych meta
     */
    public function save_meta_data($post_id) {
        // Sprawdzenie nonce
        if (!isset($_POST['wp_meta_og_manager_nonce']) || !wp_verify_nonce($_POST['wp_meta_og_manager_nonce'], 'wp_meta_og_manager_nonce')) {
            return;
        }
        
        // Sprawdzenie automatycznego zapisu
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Sprawdzenie uprawnień
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Zapisywanie danych meta
        $meta_fields = array(
            'meta_title' => '_meta_title',
            'meta_description' => '_meta_description',
            'meta_keywords' => '_meta_keywords',
            'og_title' => '_og_title',
            'og_description' => '_og_description',
            'og_image' => '_og_image',
            'og_type' => '_og_type'
        );
        
        foreach ($meta_fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Przechowywanie URLs dla różnych języków
     */
    public $language_urls = array();

    /**
     * Wyprowadzanie meta tagów w sekcji head
     */
    public function output_meta_tags() {
        global $post;
        
        if (!is_singular()) {
            return;
        }
        
        if (!$post) {
            return;
        }
        
        // Pobieranie zapisanych wartości
        $meta_title = get_post_meta($post->ID, '_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_meta_description', true);
        $meta_keywords = get_post_meta($post->ID, '_meta_keywords', true);
        $og_title = get_post_meta($post->ID, '_og_title', true);
        $og_description = get_post_meta($post->ID, '_og_description', true);
        $og_image = get_post_meta($post->ID, '_og_image', true);
        $og_type = get_post_meta($post->ID, '_og_type', true);
        
        // Domyślne wartości
        $default_title = get_the_title($post->ID);
        $default_description = wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 30, '...');
        
        // Meta tagi
        if (!empty($meta_description)) {
            echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
        }
        
        if (!empty($meta_keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($meta_keywords) . '" />' . "\n";
        }
        
        // WPML Hreflang tagi - wywołujemy wcześniej, aby wypełnić tablicę language_urls
        $this->output_hreflang_tags($post->ID);

        // Sprawdzamy czy mamy konfigurację wielu domen
        $using_different_domains = false;
        if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') && 
            $this->sitepress && 
            method_exists($this->sitepress, 'get_setting') &&
            $this->sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN) {
            $using_different_domains = true;
        }
        
        // Pobierz poprawny URL dla Open Graph uwzględniając konfigurację wielu domen
        $og_url = get_permalink($post->ID);
        if ($using_different_domains && $this->sitepress && method_exists($this->sitepress, 'get_current_language')) {
            $current_lang = $this->sitepress->get_current_language();
            if (isset($this->language_urls[$current_lang]['url'])) {
                $og_url = $this->language_urls[$current_lang]['url'];
            }
        }
        
        // Open Graph tagi
        echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
        
        // OG Tytuł
        $final_og_title = !empty($og_title) ? $og_title : (!empty($meta_title) ? $meta_title : $default_title);
        echo '<meta property="og:title" content="' . esc_attr($final_og_title) . '" />' . "\n";
        
        // OG Opis
        $final_og_description = !empty($og_description) ? $og_description : (!empty($meta_description) ? $meta_description : $default_description);
        echo '<meta property="og:description" content="' . esc_attr($final_og_description) . '" />' . "\n";
        
        // OG Typ
        if (empty($og_type)) {
            if ($post->post_type == 'post') {
                $og_type = 'article';
            } elseif ($post->post_type == 'product') {
                $og_type = 'product';
            } else {
                $og_type = 'website';
            }
        }
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
        
        // OG Obraz
        // Dla wielu domen, musimy zapewnić absolutny URL do obrazka
        if (!empty($og_image)) {
            // Sprawdź czy URL obrazu jest absolutny
            if (strpos($og_image, 'http') !== 0) {
                // Jeśli nie, zamień na absolutny
                $og_image = site_url($og_image);
            }
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        } elseif (has_post_thumbnail($post->ID)) {
            $thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            if (is_array($thumbnail_src) && !empty($thumbnail_src[0])) {
                echo '<meta property="og:image" content="' . esc_url($thumbnail_src[0]) . '" />' . "\n";
            }
        }
        
        // Dodatkowe meta tagi OG
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        
        // OG Locale
        $locale = get_locale();
        if ($this->sitepress && method_exists($this->sitepress, 'get_current_language') && method_exists($this->sitepress, 'get_language_details')) {
            $current_lang = $this->sitepress->get_current_language();
            if ($current_lang) {
                $language_details = $this->sitepress->get_language_details($current_lang);
                if (isset($language_details['default_locale'])) {
                    $locale = $language_details['default_locale'];
                }
            }
        }
        echo '<meta property="og:locale" content="' . esc_attr(str_replace('_', '-', $locale)) . '" />' . "\n";
        
        // OG Locale alternatywne dla wszystkich dostępnych języków
        if ($this->language_urls && is_array($this->language_urls)) {
            $current_lang = $this->sitepress ? $this->sitepress->get_current_language() : '';
            foreach ($this->language_urls as $lang => $data) {
                if ($lang !== $current_lang && isset($data['hreflang'])) {
                    echo '<meta property="og:locale:alternate" content="' . esc_attr(str_replace('-', '_', $data['hreflang'])) . '" />' . "\n";
                }
            }
        }
        
        // Twitter Card tagi
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($final_og_title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($final_og_description) . '" />' . "\n";
        
        if (!empty($og_image)) {
            echo '<meta name="twitter:image" content="' . esc_url($og_image) . '" />' . "\n";
        } elseif (has_post_thumbnail($post->ID)) {
            $thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            if (is_array($thumbnail_src) && !empty($thumbnail_src[0])) {
                echo '<meta name="twitter:image" content="' . esc_url($thumbnail_src[0]) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Generowanie tagów hreflang dla WPML z obsługą różnych domen
     */
    public function output_hreflang_tags($post_id) {
        // Sprawdzenie czy WPML jest aktywny
        if (!$this->sitepress || !method_exists($this->sitepress, 'get_element_trid') || !method_exists($this->sitepress, 'get_element_translations')) {
            return;
        }
        
        $post_type = get_post_type($post_id);
        if (!$post_type) {
            return;
        }
        
        // Pobierz typ elementu i jego ID
        $element_type = 'post_' . $post_type;
        $trid = $this->sitepress->get_element_trid($post_id, $element_type);
        
        if (!$trid) {
            return;
        }
        
        // Pobierz wszystkie tłumaczenia elementu
        $translations = $this->sitepress->get_element_translations($trid, $element_type);
        
        if (!$translations || !is_array($translations)) {
            return;
        }
        
        // Sprawdź czy używamy konfiguracji różnych domen
        $using_different_domains = false;
        if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') && 
            method_exists($this->sitepress, 'get_setting') &&
            $this->sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN) {
            $using_different_domains = true;
        }
        
        // Tablica do przechowywania pełnych adresów URL dla każdego języka
        $language_urls = array();
        
        // Dla każdego języka wygeneruj tag hreflang
        foreach ($translations as $lang => $translation) {
            if (!isset($translation->element_id) || empty($translation->element_id)) {
                continue;
            }
            
            // Pobierz URL dla tłumaczenia
            if ($using_different_domains) {
                // Specjalna obsługa dla wielu domen
                if (function_exists('wpml_get_permalink')) {
                    // Użyj funkcji z WPML API, która automatycznie obsłuży domeny
                    $url = apply_filters('wpml_permalink', get_permalink($translation->element_id), $lang);
                } else {
                    // Zabezpieczenie, jeśli funkcja API nie jest dostępna
                    $language_domains = $this->sitepress->get_setting('language_domains', array());
                    $home_url = get_home_url();
                    $parsed_home = parse_url($home_url);
                    $host = isset($parsed_home['host']) ? $parsed_home['host'] : '';
                    
                    // Podstawowy URL
                    $url = get_permalink($translation->element_id);
                    
                    if (isset($language_domains[$lang]) && !empty($language_domains[$lang])) {
                        // Zastąp domenę dla konkretnego języka
                        $domain = $language_domains[$lang];
                        $url = str_replace($host, $domain, $url);
                    }
                }
            } else {
                // Standardowa metoda dla innych konfiguracji WPML
                $url = get_permalink($translation->element_id);
            }
            
            if (!$url) {
                continue;
            }
            

            $hreflang = $lang;
            if (method_exists($this->sitepress, 'get_language_details')) {
                $language_details = $this->sitepress->get_language_details($lang);
                
                if (isset($language_details['default_locale'])) {

                    $hreflang = strtolower(str_replace('_', '-', $language_details['default_locale']));
                }
            }
            
            // Zapisz URL dla późniejszego użycia
            $language_urls[$lang] = array(
                'url' => $url,
                'hreflang' => $hreflang
            );
            
            // Wygeneruj tag hreflang
            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($url) . '" />' . "\n";
        }
        
        // Dodaj tag hreflang x-default dla głównego języka
        if (method_exists($this->sitepress, 'get_default_language')) {
            $default_lang = $this->sitepress->get_default_language();
            if (isset($language_urls[$default_lang])) {
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($language_urls[$default_lang]['url']) . '" />' . "\n";
            }
        }
        
        // Zapisz URLs do użycia w meta OG dla różnych języków
        $this->language_urls = $language_urls;
    }

    /**
     * Zmiana tytułu dokumentu
     */
    public function custom_document_title($title) {
        global $post;
        
        if (!is_singular() || !$post) {
            return $title;
        }
        
        $meta_title = get_post_meta($post->ID, '_meta_title', true);
        
        if (!empty($meta_title)) {
            return esc_html($meta_title);
        }
        
        return $title;
    }
}

/**
 * Inicjalizacja pluginu
 */
function wp_meta_og_manager_init() {

    $lang_dir = plugin_dir_path(__FILE__) . 'languages';
    if (!file_exists($lang_dir)) {
        wp_mkdir_p($lang_dir);
    }
    

    global $wp_meta_og_manager;
    $wp_meta_og_manager = new WP_Meta_OG_Manager();
}

/**
 * Sprawdź czy WPML jest prawidłowo skonfigurowany dla wielu domen
 */
function wp_meta_og_manager_admin_notice() {
    // Sprawdź czy WPML jest aktywny
    if (!defined('ICL_SITEPRESS_VERSION')) {
        return;
    }
    
    global $sitepress;
    if (!$sitepress || !is_object($sitepress) || !method_exists($sitepress, 'get_setting')) {
        return;
    }
    
    // Sprawdź czy używamy konfiguracji wielu domen
    if (defined('WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN') && 
        $sitepress->get_setting('language_negotiation_type') == WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN) {
        
        // Sprawdź czy wszystkie domeny są skonfigurowane
        $language_domains = $sitepress->get_setting('language_domains');
        $active_languages = $sitepress->get_active_languages();
        
        if (empty($language_domains) || count($language_domains) < count($active_languages) - 1) {
            // Przynajmniej jeden język nie ma skonfigurowanej domeny
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('WordPress Meta & OG Tags Manager - Uwaga:', 'wp-meta-og-manager'); ?></strong>
                    <?php _e('Wykryto konfigurację WPML z wieloma domenami, ale nie wszystkie języki mają przypisane domeny. Aby zapewnić prawidłowe działanie tagów Open Graph i hreflang, należy skonfigurować domeny dla wszystkich aktywnych języków w ustawieniach WPML.', 'wp-meta-og-manager'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=sitepress-multilingual-cms/menu/languages.php'); ?>" class="button button-primary">
                        <?php _e('Konfiguruj domeny WPML', 'wp-meta-og-manager'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

// Dodaj powiadomienie administracyjne dla wielodomenowej konfiguracji WPML
add_action('admin_notices', 'wp_meta_og_manager_admin_notice');


add_action('init', 'wp_meta_og_manager_init');
