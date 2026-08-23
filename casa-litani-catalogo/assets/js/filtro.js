(function () {
    const selCategoria = document.getElementById('clc-filtro-categoria');
    const selMarca = document.getElementById('clc-filtro-marca');
    const inputBuscar = document.getElementById('clc-filtro-buscar');
    const resultados = document.getElementById('clc-filtro-resultados');
    const estado = document.getElementById('clc-filtro-estado');

    if (!selCategoria) return; // shortcode no está en esta página

    let categorias = [];
    let debounce = null;

    function api(path) {
        return fetch(clcFiltro.restUrl + path).then(r => r.json());
    }

    async function cargarCategorias() {
        // parent=0 -> solo categorías de primer nivel (Ordenadores, Celulares, etc.), sin
        // mezclar las subcategorías (Notebook, Tablet...) en el mismo desplegable.
        categorias = await api('categoria_producto?per_page=100&hide_empty=true&parent=0');
        categorias.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            selCategoria.appendChild(opt);
        });
    }

    async function cargarMarcas(categoriaId) {
        selMarca.innerHTML = '<option value="">Marca: Todas</option>';
        let url = 'marca_producto?per_page=100&hide_empty=true';
        if (categoriaId) {
            // Traemos artículos de la categoría para saber qué marcas tienen stock ahí
            const articulos = await api(`articulo?categoria_producto=${categoriaId}&per_page=100&_fields=clc_datos`);
            const nombres = new Set(articulos.map(a => a.clc_datos && a.clc_datos.marca).filter(Boolean));
            const todas = await api(url);
            todas.filter(m => nombres.has(m.name)).forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                selMarca.appendChild(opt);
            });
        } else {
            const todas = await api(url);
            todas.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                selMarca.appendChild(opt);
            });
        }
    }

    function pintarResultados(articulos) {
        resultados.innerHTML = '';
        if (!articulos.length) {
            estado.textContent = 'No se encontraron artículos con ese filtro.';
            return;
        }
        estado.textContent = `${articulos.length} artículo(s) encontrados.`;
        articulos.forEach(a => {
            const datos = a.clc_datos || {};
            const card = document.createElement('a');
            card.className = 'clc-card clc-card-articulo';
            card.href = datos.permalink || '#';

            if (datos.imagen) {
                const img = document.createElement('img');
                img.src = datos.imagen;
                img.alt = a.title.rendered;
                card.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'clc-thumb-placeholder';
                placeholder.textContent = 'Foto pendiente';
                card.appendChild(placeholder);
            }

            const titulo = document.createElement('span');
            titulo.className = 'clc-card-titulo';
            titulo.textContent = a.title.rendered;
            card.appendChild(titulo);

            resultados.appendChild(card);
        });
    }

    async function buscar() {
        estado.textContent = 'Buscando…';
        let path = 'articulo?per_page=60&_fields=id,title,clc_datos';
        if (selCategoria.value) path += `&categoria_producto=${selCategoria.value}`;
        if (selMarca.value) path += `&marca_producto=${selMarca.value}`;
        if (inputBuscar.value.trim()) path += `&search=${encodeURIComponent(inputBuscar.value.trim())}`;

        const articulos = await api(path);
        pintarResultados(Array.isArray(articulos) ? articulos : []);
    }

    selCategoria.addEventListener('change', async () => {
        await cargarMarcas(selCategoria.value);
        buscar();
    });
    selMarca.addEventListener('change', buscar);
    inputBuscar.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(buscar, 350);
    });

    cargarCategorias().then(() => {
        cargarMarcas('').then(buscar);
    });
})();
