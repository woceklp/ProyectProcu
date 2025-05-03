<?php
/**
 * api/registrar_subsanacion.php - API para registrar una subsanación de trámite
 */

// Incluir archivo de configuración
require_once '../config.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaJSON('error', 'Método no permitido');
}

// Obtener y sanitizar datos
$idTramite = isset($_POST['id_tramite']) ? intval($_POST['id_tramite']) : 0;
$folioSubsanacion = isset($_POST['folio_subsanacion']) ? sanitizarEntrada($_POST['folio_subsanacion']) : '';
$fechaSubsanacion = isset($_POST['fecha_subsanacion']) ? sanitizarEntrada($_POST['fecha_subsanacion']) : '';
$descripcion = isset($_POST['descripcion']) ? sanitizarEntrada($_POST['descripcion']) : '';

// Validar datos requeridos
$errores = [];

if ($idTramite <= 0) {
    $errores[] = "ID de trámite no válido";
}

if (empty($folioSubsanacion)) {
    $errores[] = "El folio de subsanación es requerido";
}

if (empty($fechaSubsanacion)) {
    $errores[] = "La fecha de subsanación es requerida";
}

if (empty($descripcion)) {
    $errores[] = "La descripción de la subsanación es requerida";
}

// Verificar que el trámite exista
if (empty($errores)) {
    $sqlVerificar = "SELECT COUNT(*) AS total FROM Tramites WHERE ID_Tramite = ?";
    $resultadoVerificar = ejecutarConsulta($sqlVerificar, array($idTramite));
    
    if ($resultadoVerificar['status'] === 'success') {
        $row = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
        cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);
        
        if ($row['total'] === 0) {
            $errores[] = "El trámite especificado no existe";
        }
    } else {
        $errores[] = "Error al verificar el trámite: " . $resultadoVerificar['message'];
    }
}

// Consultar el estado actual antes de actualizar
if (empty($errores)) {
    $sqlEstadoActual = "SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?";
    $resultadoEstadoActual = ejecutarConsulta($sqlEstadoActual, array($idTramite));
    
    if ($resultadoEstadoActual['status'] === 'success') {
        $rowEstadoActual = sqlsrv_fetch_array($resultadoEstadoActual['stmt'], SQLSRV_FETCH_ASSOC);
        $estadoAnterior = $rowEstadoActual['ID_EstadoTramite'];
        cerrarConexion($resultadoEstadoActual['conn'], $resultadoEstadoActual['stmt']);
    } else {
        $errores[] = "Error al consultar el estado actual del trámite: " . $resultadoEstadoActual['message'];
    }
}

// Si no hay errores, registrar la subsanación
if (empty($errores)) {
    // Convertir fecha a formato SQL Server (Y-m-d)
    $fechaFormateada = date('Y-m-d', strtotime($fechaSubsanacion));
    
    // Consulta SQL para insertar nueva subsanación
    $sqlInsertar = "INSERT INTO Subsanaciones (
                   ID_Tramite, FolioSubsanacion, FechaSubsanacion, 
                   Descripcion, FechaRegistro
                   ) VALUES (?, ?, ?, ?, GETDATE())";
    
    $paramsInsertar = array(
        $idTramite,
        $folioSubsanacion,
        $fechaFormateada,
        $descripcion
    );
    
    $resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);
    
    if ($resultadoInsertar['status'] !== 'success') {
        respuestaJSON('error', "Error al registrar la subsanación: " . $resultadoInsertar['message']);
    }
    
    cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
    
    // ID de estado "Pendiente de Subsanación"
    $estadoSubsanacion = 7;
    
    // Actualizar la fecha de última actualización y estado del trámite
    $sqlActualizar = "UPDATE Tramites 
                    SET FechaUltimaActualizacion = GETDATE(),
                        ID_EstadoTramite = ?
                    WHERE ID_Tramite = ?";
    
    $paramsActualizar = array($estadoSubsanacion, $idTramite);
    $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
    
    if ($resultadoActualizar['status'] !== 'success') {
        respuestaJSON('error', "Error al actualizar el estado del trámite: " . $resultadoActualizar['message']);
    }
    
    cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
    
    // Registrar en historial de cambios
    $sqlHistorial = "INSERT INTO HistorialCambios (
                    ID_Tramite, EstadoAnterior, EstadoNuevo, 
                    Observacion, UsuarioResponsable
                    ) VALUES (?, ?, ?, ?, 'Sistema')";
    
    $observacionHistorial = "Registro de subsanación: {$folioSubsanacion}";
    $paramsHistorial = array($idTramite, $estadoAnterior, $estadoSubsanacion, $observacionHistorial);
    $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
    
    if ($resultadoHistorial['status'] === 'success') {
        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
    }
    
    // Obtener el ID de la subsanación recién insertada
    $sqlID = "SELECT MAX(ID_Subsanacion) AS id FROM Subsanaciones WHERE ID_Tramite = ?";
    $resultadoID = ejecutarConsulta($sqlID, array($idTramite));
    
    if ($resultadoID['status'] === 'success') {
        $rowID = sqlsrv_fetch_array($resultadoID['stmt'], SQLSRV_FETCH_ASSOC);
        $idSubsanacion = $rowID['id'];
        cerrarConexion($resultadoID['conn'], $resultadoID['stmt']);
    } else {
        $idSubsanacion = 0;
    }
    
    // Consultar información de la subsanación para devolverla en la respuesta
    $sqlInfo = "SELECT s.ID_Subsanacion, s.FolioSubsanacion, s.FechaSubsanacion, 
               s.Descripcion, s.FechaRegistro, t.ID_Tramite, t.CIIA,
               e.Nombre AS EstadoTramite
               FROM Subsanaciones s
               INNER JOIN Tramites t ON s.ID_Tramite = t.ID_Tramite
               INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
               WHERE s.ID_Subsanacion = ?";
    
    $resultadoInfo = ejecutarConsulta($sqlInfo, array($idSubsanacion));
    
    if ($resultadoInfo['status'] === 'success') {
        $info = sqlsrv_fetch_array($resultadoInfo['stmt'], SQLSRV_FETCH_ASSOC);
        cerrarConexion($resultadoInfo['conn'], $resultadoInfo['stmt']);
        
        // Formatear fechas para la respuesta
        if (isset($info['FechaSubsanacion']) && $info['FechaSubsanacion'] instanceof DateTime) {
            $info['FechaSubsanacion'] = $info['FechaSubsanacion']->format('Y-m-d');
        }
        
        if (isset($info['FechaRegistro']) && $info['FechaRegistro'] instanceof DateTime) {
            $info['FechaRegistro'] = $info['FechaRegistro']->format('Y-m-d H:i:s');
        }
    } else {
        $info = array(
            'ID_Subsanacion' => $idSubsanacion,
            'FolioSubsanacion' => $folioSubsanacion,
            'FechaSubsanacion' => $fechaSubsanacion,
            'Descripcion' => $descripcion,
            'ID_Tramite' => $idTramite
        );
    }
    
    // Devolver respuesta exitosa
    respuestaJSON('success', "La subsanación ha sido registrada correctamente y el estado del trámite se ha actualizado", $info);
} else {
    // Devolver errores
    respuestaJSON('error', implode(". ", $errores));
}