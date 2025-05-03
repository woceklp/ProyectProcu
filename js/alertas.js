/**
 * alertas.js - Funciones para mostrar alertas y notificaciones en el sistema
 */

/**
 * Muestra una alerta de reiteración usando SweetAlert cuando un trámite
 * lleva más de 95 días sin actualización
 * 
 * @param {number} idTramite - ID del trámite
 * @param {number} diasTranscurridos - Días desde la última actualización
 */
function mostrarAlertaReiteracion(idTramite, diasTranscurridos, statusReal) {
    // Si el status es COMPLETA, no mostrar la alerta
    if (statusReal === 'COMPLETA') {
        console.log('Trámite #' + idTramite + ' está completo, no requiere reiteración');
        return;
    }
    
    // Solo mostrar la alerta si han pasado más de 95 días
    if (diasTranscurridos >= 95) {
        // Crear el mensaje HTML con los detalles
        let mensajeHTML = `
            <div class="text-center mb-3">
                <i class="fas fa-calendar-times text-warning" style="font-size: 3rem;"></i>
            </div>
            <p class="fs-5">Este trámite lleva <strong>${diasTranscurridos} días</strong> sin actualización.</p>
            <p>
                ${diasTranscurridos >= 100 
                    ? '<strong class="text-danger">Ya requiere reiteración urgente.</strong>' 
                    : '<strong class="text-warning">Está próximo a necesitar reiteración.</strong>'}
            </p>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Por favor, contacte a la secretaría para obtener el número de folio para la reiteración.
            </div>
        `;
        
        // Mostrar la alerta usando SweetAlert2
        Swal.fire({
            title: '¡Atención! Trámite cercano a reiteración',
            html: mensajeHTML,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ir a Registrar Reiteración',
            cancelButtonText: 'Cerrar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'registrar-reiteracion.php?id=' + idTramite;
            }
        });
    }
}

/**
 * Muestra una alerta de subsanación usando SweetAlert
 * 
 * @param {number} idTramite - ID del trámite
 */
function mostrarAlertaSubsanacion(idTramite) {
    Swal.fire({
        title: 'Subsanación Requerida',
        html: `
            <div class="text-center mb-3">
                <i class="fas fa-clipboard-check text-info" style="font-size: 3rem;"></i>
            </div>
            <p>Este trámite requiere una subsanación para corregir información incorrecta o incompleta.</p>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Por favor, registre la información de la subsanación proporcionada por el RAN.
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Registrar Subsanación',
        cancelButtonText: 'Cerrar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'registrar-subsanacion.php?id=' + idTramite;
        }
    });
}

/**
 * Muestra una alerta genérica usando SweetAlert
 * 
 * @param {string} titulo - Título de la alerta
 * @param {string} mensaje - Mensaje de la alerta
 * @param {string} tipo - Tipo de alerta (success, error, warning, info, question)
 */
function mostrarAlerta(titulo, mensaje, tipo = 'info') {
    Swal.fire({
        title: titulo,
        text: mensaje,
        icon: tipo
    });
}