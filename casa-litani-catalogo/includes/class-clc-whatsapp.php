<?php
if (!defined('ABSPATH')) exit;

class CLC_Whatsapp {

    // Número único de consultas de Casa Litani (confirmado por el cliente).
    const NUMERO_FIJO = '+595985773704';

    public static function init() {
        // Nada que enganchar por ahora; los métodos se usan desde templates/shortcodes.
    }

    /**
     * Arma el link wa.me con mensaje prellenado para un artículo puntual.
     * Formato del mensaje: Estoy interesado en este producto: "Nombre del producto"
     */
    public static function link_consulta($post_id) {
        $nombre = get_the_title($post_id);
        $url = get_permalink($post_id);

        $texto = 'Estoy interesado en este producto: "' . $nombre . '" - ' . $url;

        $numero_limpio = preg_replace('/[^0-9]/', '', self::NUMERO_FIJO);
        return 'https://wa.me/' . $numero_limpio . '?text=' . rawurlencode($texto);
    }
}
