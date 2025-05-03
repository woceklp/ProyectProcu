<?php
/**
 * api/guardar_tramite.php - API para guardar un nuevo trámite
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

// Si la función validarCIIA no existe, defínela aquí
if (!function_exists('validarCIIA')) {
    function validarCIIA($ciia) {
        return (strlen($ciia) === 13 && ctype_digit($ciia));
    }
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener y sanitizar datos
$idPromovente = isset($_POST['id_promovente']) ? intval($_POST['id_promovente']) : 0;
$fecha = isset($_POST['fecha']) ? sanitizarEntrada($_POST['fecha']) : '';
$ciia = isset($_POST['ciia']) ? sanitizarEntrada($_POST['ciia']) : '';
$tipoTramite = isset($_POST['tipo_tramite']) ? intval($_POST['tipo_tramite']) : 0;
$claveTramite = isset($_POST['clave_tramite']) ? intval($_POST['clave_tramite']) : 0;
$municipio = isset($_POST['municipio']) ? intval($_POST['municipio']) : 0;
$tipoNucleoAgrario = isset($_POST['tipo_nucleo_agrario']) ? sanitizarEntrada($_POST['tipo_nucleo_agrario']) : '';
$nucleoAgrario = isset($_POST['nucleo_agrario']) ? intval($_POST['nucleo_agrario']) : 0;
$descripcion = isset($_POST['descripcion']) ? sanitizarEntrada($_POST['descripcion']) : '';

// Validar datos requeridos
if ($idPromovente <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No se ha seleccionado un promovente válido']);
    exit;
}

if (empty($fecha)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'La fecha del trámite es requerida']);
    exit;
}

if (empty($ciia) || !validarCIIA($ciia)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'El CIIA debe tener exactamente 13 dígitos numéricos']);
    exit;
}

if ($tipoTramite <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un tipo de trámite']);
    exit;
}

if ($claveTramite <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar una clave de trámite']);
    exit;
}

if ($municipio <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un municipio']);
    exit;
}

if (empty($tipoNucleoAgrario)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un tipo de núcleo agrario']);
    exit;
}

if ($nucleoAgrario <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un núcleo agrario']);
    exit;
}

if (empty($descripcion)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'La descripción del trámite es requerida']);
    exit;
}

// Verificar si ya existe un trámite con el mismo CIIA
$sqlVerificar = "SELECT COUNT(*) AS total FROM Tramites WHERE CIIA = ?";
$paramsVerificar = array($ciia);
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
    echo json_encode(['status' => 'error', 'message' => 'Ya existe un trámite registrado con el CIIA proporcionado']);
    exit;
}

// Convertir fecha a formato SQL Server (Y-m-d)
$fechaFormateada = date('Y-m-d', strtotime($fecha));

// Estado inicial del trámite (ID 1 - Iniciado)
$estadoInicial = 1;

// Estado básico inicial (ID 3 - EN PROCESO)
$estadoBasicoInicial = 3;

// Consulta SQL para insertar nuevo trámite
$sqlInsertar = "INSERT INTO Tramites (
                ID_Promovente, CIIA, FechaRegistro, ID_TipoTramite, 
                ID_ClaveTramite, ID_Municipio, ID_NucleoAgrario, 
                Descripcion, ID_EstadoTramite, ID_EstadoBasico, FechaUltimaActualizacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE());
                SELECT SCOPE_IDENTITY() AS id_tramite;";

$paramsInsertar = array(
    $idPromovente, 
    $ciia, 
    $fechaFormateada, 
    $tipoTramite, 
    $claveTramite, 
    $municipio, 
    $nucleoAgrario, 
    $descripcion, 
    $estadoInicial,
    $estadoBasicoInicial
);

$resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);

if ($resultadoInsertar['status'] === 'error') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $resultadoInsertar['message']]);
    exit;
}

// Obtener el ID del trámite recién insertado
if (sqlsrv_next_result($resultadoInsertar['stmt']) && sqlsrv_fetch($resultadoInsertar['stmt'])) {
    $idTramite = sqlsrv_get_field($resultadoInsertar['stmt'], 0);
} else {
    cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No se pudo obtener el ID del trámite']);
    exit;
}

cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);

// Registrar en historial de cambios
$sqlHistorial = "INSERT INTO HistorialCambios (
                ID_Tramite, EstadoAnterior, EstadoNuevo, Observacion, UsuarioResponsable
                ) VALUES (?, NULL, ?, 'Registro inicial del trámite', 'Sistema')";

$paramsHistorial = array($idTramite, $estadoInicial);
$resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);

if ($resultadoHistorial['status'] === 'success') {
    cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
}

// Datos a devolver
$datos = array(
    'id_tramite' => $idTramite,
    'ciia' => $ciia,
    'fecha_registro' => $fecha
);

// Devolver respuesta
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Trámite registrado correctamente', 'data' => $datos]);
exit;