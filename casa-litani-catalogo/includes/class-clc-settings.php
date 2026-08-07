<?php
if (!defined('ABSPATH')) exit;

/** Pantalla de configuración: API Key de Pexels (gratuita) para las fotos automáticas. */
class CLC_Settings {

    const OPTION_API_KEY = 'clc_pexels_api_key';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_guardar_config_fotos', [__CLASS__, 'guardar_config']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Config. de Pexels',
            'Config. Fotos',
            'manage_options',
            'clc-config-fotos',
            [__CLASS__, 'render_page']
        );
    }

    public static function get_api_key() {
        return trim(get_option(self::OPTION_API_KEY, ''));
    }

    public static function render_page() {
        $api_key = self::get_api_key();
        ?>
        <div class="wrap">
            <h1>Configuración: fotos automáticas por Pexels</h1>
            <p>Mismo sistema que se usa en ratatuin.com.ar para las recetas: la API gratuita de
            <a href="https://www.pexels.com/api/" target="_blank">Pexels</a> busca una foto por el nombre del producto
            y la carga sola. Creá una cuenta gratis en Pexels y pedí tu API Key desde
            <a href="https://www.pexels.com/api/new/" target="_blank">pexels.com/api/new</a>.</p>

            <?php if (isset($_GET['guardado'])): ?>
                <div class="notice notice-success"><p>Guardado correctamente.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="clc_guardar_config_fotos">
                <?php wp_nonce_field('clc_guardar_config_fotos', 'clc_config_fotos_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="clc_api_key">API Key de Pexels</label></th>
                        <td><input type="text" id="clc_api_key" name="clc_api_key" value="<?php echo esc_attr($api_key); ?>" style="width:400px;"></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Guardar</button></p>
            </form>

            <p>Una vez guardada, andá a <strong>Artículos → Fotos</strong> para cargar las fotos: hay un botón para
            traer una tanda ahora, un auto-cargado que va pidiendo tandas chicas solo cada 3 minutos, y también corre
            una tanda grande automática cada hora aunque no tengas la pantalla abierta.</p>
        </div>
        <?php
    }

    public static function guardar_config() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_guardar_config_fotos', 'clc_config_fotos_nonce');

        update_option(self::OPTION_API_KEY, sanitize_text_field($_POST['clc_api_key'] ?? ''));

        wp_safe_redirect(add_query_arg('guardado', '1', admin_url('edit.php?post_type=articulo&page=clc-config-fotos')));
        exit;
    }
}
