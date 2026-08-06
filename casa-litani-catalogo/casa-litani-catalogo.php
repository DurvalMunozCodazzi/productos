<?php
/**
 * Plugin Name: Casa Litani - Catálogo
 * Description: Catálogo de productos (sin precios) con categorías, marcas, importador de Excel y consulta por WhatsApp.
 * Version: 1.0.0
 * Author: Casa Litani
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CLC_PATH', plugin_dir_path(__FILE__));
define('CLC_URL', plugin_dir_url(__FILE__));
define('CLC_VERSION', '1.0.0');

require_once CLC_PATH . 'includes/class-clc-post-type.php';
require_once CLC_PATH . 'includes/class-clc-whatsapp.php';
require_once CLC_PATH . 'includes/class-clc-settings.php';
require_once CLC_PATH . 'includes/class-clc-shortcodes.php';
require_once CLC_PATH . 'includes/class-clc-importer.php';

function clc_init_plugin() {
    CLC_Post_Type::init();
    CLC_Whatsapp::init();
    CLC_Settings::init();
    CLC_Shortcodes::init();
    CLC_Importer::init();
}
add_action('plugins_loaded', 'clc_init_plugin');

function clc_activate() {
    CLC_Post_Type::register_post_type();
    CLC_Post_Type::register_taxonomies();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'clc_activate');

function clc_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'clc_deactivate');

function clc_enqueue_assets() {
    wp_enqueue_style('clc-catalogo', CLC_URL . 'assets/css/catalogo.css', [], CLC_VERSION);
    wp_enqueue_script('clc-catalogo', CLC_URL . 'assets/js/catalogo.js', [], CLC_VERSION, true);
}
add_action('wp_enqueue_scripts', 'clc_enqueue_assets');

function clc_template_include($template) {
    if (is_singular('articulo')) {
        $custom = CLC_PATH . 'templates/single-articulo.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
}
add_filter('template_include', 'clc_template_include');
