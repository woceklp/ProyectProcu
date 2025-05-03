<?php
/**
 * api/get_nucleos_agrarios.php - API para obtener núcleos agrarios según municipio y tipo
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

// Obtener parámetros
$idMunicipio = isset($_GET['id_municipio']) ? intval($_GET['id_municipio']) : 0;
$tipoNucleoAgrario = isset($_GET['tipo_nucleo_agrario']) ? sanitizarEntrada($_GET['tipo_nucleo_agrario']) : '';

// Validar parámetros
if ($idMunicipio <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe proporcionar un municipio válido']);
    exit;
}

if (empty($tipoNucleoAgrario) || !in_array($tipoNucleoAgrario, ['E', 'C'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe proporcionar un tipo de núcleo agrario válido (E o C)']);
    exit;
}

// Consulta SQL para obtener los núcleos agrarios
$sql = "SELECT ID_NucleoAgrario as id_nucleo_agrario, 
               Nombre as nombre
        FROM NucleosAgrarios 
        WHERE ID_Municipio = ? 
        AND ID_TipoNucleoAgrario = ? 
        AND Activo = 1
        ORDER BY Nombre";

$params = array($idMunicipio, $tipoNucleoAgrario);
$resultado = ejecutarConsulta($sql, $params);

if ($resultado['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultado['message']]);
    exit;
}

// Obtener resultados
$nucleosAgrarios = obtenerResultados($resultado['stmt']);

// Cerrar conexión
cerrarConexion($resultado['conn'], $resultado['stmt']);

// Devolver respuesta
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Núcleos agrarios obtenidos correctamente', 'data' => $nucleosAgrarios]);
exit;