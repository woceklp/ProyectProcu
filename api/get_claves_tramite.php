<?php
/**
 * api/get_claves_tramite.php - API para obtener claves de trámite según el tipo
 */

// Incluir archivo de configuración
require_once '../config.php';

// Si la función sanitizarEntrada no existe, defínela aquí
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

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener el ID del tipo de trámite
$idTipoTramite = isset($_GET['id_tipo_tramite']) ? intval($_GET['id_tipo_tramite']) : 0;

// Validar que se haya proporcionado un tipo de trámite válido
if ($idTipoTramite <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe proporcionar un tipo de trámite válido']);
    exit;
}

// Consulta SQL para obtener las claves de trámite
$sql = "SELECT ID_ClaveTramite as id_clave_tramite, 
               Clave as clave, 
               Descripcion as descripcion 
        FROM ClavesTramite 
        WHERE ID_TipoTramite = ? AND Activo = 1
        ORDER BY Clave";

$params = array($idTipoTramite);
$resultado = ejecutarConsulta($sql, $params);

if ($resultado['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultado['message']]);
    exit;
}

// Obtener resultados
$clavesTramite = obtenerResultados($resultado['stmt']);

// Cerrar conexión
cerrarConexion($resultado['conn'], $resultado['stmt']);

// Devolver respuesta
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Claves de trámite obtenidas correctamente', 'data' => $clavesTramite]);
exit;