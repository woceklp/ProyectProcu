<?php
/**
 * instalar-documentos.php - Script de instalación para el módulo de documentos
 * 
 * Este script debe ejecutarse una sola vez para crear las tablas necesarias
 * y configurar el módulo de generación de documentos
 */

// Incluir archivo de configuración
require_once 'config.php';

// Configuración para mostrar errores durante la instalación
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar HTML para mostrar resultados de forma amigable
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación del Módulo de Documentos</title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-cogs me-2"></i>Instalación del Módulo de Documentos</h3>
            </div>
            <div class="card-body">
                <div id="instalacion-log" class="border rounded p-3 bg-light mb-4">

<?php
// Bandera para seguir la ejecución del script
$exito = true;

// Función para ejecutar consultas y mostrar errores
function ejecutarConsultaInstalacion($sql) {
    global $exito;
    
    $resultado = ejecutarConsulta($sql);
    
    if ($resultado['status'] === 'success') {
        echo "<p class='text-success'><i class='fas fa-check-circle'></i> Consulta ejecutada con éxito.</p>";
        cerrarConexion($resultado['conn'], $resultado['stmt']);
        return true;
    } else {
        echo "<p class='text-danger'><i class='fas fa-times-circle'></i> Error: " . $resultado['message'] . "</p>";
        $exito = false;
        return false;
    }
}

// Función para verificar si una tabla existe
function tablaExiste($nombreTabla) {
    $sqlVerificar = "IF EXISTS (SELECT * FROM sysobjects WHERE name='$nombreTabla' AND xtype='U')
                     SELECT 1 AS existe
                     ELSE
                     SELECT 0 AS existe";

    $resultadoVerificar = ejecutarConsulta($sqlVerificar);

    if ($resultadoVerificar['status'] === 'success') {
        $existe = false;
        if (sqlsrv_fetch($resultadoVerificar['stmt'])) {
            $existe = (sqlsrv_get_field($resultadoVerificar['stmt'], 0) == 1);
        }
        cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);
        return $existe;
    } else {
        echo "<p class='text-danger'><i class='fas fa-times-circle'></i> Error al verificar si existe la tabla $nombreTabla: " . $resultadoVerificar['message'] . "</p>";
        return false;
    }
}

// 1. Verificar y crear tabla TiposDocumento si no existe
echo "<h4>1. Verificando tabla TiposDocumento...</h4>";
$tiposDocumentoExiste = tablaExiste('TiposDocumento');

if (!$tiposDocumentoExiste) {
    echo "<p class='text-info'><i class='fas fa-info-circle'></i> La tabla TiposDocumento no existe. Creando tabla...</p>";
    
    $sqlCrearTiposDocumento = "CREATE TABLE TiposDocumento (
        ID_TipoDocumento INT IDENTITY(1,1) PRIMARY KEY,
        Nombre NVARCHAR(100) NOT NULL,
        Descripcion NVARCHAR(255) NULL,
        Categoria NVARCHAR(50) NULL,
        CamposRequeridos NVARCHAR(MAX) NULL,
        Activo BIT DEFAULT 1
    )";
    
    ejecutarConsultaInstalacion($sqlCrearTiposDocumento);
    
    // Insertar tipos de documentos predefinidos si se creó la tabla correctamente
    if ($exito) {
        echo "<p class='text-info'><i class='fas fa-info-circle'></i> Insertando tipos de documentos predefinidos...</p>";
        
        $sqlInsertarTipos = "INSERT INTO TiposDocumento (Nombre, Descripcion, Categoria, CamposRequeridos, Activo)
        VALUES 
        ('Acta de Comparecencia', 'Documento que registra la comparecencia de un promovente ante la Procuraduría Agraria', 'Actas', '{\"fecha\": true, \"lugar\": true, \"nombre_funcionario\": true, \"cargo_funcionario\": true, \"id_funcionario\": true, \"contenido\": true}', 1),
        ('Solicitud de Información', 'Documento para solicitar información a otras dependencias', 'Oficios', '{\"fecha\": true, \"destinatario\": true, \"cargo_destinatario\": true, \"asunto\": true, \"contenido\": true}', 1),
        ('Acta de Hechos', 'Documento que registra hechos ocurridos en un ejido o comunidad', 'Actas', '{\"fecha\": true, \"lugar\": true, \"nombre_funcionario\": true, \"cargo_funcionario\": true, \"hechos\": true}', 1),
        ('Constancia de Posesión', 'Documento que certifica la posesión de un terreno', 'Constancias', '{\"fecha\": true, \"ubicacion\": true, \"medidas\": true, \"colindancias\": true}', 1),
        ('Acta de Asamblea', 'Documento que registra los acuerdos tomados en una asamblea ejidal', 'Actas', '{\"fecha\": true, \"lugar\": true, \"hora_inicio\": true, \"hora_fin\": true, \"asistentes\": true, \"acuerdos\": true}', 1)";
        
        ejecutarConsultaInstalacion($sqlInsertarTipos);
    }
} else {
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> La tabla TiposDocumento ya existe. No se realizaron cambios.</p>";
}

// 2. Verificar y crear tabla Documentos si no existe
echo "<h4>2. Verificando tabla Documentos...</h4>";
$documentosExiste = tablaExiste('Documentos');

if (!$documentosExiste) {
    echo "<p class='text-info'><i class='fas fa-info-circle'></i> La tabla Documentos no existe. Creando tabla...</p>";
    
    $sqlCrearDocumentos = "CREATE TABLE Documentos (
        ID_Documento INT IDENTITY(1,1) PRIMARY KEY,
        ID_TipoDocumento INT,
        ID_Promovente INT NULL,
        ID_Tramite INT NULL,
        Nombre NVARCHAR(255) NOT NULL,
        RutaArchivo NVARCHAR(255) NULL,
        FechaGeneracion DATETIME DEFAULT GETDATE(),
        Estado NVARCHAR(50) DEFAULT 'BORRADOR',
        FechaModificacion DATETIME NULL,
        FOREIGN KEY (ID_TipoDocumento) REFERENCES TiposDocumento(ID_TipoDocumento),
        FOREIGN KEY (ID_Promovente) REFERENCES Promoventes(ID_Promovente),
        FOREIGN KEY (ID_Tramite) REFERENCES Tramites(ID_Tramite)
    )";
    
    ejecutarConsultaInstalacion($sqlCrearDocumentos);
    
} else {
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> La tabla Documentos ya existe. No se realizaron cambios.</p>";
}

// 3. Verificar y crear tabla EstadosDescriptivos si no existe
echo "<h4>3. Verificando tabla EstadosDescriptivos...</h4>";
$estadosDescriptivosExiste = tablaExiste('EstadosDescriptivos');

if (!$estadosDescriptivosExiste) {
    echo "<p class='text-info'><i class='fas fa-info-circle'></i> La tabla EstadosDescriptivos no existe. Creando tabla...</p>";
    
    $sqlCrearEstadosDescriptivos = "CREATE TABLE EstadosDescriptivos (
        ID_EstadoDescriptivo VARCHAR(50) PRIMARY KEY,
        Nombre NVARCHAR(500) NOT NULL,
        Descripcion VARCHAR(255) NULL
    )";
    
    $creacionExitosa = ejecutarConsultaInstalacion($sqlCrearEstadosDescriptivos);
    
    // Insertar estados descriptivos predefinidos
    if ($creacionExitosa) {
        echo "<p class='text-info'><i class='fas fa-info-circle'></i> Insertando estados descriptivos predefinidos...</p>";
        
        $sqlInsertarEstadosDescriptivos = "INSERT INTO EstadosDescriptivos (ID_EstadoDescriptivo, Nombre, Descripcion)
        VALUES 
        ('INSCRITO', 'Inscrito', 'El trámite ha sido inscrito correctamente'),
        ('RECHAZADO', 'Rechazado', 'El trámite ha sido rechazado'),
        ('EN_REVISION', 'En revisión', 'El trámite se encuentra en proceso de revisión'),
        ('PENDIENTE_DOCUMENTACION', 'Pendiente de documentación', 'Se requiere documentación adicional'),
        ('PENDIENTE_PAGO', 'Pendiente de pago', 'Falta realizar el pago correspondiente'),
        ('REQUIERE_CORRECCION', 'Requiere corrección', 'Se necesitan hacer correcciones en el trámite'),
        ('CANCELADO', 'Cancelado', 'El trámite ha sido cancelado'),
        ('SUSPENDIDO', 'Suspendido', 'El trámite ha sido suspendido temporalmente'),
        ('EN_PROCESO', 'En proceso', 'El trámite está siendo procesado'),
        ('FINALIZADO', 'Finalizado', 'El trámite ha finalizado completamente')";
        
        ejecutarConsultaInstalacion($sqlInsertarEstadosDescriptivos);
    }
} else {
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> La tabla EstadosDescriptivos ya existe. No se realizaron cambios.</p>";
}

// 4. Verificar y crear tabla EstadosBasicos si no existe
echo "<h4>4. Verificando tabla EstadosBasicos...</h4>";
$estadosBasicosExiste = tablaExiste('EstadosBasicos');

if (!$estadosBasicosExiste) {
    echo "<p class='text-info'><i class='fas fa-info-circle'></i> La tabla EstadosBasicos no existe. Creando tabla...</p>";
    
    $sqlCrearEstadosBasicos = "CREATE TABLE EstadosBasicos (
        ID_EstadoBasico INT PRIMARY KEY,
        Nombre NVARCHAR(50) NOT NULL
    )";
    
    $creacionExitosa = ejecutarConsultaInstalacion($sqlCrearEstadosBasicos);
    
    // Insertar estados básicos predefinidos
    if ($creacionExitosa) {
        echo "<p class='text-info'><i class='fas fa-info-circle'></i> Insertando estados básicos predefinidos...</p>";
        
        $sqlInsertarEstadosBasicos = "INSERT INTO EstadosBasicos (ID_EstadoBasico, Nombre)
        VALUES 
        (1, 'COMPLETA'),
        (2, 'PREVENIDO'),
        (3, 'EN PROCESO')";
        
        ejecutarConsultaInstalacion($sqlInsertarEstadosBasicos);
    }
} else {
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> La tabla EstadosBasicos ya existe. No se realizaron cambios.</p>";
}

// 5. Crear directorio para guardar documentos generados
echo "<h4>5. Creando directorios para documentos...</h4>";

$directorio = 'archivos/documentos/';
if (!file_exists($directorio)) {
    if (mkdir($directorio, 0755, true)) {
        echo "<p class='text-success'><i class='fas fa-check-circle'></i> Directorio '$directorio' creado exitosamente.</p>";
    } else {
        echo "<p class='text-danger'><i class='fas fa-times-circle'></i> Error al crear el directorio '$directorio'. Verifique los permisos.</p>";
        $exito = false;
    }
} else {
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> El directorio '$directorio' ya existe.</p>";
}

// 6. Verificar y agregar FechaCompletado a la tabla Tramites si no existe
echo "<h4>6. Verificando campo FechaCompletado en tabla Tramites...</h4>";

$sqlVerificarCampo = "
IF EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'Tramites' AND COLUMN_NAME = 'FechaCompletado'
)
    SELECT 1 AS existe
ELSE
    SELECT 0 AS existe
";

$resultadoVerificarCampo = ejecutarConsulta($sqlVerificarCampo);

if ($resultadoVerificarCampo['status'] === 'success') {
    $existe = false;
    if (sqlsrv_fetch($resultadoVerificarCampo['stmt'])) {
        $existe = (sqlsrv_get_field($resultadoVerificarCampo['stmt'], 0) == 1);
    }
    cerrarConexion($resultadoVerificarCampo['conn'], $resultadoVerificarCampo['stmt']);
    
    if (!$existe) {
        echo "<p class='text-info'><i class='fas fa-info-circle'></i> El campo FechaCompletado no existe en la tabla Tramites. Agregando campo...</p>";
        
        $sqlAgregarCampo = "ALTER TABLE Tramites ADD FechaCompletado DATETIME NULL";
        
        ejecutarConsultaInstalacion($sqlAgregarCampo);
    } else {
        echo "<p class='text-success'><i class='fas fa-check-circle'></i> El campo FechaCompletado ya existe en la tabla Tramites.</p>";
    }
} else {
    echo "<p class='text-danger'><i class='fas fa-times-circle'></i> Error al verificar si existe el campo FechaCompletado: " . $resultadoVerificarCampo['message'] . "</p>";
    $exito = false;
}

// Mostrar resumen final
echo "</div>"; // Cerrar div de log de instalación

if ($exito) {
    echo "<div class='alert alert-success'>";
    echo "<h4><i class='fas fa-check-circle me-2'></i>¡Instalación Completada!</h4>";
    echo "<p>El módulo de documentos ha sido instalado correctamente. Ahora puede comenzar a utilizar todas las funcionalidades.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-warning'>";
    echo "<h4><i class='fas fa-exclamation-triangle me-2'></i>Instalación Parcial</h4>";
    echo "<p>La instalación se completó parcialmente. Algunos componentes pueden no funcionar correctamente. Revise los errores arriba y vuelva a intentarlo.</p>";
    echo "</div>";
}
?>

                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Volver al Inicio
                    </a>
                </div>
            </div>
            <div class="card-footer text-muted text-center">
                <small>Sistema Gestor de Trámites Agrarios - <?php echo date('Y'); ?></small>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>