<?php
if (!defined('ABSPATH')) exit;

class CLC_Whatsapp {

    const OPTION_NUMEROS = 'clc_whatsapp_numeros';

    public static function init() {
        // Nada que enganchar por ahora; los métodos se usan desde templates/shortcodes.
    }

    /**
     * Devuelve los números activos configurados en Ajustes > Casa Litani Catálogo.
     * Formato guardado: array de ['numero' => '549...', 'referencia' => 'Juan', 'activo' => true]
     */
    public static function get_numeros_activos() {
        $numeros = get_option(self::OPTION_NUMEROS, []);
        $activos = array_values(array_filter($numeros, function ($n) {
            return !empty($n['activo']) && !empty($n['numero']);
        }));
        return $activos;
    }

    /**
     * Elige un número rotando (round-robin) usando un contador guardado en un transient.
     */
    public static function elegir_numero_rotativo() {
        $activos = self::get_numeros_activos();
        if (empty($activos)) {
            return '';
        }
        $indice = (int) get_option('clc_whatsapp_rr_indice', 0);
        $numero = $activos[$indice % count($activos)]['numero'];
        update_option('clc_whatsapp_rr_indice', $indice + 1, false);
        return $numero;
    }

    /**
     * Arma el link wa.me con mensaje prellenado para un artículo puntual.
     */
    public static function link_consulta($post_id) {
        $numero = self::elegir_numero_rotativo();
        if (empty($numero)) {
            return '';
        }
        $nombre = get_the_title($post_id);
        $sku = get_post_meta($post_id, '_clc_sku', true);
        $url = get_permalink($post_id);

        $texto = "Hola, quiero consultar precio de: {$nombre}";
        if (!empty($sku)) {
            $texto .= " (SKU: {$sku})";
        }
        $texto .= " - {$url}";

        $numero_limpio = preg_replace('/[^0-9]/', '', $numero);
        return 'https://wa.me/' . $numero_limpio . '?text=' . rawurlencode($texto);
    }
}
