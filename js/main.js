/**
 * main.js - Funciones principales para el Sistema Gestor de Trámites Agrarios
 */
// Definir la URL base para todas las peticiones AJAX
(function() {
    // Si BASE_URL ya está definida en window, no hacer nada
    if (window.BASE_URL !== undefined) return;
    
    // De lo contrario, definirla como una propiedad de window
    window.BASE_URL = '/PROCUGESTION/';
})();

//------------------------------------------------------------------

$('#nuevoTramite').on('show.bs.modal', function() {
    // Cargar tipos de trámite
    cargarTiposTramite();
    
    // Cargar municipios
    cargarMunicipios();
    
    // Cargar tipos de núcleo agrario
    cargarTiposNucleoAgrario();
});

//------------------------------------------------------------------

var correo = '';

//------------------------------------------------------------------

// Función para cargar tipos de trámite
function cargarTiposTramite() {
    $.ajax({
        url: BASE_URL + 'api/get_tipos_tramite.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                let options = '<option value="">Seleccione...</option>';
                response.data.forEach(function(tipo) {
                    options += `<option value="${tipo.ID_TipoTramite}">${tipo.Nombre}</option>`;
                });
                $('#tipoTramite').html(options);
            } else {
                console.error('Error al cargar tipos de trámite:', response.message);
            }
        },
        error: function() {
            console.error('Error de conexión al cargar tipos de trámite');
        }
    });
}

// Función para cargar municipios
function cargarMunicipios() {
    $.ajax({
        url: BASE_URL + 'api/get_municipios.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                let options = '<option value="">Seleccione...</option>';
                response.data.forEach(function(municipio) {
                    options += `<option value="${municipio.ID_Municipio}">${municipio.Nombre}</option>`;
                });
                $('#municipio').html(options);
            } else {
                console.error('Error al cargar municipios:', response.message);
            }
        },
        error: function() {
            console.error('Error de conexión al cargar municipios');
        }
    });
}

// Función para cargar tipos de núcleo agrario
function cargarTiposNucleoAgrario() {
    $.ajax({
        url: BASE_URL + 'api/get_tipos_nucleo.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                let options = '<option value="">Seleccione...</option>';
                response.data.forEach(function(tipo) {
                    options += `<option value="${tipo.ID_TipoNucleoAgrario}">${tipo.Descripcion}</option>`;
                });
                $('#tipoNucleoAgrario').html(options);
            } else {
                console.error('Error al cargar tipos de núcleo:', response.message);
            }
        },
        error: function() {
            console.error('Error de conexión al cargar tipos de núcleo');
        }
    });
}

//------------------------------------------------------------------
/**
 * Aplica formato XXXX/YYYY a campos de folio (ej: 0123/2025)
 * @param {string} inputId - ID del elemento input al que aplicar el formato
 */
function aplicarFormatoFolio(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('input', function(e) {
        // Obtener el valor actual sin formato
        let valor = this.value.replace(/\D/g, '');
        
        // Limitar a máximo 8 dígitos (XXXX/YYYY)
        if (valor.length > 8) {
            valor = valor.substring(0, 8);
        }
        
        // Aplicar formato: XXXX/YYYY
        if (valor.length > 4) {
            this.value = valor.substring(0, 4) + '/' + valor.substring(4);
        } else {
            this.value = valor;
        }
    });
    
    // Cuando el campo pierda el foco, verificar que tenga el formato correcto
    input.addEventListener('blur', function() {
        let valor = this.value.replace(/\D/g, '');
        
        // Si tiene más de 4 dígitos pero menos de 8, completar el año actual
        if (valor.length > 4 && valor.length < 8) {
            const añoActual = new Date().getFullYear().toString();
            const digitosAñoFaltantes = 8 - valor.length;
            
            valor = valor.substring(0, 4) + añoActual.substring(0, digitosAñoFaltantes) + valor.substring(4);
            this.value = valor.substring(0, 4) + '/' + valor.substring(4);
        }
    });
}

// Cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Aplicar el formato a todos los campos de folio relevantes
    const camposFolio = [
        'folio_reloj',
        'folio_rchrp', 
        'folio_reiteracion',
        'folio_subsanacion'
    ];
    
    camposFolio.forEach(campo => {
        if (document.getElementById(campo)) {
            aplicarFormatoFolio(campo);
        }
    });
});

//------------------------------------------------------------------

$(document).ready(function() {
    // Manejo del formulario de búsqueda de promovente
    $('#btnBuscarPromovente').click(function() {
        const nombre = $('#nombre').val().trim();
        const apellidoPaterno = $('#apellidoPaterno').val().trim();
        const apellidoMaterno = $('#apellidoMaterno').val().trim();
        
        if (nombre === '' || apellidoPaterno === '' || apellidoMaterno === '') {
            mostrarAlerta('Debe completar todos los campos', 'danger');
            return;
        }
        
        // Aquí se realizaría la petición AJAX para buscar al promovente
        buscarPromovente(nombre, apellidoPaterno, apellidoMaterno);
    });
    
    // Botón para crear nuevo promovente
    $('#btnNuevoPromovente').click(function() {
        // Transferir datos del formulario de búsqueda al formulario de nuevo promovente
        $('#nuevoNombre').val($('#nombre').val());
        $('#nuevoApellidoPaterno').val($('#apellidoPaterno').val());
        $('#nuevoApellidoMaterno').val($('#apellidoMaterno').val());
        
        // Cerrar modal actual y abrir el de nuevo promovente
        $('#buscarPromovente').modal('hide');
        $('#nuevoPromovente').modal('show');
    });
    
// Guardar nuevo promovente y continuar al trámite
// En el evento click del botón guardar promovente, añadir el segundo teléfono
$('#btnGuardarPromovente').click(function() {
    // Obtener los valores de los campos
    const nombre = $('#nuevoNombre').val().trim();
    const apellidoPaterno = $('#nuevoApellidoPaterno').val().trim();
    const apellidoMaterno = $('#nuevoApellidoMaterno').val().trim();
    const telefono = $('#telefono').val().trim();
    const telefono2 = $('#telefono2').val().trim();
    const direccion = $('#direccion').val().trim();
    
    // Validar solo el nombre como campo obligatorio
    if (nombre === '') {
        mostrarAlerta('El nombre es obligatorio', 'danger', '#nuevoPromovente .modal-body');
        return;
    }
    
    // Realizar la petición AJAX para guardar el promovente
    guardarPromovente(nombre, apellidoPaterno, apellidoMaterno, telefono, telefono2, direccion);
});

// Evento para guardar nuevo trámite
    $('#btnGuardarTramite').click(function() {
        // Validar el formulario de nuevo trámite
        if (!validarFormularioTramite()) {
            return;
        }
        
        // Recolectar datos del formulario
        const datosTramite = {
            id_promovente: $('#formNuevoTramite').data('id_promovente'),
            fecha: $('#fechaTramite').val(),
            ciia: $('#ciia').val(),
            tipo_tramite: $('#tipoTramite').val(),
            clave_tramite: $('#claveTramite').val(),
            municipio: $('#municipio').val(),
            tipo_nucleo_agrario: $('#tipoNucleoAgrario').val(),
            nucleo_agrario: $('#nucleoAgrario').val(),
            descripcion: $('#descripcionTramite').val()
        };
        
        // Aquí se realizaría la petición AJAX para guardar el trámite
        guardarTramite(datosTramite);
    });
    
    // Cambio en el tipo de trámite - cargar claves correspondientes
    $('#tipoTramite').change(function() {
        const idTipoTramite = $(this).val();
        if (idTipoTramite) {
            cargarClavesTramite(idTipoTramite);
        } else {
            // Limpiar selector de claves
            $('#claveTramite').html('<option value="">Seleccione tipo de trámite primero</option>');
        }
    });
    
    // Cambio en municipio y tipo de núcleo agrario - cargar núcleos agrarios
    $('#municipio, #tipoNucleoAgrario').change(function() {
        const idMunicipio = $('#municipio').val();
        const tipoNucleoAgrario = $('#tipoNucleoAgrario').val();
        
        if (idMunicipio && tipoNucleoAgrario) {
            cargarNucleosAgrarios(idMunicipio, tipoNucleoAgrario);
        } else {
            // Limpiar selector de núcleos agrarios
            $('#nucleoAgrario').html('<option value="">Seleccione municipio y tipo primero</option>');
        }
    });
    
    // Validar CIIA (13 dígitos)
    $('#ciia').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 13);
    });
});

/**
 * Muestra una alerta en el contenedor especificado
 */
function mostrarAlerta(mensaje, tipo, contenedor = '#buscarPromovente .modal-body') {
    const alert = `
        <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Eliminar alertas previas
    $(`${contenedor} .alert`).remove();
    
    // Agregar nueva alerta
    $(contenedor).prepend(alert);
}

function prepararActualizacion(idAcuse, idTramite) {
    $('#idAcuse').val(idAcuse);
    $('#idTramite').val(idTramite);
}

/**
 * Busca un promovente por nombre y apellidos
 */
function buscarPromovente(nombre, apellidoPaterno, apellidoMaterno) {
    // Mostrar un indicador de carga
    $('#resultadosPromovente').removeClass('d-none');
    $('#listaPromoventes').html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Buscando...</p></div>');
    
    console.log("Buscando promovente con los siguientes datos:");
    console.log("Nombre:", nombre);
    console.log("Apellido Paterno:", apellidoPaterno);
    console.log("Apellido Materno:", apellidoMaterno);
    console.log("URL:", BASE_URL + 'api/buscar_promovente.php');
    
    // Convertir a formato FormData para depuración
    var formData = new FormData();
    formData.append('nombre', nombre);
    formData.append('apellido_paterno', apellidoPaterno);
    formData.append('apellido_materno', apellidoMaterno);
    
    // Hacer la petición fetch en lugar de $.ajax para mejor depuración
    fetch(BASE_URL + 'api/buscar_promovente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log("Respuesta recibida con estado:", response.status);
        return response.text(); // Primero obtener el texto para depuración
    })
    .then(text => {
        console.log("Texto de respuesta:", text);
        try {
            return JSON.parse(text); // Intentar parsear como JSON
        } catch (e) {
            throw new Error("Respuesta no es JSON válido: " + text);
        }
    })
    .then(data => {
        console.log("Datos JSON:", data);
        
        if (data.status === 'success') {
            if (data.data && data.data.length > 0) {
                // Mostrar resultados encontrados
                mostrarResultadosPromovente(data.data);
            } else {
                // No se encontró el promovente, mostrar opción para crear nuevo
                $('#listaPromoventes').html('<li class="list-group-item text-center">No se encontraron promoventes con esos datos</li>');
                $('#btnNuevoPromovente').removeClass('d-none');
            }
        } else {
            mostrarAlerta('Error: ' + data.message, 'danger');
            $('#listaPromoventes').html('');
        }
    })
    .catch(error => {
        console.error("Error en la petición:", error);
        mostrarAlerta('Error de conexión: ' + error.message, 'danger');
        $('#listaPromoventes').html('');
    });
}

/**
 * Muestra los resultados de la búsqueda de promovente
 */
function mostrarResultadosPromovente(promoventes) {
    const listaPromoventes = $('#listaPromoventes');
    listaPromoventes.empty();
    
    promoventes.forEach(function(promovente) {
        const item = `
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${promovente.nombre} ${promovente.apellido_paterno} ${promovente.apellido_materno}</strong>
                        <br>
                        <small>${promovente.telefono || 'Sin teléfono'} | ${promovente.correo || 'Sin correo'}</small>
                    </div>
                    <button class="btn btn-sm btn-primary seleccionarPromovente" 
                            data-id="${promovente.id_promovente}" 
                            data-nombre="${promovente.nombre}" 
                            data-paterno="${promovente.apellido_paterno}" 
                            data-materno="${promovente.apellido_materno}">
                        Seleccionar
                    </button>
                </div>
            </li>
        `;
        listaPromoventes.append(item);
    });
    
    // Mostrar resultados y botón para crear nuevo
    $('#resultadosPromovente').removeClass('d-none');
    $('#btnNuevoPromovente').removeClass('d-none');
    
    // Manejar evento de seleccionar promovente
    $('.seleccionarPromovente').click(function() {
        const idPromovente = $(this).data('id');
        const nombre = $(this).data('nombre');
        const paterno = $(this).data('paterno');
        const materno = $(this).data('materno');
        
        seleccionarPromovente(idPromovente, `${nombre} ${paterno} ${materno}`);
    });
}

/**
 * Selecciona un promovente y abre el modal de nuevo trámite
 */
function seleccionarPromovente(idPromovente, nombreCompleto) {
    // Cerrar modal actual
    $('#buscarPromovente').modal('hide');
    
    // Configurar el modal de nuevo trámite
    $('#nombrePromoventeCompleto').text(nombreCompleto);
    $('#formNuevoTramite').data('id_promovente', idPromovente);
    
    // Precargar fecha actual
    $('#fechaTramite').val(new Date().toISOString().substring(0, 10));
    
    // Abrir modal de nuevo trámite
    $('#nuevoTramite').modal('show');
}

/**
 * Guarda un nuevo promovente
 */
function guardarPromovente(nombre, apellidoPaterno, apellidoMaterno, telefono, telefono2, direccion) {
    // En un entorno real, esto sería una petición AJAX
    $.ajax({
        url: BASE_URL + 'api/guardar_promovente.php',
        type: 'POST',
        data: {
            nombre: nombre,
            apellido_paterno: apellidoPaterno,
            apellido_materno: apellidoMaterno,
            telefono: telefono,
            telefono2: telefono2,
            direccion: direccion
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Cerrar modal actual
                $('#nuevoPromovente').modal('hide');
                
                // Actualizar la interfaz de usuario con los datos de la respuesta
                const nombreCompleto = nombre + ' ' + apellidoPaterno + ' ' + apellidoMaterno;
                $('.nombre-promovente').text(nombreCompleto);
                $('.telefono-principal').text(telefono || 'No registrado');
                $('.telefono-secundario').text(telefono2 || 'No registrado');
                $('.direccion-promovente').text(direccion || 'No registrada');
                
                // Seleccionar el promovente recién creado para el trámite
                seleccionarPromovente(
                    response.data.id_promovente, 
                    nombreCompleto
                );
            } else {
                mostrarAlerta('Error: ' + response.message, 'danger', '#nuevoPromovente .modal-body');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión con el servidor', 'danger', '#nuevoPromovente .modal-body');
        }
    });
}

/**
 * Valida el formulario de nuevo trámite
 */
function validarFormularioTramite() {
    // Validar CIIA
    const ciia = $('#ciia').val();
    if (ciia.length !== 13 || !/^\d+$/.test(ciia)) {
        mostrarAlerta('El número CIIA debe tener exactamente 13 dígitos', 'danger', '#nuevoTramite .modal-body');
        return false;
    }
    
    // Validar selección de campos requeridos
    const camposRequeridos = ['fechaTramite', 'tipoTramite', 'claveTramite', 'municipio', 'tipoNucleoAgrario', 'nucleoAgrario'];
    let faltaCampo = false;
    
    camposRequeridos.forEach(function(campo) {
        if ($('#' + campo).val() === '' || $('#' + campo).val() === null) {
            mostrarAlerta(`Debe seleccionar ${$('#' + campo).prev('label').text()}`, 'danger', '#nuevoTramite .modal-body');
            faltaCampo = true;
            return false;
        }
    });
    
    return !faltaCampo;
}

/**
 * Guarda un nuevo trámite
 */
function guardarTramite(datosTramite) {
    // En un entorno real, esto sería una petición AJAX
    $.ajax({
        url: window.BASE_URL + 'api/guardar_tramite.php',
        type: 'POST',
        data: datosTramite,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Cerrar modal actual
                $('#nuevoTramite').modal('hide');
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    title: '¡Trámite Registrado!',
                    text: `El trámite con CIIA ${datosTramite.ciia} ha sido registrado correctamente.`,
                    icon: 'success',
                    confirmButtonText: 'Ver Detalles',
                    showCancelButton: true,
                    cancelButtonText: 'Cerrar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirigir a la página de detalles del trámite
                        window.location.href = BASE_URL + `paginas/detalle-tramite.php?id=${response.data.id_tramite}`;
                    } else {
                        // Recargar la página para mostrar los cambios
                        window.location.reload();
                    }
                });
            } else {
                mostrarAlerta('Error: ' + response.message, 'danger', '#nuevoTramite .modal-body');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión con el servidor', 'danger', '#nuevoTramite .modal-body');
        }
    });
}

/**
 * Carga las claves de trámite según el tipo seleccionado
 */
function cargarClavesTramite(idTipoTramite) {
    $.ajax({
        url: window.BASE_URL + 'api/get_claves_tramite.php',
        type: 'GET',
        data: { id_tipo_tramite: idTipoTramite },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const clavesTramite = response.data;
                let options = '<option value="">Seleccione una clave...</option>';
                
                clavesTramite.forEach(function(clave) {
                    options += `<option value="${clave.id_clave_tramite}">${clave.clave} - ${clave.descripcion}</option>`;
                });
                
                $('#claveTramite').html(options);
            } else {
                mostrarAlerta('Error al cargar claves de trámite', 'danger', '#nuevoTramite .modal-body');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión con el servidor', 'danger', '#nuevoTramite .modal-body');
        }
    });
}

/**
 * Carga los núcleos agrarios según el municipio y tipo seleccionados
 */
function cargarNucleosAgrarios(idMunicipio, tipoNucleoAgrario) {
    $.ajax({
        url: window.BASE_URL + 'api/get_nucleos_agrarios.php',
        type: 'GET',
        data: { 
            id_municipio: idMunicipio,
            tipo_nucleo_agrario: tipoNucleoAgrario 
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const nucleosAgrarios = response.data;
                let options = '<option value="">Seleccione un núcleo agrario...</option>';
                
                nucleosAgrarios.forEach(function(nucleo) {
                    options += `<option value="${nucleo.id_nucleo_agrario}">${nucleo.nombre}</option>`;
                });
                
                $('#nucleoAgrario').html(options);
            } else {
                mostrarAlerta('Error al cargar núcleos agrarios', 'danger', '#nuevoTramite .modal-body');
            }
        },
        error: function() {
            mostrarAlerta('Error de conexión con el servidor', 'danger', '#nuevoTramite .modal-body');
        }
    });
}

// Coloca esta función y el event handler al inicio de tu archivo main.js (después de la definición de BASE_URL)
function abrirModalActualizacion(idAcuse, idTramite) {
    console.log('Función llamada con:', idAcuse, idTramite);
    $('#idAcuse').val(idAcuse);
    $('#idTramite').val(idTramite);
    $('#actualizarEstadoModal').modal('show');
}

// Más abajo, dentro de tu bloque principal de $(document).ready
$(document).ready(function() {
    // Resto de tu código jQuery...
    
    // Manejo del clic en el botón "Actualizar Estado" usando delegación de eventos
    $(document).on('click', '.actualizar-estado-js', function() {
        const idAcuse = $(this).data('id-acuse');
        const idTramite = $(this).data('id-tramite');
        
        console.log('Botón clickeado. Acuse:', idAcuse, 'Trámite:', idTramite);
        
        abrirModalActualizacion(idAcuse, idTramite);
    });
    
   // Guardar cambios de estado
$(document).on('click', '#btnGuardarEstado', function() {
    // Validar que se hayan seleccionado todos los campos
    const estadoAcuse = $('#estado_acuse').val();
    const estadoDescriptivo = $('#estado_descriptivo').val();
    const estadoBasico = $('#estado_basico').val();
    
    if (!estadoAcuse || !estadoDescriptivo || !estadoBasico) {
        Swal.fire({
            icon: 'error',
            title: 'Campos requeridos',
            text: 'Debe seleccionar todos los campos: avance, estado y comentario'
        });
        return;
    }
    
    // Enviar datos mediante AJAX
    $.ajax({
        url: window.BASE_URL + 'api/actualizar_estado_acuse.php',
        type: 'POST',
        data: $('#formActualizarEstado').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $('#actualizarEstadoModal').modal('hide');
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Estado actualizado!',
                    text: response.message,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    // Recargar página para mostrar cambios
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor'
            });
        }
    });
});
});

//-------------------------------------------------------------
// Agrega este código al final de tu archivo main.js o en el documento ready
$(document).ready(function() {
    // Función para limitar campos de teléfono a exactamente 10 dígitos
    function limitarTelefono(input) {
        // Eliminar cualquier carácter que no sea dígito
        input.value = input.value.replace(/\D/g, '');
        
        // Limitar a 10 dígitos
        if (input.value.length > 10) {
            input.value = input.value.substring(0, 10);
        }
    }
    
    // Función para convertir texto a mayúsculas
    function convertirAMayusculas(input) {
        // Solo convertir a mayúsculas si no es un campo numérico
        if (input.type !== 'number' && input.type !== 'tel' && !input.classList.contains('no-uppercase')) {
            input.value = input.value.toUpperCase();
        }
    }
    
    // Aplicar limitación de 10 dígitos a todos los campos de teléfono
    $('#telefono, #telefono2').on('input', function() {
        limitarTelefono(this);
    });
    
    // Aplicar conversión a mayúsculas a todos los campos de texto al perder el foco
    $('input[type="text"], textarea').on('blur', function() {
        convertirAMayusculas(this);
    });
    
    // También aplicar conversión a mayúsculas mientras se escribe (opcional)
    $('input[type="text"], textarea').on('input', function() {
        convertirAMayusculas(this);
    });
    
    // Aplicar estas mejoras también en el formulario de nuevo promovente
    $('#nuevoNombre, #nuevoApellidoPaterno, #nuevoApellidoMaterno, #direccion').on('input', function() {
        convertirAMayusculas(this);
    });
    
    // Aplicar a cualquier campo que pueda ser agregado dinámicamente en el futuro
    $(document).on('input', 'input[type="tel"]', function() {
        limitarTelefono(this);
    });
    
    $(document).on('input', 'input[type="text"], textarea', function() {
        convertirAMayusculas(this);
    });
});
//--------------------------------------------------------------------------------------------------

// Agregar estas funciones a tu archivo main.js

// 1. Función para abrir el modal de editar promovente
function abrirModalEditarPromovente(idPromovente, referrer, tramiteId) {
    // Limpiar formulario
    $('#formEditarPromovente')[0].reset();
    
    // Establecer valores en campos ocultos
    $('#editId_Promovente').val(idPromovente);
    $('#editReferrer').val(referrer || 'lista');
    $('#editTramiteId').val(tramiteId || 0);
    
    // Obtener datos del promovente mediante AJAX
    $.ajax({
        url: window.BASE_URL + 'api/get_promovente.php',
        type: 'GET',
        data: { id: idPromovente },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const promovente = response.data;
                
                // Llenar el formulario con los datos del promovente
                $('#editNombre').val(promovente.Nombre);
                $('#editApellidoPaterno').val(promovente.ApellidoPaterno);
                $('#editApellidoMaterno').val(promovente.ApellidoMaterno);
                $('#editTelefono').val(promovente.Telefono);
                $('#editTelefono2').val(promovente.Telefono2);
                $('#editDireccion').val(promovente.Direccion);
                
                // Mostrar el modal
                $('#editarPromovente').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo obtener la información del promovente: ' + response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión con el servidor'
            });
        }
    });
}

// 2. Función para abrir el modal de registrar folio
function abrirModalRegistrarFolio(idTramite, ciia, promovente, folioActual, fechaActual) {    // Limpiar formulario
    $('#formRegistrarFolio')[0].reset();
    
    // Establecer valores en campos ocultos
    $('#folioIdTramite').val(idTramite);
    
    // Actualizar información del trámite
    let infoHTML = `<p class="mb-0"><strong>TRÁMITE:</strong> #${idTramite} - CIIA: ${ciia}</p>
                   <p class="mb-0"><strong>PROMOVENTE:</strong> ${promovente}</p>`;
    
    // Cambiar título según si es nuevo registro o actualización
    if (folioActual) {
        $('#tituloRegistrarFolio').text('Actualizar Folio RCHRP');
        infoHTML += `<p class="mb-0 mt-2"><strong>FOLIO ACTUAL:</strong> ${folioActual}</p>`;
        $('#folio_rchrp').val(folioActual);
        
        if (fechaActual) {
            const fechaFormatted = fechaActual.substring(0, 10); // Formato YYYY-MM-DD
            $('#fecha_rchrp').val(fechaFormatted);
        }
    } else {
        $('#tituloRegistrarFolio').text('Registrar Folio RCHRP');
        // Establecer fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        $('#fecha_rchrp').val(today);
    }
    
    $('#infoTramiteFolio').html(infoHTML);
    
    // Mostrar el modal
    $('#registrarFolio').modal('show');
}

// 3. Manejar guardado de edición de promovente
$(document).on('click', '#btnGuardarEditPromovente', function() {
    // Validar formulario
    const nombre = $('#editNombre').val().trim();
    
    if (nombre === '') {
        Swal.fire({
            icon: 'error',
            title: 'Campo requerido',
            text: 'El campo nombre es obligatorio.'
        });
        return;
    }
        
    // Obtener todos los datos del formulario
    const formData = {
        id_promovente: $('#editId_Promovente').val(),
        nombre: nombre,
        apellido_paterno: apellidoPaterno,
        apellido_materno: apellidoMaterno,
        telefono: $('#editTelefono').val().trim(),
        telefono2: $('#editTelefono2').val().trim(),
        direccion: $('#editDireccion').val().trim()
    };
    
    // Enviar mediante AJAX
     $.ajax({
        url: window.BASE_URL + 'api/actualizar_promovente.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Cerrar modal
                $('#editarPromovente').modal('hide');
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: 'Los datos del promovente han sido actualizados correctamente.',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    // Recargar la página o actualizar los datos según el referrer
                    const referrer = $('#editReferrer').val();
                    const tramiteId = $('#editTramiteId').val();
                    
                    if (referrer === 'detalle' && tramiteId > 0) {
                        location.href = window.BASE_URL + 'paginas/detalle-tramite.php?id=' + tramiteId + '&update_promovente=1';
                    } else {
                        location.href = window.BASE_URL + 'paginas/lista-promoventes.php?update_promovente=1';
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al actualizar los datos: ' + response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión con el servidor'
            });
        }
    });
});

// 4. Manejar guardado de folio RCHRP
// Eliminar cualquier manejador de eventos previo para evitar duplicación
$(document).off('click', '#btnGuardarFolio');

// Manejar guardado de folio RCHRP (una sola vez)
$(document).on('click', '#btnGuardarFolio', function(e) {
    e.preventDefault(); // Prevenir el comportamiento predeterminado
    
    // Validar formulario
    const folioRCHRP = $('#folio_rchrp').val().trim();
    const fechaRCHRP = $('#fecha_rchrp').val().trim();
    
    if (folioRCHRP === '' || fechaRCHRP === '') {
        Swal.fire({
            icon: 'error',
            title: 'Campos requeridos',
            text: 'Todos los campos son obligatorios'
        });
        return;
    }
    
    // Deshabilitar el botón para prevenir múltiples envíos
    $('#btnGuardarFolio').prop('disabled', true);
    
    // Obtener todos los datos del formulario
    const formData = {
        id_tramite: $('#folioIdTramite').val(),
        folio_rchrp: folioRCHRP,
        fecha_rchrp: fechaRCHRP
    };
    
    // Enviar mediante AJAX
    $.ajax({
        url: window.BASE_URL + 'api/registrar_folio_rchrp.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Cerrar modal
                $('#registrarFolio').modal('hide');
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Folio Registrado!',
                    text: 'El folio RCHRP ha sido registrado correctamente.',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    // Redirigir a la página de detalles con parámetro de éxito
                    location.href = window.BASE_URL + 'paginas/detalle-tramite.php?id=' + formData.id_tramite + '&folio_registrado=1';
                });
            } else {
                $('#btnGuardarFolio').prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al registrar el folio: ' + response.message
                });
            }
        },
        error: function() {
            $('#btnGuardarFolio').prop('disabled', false);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión con el servidor'
            });
        }
    });
});

//--------------------------------------------------------------------------------------------------

$(document).ready(function() {
    // Cargar modal de buscar promovente solo cuando se va a usar
    $('a[data-bs-target="#buscarPromovente"]').on('click', function(e) {
        if (!window.modalBuscarCargado) {
            e.preventDefault();
            $.get(window.BASE_URL + 'modulos/modal_buscar_promovente.php', function(data) {
                $('body').append(data);
                window.modalBuscarCargado = true;
                $('#buscarPromovente').modal('show');
            });
        }
    });
});