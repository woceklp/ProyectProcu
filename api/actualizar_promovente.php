<?php
/**
 * api/actualizar_promovente.php - API para actualizar los datos de un promovente
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
$idPromovente = isset($_POST['id_promovente']) ? intval($_POST['id_promovente']) : 0;
$nombre = isset($_POST['nombre']) ? sanitizarEntrada($_POST['nombre']) : '';
$apellidoPaterno = isset($_POST['apellido_paterno']) ? sanitizarEntrada($_POST['apellido_paterno']) : '';
$apellidoMaterno = isset($_POST['apellido_materno']) ? sanitizarEntrada($_POST['apellido_materno']) : '';
$telefono = isset($_POST['telefono']) ? sanitizarEntrada($_POST['telefono']) : null;
$telefono2 = isset($_POST['telefono2']) ? sanitizarEntrada($_POST['telefono2']) : null;
$direccion = isset($_POST['direccion']) ? sanitizarEntrada($_POST['direccion']) : null;

// Validar datos requeridos
if ($idPromovente <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID de promovente no válido']);
    exit;
}

if (empty($nombre)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'El campo nombre es obligatorio']);
    exit;
}

// Si un apellido está vacío, asignar un valor predeterminado
if (empty($apellidoPaterno)) {
    $apellidoPaterno = "N/A";
}

if (empty($apellidoMaterno)) {
    $apellidoMaterno = "N/A";
}

// Actualizar el promovente
$sql = "UPDATE Promoventes 
        SET Nombre = ?, 
            ApellidoPaterno = ?, 
            ApellidoMaterno = ?, 
            Telefono = ?, 
            Telefono2 = ?, 
            Direccion = ? 
        WHERE ID_Promovente = ?";

$params = array($nombre, $apellidoPaterno, $apellidoMaterno, $telefono, $telefono2, $direccion, $idPromovente);
$resultado = ejecutarConsulta($sql, $params);

if ($resultado['status'] !== 'success') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultado['message']]);
    exit;
}

cerrarConexion($resultado['conn'], $resultado['stmt']);

// Datos actualizados a devolver
$datos = array(
    'id_promovente' => $idPromovente,
    'nombre' => $nombre,
    'apellido_paterno' => $apellidoPaterno,
    'apellido_materno' => $apellidoMaterno,
    'telefono' => $telefono,
    'telefono2' => $telefono2,
    'direccion' => $direccion
);

// Devolver respuesta exitosa
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Promovente actualizado correctamente', 'data' => $datos]);
exit;