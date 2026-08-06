jQuery(function ($) {
    $('#clc-btn-buscar-foto').on('click', function () {
        var postId = $(this).data('post-id');
        var titulo = $('#title').val();
        var contenedor = $('#clc-resultados-fotos');
        contenedor.html('Buscando...');

        $.post(clcFotos.ajaxUrl, {
            action: 'clc_buscar_fotos',
            nonce: clcFotos.nonce,
            query: titulo
        }).done(function (respuesta) {
            contenedor.empty();
            if (!respuesta.success) {
                contenedor.text('Sin resultados: ' + respuesta.data);
                return;
            }
            respuesta.data.forEach(function (item) {
                var img = $('<img>', {
                    src: item.miniatura,
                    style: 'width:70px;height:70px;object-fit:cover;cursor:pointer;border:1px solid #ccc;border-radius:4px;'
                });
                img.on('click', function () {
                    img.css('opacity', 0.5);
                    $.post(clcFotos.ajaxUrl, {
                        action: 'clc_asignar_foto',
                        nonce: clcFotos.nonce,
                        post_id: postId,
                        url_imagen: item.url
                    }).done(function (r) {
                        if (r.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + r.data);
                            img.css('opacity', 1);
                        }
                    });
                });
                contenedor.append(img);
            });
        });
    });
});
