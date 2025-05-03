<?php
/**
 * api/buscar_promovente.php - API para buscar promoventes por nombre y apellidos
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

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener y sanitizar datos
$nombre = isset($_POST['nombre']) ? sanitizarEntrada($_POST['nombre']) : '';
$apellidoPaterno = isset($_POST['apellido_paterno']) ? sanitizarEntrada($_POST['apellido_paterno']) : '';
$apellidoMaterno = isset($_POST['apellido_materno']) ? sanitizarEntrada($_POST['apellido_materno']) : '';

// Validar datos requeridos
if (empty($nombre) && empty($apellidoPaterno) && empty($apellidoMaterno)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe proporcionar al menos un dato para la búsqueda']);
    exit;
}

// Y también modificar la consulta SQL:
$sql = "SELECT ID_Promovente as id_promovente, 
        Nombre as nombre, 
        ApellidoPaterno as apellido_paterno, 
        ApellidoMaterno as apellido_materno, 
        Telefono as telefono,
        Telefono2 as telefono2,
        Direccion as direccion
    FROM Promoventes 
    WHERE (Nombre LIKE ? OR ? = '')
    AND (ApellidoPaterno LIKE ? OR ? = '' OR ApellidoPaterno = 'N/A')
    AND (ApellidoMaterno LIKE ? OR ? = '' OR ApellidoMaterno = 'N/A')
    AND Activo = 1";

// Preparar parámetros con comodines para búsqueda parcial
$params = array(
    '%' . $nombre . '%', $nombre,
    '%' . $apellidoPaterno . '%', $apellidoPaterno,
    '%' . $apellidoMaterno . '%', $apellidoMaterno
);

// Ejecutar consulta
$resultado = ejecutarConsulta($sql, $params);

if ($resultado['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error en la búsqueda: ' . $resultado['message']]);
    exit;
}

// Obtener resultados
$promoventes = obtenerResultados($resultado['stmt']);
cerrarConexion($resultado['conn'], $resultado['stmt']);

// Devolver respuesta exitosa
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Búsqueda completada', 'data' => $promoventes]);
exit;