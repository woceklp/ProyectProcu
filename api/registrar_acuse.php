<?php
/**
 * paginas/registrar-acuse.php - Formulario para registrar un acuse de un trámite
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
    $numeroAcuse = isset($_POST['numero_acuse']) ? sanitizarEntrada($_POST['numero_acuse']) : '';
    $fechaRecepcion = isset($_POST['fecha_recepcion']) ? sanitizarEntrada($_POST['fecha_recepcion']) : '';
    $nombreRevisor = isset($_POST['nombre_revisor']) ? sanitizarEntrada($_POST['nombre_revisor']) : '';
    $folioReloj = isset($_POST['folio_reloj']) ? sanitizarEntrada($_POST['folio_reloj']) : '';
    $estadoTramite = isset($_POST['estado_tramite']) ? intval($_POST['estado_tramite']) : 0;
    $respuesta = isset($_POST['respuesta']) ? sanitizarEntrada($_POST['respuesta']) : '';
    
    // Validar datos
    $errores = [];
    
    if (empty($numeroAcuse) || !validarNumeroTramite($numeroAcuse)) {
        $errores[] = "El número de acuse debe tener exactamente 11 dígitos numéricos";
    }
    
    if (empty($fechaRecepcion)) {
        $errores[] = "La fecha de recepción por el RAN es requerida";
    }
    
    if (empty($nombreRevisor) && empty($folioReloj)) {
        $errores[] = "Debe proporcionar el nombre del revisor o el folio reloj";
    }
    
    if ($estadoTramite <= 0) {
        $errores[] = "Debe seleccionar un estado para el trámite";
    }
    
    // Verificar si ya existe un acuse con el mismo número para cualquier trámite
    $sqlVerificar = "SELECT COUNT(*) AS total FROM Acuses WHERE NumeroAcuse = ?";
    $paramsVerificar = array($numeroAcuse);
    $resultadoVerificar = ejecutarConsulta($sqlVerificar, $paramsVerificar);
    
    if ($resultadoVerificar['status'] === 'success') {
        $row = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
        cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);
        
        if ($row['total'] > 0) {
            $errores[] = "Ya existe un acuse registrado con ese número";
        }
    }
    
    // Si no hay errores, guardar el acuse
    if (empty($errores)) {
        // Convertir fecha a formato SQL Server (Y-m-d)
        $fechaFormateada = date('Y-m-d', strtotime($fechaRecepcion));
        
        // Consulta SQL para insertar nuevo acuse
        $sqlInsertar = "INSERT INTO Acuses (
                        ID_Tramite, NumeroAcuse, FechaRecepcionRAN, 
                        NombreRevisor, FolioReloj, ID_EstadoTramite, 
                        Respuesta, FechaRegistro
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        
        $paramsInsertar = array(
            $idTramite,
            $numeroAcuse,
            $fechaFormateada,
            $nombreRevisor ?: null,
            $folioReloj ?: null,
            $estadoTramite,
            $respuesta ?: null
        );
        
        $resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);
        
        if ($resultadoInsertar['status'] === 'success') {
            cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
            
            // Actualizar el estado del trámite en la tabla Tramites
            $sqlActualizar = "UPDATE Tramites 
                             SET ID_EstadoTramite = ?, 
                                 FechaUltimaActualizacion = GETDATE() 
                             WHERE ID_Tramite = ?";
            
            $paramsActualizar = array($estadoTramite, $idTramite);
            $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
            
            if ($resultadoActualizar['status'] === 'success') {
                cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
                
                // Registrar en historial de cambios
                $sqlHistorial = "INSERT INTO HistorialCambios (
                                ID_Tramite, EstadoAnterior, EstadoNuevo, 
                                Observacion, UsuarioResponsable
                                ) VALUES (?, 
                                    (SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?), 
                                    ?, 
                                    ?, 
                                    'Sistema')";
                
                $observacion = "Registro de acuse/número de trámite: {$numeroAcuse}";
                $paramsHistorial = array($idTramite, $idTramite, $estadoTramite, $observacion);
                $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
                
                if ($resultadoHistorial['status'] === 'success') {
                    cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
                }
                
                $mensajeExito = "El acuse ha sido registrado correctamente.";
            } else {
                $mensajeError = "Error al actualizar el estado del trámite: " . $resultadoActualizar['message'];
            }
        } else {
            $mensajeError = "Error al registrar el acuse: " . $resultadoInsertar['message'];
        }
    } else {
        $mensajeError = implode("<br>", $errores);
    }
}

// Consultar información del trámite
$sqlTramite = "SELECT t.CIIA, p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente
              FROM Tramites t
              INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente
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

// Consultar estados de trámite
$sqlEstados = "SELECT ID_EstadoTramite, Nombre, Porcentaje 
              FROM EstadosTramite 
              ORDER BY Porcentaje";

$resultadoEstados = ejecutarConsulta($sqlEstados);
$estados = ($resultadoEstados['status'] === 'success') 
          ? obtenerResultados($resultadoEstados['stmt']) 
          : array();

if ($resultadoEstados['status'] === 'success') {
    cerrarConexion($resultadoEstados['conn'], $resultadoEstados['stmt']);
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php">Listado de Trámites</a></li>
                <li class="breadcrumb-item"><a href="detalle-tramite.php?id=<?php echo $idTramite; ?>">Detalle de Trámite #<?php echo $idTramite; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Registrar Acuse</li>
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
                <h5><i class="fas fa-file-alt me-2"></i>Registrar Acuse / Número de Trámite</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-0"><strong>Trámite:</strong> #<?php echo $idTramite; ?> - CIIA: <?php echo $tramite['CIIA']; ?></p>
                    <p class="mb-0"><strong>Promovente:</strong> <?php echo $tramite['Promovente']; ?></p>
                </div>
                
                <form method="post" action="" class="mt-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="numero_acuse" class="form-label">Número de Trámite/Acuse (11 dígitos)<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" class="form-control" id="numero_acuse" name="numero_acuse" 
                                       maxlength="11" pattern="[0-9]{11}" required 
                                       placeholder="Ejemplo: 08250001236">
                            </div>
                            <div class="form-text">Ingrese los 11 dígitos del número de trámite proporcionado por el RAN.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_recepcion" class="form-label">Fecha de Recepción por el RAN<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fecha_recepcion" name="fecha_recepcion" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_revisor" class="form-label">Nombre del Revisor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="nombre_revisor" name="nombre_revisor" 
                                       placeholder="Nombre de quien revisará el trámite">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="folio_reloj" class="form-label">Folio Reloj</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="text" class="form-control" id="folio_reloj" name="folio_reloj" 
                                       placeholder="Ejemplo: Reloj:0125/2025">
                            </div>
                            <div class="form-text">Complete este campo si no conoce el nombre del revisor.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado_tramite" class="form-label">Estado del Trámite<span class="text-danger">*</span></label>
                            <select class="form-select" id="estado_tramite" name="estado_tramite" required>
                                <option value="">Seleccione...</option>
                                <?php foreach($estados as $estado): ?>
                                <option value="<?php echo $estado['ID_EstadoTramite']; ?>">
                                    <?php echo $estado['Nombre']; ?> (<?php echo $estado['Porcentaje']; ?>%)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="respuesta" class="form-label">Respuesta o Comentarios del RAN</label>
                            <textarea class="form-control" id="respuesta" name="respuesta" rows="3" 
                                      placeholder="Ingrese la respuesta o comentarios del RAN sobre este trámite"></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="detalle-tramite.php?id=<?php echo $idTramite; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Guardar Acuse
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Agregar script para validación en tiempo real
?>
<script>
$(document).ready(function() {
    // Validar número de acuse (solo dígitos y máximo 11)
    $('#numero_acuse').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);
    });
    
    // Validar que al menos uno de los campos (revisor o folio) esté lleno
    $('form').on('submit', function(e) {
        const nombreRevisor = $('#nombre_revisor').val().trim();
        const folioReloj = $('#folio_reloj').val().trim();
        
        if (nombreRevisor === '' && folioReloj === '') {
            alert('Debe proporcionar el nombre del revisor o el folio reloj');
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