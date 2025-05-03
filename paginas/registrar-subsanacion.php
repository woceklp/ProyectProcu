<?php
/**
 * paginas/registrar-subsanacion.php - Formulario para registrar una subsanación de un trámite
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

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Obtener el ID del trámite de la URL
$idTramite = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idTramite <= 0) {
    // Redirigir si no hay ID válido
    echo '<script>window.location.href = "listado-tramites.php";</script>';
    exit;
}

// Verificar si se ha enviado el formulario
$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar datos del formulario
    $folioSubsanacion = isset($_POST['folio_subsanacion']) ? sanitizarEntrada($_POST['folio_subsanacion']) : '';
    $fechaSubsanacion = isset($_POST['fecha_subsanacion']) ? sanitizarEntrada($_POST['fecha_subsanacion']) : '';
    $descripcion = isset($_POST['descripcion']) ? sanitizarEntrada($_POST['descripcion']) : '';
    
    // Validar datos
    $errores = [];
    
    if (empty($folioSubsanacion)) {
        $errores[] = "El folio de subsanación es requerido";
    }
    
    if (empty($fechaSubsanacion)) {
        $errores[] = "La fecha de subsanación es requerida";
    }
    
    if (empty($descripcion)) {
        $errores[] = "La descripción de la subsanación es requerida";
    }
    
    // Si no hay errores, guardar la subsanación
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
        
        if ($resultadoInsertar['status'] === 'success') {
            cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);
            
            // Verificar si existe el estado 7 (Pendiente de Subsanación)
            $sqlVerificarEstado = "SELECT COUNT(*) AS total FROM EstadosTramite WHERE ID_EstadoTramite = 7";
            $resultadoVerificarEstado = ejecutarConsulta($sqlVerificarEstado);
            
            if ($resultadoVerificarEstado['status'] === 'success') {
                $rowVerificarEstado = sqlsrv_fetch_array($resultadoVerificarEstado['stmt'], SQLSRV_FETCH_ASSOC);
                cerrarConexion($resultadoVerificarEstado['conn'], $resultadoVerificarEstado['stmt']);
                
                // Si existe el estado 7, utilizarlo; de lo contrario, usar un estado válido (por ejemplo, el 1)
                $estadoSubsanacion = ($rowVerificarEstado['total'] > 0) ? 7 : 1;
                
                // Actualizar la fecha de última actualización y estado del trámite
                $sqlActualizar = "UPDATE Tramites 
                SET FechaUltimaActualizacion = GETDATE(),
                    ID_EstadoTramite = ?
                WHERE ID_Tramite = ?";
                
                $paramsActualizar = array($estadoSubsanacion, $idTramite);
                $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
                
                if ($resultadoActualizar['status'] === 'success') {
                    cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);
                    
                    // Registrar en historial de cambios
                    $sqlHistorial = "INSERT INTO HistorialCambios (
                        ID_Tramite, EstadoAnterior, EstadoNuevo, 
                        Observacion, UsuarioResponsable, TipoAccion
                        ) VALUES (?, 
                            (SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?), 
                            ?, 
                            ?, 
                            'Sistema',
                            'Registro de Subsanación')";
                    
                    $observacionHistorial = "Registro de subsanación: {$folioSubsanacion}";
                    $paramsHistorial = array($idTramite, $idTramite, $estadoSubsanacion, $observacionHistorial);
                    $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
                    
                    if ($resultadoHistorial['status'] === 'success') {
                        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
                        
                        // Redirigir a la página de detalles con parámetro de éxito
                        header("Location: detalle-tramite.php?id=" . $idTramite . "&subsanacion_registrada=1");
                        exit;
                    } else {
                        $mensajeError = "Error al registrar el historial: " . $resultadoHistorial['message'];
                    }
                } else {
                    $mensajeError = "Error al actualizar el estado del trámite: " . $resultadoActualizar['message'];
                }
            } else {
                $mensajeError = "Error al verificar los estados disponibles: " . $resultadoVerificarEstado['message'];
            }
        } else {
            $mensajeError = "Error al registrar la subsanación: " . $resultadoInsertar['message'];
        }
    } else {
        $mensajeError = implode("<br>", $errores);
    }
}

// Consultar información del trámite
$sqlTramite = "SELECT t.CIIA, 
               t.FechaUltimaActualizacion, 
               p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
               e.Nombre AS Estado, 
               e.Porcentaje,
               e.ID_EstadoTramite,
               CASE 
                   WHEN EXISTS (
                       SELECT 1 FROM Acuses a 
                       WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
                   ) THEN 'PREVENIDO'
                   WHEN (
                       NOT EXISTS (
                           SELECT 1 FROM Acuses a 
                           WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
                       ) AND EXISTS (
                           SELECT 1 FROM Acuses a 
                           WHERE a.ID_Tramite = t.ID_Tramite
                       )
                   ) THEN 'COMPLETA'
                   ELSE 'EN PROCESO'
               END AS StatusReal
               FROM Tramites t
               INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente
               INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
               WHERE t.ID_Tramite = ?";

$resultadoTramite = ejecutarConsulta($sqlTramite, array($idTramite));

if ($resultadoTramite['status'] !== 'success') {
    echo '<div class="alert alert-danger">Error al cargar los datos del trámite: '.$resultadoTramite['message'].'</div>';
    include_once '../modulos/footer.php';
    exit;
}

$tramite = sqlsrv_fetch_array($resultadoTramite['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoTramite['conn'], $resultadoTramite['stmt']);

if (!$tramite) {
    echo '<div class="alert alert-warning">El trámite solicitado no existe o ha sido eliminado.</div>';
    include_once '../modulos/footer.php';
    exit;
}

// Formatear el texto del avance según el porcentaje
$avanceTexto = "";
if(isset($tramite['Porcentaje'])) {
    if($tramite['Porcentaje'] == 25) $avanceTexto = "1 DE 4 (AVANCE 25%)";
    else if($tramite['Porcentaje'] == 50) $avanceTexto = "2 DE 4 (AVANCE 50%)";
    else if($tramite['Porcentaje'] == 75) $avanceTexto = "3 DE 4 (AVANCE 75%)";
    else if($tramite['Porcentaje'] == 100) $avanceTexto = "4 DE 4 (AVANCE 100%)";
    else $avanceTexto = $tramite['Porcentaje'] . "%";
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php">Listado de Trámites</a></li>
                <li class="breadcrumb-item"><a href="detalle-tramite.php?id=<?php echo $idTramite; ?>">Detalle de Trámite #<?php echo $idTramite; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Registrar Subsanación</li>
            </ol>
        </nav>
        
        <?php if(!empty($mensajeExito)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $mensajeExito; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($mensajeError)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-times-circle me-2"></i><?php echo $mensajeError; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-clipboard-check me-2"></i>Registrar Subsanación de Trámite</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-0"><strong>TRÁMITE:</strong> #<?php echo $idTramite; ?> - CIIA: <?php echo $tramite['CIIA']; ?></p>
                    <p class="mb-0"><strong>PROMOVENTE:</strong> <?php echo $tramite['Promovente']; ?></p>
                    <p class="mb-0"><strong>STATUS ACTUAL:</strong> 
                        <span class="badge <?php echo ($tramite['StatusReal'] == 'PREVENIDO') ? 'bg-info' : 
                            (($tramite['StatusReal'] == 'COMPLETA') ? 'bg-success' : 'bg-primary'); ?>">
                            <?php echo $tramite['StatusReal']; ?>
                        </span> 
                        <?php echo $avanceTexto; ?>
                    </p>
                    <p class="mb-0"><strong>ÚLTIMA ACTUALIZACIÓN:</strong> <?php echo $tramite['FechaUltimaActualizacion']->format('d/m/Y'); ?></p>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Información:</strong> Al registrar una subsanación, el estado del trámite cambiará a "Pendiente de Subsanación".
                    Una vez que el RAN responda a la subsanación, deberá registrar un nuevo acuse con la respuesta obtenida.
                </div>
                
                <form method="post" action="" class="mt-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="folio_subsanacion" class="form-label">Folio de Subsanación<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" class="form-control" id="folio_subsanacion" name="folio_subsanacion" 
                                       required placeholder="Ingrese el folio de subsanación">
                            </div>
                            <div class="form-text">Ingrese el folio del documento de subsanación.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_subsanacion" class="form-label">Fecha de Subsanación<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fecha_subsanacion" name="fecha_subsanacion" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción de la Subsanación<span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required
                                  placeholder="Describa detalladamente los motivos de la subsanación y los errores que se están corrigiendo"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="detalle-tramite.php?id=<?php echo $idTramite; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Guardar Subsanación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Establecer la fecha actual como valor predeterminado
    var today = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_subsanacion').value = today;

    // Aquí puedes agregar más JavaScript según sea necesario
});
</script>

<script>
// Si la función aplicarFormatoFolio no está en un archivo global
function aplicarFormatoFolio(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('input', function(e) {
        // Obtener el valor actual sin formato
        let valor = this.value.replace(/\D/g, '');
        
        // Limitar a máximo 8 dígitos (XXXX/YYYY)
        if (valor.length > 8) {
            valor = valor.substring(0, 8);
        }
        
        // Aplicar formato: XXXX/YYYY
        if (valor.length > 4) {
            this.value = valor.substring(0, 4) + '/' + valor.substring(4);
        } else {
            this.value = valor;
        }
    });
    
    // Cuando el campo pierda el foco, verificar que tenga el formato correcto
    input.addEventListener('blur', function() {
        let valor = this.value.replace(/\D/g, '');
        
        // Si tiene más de 4 dígitos pero menos de 8, completar el año actual
        if (valor.length > 4 && valor.length < 8) {
            const añoActual = new Date().getFullYear().toString();
            const digitosAñoFaltantes = 8 - valor.length;
            
            valor = valor.substring(0, 4) + añoActual.substring(0, digitosAñoFaltantes) + valor.substring(4);
            this.value = valor.substring(0, 4) + '/' + valor.substring(4);
        }
    });
}

// Aplicar el formato a todos los campos de folio relevantes en esta página
document.addEventListener('DOMContentLoaded', function() {
    // Verificar cada campo individualmente
    if (document.getElementById('folio_reloj')) {
        aplicarFormatoFolio('folio_reloj');
    }
    if (document.getElementById('folio_rchrp')) {
        aplicarFormatoFolio('folio_rchrp');
    }
    if (document.getElementById('folio_reiteracion')) {
        aplicarFormatoFolio('folio_reiteracion');
    }
    if (document.getElementById('folio_subsanacion')) {
        aplicarFormatoFolio('folio_subsanacion');
    }
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>