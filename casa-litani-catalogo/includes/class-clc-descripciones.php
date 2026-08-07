<?php
if (!defined('ABSPATH')) exit;

/**
 * Genera automáticamente una descripción de venta (máx. 500 caracteres) para cada
 * artículo, combinando los datos ya importados del Excel (specs guardadas en el
 * contenido del post: RAM, memoria, etc.) con una frase acorde a la categoría.
 * No busca texto en internet — arma contenido propio a partir de lo que ya tenemos,
 * confiable y sin depender de que un modelo puntual tenga info indexada en algún lado.
 */
class CLC_Descripciones {

    const LIMITE_CARACTERES = 500;

    private static function frase_por_categoria() {
        return [
            'Celulares' => 'Ideal para el uso diario: llamadas, redes sociales, fotos y todo tu contenido favorito.',
            'Ordenadores' => 'Perfecto para trabajo, estudio o entretenimiento, con el rendimiento que necesitás.',
            'Audio' => 'Sonido de calidad para disfrutar tu música, series y llamadas donde estés.',
            'Gaming' => 'Diversión asegurada con la mejor experiencia de juego.',
            'Pantallas' => 'Imagen nítida y colores vibrantes para tu casa o tu oficina.',
            'Hogar' => 'Un aliado práctico para el día a día en tu hogar.',
            'Belleza' => 'Cuidado personal con la calidad que buscás.',
            'Movilidad' => 'Movete rápido y cómodo por la ciudad.',
            'Accesorios Varios' => 'El complemento que le faltaba a tu equipo.',
        ];
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_generar_descripciones', [__CLASS__, 'procesar']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Descripciones',
            'Descripciones',
            'manage_options',
            'clc-descripciones',
            [__CLASS__, 'render_page']
        );
    }

    private static function contar_sin_descripcion() {
        $query = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        $sin = 0;
        foreach ($query->posts as $post_id) {
            $contenido = trim(wp_strip_all_tags(get_post_field('post_content', $post_id)));
            // Consideramos "sin descripción real" el texto crudo que dejó el importador (specs sueltas, corto)
            if (mb_strlen($contenido) < 60 || !get_post_meta($post_id, '_clc_descripcion_generada', true)) {
                $sin++;
            }
        }
        return $sin;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $total = wp_count_posts('articulo')->publish;
        $sin_descripcion = self::contar_sin_descripcion();
        ?>
        <div class="wrap">
            <h1>Descripciones de artículos</h1>
            <p>Genera una descripción de venta (hasta 500 caracteres) para cada artículo, combinando los datos ya
            importados del Excel (RAM, memoria, categoría, marca) con una frase acorde al rubro. No busca texto en
            internet — arma contenido propio y confiable a partir de lo que ya tenemos.</p>

            <?php if (isset($_GET['resultado'])): ?>
                <div class="notice notice-success"><p><?php echo esc_html(urldecode($_GET['resultado'])); ?></p></div>
            <?php endif; ?>

            <p><strong><?php echo esc_html($total - $sin_descripcion); ?></strong> de <strong><?php echo esc_html($total); ?></strong> artículos ya tienen descripción generada.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="clc_generar_descripciones">
                <?php wp_nonce_field('clc_generar_descripciones', 'clc_desc_nonce'); ?>
                <button type="submit" class="button button-primary">Generar descripciones ahora (<?php echo esc_html($sin_descripcion); ?> pendientes)</button>
                <label style="margin-left:14px;"><input type="checkbox" name="clc_regenerar_todo" value="1"> Regenerar también las que ya tienen (pisa el texto actual)</label>
            </form>
        </div>
        <?php
    }

    public static function procesar() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_generar_descripciones', 'clc_desc_nonce');

        $regenerar_todo = !empty($_POST['clc_regenerar_todo']);

        $query = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $generadas = 0;
        foreach ($query->posts as $post_id) {
            $ya_generada = get_post_meta($post_id, '_clc_descripcion_generada', true);
            if ($ya_generada && !$regenerar_todo) {
                continue;
            }

            $descripcion = self::generar_descripcion($post_id);

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $descripcion,
            ]);
            update_post_meta($post_id, '_clc_descripcion_generada', '1');
            $generadas++;
        }

        $mensaje = "Se generaron descripciones para {$generadas} artículo(s).";
        wp_safe_redirect(add_query_arg('resultado', urlencode($mensaje), admin_url('edit.php?post_type=articulo&page=clc-descripciones')));
        exit;
    }

    public static function generar_descripcion($post_id) {
        $nombre = get_the_title($post_id);
        $categorias = wp_get_post_terms($post_id, 'categoria_producto', ['fields' => 'names']);
        $marcas = wp_get_post_terms($post_id, 'marca_producto', ['fields' => 'names']);
        $categoria = $categorias[0] ?? '';
        $marca = $marcas[0] ?? '';

        // Specs originales del importador (ej: "4RAM / 64GB"), guardadas en el contenido actual
        // salvo que ya hayan sido reemplazadas por una descripción generada antes.
        $contenido_actual = trim(wp_strip_all_tags(get_post_field('post_content', $post_id)));
        $ya_generada = get_post_meta($post_id, '_clc_descripcion_generada', true);
        $specs = ($ya_generada || mb_strlen($contenido_actual) > 60) ? '' : $contenido_actual;

        $frases = self::frase_por_categoria();
        $frase = $frases[$categoria] ?? 'Producto disponible en Casa Litani, con garantía y atención personalizada.';

        $partes = array_filter([$marca, $nombre]);
        $encabezado = implode(' ', $partes);

        $texto = $encabezado;
        if ($specs !== '') {
            $texto .= '. ' . $specs;
        }
        $texto .= '. ' . $frase;

        if (mb_strlen($texto) > self::LIMITE_CARACTERES) {
            $texto = mb_substr($texto, 0, self::LIMITE_CARACTERES - 3) . '...';
        }

        return $texto;
    }
}
