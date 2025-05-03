<?php


/**
 * config.php - Archivo de configuración y conexión a la base de datos
 */

// SECCIÓN 1: CONFIGURACIÓN DE ERRORES Y CONEXIÓN A BASE DE DATOS
// ---------------------------------------------------------

// Configuración de errores - mostrar todos los errores durante el desarrollo
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuración de la base de datos
$serverName = "DESKTOP-5RO860I"; // nombre del servidor SQL Server
$connectionInfo = array(
    "Database" => "SistemaGestorTramitesAgrarios",
    "UID" => "sa", // Tu nombre de usuario
    "PWD" => "root", // Tu contraseña
    "CharacterSet" => "UTF-8",
    "LoginTimeout" => 30, // Tiempo de espera para la conexión
    "ConnectionPooling" => true, // Habilitar pooling de conexiones
    "TrustServerCertificate" => true // Ignorar problemas de certificado
);

/**
 * Función mejorada para conectarse a la base de datos con reintentos
 */
function conectarDB() {
    global $serverName, $connectionInfo;
    
    // Intentar la conexión varias veces
    $maxIntentos = 3;
    $intentos = 0;
    $ultimoError = "";
    
    while ($intentos < $maxIntentos) {
        $conn = sqlsrv_connect($serverName, $connectionInfo);
        
        if($conn !== false) {
            return $conn;
        }
        
        // Registrar el error para mostrar si fallan todos los intentos
        $errores = sqlsrv_errors();
        if($errores !== null) {
            $ultimoError = "";
            foreach($errores as $error) {
                $ultimoError .= "SQLSTATE: ".$error['SQLSTATE']." - Código: ".$error['code']." - Mensaje: ".$error['message']."\n";
            }
        }
        
        // Esperar un momento antes de reintentar (aumenta el tiempo de espera en cada intento)
        sleep(1 * ($intentos + 1));
        $intentos++;
    }
    
    // Registrar el error detallado
    error_log("Error de conexión a la base de datos después de {$maxIntentos} intentos: {$ultimoError}");
    
    return false;
}

/**
 * Formatea los errores de SQL Server para mostrarlos
 */
function formatearErroresSQLSrv() {
    $errores = sqlsrv_errors();
    if($errores !== null) {
        $mensaje = "";
        foreach($errores as $error) {
            $mensaje .= "SQLSTATE: ".$error['SQLSTATE']." - Código: ".$error['code']." - Mensaje: ".$error['message']."\n";
        }
        return $mensaje;
    }
    return "Error desconocido.";
}

// SECCIÓN 2: FUNCIONES DE MANEJO DE CONSULTAS
// ---------------------------------------------------------

/**
 * Ejecuta una consulta SQL con parámetros
 */
function ejecutarConsulta($sql, $params = array()) {
    $conn = conectarDB();
    
    if($conn === false) {
        // Intentar diagnosticar el problema
        $diagnostico = "Verifique que el servicio SQL Server esté en ejecución y que los parámetros de conexión sean correctos.";
        return array('status' => 'error', 'message' => 'Error de conexión a la base de datos. ' . $diagnostico);
    }
    
    // Configurar opciones para la consulta
    $options = array("Scrollable" => SQLSRV_CURSOR_STATIC);
    $stmt = sqlsrv_query($conn, $sql, $params, $options);
    
    if($stmt === false) {
        $error = formatearErroresSQLSrv();
        sqlsrv_close($conn);
        return array('status' => 'error', 'message' => 'Error en la consulta: ' . $error);
    }
    
    return array('status' => 'success', 'stmt' => $stmt, 'conn' => $conn);
}

/**
 * Obtiene los resultados de una consulta como array asociativo
 */
function obtenerResultados($stmt) {
    $resultados = array();
    
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $resultados[] = $row;
    }
    
    return $resultados;
}

/**
 * Cierra la conexión y libera recursos
 */
function cerrarConexion($conn, $stmt = null) {
    // Verificar si $stmt es un recurso válido antes de liberarlo
    if ($stmt !== null) {
        if (is_resource($stmt)) {
            // Solo entonces verificar si es específicamente un statement de SQL Server
            if (get_resource_type($stmt) === 'SQL Server Statement') {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
    
    // Verificar si $conn es un recurso válido antes de cerrarlo
    if ($conn !== false) {
        if (is_resource($conn)) {
            // Solo entonces verificar si es específicamente una conexión de SQL Server
            if (get_resource_type($conn) === 'SQL Server Connection') {
                sqlsrv_close($conn);
            }
        }
    }
}

// SECCIÓN 3: FUNCIONES DE VALIDACIÓN Y SANITIZACIÓN
// ---------------------------------------------------------

/**
 * Sanitiza las entradas de usuario para prevenir inyección SQL
 */
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

/**
 * Valida que un CIIA tenga el formato correcto (13 dígitos)
 */
function validarCIIA($ciia) {
    return (strlen($ciia) === 13 && ctype_digit($ciia));
}

/**
 * Actualiza automáticamente el status de un trámite basado en los estados de sus acuses
 */
/**
 * Actualiza automáticamente el status de un trámite basado en los estados de sus acuses
 */
function actualizarStatusTramiteSegunAcuses($idTramite) {
    error_log("== INICIO: Sincronizando status del trámite #$idTramite ==");
    
    // Consultar todos los acuses del trámite
    $sqlAcuses = "SELECT a.ID_Acuse, a.ID_EstadoTramite, a.ID_EstadoBasico 
                 FROM Acuses a 
                 WHERE a.ID_Tramite = ? 
                 ORDER BY a.FechaRegistro DESC";
                 
    $resultadoAcuses = ejecutarConsulta($sqlAcuses, array($idTramite));
    
    if ($resultadoAcuses['status'] !== 'success') {
        error_log("Error al consultar acuses: " . $resultadoAcuses['message']);
        return false;
    }
    
    $acuses = obtenerResultados($resultadoAcuses['stmt']);
    cerrarConexion($resultadoAcuses['conn'], $resultadoAcuses['stmt']);
    
    $numAcuses = count($acuses);
    error_log("Número de acuses encontrados: $numAcuses");
    
    // Si no hay acuses, mantener el status actual
    if ($numAcuses === 0) {
        error_log("No hay acuses para el trámite #$idTramite, manteniendo status actual");
        return true;
    }
    
    // Consultar el status actual del trámite
    $sqlStatusActual = "SELECT ID_EstadoTramite, ID_EstadoBasico 
                       FROM Tramites 
                       WHERE ID_Tramite = ?";
                       
    $resultadoStatusActual = ejecutarConsulta($sqlStatusActual, array($idTramite));
    
    if ($resultadoStatusActual['status'] !== 'success') {
        error_log("Error al consultar status actual del trámite: " . $resultadoStatusActual['message']);
        return false;
    }
    
    $tramite = sqlsrv_fetch_array($resultadoStatusActual['stmt'], SQLSRV_FETCH_ASSOC);
    cerrarConexion($resultadoStatusActual['conn'], $resultadoStatusActual['stmt']);
    
    if (!$tramite) {
        error_log("No se encontró el trámite con ID: $idTramite");
        return false;
    }
    
    $statusActualID = $tramite['ID_EstadoTramite'];
    $statusBasicoActualID = $tramite['ID_EstadoBasico'];
    
    error_log("Estado actual del trámite: EstadoTramite=$statusActualID, EstadoBasico=$statusBasicoActualID");
    
    // APLICAR LA MISMA LÓGICA QUE EN LA CONSULTA SQL
    // ---------------------------------------------
    
    // 1. Determinar el estado básico primero
    $nuevoEstadoBasicoID = null;
    
    // Si hay al menos un acuse con estado PREVENIDO (ID 2)
    $hayPrevenido = false;
    foreach ($acuses as $acuse) {
        if (isset($acuse['ID_EstadoBasico']) && $acuse['ID_EstadoBasico'] == 2) {
            $hayPrevenido = true;
            break;
        }
    }
    
    if ($hayPrevenido) {
        $nuevoEstadoBasicoID = 2; // PREVENIDO
        error_log("Hay al menos un acuse PREVENIDO, asignando estado básico PREVENIDO (ID 2)");
    } else {
        // Verificar si todos los acuses son EN PROCESO (ID 1)
        $todosEnProceso = true;
        foreach ($acuses as $acuse) {
            if (!isset($acuse['ID_EstadoBasico']) || $acuse['ID_EstadoBasico'] != 1) {
                $todosEnProceso = false;
                break;
            }
        }
        
        if ($todosEnProceso) {
            $nuevoEstadoBasicoID = 1; // EN PROCESO
            error_log("Todos los acuses están EN PROCESO, asignando estado básico EN PROCESO (ID 1)");
        } else {
            // Si no son todos EN PROCESO y no hay ningún PREVENIDO, entonces deben ser COMPLETA
            $nuevoEstadoBasicoID = 3; // COMPLETA
            error_log("No hay acuses PREVENIDOS y no todos son EN PROCESO, asignando estado básico COMPLETA (ID 3)");
        }
    }
    
    // 2. Determinar el ID del estado del trámite basado en el estado básico
    $nuevoEstadoTramiteID = null;
    
    if ($nuevoEstadoBasicoID == 2) { // PREVENIDO
        $nuevoEstadoTramiteID = 7; // ID para "Prevenido"
        error_log("Estado básico PREVENIDO, asignando EstadoTramite=7 (Prevenido)");
    } else if ($nuevoEstadoBasicoID == 1) { // EN PROCESO
        // Mantener el avance actual si existe, o usar el avance del primer acuse
        if ($statusActualID >= 2 && $statusActualID <= 4) {
            $nuevoEstadoTramiteID = $statusActualID;
            error_log("Estado básico EN PROCESO, manteniendo EstadoTramite actual=$statusActualID");
        } else if ($numAcuses > 0 && isset($acuses[0]['ID_EstadoTramite'])) {
            $nuevoEstadoTramiteID = $acuses[0]['ID_EstadoTramite'];
            error_log("Estado básico EN PROCESO, usando EstadoTramite del primer acuse=$nuevoEstadoTramiteID");
        } else {
            $nuevoEstadoTramiteID = 2; // Por defecto, 1 DE 4 (25%)
            error_log("Estado básico EN PROCESO, asignando EstadoTramite=2 (25% por defecto)");
        }
    } else if ($nuevoEstadoBasicoID == 3) { // COMPLETA
        $nuevoEstadoTramiteID = 5; // ID para "Completa" (100%)
        error_log("Estado básico COMPLETA, asignando EstadoTramite=5 (Completa)");
    } else {
        // Caso improbable, pero para seguridad
        $nuevoEstadoTramiteID = $statusActualID;
        error_log("Estado básico desconocido, manteniendo EstadoTramite actual=$statusActualID");
    }
    
    error_log("Estado final calculado: EstadoTramite=$nuevoEstadoTramiteID, EstadoBasico=$nuevoEstadoBasicoID");
    
    // Solo actualizar si hay cambios en el status
    if ($nuevoEstadoTramiteID != $statusActualID || $nuevoEstadoBasicoID != $statusBasicoActualID) {
        // Actualizar el trámite
        $sqlActualizar = "UPDATE Tramites 
                         SET ID_EstadoTramite = ?, 
                             ID_EstadoBasico = ?,
                             FechaUltimaActualizacion = GETDATE() 
                         WHERE ID_Tramite = ?";
        
        $paramsActualizar = array($nuevoEstadoTramiteID, $nuevoEstadoBasicoID, $idTramite);
        $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
        
        if ($resultadoActualizar['status'] !== 'success') {
            error_log("Error al actualizar status del trámite #$idTramite: " . $resultadoActualizar['message']);
            return false;
        }
        
        cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
        
        // Determinar nombre del estado para el historial
        $estadoNombre = "";
        if ($nuevoEstadoBasicoID == 1) $estadoNombre = "EN PROCESO";
        else if ($nuevoEstadoBasicoID == 2) $estadoNombre = "PREVENIDO";
        else if ($nuevoEstadoBasicoID == 3) $estadoNombre = "COMPLETA";
        
        // Registrar en historial
        $sqlHistorial = "INSERT INTO HistorialCambios (
                        ID_Tramite, EstadoAnterior, EstadoNuevo, 
                        Observacion, UsuarioResponsable, TipoAccion
                        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $observacionHistorial = "Sincronización automática - Nuevo estado: " . $estadoNombre;
        
        $paramsHistorial = array(
            $idTramite, 
            $statusActualID, 
            $nuevoEstadoTramiteID, 
            $observacionHistorial, 
            'Sistema', 
            'Sincronización Automática'
        );
        
        $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
        
        if ($resultadoHistorial['status'] === 'success') {
            cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
        }
        
        error_log("Trámite #$idTramite actualizado exitosamente");
        error_log("== FIN: Sincronización completada con cambios ==");
        return true; // Indica que hubo un cambio en el status
    }
    
    error_log("No hay cambios necesarios para el trámite #$idTramite");
    error_log("== FIN: Sincronización completada sin cambios ==");
    return false; // Indica que no hubo cambios en el status
}

//----------------------------------------------------------

// Esta función debe agregarse al archivo config.php, cerca de las otras funciones utilitarias

/**
 * Calcula los días transcurridos para la reiteración, considerando el estado COMPLETA
 * Si el trámite está COMPLETO, devuelve 0 días (no necesita reiteración)
 */
function calcularDiasReiteracion($idTramite) {
    // Consultar el estado y fechas relevantes
    $sqlEstadoBasico = "
        SELECT 
            t.ID_EstadoBasico,
            t.ID_EstadoTramite,
            t.FechaCompletado,
            CASE 
                WHEN t.ID_EstadoBasico = 1 THEN 'COMPLETA'
                WHEN t.ID_EstadoTramite = 5 THEN 'COMPLETA'
                WHEN (
                    (SELECT COUNT(*) FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite) > 0
                    AND 
                    NOT EXISTS (
                        SELECT 1 FROM Acuses a 
                        WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
                    )
                ) THEN 'COMPLETA'
                ELSE 'EN PROCESO'
            END AS StatusReal,
            COALESCE(
                (SELECT TOP 1 r.FechaReiteracion 
                FROM Reiteraciones r 
                WHERE r.ID_Tramite = t.ID_Tramite 
                ORDER BY r.NumeroReiteracion DESC), 
                t.FechaRCHRP
            ) AS FechaInicio
        FROM Tramites t
        WHERE t.ID_Tramite = ?";
    
    $resultadoEstado = ejecutarConsulta($sqlEstadoBasico, array($idTramite));
    
    if ($resultadoEstado['status'] !== 'success') {
        return 0;
    }
    
    $datosEstado = sqlsrv_fetch_array($resultadoEstado['stmt'], SQLSRV_FETCH_ASSOC);
    cerrarConexion($resultadoEstado['conn'], $resultadoEstado['stmt']);
    
    if (!$datosEstado || !isset($datosEstado['FechaInicio']) || !$datosEstado['FechaInicio']) {
        return 0;
    }
    
    // Si está COMPLETA y tiene fecha de completado, calcular días hasta esa fecha
    if ($datosEstado['StatusReal'] === 'COMPLETA' && isset($datosEstado['FechaCompletado']) && $datosEstado['FechaCompletado']) {
        $fechaInicio = $datosEstado['FechaInicio'];
        $fechaFin = $datosEstado['FechaCompletado'];
        $diasTranscurridos = $fechaFin->diff($fechaInicio)->days;
        return $diasTranscurridos;
    }
    
    // Si está COMPLETA pero no tiene fecha de completado (casos antiguos), usar 0
    if ($datosEstado['StatusReal'] === 'COMPLETA') {
        return 0;
    }
    
    // Para trámites en proceso, calcular días hasta hoy
    $fechaActual = new DateTime();
    $fechaInicio = $datosEstado['FechaInicio'];
    $diasTranscurridos = $fechaActual->diff($fechaInicio)->days;
    
    return $diasTranscurridos;
}

//----------------------------------------------------------
// SECCIÓN 5: FUNCIONES DE RESPUESTA
// ---------------------------------------------------------

/**
 * Envía una respuesta JSON al cliente
 */
function respuestaJSON($status, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>