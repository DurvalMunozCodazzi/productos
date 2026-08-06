<?php
if (!defined('ABSPATH')) exit;

class CLC_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_clc_guardar_whatsapp', [__CLASS__, 'guardar_whatsapp']);
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=articulo',
            'Números de WhatsApp',
            'WhatsApp',
            'manage_options',
            'clc-whatsapp',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        $numeros = get_option(CLC_Whatsapp::OPTION_NUMEROS, []);
        // Aseguramos siempre al menos 3 filas para completar en el formulario
        while (count($numeros) < 3) {
            $numeros[] = ['numero' => '', 'referencia' => '', 'activo' => false];
        }
        ?>
        <div class="wrap">
            <h1>Números de WhatsApp (consultas de catálogo)</h1>
            <p>Estos números reciben las consultas de "Consultar precio" de cada artículo, rotando entre los activos.</p>
            <?php if (isset($_GET['guardado'])): ?>
                <div class="notice notice-success"><p>Guardado correctamente.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="clc_guardar_whatsapp">
                <?php wp_nonce_field('clc_guardar_whatsapp', 'clc_whatsapp_nonce'); ?>
                <table class="widefat" style="max-width:700px;">
                    <thead>
                        <tr><th>Número (con código de país, ej: 5491122334455)</th><th>Referencia</th><th>Activo</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($numeros as $i => $n): ?>
                        <tr>
                            <td><input type="text" name="numeros[<?php echo $i; ?>][numero]" value="<?php echo esc_attr($n['numero']); ?>" style="width:100%;"></td>
                            <td><input type="text" name="numeros[<?php echo $i; ?>][referencia]" value="<?php echo esc_attr($n['referencia']); ?>" style="width:100%;" placeholder="Ej: Empleado 1"></td>
                            <td><input type="checkbox" name="numeros[<?php echo $i; ?>][activo]" value="1" <?php checked(!empty($n['activo'])); ?>></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary">Guardar</button></p>
            </form>
        </div>
        <?php
    }

    public static function guardar_whatsapp() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('clc_guardar_whatsapp', 'clc_whatsapp_nonce');

        $numeros_raw = isset($_POST['numeros']) ? (array) $_POST['numeros'] : [];
        $numeros = [];
        foreach ($numeros_raw as $n) {
            $numeros[] = [
                'numero' => sanitize_text_field($n['numero'] ?? ''),
                'referencia' => sanitize_text_field($n['referencia'] ?? ''),
                'activo' => !empty($n['activo']),
            ];
        }
        update_option(CLC_Whatsapp::OPTION_NUMEROS, $numeros);

        wp_safe_redirect(add_query_arg('guardado', '1', admin_url('edit.php?post_type=articulo&page=clc-whatsapp')));
        exit;
    }
}
