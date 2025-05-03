<?php
/**
 * paginas/finalizar-tramite.php - Proceso para finalizar un trámite basado en su último acuse
 */

// Incluir archivo de configuración
require_once '../config.php';

// Obtener el ID del trámite y acuse de la URL
$idTramite = isset($_GET['id']) ? intval($_GET['id']) : 0;
$idAcuse = isset($_GET['acuse']) ? intval($_GET['acuse']) : 0;

if ($idTramite <= 0 || $idAcuse <= 0) {
    // Redirigir si no hay IDs válidos
    header("Location: listado-tramites.php");
    exit;
}

// Consultar el acuse para obtener su status
$sqlAcuse = "SELECT a.ID_EstadoTramite, a.ID_EstadoBasico, e.Nombre AS EstadoNombre 
             FROM Acuses a 
             INNER JOIN EstadosTramite e ON a.ID_EstadoTramite = e.ID_EstadoTramite
             WHERE a.ID_Acuse = ?";
$resultadoAcuse = ejecutarConsulta($sqlAcuse, array($idAcuse));

if ($resultadoAcuse['status'] !== 'success') {
    // Error al consultar el acuse
    header("Location: detalle-tramite.php?id={$idTramite}&error=consulta_acuse");
    exit;
}

$acuse = sqlsrv_fetch_array($resultadoAcuse['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoAcuse['conn'], $resultadoAcuse['stmt']);

if (!$acuse) {
    // Acuse no encontrado
    header("Location: detalle-tramite.php?id={$idTramite}&error=acuse_no_encontrado");
    exit;
}

// Consultar el status actual del trámite
$sqlStatusActual = "SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?";
$resultadoStatusActual = ejecutarConsulta($sqlStatusActual, array($idTramite));

if ($resultadoStatusActual['status'] !== 'success') {
    // Error al consultar el trámite
    header("Location: detalle-tramite.php?id={$idTramite}&error=consulta_tramite");
    exit;
}

$tramite = sqlsrv_fetch_array($resultadoStatusActual['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoStatusActual['conn'], $resultadoStatusActual['stmt']);

if (!$tramite) {
    // Trámite no encontrado
    header("Location: listado-tramites.php?error=tramite_no_encontrado");
    exit;
}

$statusAnterior = $tramite['ID_EstadoTramite'];
$statusNuevo = $acuse['ID_EstadoTramite'];

// Si el acuse está en status básico "COMPLETA" (asumimos ID 1), establecer status de trámite como 5 (Completa)
// Si el acuse está en status básico "COMPLETA" (ID 1), establecer status de trámite como 5 (Completa)
if ($acuse['ID_EstadoBasico'] == 1) {
    $statusNuevo = 5; // ID para status "Completa"
} 
// Si el acuse está en status básico "PREVENIDO" (ID 2), mantener ese status
elseif ($acuse['ID_EstadoBasico'] == 2) {
    $statusNuevo = 7; // ID para status "Prevenido"
} 
// Si el acuse está en status básico "EN PROCESO" (ID 3), usar el status de avance del acuse
elseif ($acuse['ID_EstadoBasico'] == 3) {
    $statusNuevo = $acuse['ID_EstadoTramite']; // Usar el status de avance (2, 3, o 4 dependiendo del porcentaje)
}

// Actualizar el status del trámite si es diferente al actual
if ($statusAnterior != $statusNuevo) {
    // Actualizar el status del trámite a COMPLETA (status 5)
$sqlActualizar = "UPDATE Tramites 
SET ID_EstadoTramite = 5, 
    FechaUltimaActualizacion = GETDATE() 
WHERE ID_Tramite = ?";

$paramsActualizar = array($idTramite);
$resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
    
    if ($resultadoActualizar['status'] !== 'success') {
        // Error al actualizar el trámite
        header("Location: detalle-tramite.php?id={$idTramite}&error=actualizacion_fallida");
        exit;
    }
    
    cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
    
    // Registrar en historial de cambios
    $sqlHistorial = "INSERT INTO HistorialCambios (
                    ID_Tramite, EstadoAnterior, EstadoNuevo, 
                    Observacion, UsuarioResponsable, TipoAccion
                    ) VALUES (?, ?, ?, ?, ?, ?)";
    
    $observacion = "Finiquito de trámite basado en acuse #" . $idAcuse;
    $resultadoHistorial = ejecutarConsulta($sqlHistorial, array(
        $idTramite, 
        $statusAnterior, 
        $statusNuevo, 
        $observacion, 
        'Sistema', 
        'Finiquito de Trámite'
    ));
    
    if ($resultadoHistorial['status'] === 'success') {
        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
    }
}

// Redirigir de vuelta a la página de detalles con un mensaje de éxito
header("Location: detalle-tramite.php?id={$idTramite}&tramite_finiquitado=1");
exit;
?>