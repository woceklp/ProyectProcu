<?php
/**
 * api/actualizar_tramite.php - API para actualizar los datos de un trámite existente
 */

// Incluir archivo de configuración
require_once '../config.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaJSON('error', 'Método no permitido');
}

// Obtener y sanitizar datos
$idTramite = isset($_POST['id_tramite']) ? intval($_POST['id_tramite']) : 0;
$folioRCHRP = isset($_POST['folio_rchrp']) ? sanitizarEntrada($_POST['folio_rchrp']) : '';
$fechaRCHRP = isset($_POST['fecha_rchrp']) ? sanitizarEntrada($_POST['fecha_rchrp']) : '';
$tipoTramite = isset($_POST['tipo_tramite']) ? intval($_POST['tipo_tramite']) : 0;
$claveTramite = isset($_POST['clave_tramite']) ? intval($_POST['clave_tramite']) : 0;
$descripcion = isset($_POST['descripcion']) ? sanitizarEntrada($_POST['descripcion']) : '';
$estadoTramite = isset($_POST['estado_tramite']) ? intval($_POST['estado_tramite']) : 0;

// Validar datos requeridos
$errores = [];

if ($idTramite <= 0) {
    $errores[] = "ID de trámite no válido";
}

if ($tipoTramite <= 0) {
    $errores[] = "Debe seleccionar un tipo de trámite";
}

if ($claveTramite <= 0) {
    $errores[] = "Debe seleccionar una clave de trámite";
}

if (empty($descripcion)) {
    $errores[] = "La descripción del trámite es requerida";
}

if ($estadoTramite <= 0) {
    $errores[] = "Debe seleccionar un estado para el trámite";
}

// Si hay una fecha RCHRP pero no un folio, o viceversa, es un error
if ((!empty($fechaRCHRP) && empty($folioRCHRP)) || (empty($fechaRCHRP) && !empty($folioRCHRP))) {
    $errores[] = "Si proporciona un Folio RCHRP debe proporcionar también su fecha, y viceversa";
}

// Verificar que el trámite exista y obtener datos actuales
if (empty($errores)) {
    $sqlVerificar = "SELECT t.ID_Tramite, t.CIIA, t.FolioRCHRP, t.FechaRCHRP, 
                    t.ID_TipoTramite, t.ID_ClaveTramite, t.Descripcion, t.ID_EstadoTramite
                    FROM Tramites t 
                    WHERE t.ID_Tramite = ?";
    
    $resultadoVerificar = ejecutarConsulta($sqlVerificar, array($idTramite));
    
    if ($resultadoVerificar['status'] === 'success') {
        $tramiteOriginal = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
        cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);
        
        if (!$tramiteOriginal) {
            $errores[] = "El trámite especificado no existe";
        }
    } else {
        $errores[] = "Error al verificar el trámite: " . $resultadoVerificar['message'];
    }
}

// Si no hay errores, continuar con la actualización
if (empty($errores)) {
    // Guardar el estado anterior para el historial
    $estadoAnterior = $tramiteOriginal['ID_EstadoTramite'];
    
    // Detectar cambios en los campos para el historial
    $cambios = [];
    if ($tipoTramite != $tramiteOriginal['ID_TipoTramite']) {
        $cambios[] = "Tipo de trámite";
    }
    if ($claveTramite != $tramiteOriginal['ID_ClaveTramite']) {
        $cambios[] = "Clave de trámite";
    }
    if ($descripcion != $tramiteOriginal['Descripcion']) {
        $cambios[] = "Descripción";
    }
    
    // Cambios en el Folio RCHRP y su fecha
    $cambioFolioRCHRP = false;
    if (!empty($folioRCHRP)) {
        if ($folioRCHRP != $tramiteOriginal['FolioRCHRP']) {
            $cambios[] = "Folio RCHRP";
            $cambioFolioRCHRP = true;
        }
        
        // Preparar fecha para comparación
        $fechaRCHRPFormateada = date('Y-m-d', strtotime($fechaRCHRP));
        $fechaOriginalStr = $tramiteOriginal['FechaRCHRP'] ? $tramiteOriginal['FechaRCHRP']->format('Y-m-d') : '';
        
        if ($fechaRCHRPFormateada != $fechaOriginalStr) {
            $cambios[] = "Fecha RCHRP";
            $cambioFolioRCHRP = true;
        }
    }
    
    // Preparar parámetros para la actualización
    $paramsActualizar = array();
    
    // Construir la consulta SQL dinámica dependiendo de los campos proporcionados
    $sqlActualizar = "UPDATE Tramites SET 
                     ID_TipoTramite = ?, 
                     ID_ClaveTramite = ?, 
                     Descripcion = ?, 
                     ID_EstadoTramite = ?,
                     FechaUltimaActualizacion = GETDATE()";
    
    $paramsActualizar[] = $tipoTramite;
    $paramsActualizar[] = $claveTramite;
    $paramsActualizar[] = $descripcion;
    $paramsActualizar[] = $estadoTramite;
    
    // Añadir FolioRCHRP y FechaRCHRP si se proporcionaron
    if (!empty($folioRCHRP) && !empty($fechaRCHRP)) {
        $sqlActualizar .= ", FolioRCHRP = ?, FechaRCHRP = ?";
        $paramsActualizar[] = $folioRCHRP;
        $paramsActualizar[] = date('Y-m-d', strtotime($fechaRCHRP)); // Convertir a formato SQL Server
    }
    
    // Finalizar la consulta con la condición WHERE
    $sqlActualizar .= " WHERE ID_Tramite = ?";
    $paramsActualizar[] = $idTramite;
    
    // Ejecutar la actualización
    $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
    
    if ($resultadoActualizar['status'] !== 'success') {
        respuestaJSON('error', "Error al actualizar el trámite: " . $resultadoActualizar['message']);
    }
    
    cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
    
    // Registrar en historial de cambios
    // 1. Si cambió el estado, registrar como "Cambio de Estado"
    if ($estadoAnterior != $estadoTramite) {
        $sqlHistorial = "INSERT INTO HistorialCambios (
                        ID_Tramite, EstadoAnterior, EstadoNuevo, 
                        Observacion, UsuarioResponsable, TipoAccion
                        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $observacionHistorial = "Cambio de estado del trámite";
        if (!empty($cambios)) {
            $observacionHistorial .= " y actualización de: " . implode(", ", $cambios);
        }
        
        $paramsHistorial = array(
            $idTramite, 
            $estadoAnterior, 
            $estadoTramite, 
            $observacionHistorial, 
            'Sistema', 
            'Cambio de Estado'
        );
        
        $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
        
        if ($resultadoHistorial['status'] === 'success') {
            cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
        }
    }
    // 2. Si no cambió el estado pero hay otros cambios, registrar como "Actualización de Datos"
    else if (!empty($cambios)) {
        $sqlHistorial = "INSERT INTO HistorialCambios (
                        ID_Tramite, EstadoAnterior, EstadoNuevo, 
                        Observacion, UsuarioResponsable, TipoAccion
                        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $observacionHistorial = "Actualización de: " . implode(", ", $cambios);
        
        $paramsHistorial = array(
            $idTramite, 
            null, 
            null, 
            $observacionHistorial, 
            'Sistema', 
            'Actualización de Datos'
        );
        
        $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
        
        if ($resultadoHistorial['status'] === 'success') {
            cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
        }
    }
    
    // 3. Si se agregó o modificó el Folio RCHRP, registrar evento específico
    if ($cambioFolioRCHRP) {
        $sqlHistorialRCHRP = "INSERT INTO HistorialCambios (
                             ID_Tramite, EstadoAnterior, EstadoNuevo, 
                             Observacion, UsuarioResponsable, TipoAccion
                             ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $observacionHistorialRCHRP = "Registro/Actualización de Folio RCHRP: " . $folioRCHRP;
        if (!empty($fechaRCHRP)) {
            $observacionHistorialRCHRP .= ", Fecha: " . date('d/m/Y', strtotime($fechaRCHRP));
        }
        
        $paramsHistorialRCHRP = array(
            $idTramite, 
            null, 
            null, 
            $observacionHistorialRCHRP, 
            'Sistema', 
            'Registro de Folio RCHRP'
        );
        
        $resultadoHistorialRCHRP = ejecutarConsulta($sqlHistorialRCHRP, $paramsHistorialRCHRP);
        
        if ($resultadoHistorialRCHRP['status'] === 'success') {
            cerrarConexion($resultadoHistorialRCHRP['conn'], $resultadoHistorialRCHRP['stmt']);
        }
    }
    
    // Consultar datos actualizados del trámite para devolver en la respuesta
    $sqlActualizado = "SELECT t.ID_Tramite, t.CIIA, t.FechaRegistro, t.FolioRCHRP, t.FechaRCHRP, 
                      tt.Nombre AS TipoTramite, ct.Clave AS ClaveTramite, 
                      e.Nombre AS Estado, e.Porcentaje 
                      FROM Tramites t
                      INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite
                      INNER JOIN ClavesTramite ct ON t.ID_ClaveTramite = ct.ID_ClaveTramite
                      INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
                      WHERE t.ID_Tramite = ?";
    
    $resultadoActualizado = ejecutarConsulta($sqlActualizado, array($idTramite));
    
    if ($resultadoActualizado['status'] !== 'success') {
        respuestaJSON('error', "Error al consultar los datos actualizados: " . $resultadoActualizado['message']);
    }
    
    $tramiteActualizado = sqlsrv_fetch_array($resultadoActualizado['stmt'], SQLSRV_FETCH_ASSOC);
    cerrarConexion($resultadoActualizado['conn'], $resultadoActualizado['stmt']);
    
    // Formatear fechas para la respuesta
    if (isset($tramiteActualizado['FechaRegistro']) && $tramiteActualizado['FechaRegistro'] instanceof DateTime) {
        $tramiteActualizado['FechaRegistro'] = $tramiteActualizado['FechaRegistro']->format('Y-m-d');
    }
    
    if (isset($tramiteActualizado['FechaRCHRP']) && $tramiteActualizado['FechaRCHRP'] instanceof DateTime) {
        $tramiteActualizado['FechaRCHRP'] = $tramiteActualizado['FechaRCHRP']->format('Y-m-d');
    }
    
    // Preparar mensaje de éxito
    $mensaje = "El trámite ha sido actualizado correctamente";
    if (!empty($cambios)) {
        $mensaje .= ". Se actualizaron: " . implode(", ", $cambios);
    }
    if ($estadoAnterior != $estadoTramite) {
        $mensaje .= ". Se cambió el estado del trámite";
    }
    
    // Devolver respuesta exitosa
    respuestaJSON('success', $mensaje, $tramiteActualizado);
} else {
    // Devolver errores
    respuestaJSON('error', implode(". ", $errores));
}