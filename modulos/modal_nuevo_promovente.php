<?php
/**
 * modulos/modal_nuevo_promovente.php - Modal para registro de nuevo promovente
 * Versión actualizada con estilos unificados
 */
?>
<!-- Modal Nuevo Promovente -->
<div class="modal fade" id="nuevoPromovente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Registrar Nuevo Promovente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoPromovente">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nuevoNombre" class="form-label">Nombre(s)</label>
                            <input type="text" class="form-control" id="nuevoNombre" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nuevoApellidoPaterno" class="form-label">Apellido Paterno</label>
                            <input type="text" class="form-control" id="nuevoApellidoPaterno" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nuevoApellidoMaterno" class="form-label">Apellido Materno</label>
                            <input type="text" class="form-control" id="nuevoApellidoMaterno" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono Principal</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="telefono" placeholder="10 dígitos">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono2" class="form-label">Teléfono Secundario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="telefono2" placeholder="10 dígitos (opcional)">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <textarea class="form-control" id="direccion" rows="2" placeholder="Dirección completa"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardarPromovente" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Guardar y Continuar
                </button>
            </div>
        </div>
    </div>
</div>