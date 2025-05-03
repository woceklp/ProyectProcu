<?php
/**
 * paginas/reporte-prevenidos.php - Reporte de trámites prevenidos
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Obtener ID de trámites a excluir (si existen)
$excluir = isset($_GET['excluir']) ? explode(',', $_GET['excluir']) : [];
$excludeClause = '';

if (!empty($excluir)) {
    $excludeClause = " AND t.ID_Tramite NOT IN (" . implode(',', array_map('intval', $excluir)) . ")";
}

// Consulta para obtener los trámites prevenidos
$sql = "SELECT t.ID_Tramite, 
               p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
               t.CIIA, 
               t.FolioRCHRP,
               m.Nombre AS Municipio,
               tna.Descripcion AS TipoNucleoAgrario,
               na.Nombre AS NucleoAgrario,
               ct.Clave AS ClaveTramite,
               ct.Descripcion AS DescripcionTramite,
               e.Nombre AS Estado,
               DATEDIFF(day, t.FechaUltimaActualizacion, GETDATE()) AS DiasTranscurridos
        FROM Tramites t 
        INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente 
        INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite 
        INNER JOIN ClavesTramite ct ON t.ID_ClaveTramite = ct.ID_ClaveTramite
        INNER JOIN Municipios m ON t.ID_Municipio = m.ID_Municipio
        INNER JOIN NucleosAgrarios na ON t.ID_NucleoAgrario = na.ID_NucleoAgrario
        INNER JOIN TiposNucleoAgrario tna ON na.ID_TipoNucleoAgrario = tna.ID_TipoNucleoAgrario
        INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
        WHERE t.ID_EstadoTramite = 7 " . $excludeClause . " 
        ORDER BY DiasTranscurridos DESC";

$resultado = ejecutarConsulta($sql);
$tramites = [];

if ($resultado['status'] === 'success') {
    $tramites = obtenerResultados($resultado['stmt']);
    cerrarConexion($resultado['conn'], $resultado['stmt']);
}
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="listado-tramites.php?filtro=prevenidos">Trámites Prevenidos</a></li>
                <li class="breadcrumb-item active" aria-current="page">Reporte para Impresión</li>
            </ol>
        </nav>
        
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-exclamation-circle me-2"></i>Reporte de Trámites Prevenidos</h5>
                <div>
                    <button id="btnImprimir" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-print me-1"></i>Imprimir Reporte
                    </button>
                    <a href="generar-pdf-prevenidos.php<?php echo !empty($excluir) ? '?excluir=' . implode(',', $excluir) : ''; ?>" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-file-pdf me-1"></i>Generar PDF
                    </a>
                    <a href="listado-tramites.php?filtro=prevenidos" class="btn btn-light btn-sm">
                        <i class="fas fa-list me-1"></i>Ver Listado Completo
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Botones de acción para manipular el reporte -->
                <div class="mb-3" id="accionesBtns">
                    <button id="btnSeleccionarTodos" class="btn btn-outline-primary">
                        <i class="fas fa-check-square me-1"></i>Seleccionar Todos
                    </button>
                    <button id="btnDeseleccionarTodos" class="btn btn-outline-secondary">
                        <i class="fas fa-square me-1"></i>Deseleccionar Todos
                    </button>
                    <button id="btnExcluirSeleccionados" class="btn btn-outline-danger">
                        <i class="fas fa-minus-circle me-1"></i>Excluir Seleccionados
                    </button>
                    <?php if (!empty($excluir)): ?>
                        <a href="reporte-prevenidos.php" class="btn btn-outline-success">
                            <i class="fas fa-sync me-1"></i>Restablecer Lista
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Tabla de datos para el reporte -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="tablaReporte">
                        <thead class="table-light">
                            <tr>
                                <th class="no-print"><input type="checkbox" id="checkAll"></th>
                                <th>CIIA</th>
                                <th>Folio RCHRP</th>
                                <th>Promovente</th>
                                <th>Municipio</th>
                                <th>Tipo N.A.</th>
                                <th>Núcleo Agrario</th>
                                <th>Clave</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Días</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tramites) > 0): ?>
                                <?php foreach ($tramites as $tramite): ?>
                                    <tr>
                                        <td class="no-print">
                                            <input type="checkbox" class="check-item" value="<?php echo $tramite['ID_Tramite']; ?>">
                                        </td>
                                        <td><?php echo $tramite['CIIA']; ?></td>
                                        <td><?php echo $tramite['FolioRCHRP'] ?? 'N/A'; ?></td>
                                        <td><?php echo $tramite['Promovente']; ?></td>
                                        <td><?php echo $tramite['Municipio']; ?></td>
                                        <td><?php echo $tramite['TipoNucleoAgrario']; ?></td>
                                        <td><?php echo $tramite['NucleoAgrario']; ?></td>
                                        <td><?php echo $tramite['ClaveTramite']; ?></td>
                                        <td><?php echo $tramite['DescripcionTramite']; ?></td>
                                        <td><?php echo $tramite['Estado']; ?></td>
                                        <td><?php echo $tramite['DiasTranscurridos']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center">No hay trámites prevenidos</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3" id="contadorRegistros">
                    <strong>Total de trámites:</strong> <?php echo count($tramites); ?>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                    </a>
                    <!-- Otros botones si los hay -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
        
        #accionesBtns, .breadcrumb, .btn, .navbar, footer {
            display: none !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .card-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        table {
            width: 100% !important;
        }
        
        body {
            padding: 0 !important;
            margin: 0 !important;
        }
    }
</style>

<script>
// Esperar a que el documento esté listo
$(document).ready(function() {
    // Checkbox 'Seleccionar todos'
    $('#checkAll').change(function() {
        $('.check-item').prop('checked', $(this).prop('checked'));
    });
    
    // Al hacer clic en 'Seleccionar Todos'
    $('#btnSeleccionarTodos').click(function() {
        $('.check-item').prop('checked', true);
        $('#checkAll').prop('checked', true);
    });
    
    // Al hacer clic en 'Deseleccionar Todos'
    $('#btnDeseleccionarTodos').click(function() {
        $('.check-item').prop('checked', false);
        $('#checkAll').prop('checked', false);
    });
    
    // Al hacer clic en 'Excluir Seleccionados'
    $('#btnExcluirSeleccionados').click(function() {
        var seleccionados = [];
        $('.check-item:checked').each(function() {
            seleccionados.push($(this).val());
        });
        
        if (seleccionados.length === 0) {
            alert('Debe seleccionar al menos un trámite para excluir');
            return;
        }
        
        // Obtener IDs ya excluidos (si existen)
        var excluirActuales = <?php echo json_encode($excluir); ?>;
        var nuevosExcluidos = excluirActuales.concat(seleccionados);
        
        // Redireccionar con los parámetros actualizados
        window.location.href = 'reporte-prevenidos.php?excluir=' + nuevosExcluidos.join(',');
    });
    
    // Al hacer clic en 'Imprimir Reporte'
    $('#btnImprimir').click(function() {
        window.print();
    });
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>