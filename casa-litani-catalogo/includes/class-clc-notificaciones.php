<?php
if (!defined('ABSPATH')) exit;

/**
 * Aviso diario por email si hay artículos sin foto y/o sin descripción hace más de
 * 24hs, para que no se olviden de entrar a Artículos → Fotos y descripciones.
 * Manda a la dirección de administración de WordPress (Ajustes → General).
 */
class CLC_Notificaciones {

    public static function init() {
        add_action('clc_revision_diaria', [__CLASS__, 'revisar_pendientes']);
        add_action('wp', [__CLASS__, 'activar_cron']);
    }

    public static function activar_cron() {
        if (!wp_next_scheduled('clc_revision_diaria')) {
            wp_schedule_event(time(), 'daily', 'clc_revision_diaria');
        }
    }

    public static function desactivar_cron() {
        wp_clear_scheduled_hook('clc_revision_diaria');
    }

    public static function revisar_pendientes() {
        $sin_foto = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [['column' => 'post_date', 'before' => '24 hours ago']],
            'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'NOT EXISTS']],
            'fields' => 'ids',
        ]);

        $sin_descripcion = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [['column' => 'post_date', 'before' => '24 hours ago']],
            'fields' => 'ids',
        ]);
        $ids_sin_descripcion = array_filter($sin_descripcion->posts, function ($id) {
            return '' === trim(get_post_field('post_content', $id));
        });

        $total_sin_foto = $sin_foto->found_posts;
        $total_sin_descripcion = count($ids_sin_descripcion);

        if ($total_sin_foto < 1 && $total_sin_descripcion < 1) {
            return;
        }

        $asunto = 'Casa Litani: artículos pendientes de revisar en el catálogo';
        $cuerpo = "Hola,\n\nHay artículos cargados hace más de 24hs que todavía no están completos:\n\n";
        if ($total_sin_foto > 0) {
            $cuerpo .= "- {$total_sin_foto} artículo(s) sin foto.\n";
        }
        if ($total_sin_descripcion > 0) {
            $cuerpo .= "- {$total_sin_descripcion} artículo(s) sin descripción.\n";
        }
        $cuerpo .= "\nEntrá a Artículos → Fotos y descripciones para completarlos:\n";
        $cuerpo .= admin_url('edit.php?post_type=articulo&page=clc-fotos&clc_sin_foto=1') . "\n";

        wp_mail(get_option('admin_email'), $asunto, $cuerpo);
    }
}
