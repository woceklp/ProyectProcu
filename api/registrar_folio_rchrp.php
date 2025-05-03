<?php
/**
 * api/registrar_folio_rchrp.php - API para registrar o actualizar el Folio RCHRP
 * Versión corregida para evitar entradas duplicadas en el historial
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
$idTramite = isset($_POST['id_tramite']) ? intval($_POST['id_tramite']) : 0;
$folioRCHRP = isset($_POST['folio_rchrp']) ? sanitizarEntrada($_POST['folio_rchrp']) : '';
$fechaRCHRP = isset($_POST['fecha_rchrp']) ? sanitizarEntrada($_POST['fecha_rchrp']) : '';

// Validar datos requeridos
if ($idTramite <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID de trámite no válido']);
    exit;
}

if (empty($folioRCHRP)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'El Folio RCHRP es requerido']);
    exit;
}

if (empty($fechaRCHRP)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'La fecha RCHRP es requerida']);
    exit;
}

// Verificar que el trámite exista
$sqlVerificar = "SELECT ID_Tramite, FolioRCHRP FROM Tramites WHERE ID_Tramite = ?";
$resultadoVerificar = ejecutarConsulta($sqlVerificar, array($idTramite));

if ($resultadoVerificar['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al verificar el trámite: ' . $resultadoVerificar['message']]);
    exit;
}

$tramite = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);

if (!$tramite) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'El trámite especificado no existe']);
    exit;
}

// Convertir fecha a formato SQL Server (Y-m-d)
$fechaFormateada = date('Y-m-d', strtotime($fechaRCHRP));

// Determinar si es un nuevo registro o una actualización
$estaActualizando = !empty($tramite['FolioRCHRP']);
$accion = $estaActualizando ? 'actualizado' : 'registrado';

// Actualizar el trámite con el nuevo folio RCHRP
$sqlActualizar = "UPDATE Tramites 
                SET FolioRCHRP = ?, 
                    FechaRCHRP = ?,
                    FechaUltimaActualizacion = GETDATE() 
                WHERE ID_Tramite = ?";

$paramsActualizar = array($folioRCHRP, $fechaFormateada, $idTramite);
$resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);

if ($resultadoActualizar['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el folio: ' . $resultadoActualizar['message']]);
    exit;
}

cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);

// CORRECIÓN: Verificar si ya existe un registro en el historial para evitar duplicados
$sqlVerificarHistorial = "SELECT COUNT(*) AS total FROM HistorialCambios 
                         WHERE ID_Tramite = ? 
                         AND Observacion LIKE ? 
                         AND TipoAccion = 'Registro de Folio RCHRP'
                         AND CONVERT(DATE, FechaCambio) = CONVERT(DATE, GETDATE())";

$observacionPattern = "Registro de Folio RCHRP: " . $folioRCHRP . "%";
$paramsVerificarHistorial = array($idTramite, $observacionPattern);
$resultadoVerificarHistorial = ejecutarConsulta($sqlVerificarHistorial, $paramsVerificarHistorial);

if ($resultadoVerificarHistorial['status'] === 'success') {
    $rowHistorial = sqlsrv_fetch_array($resultadoVerificarHistorial['stmt'], SQLSRV_FETCH_ASSOC);
    $existeRegistro = ($rowHistorial['total'] > 0);
    cerrarConexion($resultadoVerificarHistorial['conn'], $resultadoVerificarHistorial['stmt']);
} else {
    // Si hay error al verificar, asumimos que no existe (para evitar perder el registro)
    $existeRegistro = false;
}

// Solo registrar en historial si no existe un registro similar hoy
if (!$existeRegistro) {
    // Registrar en historial de cambios
    $sqlHistorial = "INSERT INTO HistorialCambios (
                    ID_Tramite, EstadoAnterior, EstadoNuevo, 
                    Observacion, UsuarioResponsable, TipoAccion
                    ) VALUES (?, NULL, NULL, ?, ?, ?)";
    
    $observacionHistorial = $estaActualizando 
        ? "Actualización de Folio RCHRP: {$folioRCHRP}, Fecha: " . date('d/m/Y', strtotime($fechaRCHRP))
        : "Registro de Folio RCHRP: {$folioRCHRP}, Fecha: " . date('d/m/Y', strtotime($fechaRCHRP));
    
    $paramsHistorial = array(
        $idTramite, 
        $observacionHistorial, 
        'Sistema',
        'Registro de Folio RCHRP'
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
    'message' => "El Folio RCHRP ha sido {$accion} correctamente",
    'data' => [
        'id_tramite' => $idTramite,
        'folio_rchrp' => $folioRCHRP,
        'fecha_rchrp' => date('Y-m-d', strtotime($fechaRCHRP))
    ]
]);
exit;
?>