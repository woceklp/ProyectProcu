<?php
/**
 * paginas/detalle-tramite.php - Muestra los detalles completos de un trámite
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Verificar si viene de registrar un acuse o un folio RCHRP
$acuseRegistrado = isset($_GET['acuse_registrado']) ? true : false;
$folioRegistrado = isset($_GET['folio_registrado']) ? true : false;
$tramiteFiniquitado = isset($_GET['tramite_finiquitado']) ? true : false;
$reiteracionRegistrada = isset($_GET['reiteracion_registrada']) ? true : false;
$subsanacionRegistrada = isset($_GET['subsanacion_registrada']) ? true : false;

// Obtener el ID del trámite de la URL
$idTramite = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($idTramite <= 0) {
    // Redirigir si no hay ID válido
    echo '<script>window.location.href = "listado-tramites.php";</script>';
    exit;
}

// Consultar los datos del trámite
$sqlTramite = "SELECT t.ID_Tramite, 
       t.CIIA, 
       t.FechaRegistro, 
       t.Descripcion,
       t.FolioRCHRP,
       t.FechaRCHRP,
       t.FechaUltimaActualizacion,
       t.FechaCompletado, 
       p.ID_Promovente,
       p.Nombre, 
       p.ApellidoPaterno, 
       p.ApellidoMaterno,
       p.Telefono,
       p.Telefono2,
       p.Direccion,
       tt.ID_TipoTramite,
       tt.Nombre AS TipoTramite,
       ct.ID_ClaveTramite,
       ct.Clave AS ClaveTramite,
       ct.Descripcion AS DescripcionClave,
       m.ID_Municipio,
       m.Nombre AS Municipio,
       na.ID_NucleoAgrario,
       na.Nombre AS NucleoAgrario,
       tna.ID_TipoNucleoAgrario,
       tna.Descripcion AS TipoNucleoAgrario,
       e.ID_EstadoTramite,
       e.Nombre AS Estado,
       e.Porcentaje,
       e.Descripcion AS DescripcionEstado,
       t.ID_EstadoBasico,
       eb.Nombre AS EstadoBasicoNombre,
       CASE 
        -- Prioridad 1: Si el estado del trámite es 5 (COMPLETA), siempre reportar como COMPLETA
        WHEN t.ID_EstadoTramite = 5 THEN 'COMPLETA'
        -- Prioridad 2: Si el estado básico es 1 (COMPLETA), siempre reportar como COMPLETA
        WHEN t.ID_EstadoBasico = 1 THEN 'COMPLETA'
        -- Prioridad 3: Si hay acuses con estado básico COMPLETA (3) y no hay ninguno con otro estado
        WHEN (
            (SELECT COUNT(*) FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite) > 0
            AND 
            NOT EXISTS (
                SELECT 1 FROM Acuses a 
                WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
            )
        ) THEN 'COMPLETA'
        -- Prioridad 4: Si hay algún acuse PREVENIDO (estado básico 2)
        WHEN EXISTS (
            SELECT 1 FROM Acuses a 
            WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
        ) THEN 'PREVENIDO'
        -- En cualquier otro caso
        ELSE 'EN PROCESO'
    END AS StatusReal
FROM Tramites t 
INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente 
INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite 
INNER JOIN ClavesTramite ct ON t.ID_ClaveTramite = ct.ID_ClaveTramite
INNER JOIN Municipios m ON t.ID_Municipio = m.ID_Municipio
INNER JOIN NucleosAgrarios na ON t.ID_NucleoAgrario = na.ID_NucleoAgrario
INNER JOIN TiposNucleoAgrario tna ON na.ID_TipoNucleoAgrario = tna.ID_TipoNucleoAgrario
INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite 
LEFT JOIN EstadosBasicos eb ON t.ID_EstadoBasico = eb.ID_EstadoBasico
WHERE t.ID_Tramite = ?";

$resultadoTramite = ejecutarConsulta($sqlTramite, array($idTramite));

if($resultadoTramite['status'] !== 'success') {
    // Error al consultar el trámite
    echo '<div class="alert alert-danger">Error al cargar los datos del trámite: '.$resultadoTramite['message'].'</div>';
    include_once '../modulos/footer.php';
    exit;
}

// CORRECCIÓN IMPORTANTE: Eliminar la sincronización automática innecesaria o limitarla
// Solo realizar la sincronización si no venimos de otra página que ya hizo la actualización
// y no estamos en un bucle de redirección
if (!isset($_GET['acuse_registrado']) && 
    !isset($_GET['folio_registrado']) && 
    !isset($_GET['tramite_finiquitado']) && 
    !isset($_GET['reiteracion_registrada']) && 
    !isset($_GET['redirect_from_update'])) {
    
    // Verificar si hay acuses que podrían estar desincronizados
    $sqlVerificarAcuses = "SELECT COUNT(*) AS total FROM Acuses WHERE ID_Tramite = ?";
    $resultadoVerificar = ejecutarConsulta($sqlVerificarAcuses, array($idTramite));
    
    if ($resultadoVerificar['status'] === 'success') {
        $rowVerificar = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
        if ($rowVerificar['total'] > 0) {
            // Solo sincronizamos si hay acuses
            $statusSincronizado = actualizarStatusTramiteSegunAcuses($idTramite);
        }
        cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);
    }
    
    // Solo redireccionar si hubo un cambio real en el estado
    if (isset($statusSincronizado) && $statusSincronizado === true) {
        header("Location: detalle-tramite.php?id=" . $idTramite . "&redirect_from_update=1");
        exit;
    }
}

$tramite = sqlsrv_fetch_array($resultadoTramite['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoTramite['conn'], $resultadoTramite['stmt']);

if(!$tramite) {
    // No se encontró el trámite
    echo '<div class="alert alert-warning">EL TRÁMITE SOLICITADO NO EXISTE O HA SIDO ELIMINADO.</div>';
    include_once '../modulos/footer.php';
    exit;
}
if ($tramite) {
    error_log("Trámite #$idTramite - Estado Básico: " . 
              (isset($tramite['ID_EstadoBasico']) ? $tramite['ID_EstadoBasico'] : 'No definido') . 
              " - Estado Trámite: " . 
              (isset($tramite['ID_EstadoTramite']) ? $tramite['ID_EstadoTramite'] : 'No definido') . 
              " - Status Real: " . 
              (isset($tramite['StatusReal']) ? $tramite['StatusReal'] : 'No definido'));
}
// Consultar todos los Status de trámite
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

// Consultar los Status descriptivos para el modal
$sqlStatusDesc = "SELECT ID_EstadoDescriptivo, Nombre FROM EstadosDescriptivos ORDER BY Nombre";
$resultadoStatusDesc = ejecutarConsulta($sqlStatusDesc);
$statusDescriptivos = ($resultadoStatusDesc['status'] === 'success') 
                     ? obtenerResultados($resultadoStatusDesc['stmt']) 
                     : array();
if ($resultadoStatusDesc['status'] === 'success') {
    cerrarConexion($resultadoStatusDesc['conn'], $resultadoStatusDesc['stmt']);
}

// Consultar los acuses asociados al trámite
$sqlAcuses = "SELECT a.ID_Acuse, a.NumeroAcuse, a.FechaRecepcionRAN, 
             a.NombreRevisor, a.FolioReloj, e.Nombre AS EstadoAvance, 
             e.Porcentaje, a.EstadoDescriptivo, a.Respuesta, a.FechaRegistro,
             a.ID_EstadoBasico, 
             eb.Nombre AS EstadoBasicoNombre,
             ed.Nombre AS EstadoDescriptivoNombre
             FROM Acuses a
             INNER JOIN EstadosTramite e ON a.ID_EstadoTramite = e.ID_EstadoTramite
             LEFT JOIN EstadosBasicos eb ON a.ID_EstadoBasico = eb.ID_EstadoBasico
             LEFT JOIN EstadosDescriptivos ed ON a.EstadoDescriptivo = ed.ID_EstadoDescriptivo
             WHERE a.ID_Tramite = ?
             ORDER BY a.FechaRegistro DESC";

$resultadoAcuses = ejecutarConsulta($sqlAcuses, array($idTramite));

// Inicializar variables para determinar el estado real
$hayAcuses = false;
$tieneAcusePrevenido = false;
$todosCompletados = true;
$statusRealTramite = "EN PROCESO"; // Status por defecto
$claseStatusReal = 'bg-primary';

if ($resultadoAcuses['status'] === 'success') {
    $acuses = obtenerResultados($resultadoAcuses['stmt']);
    cerrarConexion($resultadoAcuses['conn'], $resultadoAcuses['stmt']);
    
    $hayAcuses = count($acuses) > 0;
    
    // Si hay exactamente un acuse, usar su status directamente
if (count($acuses) == 1) {
    $acuse = $acuses[0];
    if (isset($acuse['ID_EstadoBasico'])) {
        if ($acuse['ID_EstadoBasico'] == 2) { // PREVENIDO
            $statusRealTramite = "PREVENIDO";
            $claseStatusReal = 'bg-info';
        } else if ($acuse['ID_EstadoBasico'] == 3) { // COMPLETA - CORREGIDO
            $statusRealTramite = "COMPLETA";
            $claseStatusReal = 'bg-success';
        } else { // EN PROCESO u otro
            $statusRealTramite = "EN PROCESO";
            $claseStatusReal = 'bg-primary';
        }
    }
} 
// Si hay múltiples acuses, aplicar las reglas
else if (count($acuses) > 1) {
    // Analizar los acuses para determinar el estado real
    foreach ($acuses as $acuse) {
        if (isset($acuse['ID_EstadoBasico']) && $acuse['ID_EstadoBasico'] == 2) {
            $tieneAcusePrevenido = true;
            $todosCompletados = false;
            break; // Ya encontramos un acuse prevenido, no necesitamos seguir
        } 
        else if (!isset($acuse['ID_EstadoBasico']) || $acuse['ID_EstadoBasico'] != 3) { // CORREGIDO
            $todosCompletados = false;
        }
    }
        
        // Determinar el estado real según las reglas
        if ($tieneAcusePrevenido) {
            $statusRealTramite = "PREVENIDO";
            $claseStatusReal = 'bg-info';
        } else if ($hayAcuses && $todosCompletados) {
            $statusRealTramite = "COMPLETA";
            $claseStatusReal = 'bg-success';
        } else {
            $statusRealTramite = "EN PROCESO";
            $claseStatusReal = 'bg-primary';
        }
    }
} else {
    // Si hay error al consultar acuses, usar el estado registrado en la tabla Tramites
    $statusRealTramite = $tramite['Estado'];
    $claseStatusReal = 'bg-primary'; // Por defecto usar azul
    $acuses = array(); // Inicializar como array vacío para evitar errores
}
    
// Consultar el historial de cambios del trámite
$sqlHistorial = "SELECT h.ID_Historial, h.FechaCambio, 
               h.EstadoAnterior, h.EstadoNuevo, h.Observacion, h.UsuarioResponsable, h.TipoAccion,
               e1.Nombre AS EstadoAnteriorNombre, e1.Porcentaje AS EstadoAnteriorPorcentaje,
               e2.Nombre AS EstadoNuevoNombre, e2.Porcentaje AS EstadoNuevoPorcentaje,
               CASE 
                   WHEN e1.ID_EstadoTramite = 7 THEN 'PREVENIDO'
                   WHEN e1.ID_EstadoTramite = 5 THEN 'COMPLETA'
                   ELSE 'EN PROCESO'
               END AS StatusAnteriorTexto,
               CASE 
                   WHEN e2.ID_EstadoTramite = 7 THEN 'PREVENIDO'
                   WHEN e2.ID_EstadoTramite = 5 THEN 'COMPLETA'
                   ELSE 'EN PROCESO'
               END AS StatusNuevoTexto
               FROM HistorialCambios h
               LEFT JOIN EstadosTramite e1 ON h.EstadoAnterior = e1.ID_EstadoTramite
               LEFT JOIN EstadosTramite e2 ON h.EstadoNuevo = e2.ID_EstadoTramite
               WHERE h.ID_Tramite = ?
               ORDER BY h.FechaCambio DESC";

$resultadoHistorial = ejecutarConsulta($sqlHistorial, array($idTramite));
$historial = ($resultadoHistorial['status'] === 'success') 
            ? obtenerResultados($resultadoHistorial['stmt']) 
            : array();
if($resultadoHistorial['status'] === 'success') {
    cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
}

// Consultar las reiteraciones asociadas al trámite
$sqlReiteraciones = "SELECT ID_Reiteracion, FolioReiteracion, FechaReiteracion, 
                   NumeroReiteracion, Observaciones, FechaRegistro
                   FROM Reiteraciones
                   WHERE ID_Tramite = ?
                   ORDER BY NumeroReiteracion ASC";

$resultadoReiteraciones = ejecutarConsulta($sqlReiteraciones, array($idTramite));
$reiteraciones = ($resultadoReiteraciones['status'] === 'success') 
                ? obtenerResultados($resultadoReiteraciones['stmt']) 
                : array();
if($resultadoReiteraciones['status'] === 'success') {
    cerrarConexion($resultadoReiteraciones['conn'], $resultadoReiteraciones['stmt']);
}

// Consultar las subsanaciones asociadas al trámite
$sqlSubsanaciones = "SELECT ID_Subsanacion, FolioSubsanacion, FechaSubsanacion, 
                    Descripcion, FechaRegistro
                    FROM Subsanaciones
                    WHERE ID_Tramite = ?
                    ORDER BY FechaRegistro DESC";

$resultadoSubsanaciones = ejecutarConsulta($sqlSubsanaciones, array($idTramite));
$subsanaciones = ($resultadoSubsanaciones['status'] === 'success') 
                ? obtenerResultados($resultadoSubsanaciones['stmt']) 
                : array();
if($resultadoSubsanaciones['status'] === 'success') {
    cerrarConexion($resultadoSubsanaciones['conn'], $resultadoSubsanaciones['stmt']);
}

// Determinar clase de badge según el porcentaje
$claseBadge = 'bg-secondary';
if(isset($tramite['Porcentaje'])) {
    if($tramite['Porcentaje'] == 0) $claseBadge = 'bg-secondary';
    else if($tramite['Porcentaje'] == 25) $claseBadge = 'bg-info';
    else if($tramite['Porcentaje'] == 50) $claseBadge = 'bg-warning';
    else if($tramite['Porcentaje'] == 75) $claseBadge = 'bg-primary';
    else if($tramite['Porcentaje'] == 100) $claseBadge = 'bg-success';
}

// Calcular los días transcurridos desde la última reiteración o desde FOLIO RCHRP
$fechaInicio = null;
$fechaOrigenTexto = "";
$diasTranscurridos = "N/A";
$necesitaReiteracion = false;

// MODIFICACIÓN 1: Comprobar explícitamente el estado COMPLETA antes de cualquier cálculo
$estaCompleto = ($tramite['StatusReal'] === 'COMPLETA' || 
                 (isset($tramite['ID_EstadoBasico']) && $tramite['ID_EstadoBasico'] == 1) || 
                 (isset($tramite['ID_EstadoTramite']) && $tramite['ID_EstadoTramite'] == 5));

// Verificar si hay reiteraciones previas
$sqlUltimaReiteracion = "SELECT TOP 1 FechaReiteracion, NumeroReiteracion 
                        FROM Reiteraciones 
                        WHERE ID_Tramite = ? 
                        ORDER BY NumeroReiteracion DESC";
$resultadoUltimaReiteracion = ejecutarConsulta($sqlUltimaReiteracion, array($idTramite));

if ($resultadoUltimaReiteracion['status'] === 'success') {
    $reiteracion = sqlsrv_fetch_array($resultadoUltimaReiteracion['stmt'], SQLSRV_FETCH_ASSOC);
    cerrarConexion($resultadoUltimaReiteracion['conn'], $resultadoUltimaReiteracion['stmt']);
    
    if ($reiteracion && isset($reiteracion['FechaReiteracion']) && $reiteracion['FechaReiteracion']) {
        // Si hay reiteración previa, usar su fecha como inicio
        $fechaInicio = $reiteracion['FechaReiteracion'];
        $numeroReiteracion = $reiteracion['NumeroReiteracion'];
        $fechaOrigenTexto = "última reiteración (#$numeroReiteracion) - " . $fechaInicio->format('d/m/Y');
    }
}

// Consultar el FOLIO RCHRP y su fecha si no tenemos fecha de inicio aún
if ($fechaInicio === null && isset($tramite['FechaRCHRP']) && $tramite['FechaRCHRP']) {
    $fechaInicio = $tramite['FechaRCHRP'];
    $fechaOrigenTexto = "FOLIO RCHRP - " . $fechaInicio->format('d/m/Y');
}

// Si hay fecha de inicio, calcular días transcurridos según el estado
if ($fechaInicio !== null) {
    if ($estaCompleto) {
        // Si está completo, verificar si tenemos fecha de completado para usar
        if (isset($tramite['FechaCompletado']) && $tramite['FechaCompletado']) {
            // Calcular días hasta la fecha de completado
            $diasTranscurridos = $tramite['FechaCompletado']->diff($fechaInicio)->days;
            $fechaOrigenTexto .= " hasta " . $tramite['FechaCompletado']->format('d/m/Y') . " (fecha de completado)";
        } else {
            // Si no hay fecha de completado (caso de registros antiguos), usar la última actualización
            if (isset($tramite['FechaUltimaActualizacion']) && $tramite['FechaUltimaActualizacion']) {
                $diasTranscurridos = $tramite['FechaUltimaActualizacion']->diff($fechaInicio)->days;
                $fechaOrigenTexto .= " hasta " . $tramite['FechaUltimaActualizacion']->format('d/m/Y') . " (fecha de última actualización)";
            } else {
                // Si tampoco hay fecha de última actualización, usar un valor fijo
                $diasTranscurridos = "0";
                $fechaOrigenTexto .= " - Trámite completado";
            }
        }
        
        // Trámites completos nunca necesitan reiteración
        $necesitaReiteracion = false;
    } else {
        // Para trámites no completados, calcular normalmente
        $fechaActual = new DateTime();
        $diasTranscurridos = $fechaActual->diff($fechaInicio)->days;
        
        // Solo verificar necesidad de reiteración si no está completo
        $necesitaReiteracion = ($diasTranscurridos >= 95);
    }
}

?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php">LISTADO DE TRÁMITES</a></li>
                <li class="breadcrumb-item active" aria-current="page">DETALLE DE TRÁMITE #<?php echo $idTramite; ?></li>
            </ol>
        </nav>
             
        <?php if($acuseRegistrado): ?>
        <div id="alertaAcuseRegistrado" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><strong>¡ACUSE REGISTRADO!</strong> El acuse ha sido registrado correctamente y el estado del trámite se ha actualizado.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
        // Utilizar setTimeout para ocultar la alerta después de unos segundos
        setTimeout(function() {
            var alerta = document.getElementById('alertaAcuseRegistrado');
            if (alerta) {
                var bsAlert = new bootstrap.Alert(alerta);
                bsAlert.close();
            }
        }, 5000); // Ocultar después de 5 segundos
        </script>
        <?php endif; ?>
        
        <?php if($folioRegistrado): ?>
        <div id="alertaFolioRegistrado" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><strong>¡FOLIO RCHRP REGISTRADO!</strong> El folio RCHRP ha sido registrado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
        // Utilizar setTimeout para ocultar la alerta después de unos segundos
        setTimeout(function() {
            var alerta = document.getElementById('alertaFolioRegistrado');
            if (alerta) {
                var bsAlert = new bootstrap.Alert(alerta);
                bsAlert.close();
            }
        }, 5000); // Ocultar después de 5 segundos
        </script>
        <?php endif; ?>

        <?php if($tramiteFiniquitado): ?>
        <div id="alertaFiniquitado" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><strong>¡TRÁMITE FINIQUITADO!</strong> El trámite ha sido finiquitado exitosamente basado en el último acuse.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
        // Utilizar setTimeout para ocultar la alerta después de unos segundos
        setTimeout(function() {
            var alerta = document.getElementById('alertaFiniquitado');
            if (alerta) {
                var bsAlert = new bootstrap.Alert(alerta);
                bsAlert.close();
            }
        }, 5000); // Ocultar después de 5 segundos
        </script>
        <?php endif; ?>

        <?php if($reiteracionRegistrada): ?>
        <div id="alertaReiteracionRegistrada" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><strong>¡REITERACIÓN REGISTRADA!</strong> La reiteración ha sido registrada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
        // Utilizar setTimeout para ocultar la alerta después de unos segundos
        setTimeout(function() {
            var alerta = document.getElementById('alertaReiteracionRegistrada');
            if (alerta) {
                var bsAlert = new bootstrap.Alert(alerta);
                bsAlert.close();
            }
        }, 5000); // Ocultar después de 5 segundos
        </script>
        <?php endif; ?>

        <!-- Información del trámite -->
        <div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div class="header-title-container">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    <span class="header-title">DETALLE DE TRÁMITE</span>
                    <span class="header-id">#<?php echo $idTramite; ?></span>
                </h5>
            </div>
            
            <div class="status-container">
                <span class="status-label">STATUS</span>
                <div class="status-badge-container">
                    <span class="status-badge <?php 
                        $statusClass = ($tramite['StatusReal'] == 'PREVENIDO') ? 'status-badge-warning' : 
                                      (($tramite['StatusReal'] == 'COMPLETA') ? 'status-badge-success' : 'status-badge-primary');
                        echo $statusClass;
                    ?>">
                        <?php echo $tramite['StatusReal']; ?>
                        <?php if($tramite['StatusReal'] == 'COMPLETA'): ?>
                            <i class="fas fa-check-circle ms-1"></i>
                        <?php elseif($tramite['StatusReal'] == 'PREVENIDO'): ?>
                            <i class="fas fa-exclamation-circle ms-1"></i>
                        <?php else: ?>
                            <i class="fas fa-clock ms-1"></i>
                        <?php endif; ?>
                    </span>
                    
                    <?php if(isset($tramite['Porcentaje']) && $tramite['StatusReal'] != 'COMPLETA'): ?>
                    <div class="progress mt-1" style="height: 8px;">
                        <div class="progress-bar 
                            <?php 
                                if($tramite['Porcentaje'] <= 25) echo 'bg-info';
                                else if($tramite['Porcentaje'] <= 50) echo 'bg-primary';
                                else if($tramite['Porcentaje'] <= 75) echo 'bg-primary';
                                else echo 'bg-success';
                            ?>" 
                            role="progressbar" 
                            style="width: <?php echo $tramite['Porcentaje']; ?>%;" 
                            aria-valuenow="<?php echo $tramite['Porcentaje']; ?>" 
                            aria-valuemin="0" 
                            aria-valuemax="100">
                        </div>
                    </div>
                    <span class="status-percentage">
                        <?php echo $tramite['Porcentaje']; ?>% completado
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>            

        <?php if($subsanacionRegistrada): ?>
<div id="alertaSubsanacionRegistrada" class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><strong>¡SUBSANACIÓN REGISTRADA!</strong> La subsanación ha sido registrada correctamente y el estado del trámite se ha actualizado.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<script>
// Utilizar setTimeout para ocultar la alerta después de unos segundos
setTimeout(function() {
    var alerta = document.getElementById('alertaSubsanacionRegistrada');
    if (alerta) {
        var bsAlert = new bootstrap.Alert(alerta);
        bsAlert.close();
    }
}, 5000); // Ocultar después de 5 segundos
</script>
<?php endif; ?>

            <!-- Alerta visual de reiteración si es necesario -->
            <?php if($necesitaReiteracion && !$estaCompleto): ?>
                <div class="alert alert-warning border-warning shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <div class="me-3">
            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="alert-heading mb-1">¡ATENCIÓN! ESTE TRÁMITE REQUIERE REITERACIÓN</h5>
            <p class="mb-1">HAN TRANSCURRIDO <strong><?php echo $diasTranscurridos; ?> DÍAS</strong> 
                DESDE <?php echo $fechaOrigenTexto; ?>. 
                <?php if($diasTranscurridos >= 100): ?>
                    <span class="text-danger">ESTE TRÁMITE YA NECESITA REITERACIÓN URGENTE.</span>
                <?php else: ?>
                    <span class="text-warning">ESTE TRÁMITE PRONTO NECESITARÁ REITERACIÓN.</span>
                <?php endif; ?>
                SE RECOMIENDA CONTACTAR A LA SECRETARÍA PARA OBTENER EL FOLIO DE REITERACIÓN.
            </p>
        </div>
        <div>
            <a href="registrar-reiteracion.php?id=<?php echo $idTramite; ?>" class="btn btn-warning">
                <i class="fas fa-sync-alt me-1"></i>REGISTRAR REITERACIÓN
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

            <!-- Información del CIIA y Promovente -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-hashtag me-2"></i>INFORMACIÓN DEL TRÁMITE</h6>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">CIIA:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['CIIA']) ? $tramite['CIIA'] : 'No registrado'; ?></dd>
                                
                                <dt class="col-sm-4">FECHA DE REGISTRO:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['FechaRegistro']) ? $tramite['FechaRegistro']->format('d/m/Y') : 'No registrada'; ?></dd>
                                
                                <dt class="col-sm-4">TIPO DE TRÁMITE:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['TipoTramite']) ? $tramite['TipoTramite'] : 'No especificado'; ?></dd>
                                
                                <dt class="col-sm-4">CLAVE DE TRÁMITE:</dt>
                                <dd class="col-sm-8"><?php echo (isset($tramite['ClaveTramite']) && isset($tramite['DescripcionClave'])) ? $tramite['ClaveTramite'] . ' - ' . $tramite['DescripcionClave'] : 'No especificada'; ?></dd>
                                
                                <dt class="col-sm-4">FOLIO RCHRP:</dt>
<dd class="col-sm-8">
    <?php if(isset($tramite['FolioRCHRP']) && !empty($tramite['FolioRCHRP'])): ?>
        <?php echo $tramite['FolioRCHRP']; ?>
    <?php endif; ?>
    <button class="btn btn-sm btn-outline-primary ms-2" 
            onclick="abrirModalRegistrarFolio(
                <?php echo $idTramite; ?>, 
                '<?php echo $tramite['CIIA']; ?>', 
                '<?php echo htmlspecialchars(($tramite['Nombre'] . ' ' . $tramite['ApellidoPaterno'] . ' ' . $tramite['ApellidoMaterno']) ?? '', ENT_QUOTES); ?>', 
                '<?php echo $tramite['FolioRCHRP'] ?: ''; ?>', 
                '<?php echo isset($tramite['FechaRCHRP']) && $tramite['FechaRCHRP'] ? $tramite['FechaRCHRP']->format('Y-m-d') : ''; ?>'
            )">
        <i class="fas fa-edit me-1"></i><?php echo isset($tramite['FolioRCHRP']) && $tramite['FolioRCHRP'] ? 'Actualizar' : 'Registrar'; ?> FOLIO
    </button>
</dd>
                                
                                <dt class="col-sm-4">FECHA RCHRP:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['FechaRCHRP']) && $tramite['FechaRCHRP'] ? $tramite['FechaRCHRP']->format('d/m/Y') : 'No registrada'; ?></dd>
                                
                                <dt class="col-sm-4">ÚLTIMA ACTUALIZACIÓN:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['FechaUltimaActualizacion']) ? $tramite['FechaUltimaActualizacion']->format('d/m/Y') : 'No registrada'; ?></dd>
                            
                                <dt class="col-sm-4">DÍAS TRANSCURRIDOS:</dt>
                                <dd class="col-sm-8">
                                    <?php if ($diasTranscurridos !== "N/A"): ?>
                                        <?php if ($estaCompleto): ?>
                                            <span><?php echo $diasTranscurridos; ?> días hasta completarse</span>
                                        <?php else: ?>
                                            <?php echo $diasTranscurridos; ?> días 
                                            <?php if($diasTranscurridos > 90 && $diasTranscurridos <= 100): ?>
                                                <span class="badge bg-warning">Próximo a reiteración</span>
                                            <?php elseif($diasTranscurridos > 100): ?>
                                                <span class="badge bg-danger">Requiere reiteración</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <small class="text-muted">(<?php echo $fechaOrigenTexto; ?>)</small>
                                    <?php else: ?>
                                        N/A <small class="text-muted">(No aplica conteo - Requiere FOLIO RCHRP)</small>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>INFORMACIÓN DEL PROMOVENTE</h6>
                            <?php if(isset($tramite['ID_Promovente'])): ?>
                                <button class="btn btn-primary btn-sm" onclick="abrirModalEditarPromovente(<?php echo $tramite['ID_Promovente']; ?>, 'detalle', <?php echo $idTramite; ?>)">
                                    <i class="fas fa-user-edit me-1"></i>EDITAR PROMOVENTE
                                </button>
                            <?php endif; ?>                          
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">NOMBRE COMPLETO:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['Nombre']) ? $tramite['Nombre'] . ' ' . $tramite['ApellidoPaterno'] . ' ' . $tramite['ApellidoMaterno'] : 'No registrado'; ?></dd>
                                
                                <dt class="col-sm-4">1ER TELÉFONO:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['Telefono']) && $tramite['Telefono'] ? $tramite['Telefono'] : 'No registrado'; ?></dd>
                                
                                <dt class="col-sm-4">2DO TELEFONO:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['Telefono2']) && $tramite['Telefono2'] ? $tramite['Telefono2'] : 'No registrado'; ?></dd>
                                
                                <dt class="col-sm-4">DIRECCIÓN:</dt>
                                <dd class="col-sm-8"><?php echo isset($tramite['Direccion']) && $tramite['Direccion'] ? $tramite['Direccion'] : 'No registrada'; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
                
            <!-- Información de la ubicación -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>UBICACIÓN</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-2">MUNICIPIO:</dt>
                        <dd class="col-sm-4"><?php echo isset($tramite['Municipio']) ? $tramite['Municipio'] : 'No especificado'; ?></dd>
                        
                        <dt class="col-sm-2">TIPO DE NÚCLEO AGRARIO:</dt>
                        <dd class="col-sm-4"><?php echo isset($tramite['TipoNucleoAgrario']) ? $tramite['TipoNucleoAgrario'] : 'No especificado'; ?></dd>
                        
                        <dt class="col-sm-2">NÚCLEO AGRARIO:</dt>
                        <dd class="col-sm-10"><?php echo isset($tramite['NucleoAgrario']) ? $tramite['NucleoAgrario'] : 'No especificado'; ?></dd>
                    </dl>
                </div>
            </div>
            
            <!-- Descripción del trámite -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-align-left me-2"></i>DESCRIPCIÓN DEL TRÁMITE:</h6>
                </div>
                <div class="card-body">
                    <p><?php echo isset($tramite['Descripcion']) ? nl2br($tramite['Descripcion']) : 'No hay descripción disponible'; ?></p>
                </div>
            </div>

            <!-- Pestañas para acuses, reiteraciones y subsanaciones -->
            <ul class="nav nav-tabs" id="detallesTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="acuses-tab" data-bs-toggle="tab" data-bs-target="#acuses" type="button" role="tab" aria-controls="acuses" aria-selected="true">
                        <i class="fas fa-file-alt me-1"></i>ACUSE/NÚMEROS DE TRÁMITE (<?php echo count($acuses); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reiteraciones-tab" data-bs-toggle="tab" data-bs-target="#reiteraciones" type="button" role="tab" aria-controls="reiteraciones" aria-selected="false">
                        <i class="fas fa-sync-alt me-1"></i>REITERACIONES (<?php echo count($reiteraciones); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="subsanaciones-tab" data-bs-toggle="tab" data-bs-target="#subsanaciones" type="button" role="tab" aria-controls="subsanaciones" aria-selected="false">
                        <i class="fas fa-clipboard-check me-1"></i>SUBSANACIONES (<?php echo count($subsanaciones); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab" aria-controls="historial" aria-selected="false">
                        <i class="fas fa-history me-1"></i>HISTORIAL DE MOVIMIENTOS (<?php echo count($historial); ?>)
                    </button>
                </li>
            </ul>
            <div class="tab-content p-3 border border-top-0 rounded-bottom" id="detallesTabContent">
                <div class="tab-pane fade show active" id="acuses" role="tabpanel" aria-labelledby="acuses-tab">                     
                    <?php if(count($acuses) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>NÚMERO DE ACUSE</th>
                                        <th>FECHA DE RECEPCIÓN AL RAN</th>
                                        <th>REVISOR / FOLIO RELOJ</th>
                                        <th>AVANCE</th>
                                        <th>STATUS</th>
                                        <th>RESPUESTA</th>
                                        <th>FECHA REGISTRO</th>
                                        <th>ACCIONES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($acuses as $acuse): 
                                        // Determinar clase del avance según el porcentaje
                                        $claseAvance = 'bg-secondary';
                                        if(isset($acuse['Porcentaje'])) {
                                            if($acuse['Porcentaje'] == 25) $claseAvance = 'bg-info';
                                            else if($acuse['Porcentaje'] == 50) $claseAvance = 'bg-warning';
                                            else if($acuse['Porcentaje'] == 75) $claseAvance = 'bg-primary';
                                            else if($acuse['Porcentaje'] == 100) $claseAvance = 'bg-success';
                                        }
                                        
                                        // Determinar clase del status básico
                                        $claseStatusBasico = 'bg-secondary';
                                        if(isset($acuse['ID_EstadoBasico'])) {
                                            if($acuse['ID_EstadoBasico'] == 1) $claseStatusBasico = 'bg-success'; // COMPLETA
                                            else if($acuse['ID_EstadoBasico'] == 2) $claseStatusBasico = 'bg-info'; // PREVENIDO
                                            else if($acuse['ID_EstadoBasico'] == 3) $claseStatusBasico = 'bg-primary'; // EN PROCESO
                                        }
                                        
                                        // Formatear texto de avance según el porcentaje
                                        $avanceTexto = "";
                                        if(isset($acuse['Porcentaje'])) {
                                            if($acuse['Porcentaje'] == 25) $avanceTexto = "1 DE 4 (25%)";
                                            else if($acuse['Porcentaje'] == 50) $avanceTexto = "2 DE 4 (50%)";
                                            else if($acuse['Porcentaje'] == 75) $avanceTexto = "3 DE 4 (75%)";
                                            else if($acuse['Porcentaje'] == 100) $avanceTexto = "4 DE 4 (100%)";
                                        }
                                        
                                        // Formatear fechas
                                        $fechaRecepcion = isset($acuse['FechaRecepcionRAN']) && $acuse['FechaRecepcionRAN'] ? $acuse['FechaRecepcionRAN']->format('d/m/Y') : 'N/A';
                                        $fechaRegistro = isset($acuse['FechaRegistro']) && $acuse['FechaRegistro'] ? $acuse['FechaRegistro']->format('d/m/Y') : 'N/A';
                                    ?>
                                    <tr>
                                        <td><?php echo isset($acuse['NumeroAcuse']) ? $acuse['NumeroAcuse'] : 'No especificado'; ?></td>
                                        <td><?php echo $fechaRecepcion; ?></td>
                                        <td>
                                            <?php 
                                                if(isset($acuse['NombreRevisor']) && $acuse['NombreRevisor']) {
                                                    echo $acuse['NombreRevisor'];
                                                } elseif(isset($acuse['FolioReloj']) && $acuse['FolioReloj']) {
                                                    echo 'Reloj: ' . $acuse['FolioReloj'];
                                                } else {
                                                    echo 'No especificado';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($avanceTexto)): ?>
                                            <span class="badge <?php echo $claseAvance; ?>"><?php echo $avanceTexto; ?></span>
                                            <?php else: ?>
                                            <span class="badge <?php echo $claseAvance; ?>"><?php echo isset($acuse['Porcentaje']) ? $acuse['Porcentaje'] . '%' : 'No especificado'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(isset($acuse['EstadoBasicoNombre']) && !empty($acuse['EstadoBasicoNombre'])): ?>
                                                <span class="badge <?php echo $claseStatusBasico; ?>"><?php echo $acuse['EstadoBasicoNombre']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No especificado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(isset($acuse['EstadoDescriptivoNombre']) && !empty($acuse['EstadoDescriptivoNombre'])): ?>
                                                <?php echo $acuse['EstadoDescriptivoNombre']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No especificado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $fechaRegistro; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary actualizar-estado-js" 
                                                    data-id-acuse="<?php echo $acuse['ID_Acuse']; ?>" 
                                                    data-id-tramite="<?php echo $idTramite; ?>">
                                                <i class="fas fa-sync-alt me-1"></i>ACTUALIZAR STATUS
                                            </button>            
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>NO HAY ACUSES REGISTRADOS PARA ESTE TRÁMITE.
                        </div>
                    <?php endif; ?>
                    <div class="text-end mt-3">
                        <a href="registrar-acuse.php?id=<?php echo $idTramite; ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i>REGISTRAR NUEVO ACUSE
                        </a>
                    </div>
                </div>

                <!-- Reiteraciones tab -->
                <div class="tab-pane fade" id="reiteraciones" role="tabpanel" aria-labelledby="reiteraciones-tab">                        
                    <?php if(count($reiteraciones) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>FOLIO REITERACIÓN</th>
                                        <th>FECHA REITERACIÓN</th>
                                        <th>NÚMERO DE REITERACIÓN</th>
                                        <th>OBSERVACIONES</th>
                                        <th>FECHA REGISTRO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($reiteraciones as $reiteracion):
                                        // Formatear fechas
                                        $fechaReiteracion = isset($reiteracion['FechaReiteracion']) && $reiteracion['FechaReiteracion'] ? $reiteracion['FechaReiteracion']->format('d/m/Y') : 'N/A';
                                        $fechaRegistro = isset($reiteracion['FechaRegistro']) && $reiteracion['FechaRegistro'] ? $reiteracion['FechaRegistro']->format('d/m/Y') : 'N/A';
                                    ?>
                                    <tr>
                                        <td><?php echo isset($reiteracion['FolioReiteracion']) ? $reiteracion['FolioReiteracion'] : 'No especificado'; ?></td>
                                        <td><?php echo $fechaReiteracion; ?></td>
                                        <td><?php echo isset($reiteracion['NumeroReiteracion']) ? $reiteracion['NumeroReiteracion'] : 'No especificado'; ?></td>
                                        <td><?php echo isset($reiteracion['Observaciones']) ? nl2br($reiteracion['Observaciones'] ?? '') : 'No hay observaciones'; ?></td>
                                        <td><?php echo $fechaRegistro; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>NO HAY REITERACIONES REGISTRADAS PARA ESTE TRÁMITE.
                        </div>
                    <?php endif; ?>
                    <?php if(count($reiteraciones) < 3): // Máximo 3 reiteraciones permitidas ?>
                        <div class="text-end mt-3">
                            <a href="registrar-reiteracion.php?id=<?php echo $idTramite; ?>" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i>Registrar Nueva Reiteración
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>YA SE HAN REGISTRADO EL MÁXIMO DE 3 REITERACIONES PERMITIDAS.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Subsanaciones tab -->
                <div class="tab-pane fade" id="subsanaciones" role="tabpanel" aria-labelledby="subsanaciones-tab">                       
                    <?php if(count($subsanaciones) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>FOLIO SUBSANACIÓN</th>
                                        <th>FECHA SUBSANACIÓN</th>
                                        <th>DESCRIPCIÓN</th>
                                        <th>FECHA REGISTRO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($subsanaciones as $subsanacion):
                                        // Formatear fechas
                                        $fechaSubsanacion = isset($subsanacion['FechaSubsanacion']) && $subsanacion['FechaSubsanacion'] ? $subsanacion['FechaSubsanacion']->format('d/m/Y') : 'N/A';
                                        $fechaRegistro = isset($subsanacion['FechaRegistro']) && $subsanacion['FechaRegistro'] ? $subsanacion['FechaRegistro']->format('d/m/Y') : 'N/A';
                                    ?>
                                    <tr>
                                        <td><?php echo isset($subsanacion['FolioSubsanacion']) ? $subsanacion['FolioSubsanacion'] : 'No especificado'; ?></td>
                                        <td><?php echo $fechaSubsanacion; ?></td>
                                        <td><?php echo isset($subsanacion['Descripcion']) ? nl2br($subsanacion['Descripcion'] ?? '') : 'No hay descripción'; ?></td>
                                        <td><?php echo $fechaRegistro; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>NO HAY SUBSANACIONES REGISTRADAS PARA ESTE TRÁMITE.
                        </div>
                    <?php endif; ?>
                    <div class="text-end mt-3">
                        <a href="registrar-subsanacion.php?id=<?php echo $idTramite; ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i>REGISTRAR NUEVA SUBSANACIÓN
                        </a>
                    </div>
                </div>

               <!-- Historial tab -->
<div class="tab-pane fade" id="historial" role="tabpanel" aria-labelledby="historial-tab">                       
    <?php if(count($historial) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                    <th class="text-center">FECHA Y HORA</th>
                    <th class="text-center">TIPO DE ACCIÓN</th>
                    <th class="text-center">STATUS ANTERIOR</th>
                    <th class="text-center">STATUS NUEVO</th>
                    <th class="text-center">DETALLES</th>
                    <th class="text-center">USUARIO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($historial as $movimiento):
                        // Formatear fecha y hora
                        $fechaHora = isset($movimiento['FechaCambio']) && $movimiento['FechaCambio'] ? $movimiento['FechaCambio']->format('d/m/Y H:i:s') : 'No especificada';
                        
                        // Determinar clases para status
                        $claseStatusAnterior = 'bg-primary'; // Por defecto EN PROCESO (azul)
                        if(isset($movimiento['StatusAnteriorTexto'])) {
                            if($movimiento['StatusAnteriorTexto'] === 'PREVENIDO') {
                                $claseStatusAnterior = 'bg-info';
                            } else if($movimiento['StatusAnteriorTexto'] === 'COMPLETA') {
                                $claseStatusAnterior = 'bg-success';
                            }
                        }
                        
                        $claseStatusNuevo = 'bg-primary'; // Por defecto EN PROCESO (azul)
                        if(isset($movimiento['StatusNuevoTexto'])) {
                            if($movimiento['StatusNuevoTexto'] === 'PREVENIDO') {
                                $claseStatusNuevo = 'bg-info';
                            } else if($movimiento['StatusNuevoTexto'] === 'COMPLETA') {
                                $claseStatusNuevo = 'bg-success';
                            }
                        }
                        
                        // Determinar ícono según el tipo de acción
                        $iconoAccion = 'fas fa-history';
                        $claseAccion = 'bg-secondary';
                        if(isset($movimiento['TipoAccion']) && !empty($movimiento['TipoAccion'])) {
                            if($movimiento['TipoAccion'] == 'Cambio de Status') {
                                $iconoAccion = 'fas fa-exchange-alt';
                                $claseAccion = 'bg-primary';
                            } else if($movimiento['TipoAccion'] == 'Registro de Acuse') {
                                $iconoAccion = 'fas fa-file-alt';
                                $claseAccion = 'bg-info';
                            } else if($movimiento['TipoAccion'] == 'Registro de Reiteración') {
                                $iconoAccion = 'fas fa-sync-alt';
                                $claseAccion = 'bg-warning';
                            } else if($movimiento['TipoAccion'] == 'Registro de Subsanación') {
                                $iconoAccion = 'fas fa-clipboard-check';
                                $claseAccion = 'bg-success';
                            } else if($movimiento['TipoAccion'] == 'Actualización de Datos') {
                                $iconoAccion = 'fas fa-edit';
                                $claseAccion = 'bg-secondary';
                            } else if($movimiento['TipoAccion'] == 'Registro de Folio RCHRP') {
                                $iconoAccion = 'fas fa-stamp';
                                $claseAccion = 'bg-info';
                            } else if($movimiento['TipoAccion'] == 'Sincronización Automática') {
                                $iconoAccion = 'fas fa-robot';
                                $claseAccion = 'bg-primary';
                            }
                        }
                        
                        // Corregir el texto en la columna "DETALLES" para que use el statusNuevo
                        $detalles = isset($movimiento['Observacion']) ? $movimiento['Observacion'] : 'No hay detalles';
                        
                        // Si es un registro de acuse, asegurarse que use el StatusNuevoTexto correcto
                        if(isset($movimiento['TipoAccion']) && $movimiento['TipoAccion'] == 'Registro de Acuse' && 
                           strpos($detalles, 'Estado:') !== false) {
                            // Reemplazar el estado en los detalles con el StatusNuevoTexto 
                            $estadoTexto = isset($movimiento['StatusNuevoTexto']) ? $movimiento['StatusNuevoTexto'] : 'EN PROCESO';
                            $detalles = preg_replace('/Estado: [A-Z]+/', 'Estado: ' . $estadoTexto, $detalles);
                        }
                    ?>
                    <tr>
                        <td><?php echo $fechaHora; ?></td>
                        <td>
                            <span class="badge <?php echo $claseAccion; ?>">
                                <i class="<?php echo $iconoAccion; ?> me-1"></i>
                                <?php echo isset($movimiento['TipoAccion']) ? $movimiento['TipoAccion'] : 'Cambio de Status'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if(isset($movimiento['StatusAnteriorTexto']) && !is_null($movimiento['StatusAnteriorTexto'])): ?>
                                <span class="badge <?php echo $claseStatusAnterior; ?>">
                                    <?php echo $movimiento['StatusAnteriorTexto']; ?>
                                </span>
                                <?php if(isset($movimiento['EstadoAnteriorPorcentaje']) && !is_null($movimiento['EstadoAnteriorPorcentaje'])): ?>
                                    <div class="text-center mt-1">
                                        <small class="text-muted"><?php echo $movimiento['EstadoAnteriorPorcentaje']; ?>%</small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(isset($movimiento['StatusNuevoTexto']) && !is_null($movimiento['StatusNuevoTexto'])): ?>
                                <span class="badge <?php echo $claseStatusNuevo; ?>">
                                    <?php echo $movimiento['StatusNuevoTexto']; ?>
                                </span>
                                <?php if(isset($movimiento['EstadoNuevoPorcentaje']) && !is_null($movimiento['EstadoNuevoPorcentaje'])): ?>
                                    <div class="text-center mt-1">
                                        <small class="text-muted"><?php echo $movimiento['EstadoNuevoPorcentaje']; ?>%</small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $detalles; ?></td>
                        <td><?php echo isset($movimiento['UsuarioResponsable']) ? $movimiento['UsuarioResponsable'] : 'No especificado'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Leyenda para tipos de acción -->
        <div class="mt-3">
            <h6 class="text-muted">TIPOS DE ACCIONES:</h6>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-primary"><i class="fas fa-exchange-alt me-1"></i>CAMBIO DE STATUS</span>
                <span class="badge bg-info"><i class="fas fa-file-alt me-1"></i>REGISTRO DE ACUSE</span>
                <span class="badge bg-warning"><i class="fas fa-sync-alt me-1"></i>REGISTRO DE REITERACIÓN</span>
                <span class="badge bg-success"><i class="fas fa-clipboard-check me-1"></i>REGISTRO DE SUBSANACIÓN</span>
                <span class="badge bg-secondary"><i class="fas fa-edit me-1"></i>ACTUALIZACIÓN DE DATOS</span>
                <span class="badge bg-info"><i class="fas fa-stamp me-1"></i>REGISTRO DE FOLIO RCHRP</span>
                <span class="badge bg-primary"><i class="fas fa-robot me-1"></i>ACTUALIZACIÓN AUTOMÁTICA</span>
            </div>
        </div>
        
        <div class="text-muted mt-2 small">
            <i class="fas fa-info-circle me-1"></i>
            ESTE HISTORIAL MUESTRA TODOS LOS CAMBIOS Y ACTUALIZACIONES REALIZADAS AL TRÁMITE.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>NO HAY REGISTROS EN EL HISTORIAL DE ESTE TRÁMITE.
        </div>
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Actualizar Status -->
<div class="modal fade" id="actualizarEstadoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">                <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i>ACTUALIZAR STATUS DE ACUSE</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <script>
            // Definir variables comúnmente utilizadas por bibliotecas para evitar errores
            window.N = window.N || {};
            window.A = window.A || {};
            </script>
            <div class="modal-body">
                <form id="formActualizarEstado">
                    <input type="hidden" id="idAcuse" name="id_acuse">
                    <input type="hidden" id="idTramite" name="id_tramite">
                    
                    <div class="mb-3">
                        <label for="estado_acuse" class="form-label">AVANCE DEL TRÁMITE<span class="text-danger">*</span></label>
                        <select class="form-select" id="estado_acuse" name="estado_acuse" required>
                            <option value="">SELECCIONE UN AVANCE...</option>
                            <?php foreach($status as $status): ?>
                            <option value="<?php echo $status['ID_EstadoTramite']; ?>">
                                <?php echo $status['Nombre']; ?> (<?php echo $status['Porcentaje']; ?>%)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Campo de status básico -->
                    <div class="mb-3">
                        <label for="estado_basico" class="form-label">STATUS<span class="text-danger">*</span></label>
                        <select class="form-select" id="estado_basico" name="estado_basico" required>
                            <option value="">SELECCIONE UN STATUS...</option>
                            <?php 
                            // Consultar los status básicos
                            $sqlStatusBasicos = "SELECT ID_EstadoBasico, Nombre FROM EstadosBasicos ORDER BY Nombre";
                            $resultadoStatusBasicos = ejecutarConsulta($sqlStatusBasicos);
                            $statusBasicos = ($resultadoStatusBasicos['status'] === 'success') 
                                            ? obtenerResultados($resultadoStatusBasicos['stmt']) 
                                            : array();
                            if ($resultadoStatusBasicos['status'] === 'success') {
                                cerrarConexion($resultadoStatusBasicos['conn'], $resultadoStatusBasicos['stmt']);
                            }
                            
                            foreach($statusBasicos as $statusBasico): 
                            ?>
                            <option value="<?php echo $statusBasico['ID_EstadoBasico']; ?>">
                                <?php echo $statusBasico['Nombre']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="estado_descriptivo" class="form-label">COMENTARIO<span class="text-danger">*</span></label>
                        <select class="form-select" id="estado_descriptivo" name="estado_descriptivo" required>
                            <option value="">SELECCIONE UN COMENTARIO...</option>
                            <?php foreach($statusDescriptivos as $statusDesc): ?>
                            <option value="<?php echo $statusDesc['ID_EstadoDescriptivo']; ?>">
                                <?php echo $statusDesc['Nombre']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">CANCELAR</button>
                        <button type="button" id="btnGuardarEstado" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>GUARDAR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Verificar que jQuery y Bootstrap se hayan cargado correctamente
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery === 'undefined') {
        console.error('jQuery no se ha cargado correctamente');
        alert('Error: jQuery no se ha cargado correctamente');
    }
    
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap no se ha cargado correctamente');
        alert('Error: Bootstrap no se ha cargado correctamente');
    }
    
    // Verificar si hay errores en la consola del navegador
    console.log('Sistema de trámites cargado correctamente');
});
</script>
<?php 
// Incluir los modales necesarios para "Nuevo Trámite" 
include '../modulos/modal_buscar_promovente.php'; 
include '../modulos/modal_nuevo_promovente.php'; 
include '../modulos/modal_nuevo_tramite.php';  
?>

<!-- Scripts jQuery, Bootstrap y SweetAlert2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script src="../js/alertas.js"></script>
<script src="../js/main.js"></script>

<!-- Script de verificación de carga de bibliotecas -->
<script>
// Esta función se ejecutará después de que jQuery esté cargado
$(document).ready(function() {
    console.log('jQuery cargado correctamente');
    
    // Verificar Bootstrap
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap no se ha cargado correctamente');
        alert('Error: Bootstrap no se ha cargado correctamente');
    } else {
        console.log('Bootstrap cargado correctamente');
    }
    
    // Verificar SweetAlert2
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 no se ha cargado correctamente');
        alert('Error: SweetAlert2 no se ha cargado correctamente');
    } else {
        console.log('SweetAlert2 cargado correctamente');
    }
    
    console.log('Sistema de trámites inicializado');
});
</script>

<!-- Script para actualización de status y otros comportamientos -->
<script>
// Función para abrir el modal de actualización de status
function abrirModalActualizacion(idAcuse, idTramite) {
    console.log('Abriendo modal para acuse:', idAcuse, 'del trámite:', idTramite);
    $('#idAcuse').val(idAcuse);
    $('#idTramite').val(idTramite);
    $('#actualizarEstadoModal').modal('show');
}

// Cuando el documento esté listo
$(document).ready(function() {
    // Asignar evento a los botones de actualización de status
    $(document).on('click', '.actualizar-estado-js', function() {
        const idAcuse = $(this).data('id-acuse');
        const idTramite = $(this).data('id-tramite');
        console.log('Botón clickeado. Acuse:', idAcuse, 'Trámite:', idTramite);
        abrirModalActualizacion(idAcuse, idTramite);
    });
    
    // Guardar cambios de status
    $(document).on('click', '#btnGuardarEstado', function() {
        // Validar que se hayan seleccionado todos los campos
        const statusAcuse = $('#estado_acuse').val();
        const statusDescriptivo = $('#estado_descriptivo').val();
        const statusBasico = $('#estado_basico').val();
        
        if (!statusAcuse || !statusDescriptivo || !statusBasico) {
            Swal.fire({
                icon: 'error',
                title: 'Campos requeridos',
                text: 'Debe seleccionar todos los campos: avance, status y comentario'
            });
            return;
        }
        
        // Mostrar indicador de carga
        $('#btnGuardarEstado').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        // Enviar datos mediante AJAX
        $.ajax({
            url: '../api/actualizar_estado_acuse.php',
            type: 'POST',
            data: $('#formActualizarEstado').serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#actualizarEstadoModal').modal('hide');
                    
                    Swal.fire({
                        icon: 'success',
                        title: '¡Status actualizado!',
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
                    $('#btnGuardarEstado').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', xhr, status, error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor'
                });
                $('#btnGuardarEstado').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar');
            }
        });
    });
    
    <?php if($necesitaReiteracion): ?>
// Ejecutar solo si es necesaria la reiteración
if (typeof mostrarAlertaReiteracion === 'function') {
    mostrarAlertaReiteracion(
        <?php echo $idTramite; ?>, 
        <?php echo $diasTranscurridos; ?>, 
        '<?php echo $tramite['StatusReal']; ?>'
    );
} else {
    console.warn('La función mostrarAlertaReiteracion no está disponible');
}
<?php endif; ?>
    
    <?php if($folioRegistrado): ?>
    // Mostrar mensaje de folio registrado
    Swal.fire({
        icon: 'success',
        title: '¡FOLIO RCHRP REGISTRADO!',
        text: 'EL FOLIO RCHRP HA SIDO REGISTRADO CORRECTAMENTE.'
    });
    <?php endif; ?>
});
</script>

<!-- Script adicional para alerta de reiteración -->
<?php if($fechaInicio !== null && $diasTranscurridos >= 95): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Debug info - Días transcurridos:', <?php echo $diasTranscurridos; ?>);
    console.log('Debug info - Status real del trámite:', '<?php echo isset($tramite["StatusReal"]) ? $tramite["StatusReal"] : "No definido"; ?>');
    console.log('Debug info - ID estado:', <?php echo isset($tramite["ID_EstadoTramite"]) ? $tramite["ID_EstadoTramite"] : "null"; ?>);
    console.log('Debug info - Estado básico:', <?php echo isset($tramite["ID_EstadoBasico"]) ? $tramite["ID_EstadoBasico"] : "null"; ?>);
    
    // Verificación explícita del status antes de mostrar la alerta
    var statusReal = '<?php echo isset($tramite["StatusReal"]) ? $tramite["StatusReal"] : "No definido"; ?>';
    
    // Si el status es COMPLETA, no mostrar alerta
    if (statusReal === 'COMPLETA') {
        console.log('Trámite está COMPLETA - No se muestra alerta de reiteración');
        return;
    }
    
    // Verificación adicional ID_EstadoBasico
    var estadoBasico = <?php echo isset($tramite["ID_EstadoBasico"]) ? $tramite["ID_EstadoBasico"] : "null"; ?>;
    if (estadoBasico === 1) { // COMPLETA
        console.log('Trámite con ID_EstadoBasico = 1 (COMPLETA) - No se muestra alerta');
        return;
    }
    
    // Verificación adicional ID_EstadoTramite
    var estadoTramite = <?php echo isset($tramite["ID_EstadoTramite"]) ? $tramite["ID_EstadoTramite"] : "null"; ?>;
    if (estadoTramite === 5) { // COMPLETA (ID 5)
        console.log('Trámite con ID_EstadoTramite = 5 (COMPLETA) - No se muestra alerta');
        return;
    }
    
    // Si pasa todas las verificaciones, mostrar la alerta de reiteración
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¡Atención! Trámite cercano a reiteración',
            html: `
                <div class="text-center mb-3">
                    <i class="fas fa-calendar-times text-warning" style="font-size: 3rem;"></i>
                </div>
                <p class="fs-5">Este trámite lleva <strong><?php echo $diasTranscurridos; ?> días</strong> sin actualización desde <?php echo $fechaOrigenTexto; ?>.</p>
                <p>
                    <?php if($diasTranscurridos >= 100): ?>
                    <strong class="text-danger">Ya requiere reiteración urgente.</strong>
                    <?php else: ?>
                    <strong class="text-warning">Está próximo a necesitar reiteración.</strong>
                    <?php endif; ?>
                </p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Por favor, contacte a la secretaría para obtener el número de folio para la reiteración.
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ir a Registrar Reiteración',
            cancelButtonText: 'Cerrar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'registrar-reiteracion.php?id=<?php echo $idTramite; ?>';
            }
        });
    } else {
        console.error('SweetAlert2 no está disponible');
    }
});
</script>

<?php endif; ?>


<?php
// Incluir el footer debe ser lo último
include_once '../modulos/footer.php';
?>