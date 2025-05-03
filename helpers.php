<?php
/**
 * helpers.php - Funciones auxiliares utilizadas en toda la aplicación
 */

// Función para sanitizar entradas de usuario
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

// Función para validar CIIA (13 dígitos)
function validarCIIA($ciia) {
    return (strlen($ciia) === 13 && ctype_digit($ciia));
}

// Función para validar número de trámite/acuse (11 dígitos)
function validarNumeroTramite($numeroTramite) {
    return (strlen($numeroTramite) === 11 && ctype_digit($numeroTramite));
}

// Función para formatear fechas desde SQL Server
function formatearFecha($fecha, $formato = 'd/m/Y') {
    if($fecha instanceof DateTime) {
        return $fecha->format($formato);
    }
    return '';
}

// Función para respuesta JSON estandarizada
function respuestaJSON($status, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}