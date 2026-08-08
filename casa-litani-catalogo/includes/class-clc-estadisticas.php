<?php
if (!defined('ABSPATH') ) exit;

/**
 * Cuenta cuántas veces se tocó "Consultar" o "Compartir" en cada artículo (vía beacon
 * JS, sin recargar página) y muestra un ranking simple en el admin — para que el
 * cliente sepa qué productos generan más interés real, no solo vistas.
 */
class CLC_Estadisticas {

    public static function init() {
        add_action('wp_ajax_clc_track_click', [__CLASS__, 'track_click']);
        add_action('wp_ajax_nopriv_clc_track_click', [__CLASS__, 'track_click']);
        add_action('wp_footer', [__CLASS__, 'imprimir_script']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
    }

    public static function imprimir_script() {
        if (!is_singular('articulo')) {
            return;
        }
        ?>
        <script>
        (function () {
            function rastrear(tipo, postId) {
                var datos = new FormData();
                datos.append('action', 'clc_track_click');
                datos.append('tipo', tipo);
                datos.append('post_id', postId);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', datos);
                } else {
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: datos, keepalive: true });
                }
            }
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.clc-btn-whatsapp, .clc-btn-compartir');
                if (!btn) return;
                var tipo = btn.classList.contains('clc-btn-compartir') ? 'compartir' : 'consultar';
                rastrear(tipo, <?php echo (int) get_the_ID(); ?>);
            });
        })();
        </script>
        <?php
    }

    public static function track_click() {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $tipo = ('compartir' === ($_POST['tipo'] ?? '')) ? 'compartir' : 'consultar';
        if ($post_id && 'articulo' === get_post_type($post_id)) {
            $meta_key = '_clc_clicks_' . $tipo;
            $actual = (int) get_post_meta($post_id, $meta_key, true);
            update_post_meta($post_id, $meta_key, $actual + 1);
        }
        wp_send_json_success();
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Estadísticas de interés',
            'Estadísticas',
            'manage_options',
            'clc-estadisticas',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $query = new WP_Query([
            'post_type' => 'articulo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_clc_clicks_consultar',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ]);
        ?>
        <div class="wrap">
            <h1>Estadísticas de interés</h1>
            <p>Cuántas veces se tocó "Consultar" o "Compartir" en cada artículo, desde que existe esta versión del plugin.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th style="width:140px;">Consultas por WhatsApp</th>
                        <th style="width:140px;">Veces compartido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()): while ($query->have_posts()): $query->the_post();
                        $post_id = get_the_ID();
                        $consultas = (int) get_post_meta($post_id, '_clc_clicks_consultar', true);
                        $compartidos = (int) get_post_meta($post_id, '_clc_clicks_compartir', true);
                        if ($consultas < 1 && $compartidos < 1) continue;
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank"><?php the_title(); ?></a></td>
                            <td><?php echo esc_html($consultas); ?></td>
                            <td><?php echo esc_html($compartidos); ?></td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); else: ?>
                        <tr><td colspan="3">Todavía no hay clics registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
