<?php
/**
 * modulos/modal_nuevo_tramite.php - Modal para registro de nuevo trámite
 * Versión actualizada con estilos unificados
 */
?>
<!-- Modal Nuevo Trámite -->
<div class="modal fade" id="nuevoTramite" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2"></i>Registrar Nuevo Trámite
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Promovente:</strong> <span id="nombrePromoventeCompleto"></span>
                </div>
                
                <form id="formNuevoTramite">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fechaTramite" class="form-label">Fecha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fechaTramite" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ciia" class="form-label">Número de CIIA (13 dígitos)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" class="form-control" id="ciia" maxlength="13" pattern="[0-9]{13}" required>
                            </div>
                            <div class="form-text">Ejemplo: 0806225000256</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipoTramite" class="form-label">Tipo de Trámite</label>
                            <select class="form-select" id="tipoTramite" required>
                                <option value="">Seleccione...</option>
                                <!-- Se llenará dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="claveTramite" class="form-label">Clave de Trámite</label>
                            <select class="form-select" id="claveTramite" required>
                                <option value="">Seleccione tipo de trámite primero</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="municipio" class="form-label">Municipio</label>
                            <select class="form-select" id="municipio" required>
                                <option value="">Seleccione...</option>
                                <!-- Se llenará dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tipoNucleoAgrario" class="form-label">Tipo de Núcleo Agrario</label>
                            <select class="form-select" id="tipoNucleoAgrario" required>
                                <option value="">Seleccione...</option>
                                <!-- Se llenará dinámicamente -->
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nucleoAgrario" class="form-label">Núcleo Agrario</label>
                            <select class="form-select" id="nucleoAgrario" required>
                                <option value="">Seleccione municipio y tipo primero</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcionTramite" class="form-label">Descripción del Trámite</label>
                        <textarea class="form-control" id="descripcionTramite" rows="3" required 
                                  placeholder="Proporcione una descripción detallada del trámite"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardarTramite" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Guardar Trámite
                </button>
            </div>
        </div>
    </div>
</div>