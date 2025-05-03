<?php
/**
 * api/registrar_reiteracion.php - API para registrar una reiteración de trámite
 */

// Incluir archivo de configuración
require_once '../config.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaJSON('error', 'Método no permitido');
}

// Obtener y sanitizar datos
$idTramite = isset($_POST['id_tramite']) ? intval($_POST['id_tramite']) : 0;
$folioReiteracion = isset($_POST['folio_reiteracion']) ? sanitizarEntrada($_POST['folio_reiteracion']) : '';
$fechaReiteracion = isset($_POST['fecha_reiteracion']) ? sanitizarEntrada($_POST['fecha_reiteracion']) : '';
$observaciones = isset($_POST['observaciones']) ? sanitizarEntrada($_POST['observaciones']) : '';

// Validar datos requeridos
$errores = [];

if ($idTramite <= 0) {
    $errores[] = "ID de trámite no válido";
}

if (empty($folioReiteracion)) {
    $errores[] = "El folio de reiteración es requerido";
}

if (empty($fechaReiteracion)) {
    $errores[] = "La fecha de reiteración es requerida";
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

// Verificar que no se haya superado el límite de 3 reiteraciones
if (empty($errores)) {
    $sqlContar = "SELECT COUNT(*) AS total FROM Reiteraciones WHERE ID_Tramite = ?";
    $resultadoContar = ejecutarConsulta($sqlContar, array($idTramite));
    
    if ($resultadoContar['status'] === 'success') {
        $row = sqlsrv_fetch_array($resultadoContar['stmt'], SQLSRV_FETCH_ASSOC);
        cerrarConexion($resultadoContar['conn'], $resultadoContar['stmt']);
        
        if ($row['total'] >= 3) {
            $errores[] = "Este trámite ya tiene el máximo de 3 reiteraciones permitidas";
        } else {
            // Determinar el número de reiteración
            $numeroReiteracion = $row['total'] + 1;
        }
    } else {
        $errores[] = "Error al verificar las reiteraciones existentes: " . $resultadoContar['message'];
    }
}

// Si no hay errores, registrar la reiteración
if (empty($errores)) {
    // Convertir fecha a formato SQL Server (Y-m-d)
    $fechaFormateada = date('Y-m-d', strtotime($fechaReiteracion));
    
    // Consulta SQL para insertar nueva reiteración
    $sqlInsertar = "INSERT INTO Reiteraciones (
                   ID_Tramite, FolioReiteracion, FechaReiteracion, 
                   NumeroReiteracion, Observaciones, FechaRegistro
                   ) VALUES (?, ?, ?, ?, ?, GETDATE())";
    
    $paramsInsertar = array(
        $idTramite,
        $folioReiteracion,
        $fechaFormateada,
        $numeroReiteracion,
        $observaciones ?: null
    );
    
    $resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);
    
    if ($resultadoInsertar['status'] !== 'success') {
        respuestaJSON('error', "Error al registrar la reiteración: " . $resultadoInsertar['message']);
    }
    
    cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
    
    // Actualizar la fecha de última actualización del trámite
    $sqlActualizar = "UPDATE Tramites 
                    SET FechaUltimaActualizacion = GETDATE(),
                        ID_EstadoTramite = CASE 
                            WHEN ID_EstadoTramite = 6 THEN 2 -- Si estaba pendiente de reiteración, ponerlo en proceso (25%)
                            ELSE ID_EstadoTramite 
                        END
                    WHERE ID_Tramite = ?";
    
    $paramsActualizar = array($idTramite);
    $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
    
    if ($resultadoActualizar['status'] !== 'success') {
        respuestaJSON('error', "Error al actualizar el trámite: " . $resultadoActualizar['message']);
    }
    
    cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
    
    // Registrar en historial de cambios
    $sqlHistorial = "INSERT INTO HistorialCambios (
                    ID_Tramite, EstadoAnterior, EstadoNuevo, 
                    Observacion, UsuarioResponsable
                    ) VALUES (?, 
                        (SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?), 
                        (SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?), 
                        ?, 
                        'Sistema')";
    
    $observacionHistorial = "Registro de reiteración #{$numeroReiteracion}: {$folioReiteracion}";
    $paramsHistorial = array($idTramite, $idTramite, $idTramite, $observacionHistorial);
    $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
    
    if ($resultadoHistorial['status'] === 'success') {
        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
    }
    
    // Consultar información actualizada para devolverla en la respuesta
    $sqlInfo = "SELECT t.ID_Tramite, r.ID_Reiteracion, r.FolioReiteracion, r.NumeroReiteracion, 
               r.FechaReiteracion, r.FechaRegistro
               FROM Reiteraciones r
               INNER JOIN Tramites t ON r.ID_Tramite = t.ID_Tramite
               WHERE r.ID_Tramite = ? AND r.NumeroReiteracion = ?";
    
    $resultadoInfo = ejecutarConsulta($sqlInfo, array($idTramite, $numeroReiteracion));
    
    if ($resultadoInfo['status'] !== 'success') {
        respuestaJSON('error', "Error al consultar información de la reiteración: " . $resultadoInfo['message']);
    }
    
    $info = sqlsrv_fetch_array($resultadoInfo['stmt'], SQLSRV_FETCH_ASSOC);
    cerrarConexion($resultadoInfo['conn'], $resultadoInfo['stmt']);
    
    // Formatear fechas para la respuesta
    if (isset($info['FechaReiteracion']) && $info['FechaReiteracion'] instanceof DateTime) {
        $info['FechaReiteracion'] = $info['FechaReiteracion']->format('Y-m-d');
    }
    
    if (isset($info['FechaRegistro']) && $info['FechaRegistro'] instanceof DateTime) {
        $info['FechaRegistro'] = $info['FechaRegistro']->format('Y-m-d H:i:s');
    }
    
    // Agregar información adicional
    $info['observaciones'] = $observaciones;
    
    // Devolver respuesta exitosa
    respuestaJSON('success', "La reiteración #{$numeroReiteracion} ha sido registrada correctamente", $info);
} else {
    // Devolver errores
    respuestaJSON('error', implode(". ", $errores));
}