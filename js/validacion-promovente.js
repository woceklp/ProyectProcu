// validacion-promovente.js - Script actualizado para la validación de promoventes
document.addEventListener('DOMContentLoaded', function() {
    // Limpiar el formulario cada vez que se abre el modal
    $('#buscarPromovente').on('show.bs.modal', function() {
        // Limpiar todos los campos del formulario
        $('#formBuscarPromovente')[0].reset();
        
        // Ocultar resultados anteriores
        $('#resultadosPromovente').addClass('d-none');
        $('#listaPromoventes').empty();
        
        // Ocultar botón de nuevo promovente
        $('#btnNuevoPromovente').addClass('d-none');
    });
    
    // Evento click para el botón de búsqueda
    $('#btnBuscarPromovente').click(function() {
        const nombre = $('#nombre').val().trim();
        const apellidoPaterno = $('#apellidoPaterno').val().trim();
        const apellidoMaterno = $('#apellidoMaterno').val().trim();
        
        // Validación básica (solo verificar que al menos uno tenga valor)
        if (nombre === '' && apellidoPaterno === '' && apellidoMaterno === '') {
            mostrarAlerta('Debe completar al menos uno de los campos', 'danger');
            return;
        }
        
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Validando promovente',
            text: 'Por favor espere...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Realizar petición AJAX para buscar al promovente
        $.ajax({
            url: BASE_URL + 'api/buscar_promovente.php',
            type: 'POST',
            data: {
                nombre: nombre,
                apellido_paterno: apellidoPaterno,
                apellido_materno: apellidoMaterno
            },
            dataType: 'json',
            success: function(response) {
                Swal.close(); // Cerrar el indicador de carga
                
                if (response.status === 'success') {
                    // Ocultar el modal de búsqueda
                    $('#buscarPromovente').modal('hide');
                    
                    // Resetear y preparar el modal de resultados
                    resetearModalResultados();
                    
                    if (response.data && response.data.length > 0) {
                        // Mostrar resultado de promovente encontrado
                        mostrarPromoventeEncontrado(response.data);
                    } else {
                        // Mostrar resultado de promovente no encontrado
                        mostrarPromoventeNoEncontrado(nombre, apellidoPaterno, apellidoMaterno);
                    }
                    
                    // Mostrar el modal de resultados
                    $('#resultadosValidacionModal').modal('show');
                } else {
                    // Manejar error
                    mostrarErrorValidacion(response.message || 'Error al buscar promovente');
                }
            },
            error: function() {
                Swal.close();
                mostrarErrorValidacion('Error de conexión con el servidor');
            }
        });
    });
        
    // Evento para volver a la búsqueda
    $('#btnVolverBuscar').click(function() {
        $('#resultadosValidacionModal').modal('hide');
        $('#buscarPromovente').modal('show');
    });
    
    // Evento para continuar con promovente existente
    $('#btnContinuarPromovente').click(function() {
        const idPromovente = $(this).data('id-promovente');
        const nombreCompleto = $(this).data('nombre-completo');
        
        // Cerrar modal de resultados
        $('#resultadosValidacionModal').modal('hide');
        
        // Limpiar el formulario de búsqueda para la próxima vez
        $('#formBuscarPromovente')[0].reset();
        
        // Seleccionar el promovente y continuar
        seleccionarPromovente(idPromovente, nombreCompleto);
    });
    
    // Evento para registrar nuevo promovente
    $('#btnRegistrarNuevo').click(function() {
        // Cerrar modal de resultados
        $('#resultadosValidacionModal').modal('hide');
        
        // Transferir datos al formulario de nuevo promovente
        $('#nuevoNombre').val($('#nombre').val());
        $('#nuevoApellidoPaterno').val($('#apellidoPaterno').val());
        $('#nuevoApellidoMaterno').val($('#apellidoMaterno').val());
        
        // Limpiar el formulario de búsqueda para la próxima vez
        $('#formBuscarPromovente')[0].reset();
        
        // Abrir modal para nuevo promovente
        $('#nuevoPromovente').modal('show');
    });
});

// Función para mostrar alertas dentro del modal de búsqueda
function mostrarAlerta(mensaje, tipo) {
    // Usar SweetAlert2 para mostrar mensajes con estilo consistente
    Swal.fire({
        icon: tipo === 'danger' ? 'error' : tipo,
        title: 'Atención',
        text: mensaje,
        confirmButtonColor: '#336699'  // Color primario del sistema
    });
}

// Función para resetear el modal de resultados
function resetearModalResultados() {
    // Ocultar todos los contenidos
    $('#promoventeEncontradoContent, #promoventeNoEncontradoContent, #errorValidacionContent').addClass('d-none');
    $('#btnContinuarPromovente, #btnRegistrarNuevo').addClass('d-none');
    
    // Resetear colores y títulos por defecto - usando los colores del sistema
    $('#resultadosHeaderColor').attr('class', 'modal-header bg-primary text-white');
    $('#resultadosIcon').attr('class', 'fas fa-info-circle me-2');
    $('#resultadosTitulo').text('Resultados de Validación');
}

// Función para mostrar promovente encontrado
function mostrarPromoventeEncontrado(promoventes) {
    // Cambiar apariencia del header a éxito
    $('#resultadosHeaderColor').attr('class', 'modal-header bg-success text-white');
    $('#resultadosIcon').attr('class', 'fas fa-check-circle me-2');
    $('#resultadosTitulo').text('Promoventes Encontrados');
    
    // Limpiar contenido anterior
    $('#promoventeEncontradoContent').html('');
    
    // Si solo hay un promovente, mostrar como antes
    if (promoventes.length === 1) {
        const promovente = promoventes[0];
        const nombreCompleto = promovente.nombre + ' ' + promovente.apellido_paterno + ' ' + promovente.apellido_materno;
        let infoHTML = `
            <div class="text-center mb-4">
                <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                <h5 class="text-success">¡Promovente encontrado!</h5>
                <p>Se ha encontrado al promovente con los datos proporcionados.</p>
            </div>
            
            <div class="card border-success mb-3">
                <div class="card-header bg-success text-white">Detalles del promovente</div>
                <div class="card-body">
                    <h5 class="card-title">${nombreCompleto}</h5>
                    <p class="card-text">Teléfono: ${promovente.telefono || 'No registrado'}</p>
                    ${promovente.telefono2 ? `<p class="card-text">Teléfono 2: ${promovente.telefono2}</p>` : ''}
                    ${promovente.direccion ? `<p class="card-text">Dirección: ${promovente.direccion}</p>` : ''}
                </div>
            </div>
            
            <div class="alert alert-success">
                <i class="fas fa-info-circle me-2"></i>
                Puede continuar con este promovente para evitar duplicados.
            </div>
        `;
        
        $('#promoventeEncontradoContent').html(infoHTML);
        
        // Configurar botón para continuar
        $('#btnContinuarPromovente')
            .data('id-promovente', promovente.id_promovente)
            .data('nombre-completo', nombreCompleto)
            .removeClass('d-none');
    } 
    // Si hay múltiples promoventes, mostrar lista para elegir
    else {
        let infoHTML = `
            <div class="text-center mb-3">
                <i class="fas fa-users text-primary fa-4x mb-3"></i>
                <h5 class="text-primary">Se encontraron varios promoventes similares</h5>
                <p>Por favor, seleccione el promovente correcto o registre uno nuevo si no corresponde a ninguno:</p>
            </div>
        `;
        
        infoHTML += '<div class="list-group mb-3">';
        promoventes.forEach(promovente => {
            const nombreCompleto = promovente.nombre + ' ' + promovente.apellido_paterno + ' ' + promovente.apellido_materno;
            infoHTML += `
                <button type="button" class="list-group-item list-group-item-action seleccionar-promovente" 
                        data-id-promovente="${promovente.id_promovente}" 
                        data-nombre-completo="${nombreCompleto}">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1">${nombreCompleto}</h5>
                    </div>
                    <p class="mb-1">Teléfono: ${promovente.telefono || 'No registrado'}</p>
                    ${promovente.telefono2 ? `<p class="mb-1">Teléfono 2: ${promovente.telefono2}</p>` : ''}
                    ${promovente.direccion ? `<p class="mb-1">Dirección: ${promovente.direccion}</p>` : ''}
                </button>
            `;
        });
        infoHTML += '</div>';
        
        infoHTML += `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Si ninguno de estos promoventes corresponde a la persona que busca, puede registrar un nuevo promovente.
            </div>
        `;
        
        $('#promoventeEncontradoContent').html(infoHTML);
        
        // Mostrar el botón para registrar nuevo (es probable que sea otra persona)
        $('#btnRegistrarNuevo').removeClass('d-none');
        
        // Manejar el clic en un promovente de la lista
        $('.seleccionar-promovente').click(function() {
            const idPromovente = $(this).data('id-promovente');
            const nombreCompleto = $(this).data('nombre-completo');
            
            // Ocultar todos los ítems de la lista
            $('.seleccionar-promovente').removeClass('active');
            // Activar el ítem seleccionado
            $(this).addClass('active');
            
            // Configurar botón para continuar con el promovente seleccionado
            $('#btnContinuarPromovente')
                .data('id-promovente', idPromovente)
                .data('nombre-completo', nombreCompleto)
                .removeClass('d-none');
        });
    }
    
    // Mostrar contenido
    $('#promoventeEncontradoContent').removeClass('d-none');
}

// Función para mostrar cuando no se encuentra promovente
function mostrarPromoventeNoEncontrado(nombre, apellidoPaterno, apellidoMaterno) {
    // Cambiar apariencia del header a información
    $('#resultadosHeaderColor').attr('class', 'modal-header bg-primary text-white');
    $('#resultadosIcon').attr('class', 'fas fa-user-plus me-2');
    $('#resultadosTitulo').text('Promovente No Encontrado');
    
    // Mostrar datos ingresados
    $('#nombreIngresado').text(nombre);
    $('#paternoIngresado').text(apellidoPaterno);
    $('#maternoIngresado').text(apellidoMaterno);
    
    // Mostrar solo botón para registrar nuevo
    $('#btnRegistrarNuevo').removeClass('d-none');
    
    // Mostrar contenido
    $('#promoventeNoEncontradoContent').removeClass('d-none');
}

// Función para mostrar errores
function mostrarErrorValidacion(mensaje) {
    // Cambiar apariencia del header a error
    $('#resultadosHeaderColor').attr('class', 'modal-header bg-danger text-white');
    $('#resultadosIcon').attr('class', 'fas fa-exclamation-triangle me-2');
    $('#resultadosTitulo').text('Error de Validación');
    
    // Mostrar mensaje de error
    $('#mensajeError').text(mensaje);
    
    // Ocultar modal de búsqueda y mostrar modal de resultados
    $('#buscarPromovente').modal('hide');
    
    // Mostrar contenido
    $('#errorValidacionContent').removeClass('d-none');
    $('#resultadosValidacionModal').modal('show');
}