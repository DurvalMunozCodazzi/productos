<?php
if (!defined('ABSPATH')) exit;

/**
 * Redimensiona/comprime cualquier foto de artículo (subida a mano o traída de Pexels)
 * apenas se adjunta — las fotos sacadas con el celular pesan varios MB y hacen que el
 * catálogo cargue lento en 4G. Se enchufa a wp_generate_attachment_metadata, que corre
 * automáticamente en media_handle_upload() y media_handle_sideload().
 */
class CLC_Imagenes {

    const ANCHO_MAXIMO = 1200;
    const CALIDAD_JPEG = 82;

    public static function init() {
        add_filter('wp_generate_attachment_metadata', [__CLASS__, 'comprimir_original'], 10, 2);
    }

    public static function comprimir_original($metadata, $attachment_id) {
        $ruta = get_attached_file($attachment_id);
        if (!$ruta || !file_exists($ruta)) {
            return $metadata;
        }
        if (0 !== strpos((string) get_post_mime_type($attachment_id), 'image/')) {
            return $metadata;
        }

        $editor = wp_get_image_editor($ruta);
        if (is_wp_error($editor)) {
            return $metadata;
        }

        $tamano = $editor->get_size();
        if ($tamano && $tamano['width'] > self::ANCHO_MAXIMO) {
            $editor->resize(self::ANCHO_MAXIMO, self::ANCHO_MAXIMO, false);
        }
        $editor->set_quality(self::CALIDAD_JPEG);
        $guardado = $editor->save($ruta);

        if (!is_wp_error($guardado)) {
            // Recalculamos los metadatos con el archivo ya redimensionado, sin volver a pasar
            // por este mismo filtro (si no, se llama a sí mismo en bucle infinito).
            remove_filter('wp_generate_attachment_metadata', [__CLASS__, 'comprimir_original'], 10);
            $metadata = wp_generate_attachment_metadata($attachment_id, $ruta);
            add_filter('wp_generate_attachment_metadata', [__CLASS__, 'comprimir_original'], 10, 2);
        }

        return $metadata;
    }
}
