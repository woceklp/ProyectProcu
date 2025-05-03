<?php
/**
 * paginas/registrar-acuse.php - Formulario para registrar un acuse de un trámite
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir la función sanitizarEntrada si no existe
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

// También asegúrate de definir la función validarNumeroTramite si no existe
if (!function_exists('validarNumeroTramite')) {
    function validarNumeroTramite($numeroTramite) {
        return (strlen($numeroTramite) === 11 && ctype_digit($numeroTramite));
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
    $numeroAcuse = isset($_POST['numero_acuse']) ? sanitizarEntrada($_POST['numero_acuse']) : '';
    $fechaRecepcion = isset($_POST['fecha_recepcion']) ? sanitizarEntrada($_POST['fecha_recepcion']) : '';
    $nombreRevisor = isset($_POST['nombre_revisor']) ? sanitizarEntrada($_POST['nombre_revisor']) : '';
    $folioReloj = isset($_POST['folio_reloj']) ? sanitizarEntrada($_POST['folio_reloj']) : '';
    $avanceTramite = isset($_POST['avance_tramite']) ? intval($_POST['avance_tramite']) : 0;
    $estatusTramite = isset($_POST['estatus_tramite']) ? intval($_POST['estatus_tramite']) : 0;
    $respuestaRAN = isset($_POST['respuesta_ran']) ? sanitizarEntrada($_POST['respuesta_ran']) : '';
    $comentarios = isset($_POST['comentarios']) ? sanitizarEntrada($_POST['comentarios']) : '';
    
    // Validar datos
    $errores = [];

    // Verificar que al menos uno de los tres campos esté completo
    if (empty($numeroAcuse) && empty($nombreRevisor) && empty($folioReloj)) {
        $errores[] = "Debe proporcionar al menos uno de los siguientes: Número de Acuse, Nombre del Revisor o Folio Reloj";
    }

    // Si se proporcionó un número de acuse, validar su formato
    if (!empty($numeroAcuse) && !validarNumeroTramite($numeroAcuse)) {
        $errores[] = "El número de acuse debe tener exactamente 11 dígitos numéricos";
    }
          
    if (empty($fechaRecepcion)) {
        $errores[] = "La fecha de recepción por el RAN es requerida";
    }
    
    if ($avanceTramite <= 0) {
        $errores[] = "Debe seleccionar un avance para el trámite";
    }

    if ($estatusTramite <= 0) {
        $errores[] = "Debe seleccionar un estatus para el trámite";
    }
    
    if (empty($respuestaRAN)) {
        $errores[] = "Debe seleccionar una respuesta del RAN";
    }

    // Verificar si ya existe un acuse con el mismo número para cualquier trámite
    if (!empty($numeroAcuse)) {
        $sqlVerificar = "SELECT COUNT(*) AS total FROM Acuses WHERE NumeroAcuse = ?";
        $paramsVerificar = array($numeroAcuse);
        $resultadoVerificar = ejecutarConsulta($sqlVerificar, $paramsVerificar);
        
        if ($resultadoVerificar['status'] === 'success') {
            $row = sqlsrv_fetch_array($resultadoVerificar['stmt'], SQLSRV_FETCH_ASSOC);
            cerrarConexion($resultadoVerificar['conn'], $resultadoVerificar['stmt']);
            
            if ($row['total'] > 0) {
                $errores[] = "Ya existe un acuse registrado con ese número";
            }
        }
    }

    // Si no hay errores, guardar el acuse
    if (empty($errores)) {
        // Consultar estado actual antes de actualizar
        $sqlEstadoActual = "SELECT ID_EstadoTramite FROM Tramites WHERE ID_Tramite = ?";
        $resultadoEstadoActual = ejecutarConsulta($sqlEstadoActual, array($idTramite));
        
        if ($resultadoEstadoActual['status'] !== 'success') {
            $errores[] = "Error al consultar el estado actual del trámite";
        } else {
            $rowEstadoActual = sqlsrv_fetch_array($resultadoEstadoActual['stmt'], SQLSRV_FETCH_ASSOC);
            $estadoAnterior = $rowEstadoActual['ID_EstadoTramite'];
            cerrarConexion($resultadoEstadoActual['conn'], $resultadoEstadoActual['stmt']);

            // Convertir fecha a formato SQL Server (Y-m-d)
            $fechaFormateada = date('Y-m-d', strtotime($fechaRecepcion));
            
            // Consulta SQL para insertar nuevo acuse
            $sqlInsertar = "INSERT INTO Acuses (
                ID_Tramite, NumeroAcuse, FechaRecepcionRAN, 
                NombreRevisor, FolioReloj, ID_EstadoTramite, 
                EstadoDescriptivo, ID_EstadoBasico, Respuesta, FechaRegistro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

            $paramsInsertar = array(
                $idTramite,
                $numeroAcuse ?: null,
                $fechaFormateada,
                $nombreRevisor ?: null,
                $folioReloj ?: null,
                $avanceTramite,         // Avance del trámite
                $respuestaRAN,          // Respuesta del RAN
                $estatusTramite,        // Estatus básico del trámite
                $comentarios ?: null
            );
            
            $resultadoInsertar = ejecutarConsulta($sqlInsertar, $paramsInsertar);
            
            if ($resultadoInsertar['status'] === 'success') {
                cerrarConexion($resultadoInsertar['conn'], $resultadoInsertar['stmt']);

                // Determinar el estado nuevo real para actualizar el trámite
                $estadoNuevoReal = $avanceTramite;

                // Si el estado básico es PREVENIDO (ID 2), establecer estado trámite como 7 (Prevenido)
                if ($estatusTramite == 2) {
                    $estadoNuevoReal = 7; // ID para estado "Prevenido" en EstadosTramite
                }
                
                // Actualizar el estado del trámite y el estado básico
                $sqlActualizar = "UPDATE Tramites 
                                SET ID_EstadoTramite = ?, 
                                    ID_EstadoBasico = ?,
                                    FechaUltimaActualizacion = GETDATE() 
                                WHERE ID_Tramite = ?";

                // Si el estado básico es PREVENIDO (ID 2), usamos el mismo ID de avance pero marcamos el básico como PREVENIDO
                $paramsActualizar = array($avanceTramite, $estatusTramite, $idTramite);
                
                $paramsActualizar = array($estadoNuevoReal, $estatusTramite, $idTramite);
                $resultadoActualizar = ejecutarConsulta($sqlActualizar, $paramsActualizar);
                
                if ($resultadoActualizar['status'] === 'success') {
                    cerrarConexion($resultadoActualizar['conn'], $resultadoActualizar['stmt']);

                    // Registrar en historial de cambios
                    $sqlHistorial = "INSERT INTO HistorialCambios (
                        ID_Tramite, EstadoAnterior, EstadoNuevo, 
                        Observacion, UsuarioResponsable, TipoAccion
                        ) VALUES (?, ?, ?, ?, ?, ?)";
        
                    $observacionHistorial = "";
                    if (!empty($numeroAcuse)) {
                        $observacionHistorial .= "Registro de acuse: {$numeroAcuse}";
                    }
                    if (!empty($nombreRevisor)) {
                        $observacionHistorial .= (!empty($observacionHistorial) ? ", " : "") . "Revisor: {$nombreRevisor}";
                    }
                    if (!empty($folioReloj)) {
                        $observacionHistorial .= (!empty($observacionHistorial) ? ", " : "") . "Folio Reloj: {$folioReloj}";
                    }
                    if (empty($observacionHistorial)) {
                        $observacionHistorial = "Actualización de estado del trámite";
                    }
                    
                    // Añadir información del estado básico
                    $observacionHistorial .= ", Estado: " . ($estatusTramite == 2 ? "PREVENIDO" : ($estatusTramite == 1 ? "COMPLETA" : "EN PROCESO"));
                    
                    $paramsHistorial = array(
                        $idTramite, 
                        $estadoAnterior, 
                        $estadoNuevoReal, 
                        $observacionHistorial, 
                        'Sistema',
                        'Registro de Acuse'
                    );
                    
                    $resultadoHistorial = ejecutarConsulta($sqlHistorial, $paramsHistorial);
                    
                    if ($resultadoHistorial['status'] === 'success') {
                        cerrarConexion($resultadoHistorial['conn'], $resultadoHistorial['stmt']);
                        
                        // Sincronizar el estado del trámite con sus acuses
                        actualizarStatusTramiteSegunAcuses($idTramite);
                        
                        // Redireccionar directamente con PHP
                        header("Location: detalle-tramite.php?id=" . $idTramite . "&acuse_registrado=1");
                        exit;
                    }
                } else {
                    $mensajeError = "Error al actualizar el estado del trámite: " . $resultadoActualizar['message'];
                }
            } else {
                $mensajeError = "Error al registrar el acuse: " . $resultadoInsertar['message'];
            }
        }
    } else {
        $mensajeError = implode("<br>", $errores);
    }
}

// Consultar información del trámite
$sqlTramite = "SELECT t.CIIA, 
              p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
              e.ID_EstadoTramite, 
              e.Nombre AS Estado,
              e.Porcentaje,
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

// Consultar estados de trámite para el AVANCE
$sqlEstados = "SELECT ID_EstadoTramite, Nombre, Porcentaje 
              FROM EstadosTramite 
              ORDER BY Porcentaje";

$resultadoEstados = ejecutarConsulta($sqlEstados);
$estados = ($resultadoEstados['status'] === 'success') 
          ? obtenerResultados($resultadoEstados['stmt']) 
          : array();
if ($resultadoEstados['status'] === 'success') {
    cerrarConexion($resultadoEstados['conn'], $resultadoEstados['stmt']);
}

// Consultar los estados básicos para el ESTATUS
$sqlEstadosBasicos = "SELECT ID_EstadoBasico, Nombre 
                     FROM EstadosBasicos 
                     ORDER BY Nombre";

$resultadoEstadosBasicos = ejecutarConsulta($sqlEstadosBasicos);
$estadosBasicos = ($resultadoEstadosBasicos['status'] === 'success') 
                ? obtenerResultados($resultadoEstadosBasicos['stmt']) 
                : array();
if ($resultadoEstadosBasicos['status'] === 'success') {
    cerrarConexion($resultadoEstadosBasicos['conn'], $resultadoEstadosBasicos['stmt']);
}

// Consultar los estados descriptivos para la RESPUESTA
$sqlEstadosDesc = "SELECT ID_EstadoDescriptivo, Nombre 
                  FROM EstadosDescriptivos 
                  ORDER BY Nombre";

$resultadoEstadosDesc = ejecutarConsulta($sqlEstadosDesc);
$estadosDesc = ($resultadoEstadosDesc['status'] === 'success') 
              ? obtenerResultados($resultadoEstadosDesc['stmt']) 
              : array();
if ($resultadoEstadosDesc['status'] === 'success') {
    cerrarConexion($resultadoEstadosDesc['conn'], $resultadoEstadosDesc['stmt']);
}

?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php">LISTADO DE TRÁMITES</a></li>
                <li class="breadcrumb-item"><a href="detalle-tramite.php?id=<?php echo $idTramite; ?>">DETALLE DE TRÁMITE #<?php echo $idTramite; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">REGISTRAR ACUSE</li>
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
                <h5><i class="fas fa-file-alt me-2"></i>Registrar Acuse / Número de Trámite</h5>
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
                </div>

                <form method="post" action="" class="mt-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="numero_acuse" class="form-label">NÚMERO DE TRÁMITE/ACUSE (11 DÍGITOS)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" class="form-control" id="numero_acuse" name="numero_acuse" 
                                maxlength="11" pattern="[0-9]{11}"
                                placeholder="Ejemplo: 08250001236">
                            </div>
                            <div class="form-text">EL NÚMERO DE ACUSE ES OPCIONAL SI PROPORCIONA EL NOMBRE DEL REVISOR O FOLIO RELOJ.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_recepcion" class="form-label">FECHA DE RECEPCIÓN POR EL RAN<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="fecha_recepcion" name="fecha_recepcion" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_revisor" class="form-label">NOMBRE DEL REVISOR</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="nombre_revisor" name="nombre_revisor" 
                                       placeholder="Nombre de quien revisará el trámite">
                            </div>
                            <div class="form-text">OPCIONAL SI PROPORCIONA NÚMERO DE ACUSE O FOLIO RELOJ.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="folio_reloj" class="form-label">FOLIO RELOJ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="text" class="form-control" id="folio_reloj" name="folio_reloj" 
                                       placeholder="Ejemplo: Reloj:0125/2025">
                            </div>
                            <div class="form-text">OPCIONAL SI PROPORCIONA EL NÚMERO DE ACUSE O NOMBRE DEL REVISOR.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="avance_tramite" class="form-label">AVANCE DEL TRÁMITE<span class="text-danger">*</span></label>
                            <select class="form-select" id="avance_tramite" name="avance_tramite" required>
                                <option value="">SELECCIONE...</option>
                                <?php foreach($estados as $estado): ?>
                                    <?php if($estado['Porcentaje'] > 0): // Excluir estado con 0% ?>
                                    <option value="<?php echo $estado['ID_EstadoTramite']; ?>">
                                        <?php echo $estado['Porcentaje'] == 25 ? '1 DE 4 (AVANCE 25%)' : 
                                               ($estado['Porcentaje'] == 50 ? '2 DE 4 (AVANCE 50%)' : 
                                               ($estado['Porcentaje'] == 75 ? '3 DE 4 (AVANCE 75%)' : 
                                               ($estado['Porcentaje'] == 100 ? '4 DE 4 (AVANCE 100%)' : $estado['Nombre'] . ' (' . $estado['Porcentaje'] . '%)'))); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Seleccione el porcentaje de avance en el trámite.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="estatus_tramite" class="form-label">STATUS DEL TRÁMITE<span class="text-danger">*</span></label>
                            <select class="form-select" id="estatus_tramite" name="estatus_tramite" required>
                                <option value="">SELECCIONE...</option>
                                <?php foreach($estadosBasicos as $estadoBasico): ?>
                                <option value="<?php echo $estadoBasico['ID_EstadoBasico']; ?>">
                                    <?php echo $estadoBasico['Nombre']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Seleccione si el trámite está COMPLETO, PREVENIDO o EN PROCESO.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="respuesta_ran" class="form-label">RESPUESTA DEL RAN<span class="text-danger">*</span></label>
                            <select class="form-select" id="respuesta_ran" name="respuesta_ran" required>
                                <option value="">SELECCIONE...</option>
                                <?php foreach($estadosDesc as $estadoDesc): ?>
                                <option value="<?php echo $estadoDesc['ID_EstadoDescriptivo']; ?>">
                                    <?php echo $estadoDesc['Nombre']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Seleccione la respuesta proporcionada por el RAN.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="comentarios" class="form-label">COMENTARIOS ADICIONALES</label>
                        <textarea class="form-control" id="comentarios" name="comentarios" rows="3" 
                                placeholder="Información adicional o notas sobre este acuse"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="detalle-tramite.php?id=<?php echo $idTramite; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>CANCELAR
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>GUARDAR ACUSE
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Esperar a que jQuery esté disponible
    function iniciarCuandoJQueryEsteDisponible() {
        if (typeof jQuery !== 'undefined') {
            // Ahora jQuery está disponible, podemos usarlo con seguridad
            jQuery(document).ready(function($) {
                // Establecer la fecha actual como valor predeterminado
                var today = new Date().toISOString().split('T')[0];
                $('#fecha_recepcion').val(today);
                
                // Validar número de acuse (solo dígitos y máximo 11)
                $('#numero_acuse').on('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);
                });
                
                // Manejar el envío del formulario con SweetAlert para confirmación
                $('form').on('submit', function(e) {
                    e.preventDefault(); // Evitar el envío inmediato del formulario
                    
                    const numeroAcuse = $('#numero_acuse').val().trim();
                    const nombreRevisor = $('#nombre_revisor').val().trim();
                    const folioReloj = $('#folio_reloj').val().trim();
                    
                    // Validar que al menos uno de los tres campos esté lleno
                    if (numeroAcuse === '' && nombreRevisor === '' && folioReloj === '') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Campos requeridos',
                            text: 'Debe proporcionar al menos uno de los siguientes: Número de Acuse, Nombre del Revisor o Folio Reloj'
                        });
                        return false;
                    }
                    
                    // Si proporcionó número de acuse, validar que tenga 11 dígitos
                    if (numeroAcuse !== '' && numeroAcuse.length !== 11) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de formato',
                            text: 'El número de acuse debe tener exactamente 11 dígitos'
                        });
                        return false;
                    }
                    
                    // Si pasa todas las validaciones, mostrar diálogo de confirmación
                    Swal.fire({
                        title: '¿Está seguro?',
                        text: "Va a registrar un nuevo acuse para este trámite",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, guardar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Si el usuario confirma, enviar el formulario
                            this.submit();
                        }
                    });
                });
            });
        } else {
            // Si jQuery todavía no está disponible, esperar un poco y verificar de nuevo
            setTimeout(iniciarCuandoJQueryEsteDisponible, 100);
        }
    }
    
    // Iniciar el proceso de verificación de jQuery
    iniciarCuandoJQueryEsteDisponible();
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>