<?php
/**
 * paginas/editar-promovente.php - Formulario para editar un promovente
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Obtener el ID del promovente, referrer y tramite ID de la URL
$idPromovente = isset($_GET['id']) ? intval($_GET['id']) : 0;
$referrer = isset($_GET['referrer']) ? sanitizarEntrada($_GET['referrer']) : 'lista';
$tramiteId = isset($_GET['tramite']) ? intval($_GET['tramite']) : 0;

if ($idPromovente <= 0) {
    // Redirigir si no hay ID válido
    echo '<script>window.location.href = "lista-promoventes.php";</script>';
    exit;
}

// Verificar si se ha enviado el formulario
$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar datos del formulario
    $nombre = isset($_POST['nombre']) ? sanitizarEntrada($_POST['nombre']) : '';
    $apellidoPaterno = isset($_POST['apellido_paterno']) ? sanitizarEntrada($_POST['apellido_paterno']) : '';
    $apellidoMaterno = isset($_POST['apellido_materno']) ? sanitizarEntrada($_POST['apellido_materno']) : '';
    $telefono = isset($_POST['telefono']) ? sanitizarEntrada($_POST['telefono']) : '';
    $telefono2 = isset($_POST['telefono2']) ? sanitizarEntrada($_POST['telefono2']) : '';
    $direccion = isset($_POST['direccion']) ? sanitizarEntrada($_POST['direccion']) : '';
    
    // Validar datos requeridos
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }
    
    // Si no hay errores, actualizar el promovente
    if (empty($errores)) {
        // Sanitizar valores posiblemente vacíos
        if (empty($apellidoPaterno)) $apellidoPaterno = 'N/A';
        if (empty($apellidoMaterno)) $apellidoMaterno = 'N/A';
        
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
        
        if ($resultado['status'] === 'success') {
            cerrarConexion($resultado['conn'], $resultado['stmt']);
            
            // Redireccionar según de donde vino
            if ($referrer === 'detalle' && $tramiteId > 0) {
                header("Location: detalle-tramite.php?id=" . $tramiteId . "&update_promovente=1");
            } else {
                header("Location: lista-promoventes.php?update_promovente=1");
            }
            exit;
        } else {
            $mensajeError = "Error al actualizar los datos: " . $resultado['message'];
        }
    } else {
        $mensajeError = implode("<br>", $errores);
    }
}

// Consultar datos del promovente
$sqlPromovente = "SELECT Nombre, ApellidoPaterno, ApellidoMaterno, Telefono, Telefono2, Direccion 
                 FROM Promoventes WHERE ID_Promovente = ?";
$resultadoPromovente = ejecutarConsulta($sqlPromovente, array($idPromovente));

if ($resultadoPromovente['status'] !== 'success') {
    echo '<div class="alert alert-danger">Error al cargar los datos del promovente: '.$resultadoPromovente['message'].'</div>';
    include_once '../modulos/footer.php';
    exit;
}

$promovente = sqlsrv_fetch_array($resultadoPromovente['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoPromovente['conn'], $resultadoPromovente['stmt']);

if (!$promovente) {
    echo '<div class="alert alert-warning">El promovente solicitado no existe o ha sido eliminado.</div>';
    include_once '../modulos/footer.php';
    exit;
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <?php if ($referrer === 'detalle' && $tramiteId > 0): ?>
                <li class="breadcrumb-item"><a href="detalle-tramite.php?id=<?php echo $tramiteId; ?>">DETALLE DE TRÁMITE #<?php echo $tramiteId; ?></a></li>
                <?php else: ?>
                <li class="breadcrumb-item"><a href="lista-promoventes.php">LISTADO DE PROMOVENTES</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page">EDITAR PROMOVENTE</li>
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
                <h5><i class="fas fa-user-edit me-2"></i>EDITAR PROMOVENTE</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="id_promovente" value="<?php echo $idPromovente; ?>">
                    <input type="hidden" name="referrer" value="<?php echo $referrer; ?>">
                    <input type="hidden" name="tramite_id" value="<?php echo $tramiteId; ?>">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nombre" class="form-label">Nombre(s)<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($promovente['Nombre']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="apellido_paterno" class="form-label">Apellido Paterno</label>
                            <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?php echo ($promovente['ApellidoPaterno'] !== 'N/A') ? htmlspecialchars($promovente['ApellidoPaterno']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="apellido_materno" class="form-label">Apellido Materno</label>
                            <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?php echo ($promovente['ApellidoMaterno'] !== 'N/A') ? htmlspecialchars($promovente['ApellidoMaterno']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono Principal</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="telefono" name="telefono" 
                                       maxlength="10" pattern="[0-9]{10}" placeholder="10 dígitos"
                                       value="<?php echo htmlspecialchars($promovente['Telefono'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono2" class="form-label">Teléfono Secundario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="telefono2" name="telefono2" 
                                       maxlength="10" pattern="[0-9]{10}" placeholder="10 dígitos (opcional)"
                                       value="<?php echo htmlspecialchars($promovente['Telefono2'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2" 
                                      placeholder="Dirección completa"><?php echo htmlspecialchars($promovente['Direccion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <?php if ($referrer === 'detalle' && $tramiteId > 0): ?>
                        <a href="detalle-tramite.php?id=<?php echo $tramiteId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>CANCELAR
                        </a>
                        <?php else: ?>
                        <a href="lista-promoventes.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>CANCELAR
                        </a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>GUARDAR CAMBIOS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>