<?php
/**
 * api/actualizar_estado_acuse.php - API para actualizar el status de un acuse
 * Versión mejorada con sincronización correcta de status entre acuses y trámites
 * y registro de fecha de completado para trámites finalizados
 */

// Incluir archivo de configuración
require_once '../config.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener y sanitizar datos
$idAcuse = isset($_POST['id_acuse']) ? intval($_POST['id_acuse']) : 0;
$idTramite = isset($_POST['id_tramite']) ? intval($_POST['id_tramite']) : 0;
$estadoAcuse = isset($_POST['estado_acuse']) ? intval($_POST['estado_acuse']) : 0;
$estadoDescriptivo = isset($_POST['estado_descriptivo']) ? sanitizarEntrada($_POST['estado_descriptivo']) : 0;
$estadoBasico = isset($_POST['estado_basico']) ? intval($_POST['estado_basico']) : 0;

// Validar datos requeridos
if ($idAcuse <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID de acuse no válido']);
    exit;
}

if ($idTramite <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID de trámite no válido']);
    exit;
}

if ($estadoAcuse <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un avance para el trámite']);
    exit;
}

if ($estadoDescriptivo <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un comentario']);
    exit;
}

if ($estadoBasico <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un estado básico']);
    exit;
}

// Consultar el estado actual del acuse
$sqlEstadoActual = "SELECT ID_EstadoTramite, ID_EstadoBasico FROM Acuses WHERE ID_Acuse = ?";
$resultadoEstadoActual = ejecutarConsulta($sqlEstadoActual, array($idAcuse));

if ($resultadoEstadoActual['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar el estado actual del acuse']);
    exit;
}

$rowEstadoActual = sqlsrv_fetch_array($resultadoEstadoActual['stmt'], SQLSRV_FETCH_ASSOC);
if (!$rowEstadoActual) {
    cerrarConexion($resultadoEstadoActual['conn'], $resultadoEstadoActual['stmt']);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'El acuse no existe']);
    exit;
}

$estadoAnterior = $rowEstadoActual['ID_EstadoTramite'];
$estadoBasicoAnterior = $rowEstadoActual['ID_EstadoBasico'];
cerrarConexion($resultadoEstadoActual['conn'], $resultadoEstadoActual['stmt']);

// Actualizar el acuse con el nuevo estado
$sqlActualizar = "UPDATE Acuses SET 
                 ID_EstadoTramite = ?, 
                 EstadoDescriptivo = ?,
                 ID_EstadoBasico = ?
                 WHERE ID_Acuse = ?";

$paramsActualizar = array($estadoAcuse, $estadoDescriptivo, $estadoBasico, $idAcuse);
$resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);

if ($resultadoActualizar['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el estado del acuse: ' . $resultadoActualizar['message']]);
    exit;
}

cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);

// Consultar el estado actual del trámite antes de actualizar
$sqlTramiteActual = "SELECT ID_EstadoTramite, ID_EstadoBasico FROM Tramites WHERE ID_Tramite = ?";
$resultadoTramiteActual = ejecutarConsulta($sqlTramiteActual, array($idTramite));

if ($resultadoTramiteActual['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar el estado actual del trámite']);
    exit;
}

$tramiteActual = sqlsrv_fetch_array($resultadoTramiteActual['stmt'], SQLSRV_FETCH_ASSOC);
$tramiteEstadoActual = $tramiteActual['ID_EstadoTramite'];
$tramiteEstadoBasicoActual = $tramiteActual['ID_EstadoBasico'];
cerrarConexion($resultadoTramiteActual['conn'], $resultadoTramiteActual['stmt']);

// Variable para indicar si se completó el trámite
$tramiteCompletado = false;

// Asegurarse de que el estado del trámite esté sincronizado con sus acuses
// Esta función está definida en config.php y contiene la lógica de sincronización

// Consultar todos los acuses del trámite para determinar su estado
$sqlAcuses = "SELECT a.ID_Acuse, a.ID_EstadoTramite, a.ID_EstadoBasico 
             FROM Acuses a 
             WHERE a.ID_Tramite = ? 
             ORDER BY a.FechaRegistro DESC";
             
$resultadoAcuses = ejecutarConsulta($sqlAcuses, array($idTramite));

if ($resultadoAcuses['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar los acuses del trámite']);
    exit;
}

$acuses = obtenerResultados($resultadoAcuses['stmt']);
cerrarConexion($resultadoAcuses['conn'], $resultadoAcuses['stmt']);

$numAcuses = count($acuses);
$hayPrevenido = false;
$todosCompletados = true;

// Analizar los estados de los acuses
if ($numAcuses > 0) {
    foreach ($acuses as $acuse) {
        if (isset($acuse['ID_EstadoBasico']) && $acuse['ID_EstadoBasico'] == 2) {
            $hayPrevenido = true;
            $todosCompletados = false;
            break;
        } 
        elseif (!isset($acuse['ID_EstadoBasico']) || $acuse['ID_EstadoBasico'] != 1) {
            $todosCompletados = false;
        }
    }
}

// Determinar el nuevo estado básico
$nuevoEstadoBasicoID = null;

if ($hayPrevenido) {
    $nuevoEstadoBasicoID = 2; // PREVENIDO
} elseif ($todosCompletados) {
    $nuevoEstadoBasicoID = 1; // COMPLETA
} else {
    $nuevoEstadoBasicoID = 3; // EN PROCESO
}

// Determinar el nuevo estado del trámite basado en el estado básico
$nuevoEstadoTramiteID = null;

if ($nuevoEstadoBasicoID == 2) { // PREVENIDO
    $nuevoEstadoTramiteID = 7; // ID para "Prevenido"
} elseif ($nuevoEstadoBasicoID == 1) { // COMPLETA
    $nuevoEstadoTramiteID = 5; // ID para "Completa" (100%)
} else { // EN PROCESO
    // Mantener el avance actual si existe, o usar el avance del primer acuse
    if ($tramiteEstadoActual >= 2 && $tramiteEstadoActual <= 4) {
        $nuevoEstadoTramiteID = $tramiteEstadoActual;
    } elseif ($numAcuses > 0 && isset($acuses[0]['ID_EstadoTramite'])) {
        $nuevoEstadoTramiteID = $acuses[0]['ID_EstadoTramite'];
    } else {
        $nuevoEstadoTramiteID = 2; // Por defecto, 1 DE 4 (25%)
    }
}

// Determinar si hay un cambio a estado COMPLETA o desde COMPLETA
$cambioACompleta = ($nuevoEstadoBasicoID == 1 && $tramiteEstadoBasicoActual != 1);
$cambioDesdeLaCompletado = ($nuevoEstadoBasicoID != 1 && $tramiteEstadoBasicoActual == 1);

// Decisión de SQL basada en el estado
if ($nuevoEstadoBasicoID == 1 || $nuevoEstadoTramiteID == 5) {
    // Cuando se actualiza a estado COMPLETA (ID_EstadoBasico = 1 o ID_EstadoTramite = 5)
    $sqlActualizarTramite = "UPDATE Tramites 
                           SET ID_EstadoTramite = ?, 
                               ID_EstadoBasico = ?,
                               FechaCompletado = GETDATE(),
                               FechaUltimaActualizacion = GETDATE() 
                           WHERE ID_Tramite = ?";
    $tramiteCompletado = true;
} else {
    // Actualización normal para otros estados (sin actualizar FechaCompletado)
    $sqlActualizarTramite = "UPDATE Tramites 
                           SET ID_EstadoTramite = ?, 
                               ID_EstadoBasico = ?,
                               FechaUltimaActualizacion = GETDATE() 
                           WHERE ID_Tramite = ?";
    
    // Si estaba completado antes y ahora no, limpiar la fecha de completado
    if ($cambioDesdeLaCompletado) {
        $sqlActualizarTramite = "UPDATE Tramites 
                               SET ID_EstadoTramite = ?, 
                                   ID_EstadoBasico = ?,
                                   FechaCompletado = NULL,
                                   FechaUltimaActualizacion = GETDATE() 
                               WHERE ID_Tramite = ?";
    }
}

// Ejecutar la actualización del trámite
$paramsActualizarTramite = array($nuevoEstadoTramiteID, $nuevoEstadoBasicoID, $idTramite);
$resultadoActualizarTramite = ejecutarConsulta($sqlActualizarTramite, $paramsActualizarTramite);

if ($resultadoActualizarTramite['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el estado del trámite: ' . $resultadoActualizarTramite['message']]);
    exit;
}

cerrarConexion($resultadoActualizarTramite['conn'], $resultadoActualizarTramite['stmt']);

// Registrar en historial de cambios si hubo un cambio de estado
if ($tramiteEstadoActual != $nuevoEstadoTramiteID || $tramiteEstadoBasicoActual != $nuevoEstadoBasicoID) {
    $sqlHistorial = "INSERT INTO HistorialCambios (
                    ID_Tramite, EstadoAnterior, EstadoNuevo, 
                    Observacion, UsuarioResponsable, TipoAccion
                    ) VALUES (?, ?, ?, ?, ?, ?)";
    
    $observacionHistorial = "Sincronización automática - Actualización de acuse";
    if ($tramiteCompletado) {
        $observacionHistorial .= " - Trámite completado";
    } elseif ($cambioDesdeLaCompletado) {
        $observacionHistorial .= " - Reactivación de trámite completado";
    }
    
    $paramsHistorial = array(
        $idTramite, 
        $tramiteEstadoActual, 
        $nuevoEstadoTramiteID, 
        $observacionHistorial, 
        'Sistema', 
        'Actualización de Acuse'
    );
    
    $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
    
    if ($resultadoHistorial['status'] === 'success') {
        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
    }
}

// Devolver respuesta exitosa
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success', 
    'message' => 'El status del acuse ha sido actualizado correctamente' . 
                 ($tramiteCompletado ? ' y el trámite ha sido marcado como completado' : '') .
                 ($cambioDesdeLaCompletado ? ' y el trámite ha sido reactivado' : '')
]);
exit;
?>