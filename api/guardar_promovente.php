<?php
/**
 * api/guardar_promovente.php - API para guardar un nuevo promovente
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
$telefono = isset($_POST['telefono']) ? sanitizarEntrada($_POST['telefono']) : null;
$telefono2 = isset($_POST['telefono2']) ? sanitizarEntrada($_POST['telefono2']) : null;
$direccion = isset($_POST['direccion']) ? sanitizarEntrada($_POST['direccion']) : null;

// Validar datos requeridos
if (empty($nombre)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'El campo nombre es obligatorio']);
    exit;
}

// Si un apellido está vacío, asignar un valor predeterminado para la base de datos
if (empty($apellidoPaterno)) {
    $apellidoPaterno = "N/A";
}

if (empty($apellidoMaterno)) {
    $apellidoMaterno = "N/A";
}

// Verificar si ya existe un promovente con esos datos exactos
$sqlVerificar = "SELECT COUNT(*) AS total FROM Promoventes 
                WHERE Nombre = ? 
                AND ApellidoPaterno = ? 
                AND ApellidoMaterno = ?
                AND Activo = 1";

$paramsVerificar = array($nombre, $apellidoPaterno, $apellidoMaterno);
$resultadoVerificar = ejecutarConsulta($sqlVerificar, $paramsVerificar);

if ($resultadoVerificar['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultadoVerificar['message']]);
    exit;
}

$row = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);

if ($row['total'] > 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Ya existe un promovente con esos datos exactos']);
    exit;
}

// Consulta SQL para insertar nuevo promovente
$sqlInsertar = "INSERT INTO Promoventes (Nombre, ApellidoPaterno, ApellidoMaterno, Telefono, Telefono2, Direccion)
               VALUES (?, ?, ?, ?, ?, ?);
               SELECT SCOPE_IDENTITY() AS id_promovente;";

$paramsInsertar = array($nombre, $apellidoPaterno, $apellidoMaterno, $telefono, $telefono2, $direccion);
$resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);

if ($resultadoInsertar['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultadoInsertar['message']]);
    exit;
}

// Obtener el ID del promovente recién insertado
if (sqlsrv_next_result($resultadoInsertar['stmt']) && sqlsrv_fetch($resultadoInsertar['stmt'])) {
    $idPromovente = sqlsrv_get_field($resultadoInsertar['stmt'], 0);
} else {
    cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No se pudo obtener el ID del promovente']);
    exit;
}

cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);

// Datos a devolver
$datos = array(
    'id_promovente' => $idPromovente,
    'nombre' => $nombre,
    'apellido_paterno' => $apellidoPaterno,
    'apellido_materno' => $apellidoMaterno,
    'telefono' => $telefono,
    'telefono2' => $telefono2
);

// Devolver respuesta
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Promovente registrado correctamente', 'data' => $datos]);
exit;