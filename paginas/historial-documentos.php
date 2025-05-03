<?php
/**
 * paginas/historial-documentos.php - Historial de documentos generados
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Obtener filtros
$promoventeId = isset($_GET['promovente']) ? intval($_GET['promovente']) : 0;
$tipoDocumentoId = isset($_GET['tipo']) ? intval($_GET['tipo']) : 0;
$fechaDesde = isset($_GET['desde']) ? sanitizarEntrada($_GET['desde']) : '';
$fechaHasta = isset($_GET['hasta']) ? sanitizarEntrada($_GET['hasta']) : '';

// Construir la consulta SQL con filtros
$sql = "SELECT d.ID_Documento, d.Nombre, td.Nombre AS TipoDocumento, 
               p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
               t.CIIA, d.FechaGeneracion, d.Estado
        FROM Documentos d
        INNER JOIN TiposDocumento td ON d.ID_TipoDocumento = td.ID_TipoDocumento
        LEFT JOIN Promoventes p ON d.ID_Promovente = p.ID_Promovente
        LEFT JOIN Tramites t ON d.ID_Tramite = t.ID_Tramite
        WHERE 1=1";

$params = array();

if ($promoventeId > 0) {
    $sql .= " AND d.ID_Promovente = ?";
    $params[] = $promoventeId;
}

if ($tipoDocumentoId > 0) {
    $sql .= " AND d.ID_TipoDocumento = ?";
    $params[] = $tipoDocumentoId;
}

if (!empty($fechaDesde)) {
    $sql .= " AND d.FechaGeneracion >= ?";
    $params[] = $fechaDesde . ' 00:00:00';
}

if (!empty($fechaHasta)) {
    $sql .= " AND d.FechaGeneracion <= ?";
    $params[] = $fechaHasta . ' 23:59:59';
}

$sql .= " ORDER BY d.FechaGeneracion DESC";

// Ejecutar la consulta
$resultado = ejecutarConsulta($sql, $params);
$documentos = [];

if ($resultado['status'] === 'success') {
    $documentos = obtenerResultados($resultado['stmt']);
    cerrarConexion($resultado['conn'], $resultado['stmt']);
}

// Obtener tipos de documentos para el filtro
$sqlTiposDocumento = "SELECT ID_TipoDocumento, Nombre FROM TiposDocumento WHERE Activo = 1 ORDER BY Nombre";
$resultadoTiposDocumento = ejecutarConsulta($sqlTiposDocumento);
$tiposDocumento = [];

if ($resultadoTiposDocumento['status'] === 'success') {
    $tiposDocumento = obtenerResultados($resultadoTiposDocumento['stmt']);
    cerrarConexion($resultadoTiposDocumento['conn'], $resultadoTiposDocumento['stmt']);
}

// Obtener promoventes para el filtro
$sqlPromoventes = "SELECT DISTINCT p.ID_Promovente, p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS NombreCompleto
                  FROM Promoventes p
                  INNER JOIN Documentos d ON p.ID_Promovente = d.ID_Promovente
                  ORDER BY NombreCompleto";
$resultadoPromoventes = ejecutarConsulta($sqlPromoventes);
$promoventes = [];

if ($resultadoPromoventes['status'] === 'success') {
    $promoventes = obtenerResultados($resultadoPromoventes['stmt']);
    cerrarConexion($resultadoPromoventes['conn'], $resultadoPromoventes['stmt']);
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item"><a href="documentos.php">GENERACIÓN DE DOCUMENTOS</a></li>
                <li class="breadcrumb-item active" aria-current="page">HISTORIAL DE DOCUMENTOS</li>
            </ol>
        </nav>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-history me-2"></i>HISTORIAL DE DOCUMENTOS</h5>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form action="" method="get" class="mb-4">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="promovente" class="form-label">PROMOVENTE</label>
                            <select class="form-select" id="promovente" name="promovente">
                                <option value="">Todos los promoventes</option>
                                <?php foreach($promoventes as $promovente): ?>
                                <option value="<?php echo $promovente['ID_Promovente']; ?>" <?php echo ($promovente['ID_Promovente'] == $promoventeId) ? 'selected' : ''; ?>>
                                    <?php echo $promovente['NombreCompleto']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="tipo" class="form-label">TIPO DE DOCUMENTO</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos los tipos</option>
                                <?php foreach($tiposDocumento as $tipo): ?>
                                <option value="<?php echo $tipo['ID_TipoDocumento']; ?>" <?php echo ($tipo['ID_TipoDocumento'] == $tipoDocumentoId) ? 'selected' : ''; ?>>
                                    <?php echo $tipo['Nombre']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="desde" class="form-label">FECHA DESDE</label>
                            <input type="date" class="form-control" id="desde" name="desde" value="<?php echo $fechaDesde; ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="hasta" class="form-label">FECHA HASTA</label>
                            <input type="date" class="form-control" id="hasta" name="hasta" value="<?php echo $fechaHasta; ?>">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Filtrar
                                </button>
                                <a href="historial-documentos.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-eraser me-1"></i>Limpiar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Tabla de resultados -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>DOCUMENTO</th>
                                <th>TIPO</th>
                                <th>PROMOVENTE</th>
                                <th>CIIA</th>
                                <th>FECHA</th>
                                <th>ESTADO</th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($documentos) > 0): ?>
                                <?php foreach($documentos as $documento): ?>
                                    <tr>
                                        <td><?php echo $documento['Nombre']; ?></td>
                                        <td><?php echo $documento['TipoDocumento']; ?></td>
                                        <td><?php echo $documento['Promovente'] ?? 'N/A'; ?></td>
                                        <td><?php echo $documento['CIIA'] ?? 'N/A'; ?></td>
                                        <td><?php echo $documento['FechaGeneracion']->format('d/m/Y H:i'); ?></td>
                                        <td>
                                            <span class="badge <?php echo ($documento['Estado'] == 'FINALIZADO') ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $documento['Estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="../archivos/documentos/<?php echo $documento['ID_Documento']; ?>.pdf" target="_blank" class="btn btn-sm btn-info" title="Ver documento">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="descargar-documento.php?id=<?php echo $documento['ID_Documento']; ?>" class="btn btn-sm btn-success" title="Descargar">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if($documento['Estado'] == 'BORRADOR'): ?>
                                                <a href="generar-documento.php?id=<?php echo $documento['ID_Documento']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No se encontraron documentos con los filtros seleccionados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Botones inferiores -->
                <div class="d-flex justify-content-between mt-4">
                    <a href="documentos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>VOLVER A DOCUMENTOS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>