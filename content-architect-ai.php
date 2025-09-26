<?php
/**
 * Plugin Name: Content Architect AI
 * Description: חבילת AI ו-SEO חכמה לניהול, ארכיטקטורה וקיבוץ תוכן + פירורי לחם, מניעת קניבליזציה, שדות SEO, סכמות, עמוד בית דינמי ועוד. עובד נהדר עם Hello Elementor וכל תבנית וורדפרס.
 * Version: 1.3.0
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * Author: Your Team
 * Text Domain: content-architect-ai
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define('CAI_VERSION', '1.0.0');
define('CAI_PLUGIN_FILE', __FILE__);
define('CAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAI_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!function_exists('cai_get_openai_api_key')) {
    function cai_get_openai_api_key() {
        static $cached_key = null;
        if ($cached_key !== null) {
            return $cached_key;
        }
        $key = '';
        if (defined('CAI_OPENAI_API_KEY') && CAI_OPENAI_API_KEY) {
            $key = CAI_OPENAI_API_KEY;
        } else {
            $options = get_option('cai_settings', []);
            $key = isset($options['openai_api_key']) ? trim($options['openai_api_key']) : '';
        }
        $cached_key = apply_filters('cai_openai_api_key', $key);
        return $cached_key;
    }
}

// Simple PSR-4-like loader
spl_autoload_register(function($class){
    if (strpos($class, 'CAI_') === 0) {
        $file = CAI_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_','-',$class)) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

register_activation_hook(__FILE__, function(){
    // default options
    if (!get_option('cai_settings')) {
        add_option('cai_settings', array(
            'openai_api_key' => '',
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'auto_index_on_save' => 1,
            'auto_internal_links' => 1,
            'auto_schema' => 1,
            'auto_breadcrumbs_jsonld' => 1,
            'home_sections' => 3,
            'posts_per_cluster' => 4,
        ));
    }
    // ensure taxonomies exist on activation
    CAI_Taxonomies::register();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});

// init
add_action('init', ['CAI_Taxonomies', 'register']);

add_action('plugins_loaded', function(){
    new CAI_Generator();
    new CAI_Admin();
    new CAI_Breadcrumbs();
    new CAI_Cannibalization();
    new CAI_Internal_Links();
    new CAI_Schema();
    new CAI_REST();
    new CAI_Homepage();
    new CAI_Enhancements();
    new CAI_Head();
    if (is_admin()) new CAI_Editor();
});

// assets
add_action('admin_enqueue_scripts', function(){
    wp_enqueue_style('cai-admin', CAI_PLUGIN_URL . 'assets/css/admin.css', [], CAI_VERSION);
    wp_enqueue_script('cai-admin', CAI_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], CAI_VERSION, true);
    wp_localize_script('cai-admin','caiVars', ['ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('cai-admin')]);
});



// Frontend styles
add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('cai-frontend', CAI_PLUGIN_URL . 'assets/css/frontend.css', [], CAI_VERSION);
    wp_enqueue_script('cai-frontend', CAI_PLUGIN_URL . 'assets/js/frontend.js', [], CAI_VERSION, true);
});
