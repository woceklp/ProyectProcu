<?php
/**
 * modulos/modal_buscar_promovente.php - Modal para buscar promovente con estilos actualizados
 */
?>
<!-- Modal Buscar Promovente -->
<div class="modal fade" id="buscarPromovente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>Validar Promovente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Ingrese los datos del promovente para verificar si ya está registrado en el sistema:</p>
                <form id="formBuscarPromovente">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre(s)</label>
                    <input type="text" class="form-control form-control-lg" id="nombre" required>
                </div>
                <div class="mb-3">
                    <label for="apellidoPaterno" class="form-label">Apellido Paterno</label>
                    <input type="text" class="form-control form-control-lg" id="apellidoPaterno">
                </div>
                <div class="mb-3">
                    <label for="apellidoMaterno" class="form-label">Apellido Materno</label>
                    <input type="text" class="form-control form-control-lg" id="apellidoMaterno">
                </div>
                </form>
                <div id="resultadosPromovente" class="mt-3 d-none">
                    <h6>Promoventes encontrados:</h6>
                    <ul class="list-group" id="listaPromoventes">
                        <!-- Aquí se cargarán dinámicamente los promoventes encontrados -->
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cerrar
                </button>
                <button type="button" id="btnBuscarPromovente" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Buscar
                </button>
                <button type="button" id="btnNuevoPromovente" class="btn btn-success d-none">
                    <i class="fas fa-user-plus me-1"></i>Nuevo Promovente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Resultados de Validación -->
<div class="modal fade" id="resultadosValidacionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div id="resultadosHeaderColor" class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i id="resultadosIcon" class="fas fa-info-circle me-2"></i>
                    <span id="resultadosTitulo">Resultados de Validación</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Contenido para cuando se encuentra un promovente -->
                <div id="promoventeEncontradoContent" class="d-none">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h5 class="text-success">¡Promovente encontrado!</h5>
                        <p>Se ha encontrado al promovente con los datos proporcionados.</p>
                    </div>
                    
                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white">Detalles del promovente</div>
                        <div class="card-body">
                            <h5 class="card-title" id="nombreCompletoEncontrado"></h5>
                            <p class="card-text" id="telefonosEncontrado"></p>
                        </div>
                    </div>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle me-2"></i>
                        Puede continuar con este promovente para evitar duplicados.
                    </div>
                </div>
                
                <!-- Contenido para cuando NO se encuentra un promovente -->
                <div id="promoventeNoEncontradoContent" class="d-none">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus text-primary fa-4x mb-3"></i>
                        <h5 class="text-primary">No se encontraron coincidencias</h5>
                        <p>No se encontró ningún promovente con los datos ingresados.</p>
                    </div>
                    
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">Datos ingresados</div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Nombre:</strong> <span id="nombreIngresado"></span></p>
                            <p class="mb-1"><strong>Apellido Paterno:</strong> <span id="paternoIngresado"></span></p>
                            <p class="mb-1"><strong>Apellido Materno:</strong> <span id="maternoIngresado"></span></p>
                        </div>
                    </div>
                    
                    <div class="alert alert-primary">
                        <i class="fas fa-info-circle me-2"></i>
                        ¿Desea registrar un nuevo promovente con estos datos?
                    </div>
                </div>
                
                <!-- Contenido para cuando hay un error -->
                <div id="errorValidacionContent" class="d-none">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle text-danger fa-4x mb-3"></i>
                        <h5 class="text-danger">¡Ocurrió un error!</h5>
                        <p id="mensajeError">No fue posible validar al promovente.</p>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-info-circle me-2"></i>
                        Intente nuevamente o contacte al administrador del sistema.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnVolverBuscar">
                    <i class="fas fa-arrow-left me-1"></i>Volver
                </button>
                <button type="button" class="btn btn-success d-none" id="btnContinuarPromovente">
                    <i class="fas fa-check me-1"></i>Continuar con este promovente
                </button>
                <button type="button" class="btn btn-primary d-none" id="btnRegistrarNuevo">
                    <i class="fas fa-user-plus me-1"></i>Registrar nuevo
                </button>
            </div>
        </div>
    </div>
</div>