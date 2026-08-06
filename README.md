# Casa Litani - Catálogo (plugin WordPress)

Catálogo de productos sin precios, con categorías, marcas, importador de Excel y botón de consulta por WhatsApp (round-robin entre varios números).

## Instalación

1. Comprimir la carpeta `casa-litani-catalogo/` en un `.zip`.
2. En WordPress: Plugins → Añadir nuevo → Subir plugin → seleccionar el `.zip` → Instalar → Activar.
3. Ir a **Artículos → WhatsApp** y cargar los 3 números (con código de país, ej: `5491122334455`).
4. (Opcional, para importar el Excel) Instalar PhpSpreadsheet vía Composer dentro de la carpeta del plugin en el hosting (Plesk):
   ```
   cd wp-content/plugins/casa-litani-catalogo
   composer require phpoffice/phpspreadsheet
   ```
5. Ir a **Artículos → Importar Excel**, subir el archivo del cliente. El sistema crea categorías, marcas y artículos automáticamente según el mapeo de hojas definido en `includes/class-clc-importer.php`.

## Estructura

- `includes/class-clc-post-type.php` — Custom Post Type "Artículo" + taxonomías Categoría/Marca.
- `includes/class-clc-whatsapp.php` — lógica de armado de link `wa.me` y rotación de números.
- `includes/class-clc-settings.php` — pantalla de administración de los números de WhatsApp.
- `includes/class-clc-importer.php` — importador de Excel (PhpSpreadsheet).
- `includes/class-clc-shortcodes.php` — shortcodes de frontend: `[clc_categorias]`, `[clc_marcas categoria="..."]`, `[clc_articulos categoria="..." marca="..."]`, `[clc_boton_whatsapp]`.
- `templates/single-articulo.php` — ficha de producto (imagen + descripción + botón WhatsApp).

## Categorías vigentes (acordadas con el cliente)

Celulares, Ordenadores (PC/Portátiles/Tablets), Audio, Gaming, Pantallas (TV/Proyectores), Hogar, Belleza, Movilidad, Accesorios Varios.

## Pendiente de definir con el cliente

- Ubicación final de Relojes/Smartwatches e Impresoras dentro de "Accesorios Varios" (hoy mapeados ahí).
- Carga de productos de la categoría "Belleza" (sin datos en el Excel actual).
- Proceso de captura/carga de imágenes por SKU (aparte del importador).
