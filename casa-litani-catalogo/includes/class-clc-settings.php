<?php
if (!defined('ABSPATH')) exit;

/**
 * Pantalla de configuración de la búsqueda automática de fotos (Google Custom Search).
 */
class CLC_Settings {

    const OPTION_API_KEY = 'clc_google_api_key';
    const OPTION_CX = 'clc_google_cx';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_guardar_config_fotos', [__CLASS__, 'guardar_config']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Config. búsqueda de fotos',
            'Config. Fotos',
            'manage_options',
            'clc-config-fotos',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        $api_key = get_option(self::OPTION_API_KEY, '');
        $cx = get_option(self::OPTION_CX, '');
        ?>
        <div class="wrap">
            <h1>Configuración: búsqueda automática de fotos</h1>
            <p>Usa la API de Google Custom Search (Búsqueda de Imágenes) para sugerir fotos de cada artículo por nombre/modelo,
            igual que se hizo con las recetas de Ratatuin. Necesitás una API Key y un Search Engine ID (CX) configurados
            para búsqueda de imágenes en toda la web, en <a href="https://programmablesearchengine.google.com/" target="_blank">Google Programmable Search Engine</a>.</p>

            <?php if (isset($_GET['guardado'])): ?>
                <div class="notice notice-success"><p>Guardado correctamente.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="clc_guardar_config_fotos">
                <?php wp_nonce_field('clc_guardar_config_fotos', 'clc_config_fotos_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="clc_api_key">API Key de Google</label></th>
                        <td><input type="text" id="clc_api_key" name="clc_api_key" value="<?php echo esc_attr($api_key); ?>" style="width:400px;"></td>
                    </tr>
                    <tr>
                        <th><label for="clc_cx">Search Engine ID (CX)</label></th>
                        <td><input type="text" id="clc_cx" name="clc_cx" value="<?php echo esc_attr($cx); ?>" style="width:400px;"></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Guardar</button></p>
            </form>

            <p>Una vez configurado, entrá a cualquier <strong>Artículo</strong> y vas a ver el botón "Buscar foto" en el
            editor — te muestra resultados para elegir con un click, igual que en Ratatuin.</p>
        </div>
        <?php
    }

    public static function guardar_config() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_guardar_config_fotos', 'clc_config_fotos_nonce');

        update_option(self::OPTION_API_KEY, sanitize_text_field($_POST['clc_api_key'] ?? ''));
        update_option(self::OPTION_CX, sanitize_text_field($_POST['clc_cx'] ?? ''));

        wp_safe_redirect(add_query_arg('guardado', '1', admin_url('edit.php?post_type=articulo&page=clc-config-fotos')));
        exit;
    }
}
