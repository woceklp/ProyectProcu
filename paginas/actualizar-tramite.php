<?php
/**
 * paginas/actualizar-tramite.php - Formulario para actualizar un trámite existente
 */

// Incluir archivo de configuración
require_once '../config.php';

// Incluir el header
include_once '../modulos/header.php';

// Obtener el ID del trámite de la URL
$idTramite = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idTramite <= 0) {
    // Redirigir si no hay ID válido
    echo '<script>window.location.href = "listado-tramites.php";</script>';
    exit;
}

// Verificar si se ha enviado el formulario
$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar datos del formulario
    $folioRCHRP = isset($_POST['folio_rchrp']) ? sanitizarEntrada($_POST['folio_rchrp']) : '';
    $fechaRCHRP = isset($_POST['fecha_rchrp']) ? sanitizarEntrada($_POST['fecha_rchrp']) : '';
    $tipoTramite = isset($_POST['tipo_tramite']) ? intval($_POST['tipo_tramite']) : 0;
    $claveTramite = isset($_POST['clave_tramite']) ? intval($_POST['clave_tramite']) : 0;
    $descripcion = isset($_POST['descripcion']) ? sanitizarEntrada($_POST['descripcion']) : '';
    $statusTramite = isset($_POST['estado_tramite']) ? intval($_POST['estado_tramite']) : 0;
    
    // Validar datos requeridos
    $errores = [];
    
    if ($tipoTramite <= 0) {
        $errores[] = "Debe seleccionar un tipo de trámite";
    }
    
    if ($claveTramite <= 0) {
        $errores[] = "Debe seleccionar una clave de trámite";
    }
    
    if (empty($descripcion)) {
        $errores[] = "La descripción del trámite es requerida";
    }
    
    if ($statusTramite <= 0) {
        $errores[] = "Debe seleccionar un status para el trámite";
    }
    
    // Si hay una fecha RCHRP pero no un folio, o viceversa, es un error
    if ((!empty($fechaRCHRP) && empty($folioRCHRP)) || (empty($fechaRCHRP) && !empty($folioRCHRP))) {
        $errores[] = "Si proporciona un Folio RCHRP debe proporcionar también su fecha, y viceversa";
    }

    // Si no hay errores, continuar con la actualización
    if (empty($errores)) {
        // Consultar status actual antes de actualizar
        $sqlStatusActual = "SELECT ID_EstadoTramite, ID_EstadoBasico FROM Tramites WHERE ID_Tramite = ?";
        $resultadoStatusActual = ejecutarConsulta($sqlStatusActual, array($idTramite));
        
        if ($resultadoStatusActual['status'] !== 'success') {
            $errores[] = "Error al consultar el status actual del trámite";
        } else {
            $rowStatusActual = sqlsrv_fetch_array($resultadoStatusActual['stmt'], SQLSRV_FETCH_ASSOC);
            $statusAnterior = $rowStatusActual['ID_EstadoTramite'];
            $statusBasicoAnterior = $rowStatusActual['ID_EstadoBasico'];
            cerrarConexion($resultadoStatusActual['conn'], $resultadoStatusActual['stmt']);

            // Preparar parámetros para la actualización
            $sqlActualizar = "UPDATE Tramites SET 
                             ID_TipoTramite = ?, 
                             ID_ClaveTramite = ?, 
                             Descripcion = ?, 
                             ID_EstadoTramite = ?,
                             FechaUltimaActualizacion = GETDATE()";
            
            $paramsActualizar = array();
            $paramsActualizar[] = $tipoTramite;
            $paramsActualizar[] = $claveTramite;
            $paramsActualizar[] = $descripcion;
            $paramsActualizar[] = $statusTramite;
            
            // Determinar el status básico según el status del trámite
            // Status 5 = Completa (100%)
            // Status 7 = Prevenido
            // Status 2,3,4 = En Proceso (25%, 50%, 75%)
            $statusBasico = 3; // Por defecto, en proceso
            
            if ($statusTramite == 5) {
                $statusBasico = 1; // Completa
            } else if ($statusTramite == 7) {
                $statusBasico = 2; // Prevenido
            }
            
            // Añadir el status básico a la consulta
            $sqlActualizar .= ", ID_EstadoBasico = ?";
            $paramsActualizar[] = $statusBasico;
            
            // Añadir FolioRCHRP y FechaRCHRP si se proporcionaron
            if (!empty($folioRCHRP) && !empty($fechaRCHRP)) {
                $sqlActualizar .= ", FolioRCHRP = ?, FechaRCHRP = ?";
                $paramsActualizar[] = $folioRCHRP;
                $paramsActualizar[] = date('Y-m-d', strtotime($fechaRCHRP)); // Convertir a formato SQL Server
            }
            
            // Finalizar la consulta con la condición WHERE
            $sqlActualizar .= " WHERE ID_Tramite = ?";
            $paramsActualizar[] = $idTramite;

            // Ejecutar la actualización
            $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
            
            if ($resultadoActualizar['status'] !== 'success') {
                $mensajeError = "Error al actualizar el trámite: " . $resultadoActualizar['message'];
            } else {
                cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
                
                // Registrar en historial de cambios si cambió el status
                if ($statusAnterior != $statusTramite) {
                    $sqlHistorial = "INSERT INTO HistorialCambios (
                                    ID_Tramite, EstadoAnterior, EstadoNuevo, 
                                    Observacion, UsuarioResponsable, TipoAccion
                                    ) VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $observacionHistorial = "Actualización manual del status del trámite";
                    $paramsHistorial = array(
                        $idTramite, 
                        $statusAnterior, 
                        $statusTramite, 
                        $observacionHistorial, 
                        'Sistema',
                        'Cambio de Status'
                    );
                    
                    $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
                    
                    if ($resultadoHistorial['status'] === 'success') {
                        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
                    }
                }
                
                // Cambios en el Folio RCHRP y su fecha
                $cambioFolioRCHRP = false;
                if (!empty($folioRCHRP)) {
                    $sqlHistorialRCHRP = "INSERT INTO HistorialCambios (
                                         ID_Tramite, EstadoAnterior, EstadoNuevo, 
                                         Observacion, UsuarioResponsable, TipoAccion
                                         ) VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $observacionHistorialRCHRP = "Registro/Actualización de Folio RCHRP: " . $folioRCHRP;
                    if (!empty($fechaRCHRP)) {
                        $observacionHistorialRCHRP .= ", Fecha: " . date('d/m/Y', strtotime($fechaRCHRP));
                    }
                    
                    $paramsHistorialRCHRP = array(
                        $idTramite, 
                        null, 
                        null, 
                        $observacionHistorialRCHRP, 
                        'Sistema', 
                        'Registro de Folio RCHRP'
                    );
                    
                    $resultadoHistorialRCHRP = ejecutarConsulta($sqlHistorialRCHRP, $paramsHistorialRCHRP);
                    
                    if ($resultadoHistorialRCHRP['status'] === 'success') {
                        cerrarConexion($resultadoHistorialRCHRP['conn'], $resultadoHistorialRCHRP['stmt']);
                    }
                }
                
                $mensajeExito = "El trámite ha sido actualizado correctamente.";
            }
        }
    } else {
        $mensajeError = implode("<br>", $errores);
    }
}

// Consultar información del trámite
$sqlTramite = "SELECT t.CIIA, 
               t.FechaRegistro, 
               t.Descripcion,
               t.FolioRCHRP,
               t.FechaRCHRP,
               t.ID_TipoTramite,
               tt.Nombre AS TipoTramite,
               t.ID_ClaveTramite,
               ct.Clave AS ClaveTramite,
               p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
               t.ID_EstadoTramite,
               e.Nombre AS Estado
               FROM Tramites t
               INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente
               INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite
               INNER JOIN ClavesTramite ct ON t.ID_ClaveTramite = ct.ID_ClaveTramite
               INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
               WHERE t.ID_Tramite = ?";

$resultadoTramite = ejecutarConsulta($sqlTramite, array($idTramite));

if ($resultadoTramite['status'] !== 'success') {
    echo '<div class="alert alert-danger">Error al cargar los datos del trámite: '.$resultadoTramite['message'].'</div>';
    include_once '../modulos/footer.php';
    exit;
}

$tramite = sqlsrv_fetch_array($resultadoTramite['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoTramite['conn'], $resultadoTramite['stmt']);

if (!$tramite) {
    echo '<div class="alert alert-warning">El trámite solicitado no existe o ha sido eliminado.</div>';
    include_once '../modulos/footer.php';
    exit;
}

// Consultar los tipos de trámite disponibles
$sqlTiposTramite = "SELECT ID_TipoTramite, Nombre FROM TiposTramite WHERE Activo = 1 ORDER BY Nombre";
$resultadoTiposTramite = ejecutarConsulta($sqlTiposTramite);
$tiposTramite = ($resultadoTiposTramite['status'] === 'success') 
               ? obtenerResultados($resultadoTiposTramite['stmt']) 
               : array();
if ($resultadoTiposTramite['status'] === 'success') {
    cerrarConexion($resultadoTiposTramite['conn'], $resultadoTiposTramite['stmt']);
}

// Consultar las claves de trámite para el tipo actual
$sqlClavesTramite = "SELECT ID_ClaveTramite, Clave, Descripcion 
                   FROM ClavesTramite 
                   WHERE ID_TipoTramite = ? AND Activo = 1
                   ORDER BY Clave";
$resultadoClavesTramite = ejecutarConsulta($sqlClavesTramite, array($tramite['ID_TipoTramite']));
$clavesTramite = ($resultadoClavesTramite['status'] === 'success') 
                ? obtenerResultados($resultadoClavesTramite['stmt']) 
                : array();
if ($resultadoClavesTramite['status'] === 'success') {
    cerrarConexion($resultadoClavesTramite['conn'], $resultadoClavesTramite['stmt']);
}

// Consultar status de trámite
$sqlStatus = "SELECT ID_EstadoTramite, Nombre, Porcentaje 
              FROM EstadosTramite 
              ORDER BY Porcentaje";
$resultadoStatus = ejecutarConsulta($sqlStatus);
$status = ($resultadoStatus['status'] === 'success') 
          ? obtenerResultados($resultadoStatus['stmt']) 
          : array();
if ($resultadoStatus['status'] === 'success') {
    cerrarConexion($resultadoStatus['conn'], $resultadoStatus['stmt']);
}

?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php">LLISTADO DE TRÁMITES</a></li>
                <li class="breadcrumb-item"><a href="detalle-tramite.php?id=<?php echo $idTramite; ?>">DETALLE DE TRÁMITE #<?php echo $idTramite; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">ACTUALIZAR TRÁMITE</li>
            </ol>
        </nav>
        
        <?php if(!empty($mensajeExito)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $mensajeExito; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($mensajeError)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-times-circle me-2"></i><?php echo $mensajeError; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-edit me-2"></i>ACTUALIZAR TRÁMITE #<?php echo $idTramite; ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-0"><strong>CIIA:</strong> <?php echo $tramite['CIIA']; ?></p>
                    <p class="mb-0"><strong>PROMOVENTE:</strong> <?php echo $tramite['Promovente']; ?></p>
                    <p class="mb-0"><strong>FECHA DE REGISTRO:</strong> <?php echo $tramite['FechaRegistro']->format('d/m/Y'); ?></p>
                </div>
                
                <form method="post" action="" class="mt-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="folio_rchrp" class="form-label">FOLIO RCHRP</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                                <input type="text" class="form-control" id="folio_rchrp" name="folio_rchrp" 
                                       value="<?php echo $tramite['FolioRCHRP'] ?? ''; ?>"
                                       placeholder="Ingrese el Folio RCHRP si está disponible">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_rchrp" class="form-label">Fecha RCHRP</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fecha_rchrp" name="fecha_rchrp"
                                       value="<?php echo $tramite['FechaRCHRP'] ? $tramite['FechaRCHRP']->format('Y-m-d') : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_tramite" class="form-label">TIPO DE TRÁMITE<span class="text-danger">*</span></label>
                            <select class="form-select" id="tipo_tramite" name="tipo_tramite" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($tiposTramite as $tipo): ?>
                                <option value="<?php echo $tipo['ID_TipoTramite']; ?>" 
                                        <?php echo ($tipo['ID_TipoTramite'] == $tramite['ID_TipoTramite']) ? 'selected' : ''; ?>>
                                    <?php echo $tipo['Nombre']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="clave_tramite" class="form-label">CLAVE DE TRÁMITE<span class="text-danger">*</span></label>
                            <select class="form-select" id="clave_tramite" name="clave_tramite" required>
                                <option value="">SELECCIONE TIPO DE TRÁMITE PRIMERO</option>
                                <?php foreach($clavesTramite as $clave): ?>
                                <option value="<?php echo $clave['ID_ClaveTramite']; ?>" 
                                        <?php echo ($clave['ID_ClaveTramite'] == $tramite['ID_ClaveTramite']) ? 'selected' : ''; ?>>
                                    <?php echo $clave['Clave'] . ' - ' . $clave['Descripcion']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">DESCRIPCIÓN DEL TRÁMITE<span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required><?php echo $tramite['Descripcion']; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="estado_tramite" class="form-label">STATUS DEL TRÁMITE<span class="text-danger">*</span></label>
                        <select class="form-select" id="estado_tramite" name="estado_tramite" required>
                            <option value="">SELECCIONE...</option>
                            <?php foreach($status as $status): ?>
                            <option value="<?php echo $status['ID_EstadoTramite']; ?>" 
                                    <?php echo ($status['ID_EstadoTramite'] == $tramite['ID_EstadoTramite']) ? 'selected' : ''; ?>>
                                <?php echo $status['Nombre'] . ' (' . $status['Porcentaje'] . '%)'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="detalle-tramite.php?id=<?php echo $idTramite; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>CANCELAR
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>GUARDAR CAMBIOS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Manejar cambio en el tipo de trámite para cargar las claves correspondientes
    $('#tipo_tramite').change(function() {
        const idTipoTramite = $(this).val();
        
        if (idTipoTramite) {
            // Realizar petición AJAX para obtener las claves de este tipo
            $.ajax({
                url: '../api/get_claves_tramite.php',
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
                        
                        $('#clave_tramite').html(options);
                    } else {
                        alert('Error al cargar claves de trámite: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error de conexión al cargar claves de trámite');
                }
            });
        } else {
            $('#clave_tramite').html('<option value="">Seleccione tipo de trámite primero</option>');
        }
    });
    
    // Validar que si hay fecha RCHRP también hay folio y viceversa
    $('form').on('submit', function(e) {
        const folioRCHRP = $('#folio_rchrp').val().trim();
        const fechaRCHRP = $('#fecha_rchrp').val().trim();
        
        if ((folioRCHRP !== '' && fechaRCHRP === '') || (folioRCHRP === '' && fechaRCHRP !== '')) {
            alert('Si proporciona un Folio RCHRP debe proporcionar también su fecha, y viceversa');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>