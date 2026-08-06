<?php
if (!defined('ABSPATH')) exit;

class CLC_Post_Type {

    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_taxonomies']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta']);
        add_filter('manage_articulo_posts_columns', [__CLASS__, 'admin_columns']);
        add_action('manage_articulo_posts_custom_column', [__CLASS__, 'admin_column_content'], 10, 2);
    }

    public static function register_post_type() {
        register_post_type('articulo', [
            'labels' => [
                'name' => 'Artículos',
                'singular_name' => 'Artículo',
                'add_new_item' => 'Agregar Artículo',
                'edit_item' => 'Editar Artículo',
                'search_items' => 'Buscar Artículos',
                'all_items' => 'Todos los Artículos',
            ],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'producto'],
            'menu_icon' => 'dashicons-cart',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_taxonomies() {
        register_taxonomy('categoria_producto', 'articulo', [
            'labels' => [
                'name' => 'Categorías',
                'singular_name' => 'Categoría',
            ],
            'hierarchical' => true,
            'public' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'categoria'],
        ]);

        register_taxonomy('marca_producto', 'articulo', [
            'labels' => [
                'name' => 'Marcas',
                'singular_name' => 'Marca',
            ],
            'hierarchical' => true,
            'public' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'marca'],
        ]);
    }

    public static function add_meta_boxes() {
        add_meta_box('clc_articulo_datos', 'Datos del Artículo', [__CLASS__, 'render_meta_box'], 'articulo', 'normal', 'high');
    }

    public static function render_meta_box($post) {
        wp_nonce_field('clc_save_articulo', 'clc_articulo_nonce');
        $sku = get_post_meta($post->ID, '_clc_sku', true);
        $estado = get_post_meta($post->ID, '_clc_estado', true) ?: 'activo';
        ?>
        <p>
            <label for="clc_sku"><strong>SKU / Código</strong></label><br>
            <input type="text" id="clc_sku" name="clc_sku" value="<?php echo esc_attr($sku); ?>" style="width:100%;" placeholder="Ej: CEL-0231">
        </p>
        <p>
            <label for="clc_estado"><strong>Estado</strong></label><br>
            <select id="clc_estado" name="clc_estado">
                <option value="activo" <?php selected($estado, 'activo'); ?>>Activo</option>
                <option value="discontinuado" <?php selected($estado, 'discontinuado'); ?>>Discontinuado</option>
                <option value="pendiente_foto" <?php selected($estado, 'pendiente_foto'); ?>>Pendiente de foto</option>
            </select>
        </p>
        <?php
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['clc_articulo_nonce']) || !wp_verify_nonce($_POST['clc_articulo_nonce'], 'clc_save_articulo')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['clc_sku'])) {
            update_post_meta($post_id, '_clc_sku', sanitize_text_field($_POST['clc_sku']));
        }
        if (isset($_POST['clc_estado'])) {
            update_post_meta($post_id, '_clc_estado', sanitize_text_field($_POST['clc_estado']));
        }
    }

    public static function admin_columns($columns) {
        $columns['clc_sku'] = 'SKU';
        $columns['clc_estado'] = 'Estado';
        return $columns;
    }

    public static function admin_column_content($column, $post_id) {
        if ($column === 'clc_sku') {
            echo esc_html(get_post_meta($post_id, '_clc_sku', true));
        }
        if ($column === 'clc_estado') {
            echo esc_html(get_post_meta($post_id, '_clc_estado', true) ?: 'activo');
        }
    }
}
