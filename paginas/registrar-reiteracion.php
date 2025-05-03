<?php
/**
 * paginas/registrar-reiteracion.php - Formulario para registrar una reiteración de un trámite
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir la función sanitizarEntrada si no existe
if (!function_exists('sanitizarEntrada')) {
    function sanitizarEntrada($datos) {
        if(is_array($datos)) {
            foreach($datos as $key => $valor) {
                $datos[$key] = sanitizarEntrada($valor);
            }
        } else {
            $datos = trim($datos);
            $datos = stripslashes($datos);
            $datos = htmlspecialchars($datos, ENT_QUOTES, 'UTF-8');
        }
        return $datos;
    }
}

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

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
    $folioReiteracion = isset($_POST['folio_reiteracion']) ? sanitizarEntrada($_POST['folio_reiteracion']) : '';
    $fechaReiteracion = isset($_POST['fecha_reiteracion']) ? sanitizarEntrada($_POST['fecha_reiteracion']) : '';
    $observaciones = isset($_POST['observaciones']) ? sanitizarEntrada($_POST['observaciones']) : '';
    
    // Validar datos
    $errores = [];
    
    if (empty($folioReiteracion)) {
        $errores[] = "El folio de reiteración es requerido";
    }
    
    if (empty($fechaReiteracion)) {
        $errores[] = "La fecha de reiteración es requerida";
    }
    
    // Verificar que no se haya superado el límite de 3 reiteraciones
    $sqlContar = "SELECT COUNT(*) AS total FROM Reiteraciones WHERE ID_Tramite = ?";
    $resultadoContar = ejecutarConsulta($sqlContar, array($idTramite));
    
    if ($resultadoContar['status'] === 'success') {
        $row = sqlsrv_fetch_array($resultadoContar['stmt'], SQLSRV_FETCH_ASSOC);
        cerrarConexion($resultadoContar['conn'], $resultadoContar['stmt']);
        
        if ($row['total'] >= 3) {
            $errores[] = "Este trámite ya tiene el máximo de 3 reiteraciones permitidas";
        } else {
            // Determinar el número de reiteración
            $numeroReiteracion = $row['total'] + 1;
        }
    } else {
        $errores[] = "Error al verificar las reiteraciones existentes: " . $resultadoContar['message'];
    }
    
    // Si no hay errores, guardar la reiteración
    if (empty($errores)) {
        // Convertir fecha a formato SQL Server (Y-m-d)
        $fechaFormateada = date('Y-m-d', strtotime($fechaReiteracion));
        
        // Consulta SQL para insertar nueva reiteración
        $sqlInsertar = "INSERT INTO Reiteraciones (
                       ID_Tramite, FolioReiteracion, FechaReiteracion, 
                       NumeroReiteracion, Observaciones, FechaRegistro
                       ) VALUES (?, ?, ?, ?, ?, GETDATE())";
        
        $paramsInsertar = array(
            $idTramite,
            $folioReiteracion,
            $fechaFormateada,
            $numeroReiteracion,
            $observaciones ?: null
        );
        
        $resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);
        
        if ($resultadoInsertar['status'] === 'success') {
            cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
            
            // Actualizar la fecha de última actualización del trámite
            $sqlActualizar = "UPDATE Tramites 
                            SET FechaUltimaActualizacion = GETDATE()
                            WHERE ID_Tramite = ?";
            
            $paramsActualizar = array($idTramite);
            $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
            
            if ($resultadoActualizar['status'] === 'success') {
                cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
                
                // Registrar en historial de cambios
                $sqlHistorial = "INSERT INTO HistorialCambios (
                                ID_Tramite, EstadoAnterior, EstadoNuevo, 
                                Observacion, UsuarioResponsable, TipoAccion
                                ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $observacionHistorial = "Registro de reiteración #" . $numeroReiteracion . ": " . $folioReiteracion;
                $paramsHistorial = array(
                    $idTramite, 
                    null, 
                    null, 
                    $observacionHistorial, 
                    'Sistema',
                    'Registro de Reiteración'
                );
                
                $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
                
                if ($resultadoHistorial['status'] === 'success') {
                    cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
                }
                
                // Redireccionar directamente a la página de detalles con mensaje de éxito
                header("Location: detalle-tramite.php?id=" . $idTramite . "&reiteracion_registrada=1");
                exit;
            } else {
                $mensajeError = "Error al actualizar el trámite: " . $resultadoActualizar['message'];
            }
        } else {
            $mensajeError = "Error al registrar la reiteración: " . $resultadoInsertar['message'];
        }
    } else {
        $mensajeError = implode("<br>", $errores);
    }
}

// Consultar información del trámite
$sqlTramite = "SELECT t.CIIA, 
              t.FechaRegistro, t.FechaRCHRP, t.FechaUltimaActualizacion,
              p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
              e.ID_EstadoTramite,
              e.Nombre AS Estado,
              e.Porcentaje,
              CASE 
                  WHEN EXISTS (
                      SELECT 1 FROM Acuses a 
                      WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
                  ) THEN 'PREVENIDO'
                  WHEN (
                      NOT EXISTS (
                          SELECT 1 FROM Acuses a 
                          WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
                      ) AND EXISTS (
                          SELECT 1 FROM Acuses a 
                          WHERE a.ID_Tramite = t.ID_Tramite
                      )
                  ) THEN 'COMPLETA'
                  ELSE 'EN PROCESO'
              END AS StatusReal
              FROM Tramites t
              INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente
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

// Formatear el texto del avance según el porcentaje
$avanceTexto = "";
if(isset($tramite['Porcentaje'])) {
    if($tramite['Porcentaje'] == 25) $avanceTexto = "1 DE 4 (AVANCE 25%)";
    else if($tramite['Porcentaje'] == 50) $avanceTexto = "2 DE 4 (AVANCE 50%)";
    else if($tramite['Porcentaje'] == 75) $avanceTexto = "3 DE 4 (AVANCE 75%)";
    else if($tramite['Porcentaje'] == 100) $avanceTexto = "4 DE 4 (AVANCE 100%)";
    else $avanceTexto = $tramite['Porcentaje'] . "%";
}

// Consultar las reiteraciones previas
$sqlReiteraciones = "SELECT ID_Reiteracion, FolioReiteracion, FechaReiteracion, 
                   NumeroReiteracion, Observaciones, FechaRegistro
                   FROM Reiteraciones
                   WHERE ID_Tramite = ?
                   ORDER BY NumeroReiteracion ASC";

$resultadoReiteraciones = ejecutarConsulta($sqlReiteraciones, array($idTramite));
$reiteraciones = ($resultadoReiteraciones['status'] === 'success') 
                ? obtenerResultados($resultadoReiteraciones['stmt']) 
                : array();
if ($resultadoReiteraciones['status'] === 'success') {
    cerrarConexion($resultadoReiteraciones['conn'], $resultadoReiteraciones['stmt']);
}

// Calcular días transcurridos desde la última reiteración o FOLIO RCHRP
$fechaInicio = null;
$fechaOrigenTexto = "";
$diasTranscurridos = "N/A";

// Verificar si hay reiteraciones previas
if (count($reiteraciones) > 0) {
    $ultimaReiteracion = $reiteraciones[count($reiteraciones) - 1];
    $fechaInicio = $ultimaReiteracion['FechaReiteracion'];
    $fechaOrigenTexto = "última reiteración (#" . $ultimaReiteracion['NumeroReiteracion'] . ") - " . $fechaInicio->format('d/m/Y');
} 
// Si no hay reiteraciones, usar la fecha RCHRP
elseif (isset($tramite['FechaRCHRP']) && $tramite['FechaRCHRP']) {
    $fechaInicio = $tramite['FechaRCHRP'];
    $fechaOrigenTexto = "FOLIO RCHRP - " . $fechaInicio->format('d/m/Y');
}

// Calcular días transcurridos
if ($fechaInicio !== null) {
    $fechaActual = new DateTime();
    $diasTranscurridos = $fechaActual->diff($fechaInicio)->days;
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php">LISTADO DE TRÁMITES</a></li>
                <li class="breadcrumb-item"><a href="detalle-tramite.php?id=<?php echo $idTramite; ?>">DETALLE DE TRÁMITE #<?php echo $idTramite; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">REGISTRAR REITERACIÓN</li>
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
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-sync-alt me-2"></i>Registrar Reiteración de Trámite</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-0"><strong>TRÁMITE:</strong> #<?php echo $idTramite; ?> - CIIA: <?php echo $tramite['CIIA']; ?></p>
                    <p class="mb-0"><strong>PROMOVENTE:</strong> <?php echo $tramite['Promovente']; ?></p>
                    <p class="mb-0"><strong>STATUS ACTUAL:</strong> 
                        <span class="badge <?php echo ($tramite['StatusReal'] == 'PREVENIDO') ? 'bg-info' : 
                            (($tramite['StatusReal'] == 'COMPLETA') ? 'bg-success' : 'bg-primary'); ?>">
                            <?php echo $tramite['StatusReal']; ?>
                        </span> 
                        <?php echo $avanceTexto; ?>
                    </p>
                    <p class="mb-0"><strong>FECHA DE REGISTRO:</strong> <?php echo $tramite['FechaRegistro']->format('d/m/Y'); ?></p>
                    <?php if ($diasTranscurridos !== "N/A"): ?>
                    <p class="mb-0"><strong>DÍAS TRANSCURRIDOS:</strong> <?php echo $diasTranscurridos; ?> días (desde <?php echo $fechaOrigenTexto; ?>)</p>
                    <?php endif; ?>
                </div>                
                <?php if (count($reiteraciones) > 0): ?>
                <div class="alert alert-warning mt-3">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>REITERACIONES PREVIAS:</h6>
                    <ul class="mb-0">
                        <?php foreach($reiteraciones as $reiteracion): ?>
                        <li>
                            <strong>Reiteración #<?php echo $reiteracion['NumeroReiteracion']; ?>:</strong> 
                            Folio <?php echo $reiteracion['FolioReiteracion']; ?> - 
                            Fecha: <?php echo $reiteracion['FechaReiteracion']->format('d/m/Y'); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (count($reiteraciones) >= 3): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-ban me-2"></i>
                    <strong>¡ATENCIÓN!</strong> Este trámite ya tiene el máximo de 3 reiteraciones permitidas. 
                    No es posible registrar más reiteraciones.
                </div>
                <div class="text-center mt-4">
                    <a href="detalle-tramite.php?id=<?php echo $idTramite; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>VOLVER A DETALLES DEL TRÁMITE
                    </a>
                </div>
                <?php else: ?>
                <form method="post" action="" class="mt-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="folio_reiteracion" class="form-label">FOLIO DE REITERACIÓN<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                                <input type="text" class="form-control" id="folio_reiteracion" name="folio_reiteracion" 
                                       required placeholder="Ingrese el folio del documento de reiteración">
                            </div>
                            <div class="form-text">Ingrese el folio proporcionado por la secretaría para la reiteración.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_reiteracion" class="form-label">FECHA DE REITERACIÓN<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fecha_reiteracion" name="fecha_reiteracion" required>
                            </div>
                            <div class="form-text">Fecha en que se emitió el documento de reiteración.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">OBSERVACIONES</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                  placeholder="Observaciones o comentarios adicionales sobre esta reiteración"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Esta será la reiteración número <strong><?php echo (count($reiteraciones) + 1); ?></strong> para este trámite. 
                        Recuerde que solo se permiten un máximo de 3 reiteraciones por trámite.
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="detalle-tramite.php?id=<?php echo $idTramite; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>CANCELAR
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-sync-alt me-1"></i>REGISTRAR REITERACIÓN
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Establecer la fecha actual como valor predeterminado
    var today = new Date().toISOString().split('T')[0];
    $('#fecha_reiteracion').val(today);
    
    // Manejar el envío del formulario con SweetAlert para confirmación
    $('form').on('submit', function(e) {
        e.preventDefault(); // Evitar el envío inmediato del formulario
        
        const folioReiteracion = $('#folio_reiteracion').val().trim();
        const fechaReiteracion = $('#fecha_reiteracion').val().trim();
        
        // Validar campos obligatorios
        if (folioReiteracion === '' || fechaReiteracion === '') {
            Swal.fire({
                icon: 'error',
                title: 'Campos requeridos',
                text: 'Los campos Folio de Reiteración y Fecha de Reiteración son obligatorios'
            });
            return false;
        }
        
        // Si pasa todas las validaciones, mostrar diálogo de confirmación
        Swal.fire({
            title: '¿Está seguro?',
            text: "Va a registrar la reiteración #<?php echo (count($reiteraciones) + 1); ?> para este trámite",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, registrar reiteración',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Si el usuario confirma, enviar el formulario
                this.submit();
            }
        });
    });
});
</script>

<script>
// Si la función aplicarFormatoFolio no está en un archivo global
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

// Aplicar el formato a todos los campos de folio relevantes en esta página
document.addEventListener('DOMContentLoaded', function() {
    // Verificar cada campo individualmente
    if (document.getElementById('folio_reloj')) {
        aplicarFormatoFolio('folio_reloj');
    }
    if (document.getElementById('folio_rchrp')) {
        aplicarFormatoFolio('folio_rchrp');
    }
    if (document.getElementById('folio_reiteracion')) {
        aplicarFormatoFolio('folio_reiteracion');
    }
    if (document.getElementById('folio_subsanacion')) {
        aplicarFormatoFolio('folio_subsanacion');
    }
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>