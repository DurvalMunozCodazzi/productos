<?php
/**
 * Plugin Name: Casa Litani - Catálogo
 * Description: Catálogo de productos (sin precios) con categorías, marcas, importador de Excel y consulta por WhatsApp.
 * Version: 4.4.0
 * Author: Durval Muñoz Codazzi - Web Sobre Ruedas
 * Author URI: https://websobreruedas.com
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CLC_PATH', plugin_dir_path(__FILE__));
define('CLC_URL', plugin_dir_url(__FILE__));
define('CLC_VERSION', '4.4.0');

require_once CLC_PATH . 'includes/class-clc-cache.php';
require_once CLC_PATH . 'includes/class-clc-layout.php';
require_once CLC_PATH . 'includes/class-clc-post-type.php';
require_once CLC_PATH . 'includes/class-clc-whatsapp.php';
require_once CLC_PATH . 'includes/class-clc-settings.php';
require_once CLC_PATH . 'includes/class-clc-shortcodes.php';
require_once CLC_PATH . 'includes/class-clc-xlsx-reader.php';
require_once CLC_PATH . 'includes/class-clc-importer.php';
require_once CLC_PATH . 'includes/class-clc-fotos.php';
require_once CLC_PATH . 'includes/class-clc-filtro.php';
require_once CLC_PATH . 'includes/class-clc-pexels.php';
require_once CLC_PATH . 'includes/class-clc-descripciones.php';
require_once CLC_PATH . 'includes/class-clc-backup.php';
require_once CLC_PATH . 'includes/class-clc-exportar.php';
require_once CLC_PATH . 'includes/class-clc-estadisticas.php';
require_once CLC_PATH . 'includes/class-clc-imagenes.php';
require_once CLC_PATH . 'includes/class-clc-sitemap.php';
require_once CLC_PATH . 'includes/class-clc-offline.php';
require_once CLC_PATH . 'includes/class-clc-notificaciones.php';
require_once CLC_PATH . 'includes/class-clc-migracion.php';

function clc_init_plugin() {
    CLC_Post_Type::init();
    CLC_Whatsapp::init();
    CLC_Settings::init();
    CLC_Shortcodes::init();
    CLC_Importer::init();
    CLC_Fotos::init();
    CLC_Filtro::init();
    CLC_Pexels::init();
    CLC_Descripciones::init();
    CLC_Backup::init();
    CLC_Exportar::init();
    CLC_Estadisticas::init();
    CLC_Imagenes::init();
    CLC_Sitemap::init();
    CLC_Offline::init();
    CLC_Notificaciones::init();
    CLC_Migracion::init();
}
add_action('plugins_loaded', 'clc_init_plugin');

function clc_activate() {
    CLC_Post_Type::register_post_type();
    CLC_Post_Type::register_taxonomies();
    clc_crear_pagina_catalogo();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'clc_activate');

/**
 * Las reglas nuevas (sitemap.xml, clc-sw.js) necesitan un flush de rewrite rules que
 * activation_hook no dispara en una actualización sobre una instalación ya activa
 * (desactivar+reinstalar sí lo hace, pero por las dudas lo forzamos una vez por versión).
 */
function clc_flush_si_hace_falta() {
    if (get_option('clc_version_flushed') !== CLC_VERSION) {
        flush_rewrite_rules();
        update_option('clc_version_flushed', CLC_VERSION);
    }
}
add_action('init', 'clc_flush_si_hace_falta', 20);

/**
 * Crea automáticamente la página "Catálogo" si no existe todavía. Solo hace falta
 * que exista para que la URL /catalogo/ tenga dónde apuntar — el contenido real
 * lo arma templates/catalogo.php (vía template_include), que ignora el post_content.
 */
function clc_crear_pagina_catalogo() {
    if (get_page_by_path('catalogo')) {
        return;
    }
    wp_insert_post([
        'post_title' => 'Catálogo',
        'post_name' => 'catalogo',
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);
}

function clc_deactivate() {
    CLC_Pexels::desactivar_cron();
    CLC_Backup::desactivar_cron_backup();
    CLC_Notificaciones::desactivar_cron();
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
    if (is_page('catalogo')) {
        $custom = CLC_PATH . 'templates/catalogo.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    if (is_404()) {
        $custom = CLC_PATH . 'templates/404.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
}
add_filter('template_include', 'clc_template_include');
