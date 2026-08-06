<?php
/**
 * Plugin Name: Casa Litani - Catálogo
 * Description: Catálogo de productos (sin precios) con categorías, marcas, importador de Excel y consulta por WhatsApp.
 * Version: 1.1.0
 * Author: Durval Muñoz Codazzi - Web Sobre Ruedas
 * Author URI: https://websobreruedas.com
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CLC_PATH', plugin_dir_path(__FILE__));
define('CLC_URL', plugin_dir_url(__FILE__));
define('CLC_VERSION', '1.1.0');

require_once CLC_PATH . 'includes/class-clc-post-type.php';
require_once CLC_PATH . 'includes/class-clc-whatsapp.php';
require_once CLC_PATH . 'includes/class-clc-settings.php';
require_once CLC_PATH . 'includes/class-clc-shortcodes.php';
require_once CLC_PATH . 'includes/class-clc-importer.php';
require_once CLC_PATH . 'includes/class-clc-fotos.php';
require_once CLC_PATH . 'includes/class-clc-filtro.php';

function clc_init_plugin() {
    CLC_Post_Type::init();
    CLC_Whatsapp::init();
    CLC_Settings::init();
    CLC_Shortcodes::init();
    CLC_Importer::init();
    CLC_Fotos::init();
    CLC_Filtro::init();
}
add_action('plugins_loaded', 'clc_init_plugin');

function clc_activate() {
    CLC_Post_Type::register_post_type();
    CLC_Post_Type::register_taxonomies();
    clc_crear_pagina_catalogo();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'clc_activate');

/** Crea automáticamente la página "Catálogo" con el shortcode de categorías, si todavía no existe. */
function clc_crear_pagina_catalogo() {
    $existente = get_page_by_path('catalogo');
    if ($existente) {
        return;
    }
    $contenido = "<h2>Explorá por categoría</h2>\n[clc_categorias]\n\n<h2>O buscá directo</h2>\n[clc_filtro_catalogo]\n\n[clc_credito]";
    wp_insert_post([
        'post_title' => 'Catálogo',
        'post_name' => 'catalogo',
        'post_content' => $contenido,
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);
}

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
    if (is_tax('categoria_producto')) {
        $custom = CLC_PATH . 'templates/taxonomy-categoria_producto.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
}
add_filter('template_include', 'clc_template_include');
