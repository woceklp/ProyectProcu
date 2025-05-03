<?php
/**
 * modulos/modal_editar_promovente.php - Modal para editar datos de un promovente
 */
?>
<!-- Modal Editar Promovente -->
<div class="modal fade" id="editarPromovente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Editar Datos del Promovente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarPromovente">
                    <input type="hidden" id="editId_Promovente" name="id_promovente">
                    <input type="hidden" id="editReferrer" name="referrer">
                    <input type="hidden" id="editTramiteId" name="tramite_id">
                    
                    <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="editNombre" class="form-label">Nombre(s)<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editNombre" name="nombre" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="editApellidoPaterno" class="form-label">Apellido Paterno</label>
                        <input type="text" class="form-control" id="editApellidoPaterno" name="apellido_paterno">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="editApellidoMaterno" class="form-label">Apellido Materno</label>
                        <input type="text" class="form-control" id="editApellidoMaterno" name="apellido_materno">
                    </div>   
                    </div>  
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editTelefono" class="form-label">Teléfono Principal</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="editTelefono" name="telefono" 
                                       maxlength="10" pattern="[0-9]{10}" placeholder="10 dígitos">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editTelefono2" class="form-label">Teléfono Secundario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="editTelefono2" name="telefono2" 
                                       maxlength="10" pattern="[0-9]{10}" placeholder="10 dígitos (opcional)">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editDireccion" class="form-label">Dirección</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <textarea class="form-control" id="editDireccion" name="direccion" rows="2" 
                                      placeholder="Dirección completa"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardarEditPromovente" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>