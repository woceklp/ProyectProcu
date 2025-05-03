<?php
/**
 * modulos/modal_registrar_folio.php - Modal para registrar/actualizar Folio RCHRP
 */
?>
<!-- Modal Registrar Folio RCHRP -->
<div class="modal fade" id="registrarFolio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-stamp me-2"></i><span id="tituloRegistrarFolio">Registrar Folio RCHRP</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" id="infoTramiteFolio">
                    <!-- Se llenará dinámicamente -->
                </div>
                
                <form id="formRegistrarFolio">
                    <input type="hidden" id="folioIdTramite" name="id_tramite">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="folio_rchrp" class="form-label">Folio RCHRP<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-stamp"></i></span>
                                <input type="text" class="form-control" id="folio_rchrp" name="folio_rchrp" required 
                                       placeholder="Ingrese el Folio RCHRP">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_rchrp" class="form-label">Fecha RCHRP<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fecha_rchrp" name="fecha_rchrp" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardarFolio" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Guardar Folio
                </button>
            </div>
        </div>
    </div>
</div>