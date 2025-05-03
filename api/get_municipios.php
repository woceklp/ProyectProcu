<?php
require_once '../config.php';

header('Content-Type: application/json');

$sql = "SELECT ID_Municipio, Nombre FROM Municipios WHERE Activo = 1 ORDER BY Nombre";
$resultado = ejecutarConsulta($sql);

if ($resultado['status'] === 'success') {
    $municipios = obtenerResultados($resultado['stmt']);
    cerrarConexion($resultado['conn'], $resultado['stmt']);
    echo json_encode(['status' => 'success', 'message' => 'Municipios obtenidos', 'data' => $municipios]);
} else {
    echo json_encode(['status' => 'error', 'message' => $resultado['message']]);
}
?>