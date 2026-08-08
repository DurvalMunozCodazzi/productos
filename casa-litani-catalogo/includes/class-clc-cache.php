<?php
if (!defined('ABSPATH')) exit;

/**
 * Borra la caché de WP Fastest Cache (si está instalado y activo) después de
 * operaciones que cambian muchos artículos de golpe — reimportar Excel, restaurar
 * un backup — para que el catálogo no se quede mostrando versiones viejas.
 * No hace nada si el plugin de caché no está instalado; es seguro llamarla siempre.
 */
class CLC_Cache {

    public static function limpiar_si_corresponde() {
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
            return;
        }
        // Fallback por si en algún momento cambian a otro plugin de caché conocido.
        if (class_exists('WpFastestCache')) {
            $wpfc = new WpFastestCache();
            if (method_exists($wpfc, 'deleteCache')) {
                $wpfc->deleteCache(true);
            }
        }
    }
}
