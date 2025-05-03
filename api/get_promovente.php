<?php
/**
 * api/get_promovente.php - API para obtener los datos de un promovente
 */

// Incluir archivo de configuración
require_once '../config.php';

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener el ID del promovente
$idPromovente = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validar que se haya proporcionado un ID válido
if ($idPromovente <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID de promovente no válido']);
    exit;
}

// Consulta SQL para obtener los datos del promovente
$sql = "SELECT ID_Promovente, Nombre, ApellidoPaterno, ApellidoMaterno, 
        Telefono, Telefono2, Direccion
        FROM Promoventes 
        WHERE ID_Promovente = ? AND Activo = 1";

$params = array($idPromovente);
$resultado = ejecutarConsulta($sql, $params);

if ($resultado['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultado['message']]);
    exit;
}

// Obtener resultado
$promovente = sqlsrv_fetch_array($resultado['stmt'], SQLSRV_FETCH_ASSOC);

// Cerrar conexión
cerrarConexion($resultado['conn'], $resultado['stmt']);

if (!$promovente) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Promovente no encontrado']);
    exit;
}

// Devolver respuesta
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Datos obtenidos correctamente', 'data' => $promovente]);
exit;