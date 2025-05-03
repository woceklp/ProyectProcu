<?php
/**
 * paginas/documentos.php - Página principal para la generación de documentos
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Consultar las categorías de documentos
$sqlCategorias = "SELECT DISTINCT Categoria FROM TiposDocumento WHERE Activo = 1 ORDER BY Categoria";
$resultadoCategorias = ejecutarConsulta($sqlCategorias);
$categorias = [];

if ($resultadoCategorias['status'] === 'success') {
    $categorias = obtenerResultados($resultadoCategorias['stmt']);
    cerrarConexion($resultadoCategorias['conn'], $resultadoCategorias['stmt']);
}

// Obtener la categoría seleccionada (si existe)
$categoriaSeleccionada = isset($_GET['categoria']) ? sanitizarEntrada($_GET['categoria']) : '';

// Consultar los tipos de documentos
$sqlDocumentos = "SELECT ID_TipoDocumento, Nombre, Descripcion, Categoria 
                 FROM TiposDocumento 
                 WHERE Activo = 1 " . 
                 ($categoriaSeleccionada ? "AND Categoria = '" . $categoriaSeleccionada . "'" : "") . 
                 " ORDER BY Nombre";
$resultadoDocumentos = ejecutarConsulta($sqlDocumentos);
$documentos = [];

if ($resultadoDocumentos['status'] === 'success') {
    $documentos = obtenerResultados($resultadoDocumentos['stmt']);
    cerrarConexion($resultadoDocumentos['conn'], $resultadoDocumentos['stmt']);
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item active" aria-current="page">GENERACIÓN DE DOCUMENTOS</li>
            </ol>
        </nav>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-file-alt me-2"></i>GENERACIÓN DE DOCUMENTOS</h5>
            </div>
            <div class="card-body">
                <!-- Buscador y filtros -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" id="buscarDocumento" placeholder="Buscar documento por nombre...">
                            <button class="btn btn-outline-primary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="filtroCategoria" onchange="window.location='documentos.php?categoria='+this.value">
                            <option value="">Todas las categorías</option>
                            <?php foreach($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['Categoria']; ?>" <?php echo ($categoria['Categoria'] == $categoriaSeleccionada) ? 'selected' : ''; ?>>
                                <?php echo $categoria['Categoria']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Categorías -->
                <div class="mb-4">
                    <h6>CATEGORÍAS:</h6>
                    <div class="btn-group mb-3">
                        <a href="documentos.php" class="btn <?php echo empty($categoriaSeleccionada) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Todas
                        </a>
                        <?php foreach($categorias as $categoria): ?>
                        <a href="documentos.php?categoria=<?php echo $categoria['Categoria']; ?>" 
                           class="btn <?php echo ($categoria['Categoria'] == $categoriaSeleccionada) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <?php echo $categoria['Categoria']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Documentos disponibles -->
                <h6>DOCUMENTOS DISPONIBLES:</h6>
                <div class="row">
                    <?php if(count($documentos) > 0): ?>
                        <?php foreach($documentos as $documento): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><?php echo $documento['Nombre']; ?></h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?php echo $documento['Descripcion']; ?></p>
                                    <p class="badge bg-info"><?php echo $documento['Categoria']; ?></p>
                                </div>
                                <div class="card-footer bg-white">
                                    <a href="generar-documento.php?tipo=<?php echo $documento['ID_TipoDocumento']; ?>" class="btn btn-primary w-100">
                                        <i class="fas fa-file-alt me-1"></i>GENERAR DOCUMENTO
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No hay documentos disponibles en esta categoría.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Historial de documentos -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>VOLVER AL INICIO
                    </a>
                    <a href="historial-documentos.php" class="btn btn-primary">
                        <i class="fas fa-history me-1"></i>VER HISTORIAL DE DOCUMENTOS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Funcionalidad para el buscador
    $('#buscarDocumento').on('keyup', function() {
        const valor = $(this).val().toLowerCase();
        $('.card').each(function() {
            const titulo = $(this).find('.card-header h6').text().toLowerCase();
            const descripcion = $(this).find('.card-body p').text().toLowerCase();
            
            if (titulo.indexOf(valor) > -1 || descripcion.indexOf(valor) > -1) {
                $(this).parent().show();
            } else {
                $(this).parent().hide();
            }
        });
    });
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>