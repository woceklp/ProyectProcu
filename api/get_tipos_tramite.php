<?php
require_once '../config.php';

header('Content-Type: application/json');

$sql = "SELECT ID_TipoTramite, Nombre FROM TiposTramite WHERE Activo = 1 ORDER BY Nombre";
$resultado = ejecutarConsulta($sql);

if ($resultado['status'] === 'success') {
    $tipos = obtenerResultados($resultado['stmt']);
    cerrarConexion($resultado['conn'], $resultado['stmt']);
    echo json_encode(['status' => 'success', 'message' => 'Tipos de trámite obtenidos', 'data' => $tipos]);
} else {
    echo json_encode(['status' => 'error', 'message' => $resultado['message']]);
}
?>