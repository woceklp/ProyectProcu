<?php
/**
 * paginas/reporte-reiteraciones.php - Reporte de trámites por reiterarse
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

// Consulta para obtener los trámites por reiterarse
$sql = "WITH UltimasFechas AS (
    SELECT t.ID_Tramite, 
        p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
        t.CIIA, 
        t.FolioRCHRP,
        t.FechaRCHRP,
        m.Nombre AS Municipio,
        tna.Descripcion AS TipoNucleoAgrario,
        na.Nombre AS NucleoAgrario,
        ct.Clave AS ClaveTramite,
        e.Nombre AS Estado, 
        e.Porcentaje AS Avance,
        (SELECT COUNT(*) FROM Reiteraciones r WHERE r.ID_Tramite = t.ID_Tramite) AS NumeroReiteraciones,
        COALESCE(
            (SELECT TOP 1 r.FechaReiteracion 
            FROM Reiteraciones r 
            WHERE r.ID_Tramite = t.ID_Tramite 
            ORDER BY r.NumeroReiteracion DESC), 
            t.FechaRCHRP
        ) AS FechaInicio,
       CASE
    -- Si está COMPLETA y tiene fecha de completado, calcular días hasta la fecha de completado
    WHEN (t.ID_EstadoBasico = 1 OR t.ID_EstadoTramite = 5) AND t.FechaCompletado IS NOT NULL THEN
        DATEDIFF(day, 
            COALESCE(
                (SELECT TOP 1 r.FechaReiteracion 
                 FROM Reiteraciones r 
                 WHERE r.ID_Tramite = t.ID_Tramite 
                 ORDER BY r.NumeroReiteracion DESC), 
                t.FechaRCHRP
            ), 
            t.FechaCompletado
        )
    -- Si está COMPLETA pero no tiene fecha de completado (casos antiguos), usar 0
    WHEN t.ID_EstadoBasico = 1 OR t.ID_EstadoTramite = 5 THEN 0
    -- Si todos los acuses tienen estado COMPLETA
    WHEN (
        (SELECT COUNT(*) FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite) > 0
        AND 
        NOT EXISTS (
            SELECT 1 FROM Acuses a 
            WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
        )
    ) THEN 
        -- Si tiene fecha de completado, usarla
        CASE WHEN t.FechaCompletado IS NOT NULL THEN
            DATEDIFF(day, 
                COALESCE(
                    (SELECT TOP 1 r.FechaReiteracion 
                     FROM Reiteraciones r 
                     WHERE r.ID_Tramite = t.ID_Tramite 
                     ORDER BY r.NumeroReiteracion DESC), 
                    t.FechaRCHRP
                ), 
                t.FechaCompletado
            )
        ELSE 0 END
    -- Para trámites en proceso, calcular días normalmente
    ELSE DATEDIFF(day, 
        COALESCE(
            (SELECT TOP 1 r.FechaReiteracion 
             FROM Reiteraciones r 
             WHERE r.ID_Tramite = t.ID_Tramite 
             ORDER BY r.NumeroReiteracion DESC), 
            t.FechaRCHRP
        ), 
        GETDATE()
    )
END AS DiasTranscurridos
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM Acuses a 
                WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
            ) OR t.ID_EstadoTramite = 7 THEN 'PREVENIDO'
            WHEN (
                EXISTS (
                    SELECT 1 FROM Acuses a 
                    WHERE a.ID_Tramite = t.ID_Tramite
                ) AND
                NOT EXISTS (
                    SELECT 1 FROM Acuses a 
                    WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
                )
            ) THEN 'COMPLETA'
            ELSE 'EN PROCESO'
        END AS StatusReal
    FROM Tramites t 
    INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente 
    INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite 
    INNER JOIN ClavesTramite ct ON t.ID_ClaveTramite = ct.ID_ClaveTramite
    INNER JOIN Municipios m ON t.ID_Municipio = m.ID_Municipio
    INNER JOIN NucleosAgrarios na ON t.ID_NucleoAgrario = na.ID_NucleoAgrario
    INNER JOIN TiposNucleoAgrario tna ON na.ID_TipoNucleoAgrario = tna.ID_TipoNucleoAgrario
    INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
    WHERE (
        EXISTS (SELECT 1 FROM Reiteraciones r WHERE r.ID_Tramite = t.ID_Tramite)
        OR t.FechaRCHRP IS NOT NULL
    )
    {$excludeClause}
)
SELECT * FROM UltimasFechas
WHERE DiasTranscurridos >= 95
AND StatusReal <> 'COMPLETA'  -- Excluir los trámites con status COMPLETA
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
                <li class="breadcrumb-item"><a href="listado-tramites.php?filtro=reiteracion">Trámites por Reiterarse</a></li>
                <li class="breadcrumb-item active" aria-current="page">Reporte para Impresión</li>
            </ol>
        </nav>
        
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-sync-alt me-2"></i>Reporte de Trámites por Reiterarse (95 días o más)</h5>
                <div>
                    <button id="btnImprimir" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-print me-1"></i>Imprimir Reporte
                    </button>
                    <a href="generar-pdf-reiteraciones.php<?php echo !empty($excluir) ? '?excluir=' . implode(',', $excluir) : ''; ?>" class="btn btn-light btn-sm" id="btnGenPDF">
                        <i class="fas fa-file-pdf me-1"></i>Generar PDF
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
                        <a href="reporte-reiteraciones.php" class="btn btn-outline-success">
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
                                <th>FOLIO RCHRP</th>
                                <th>PROMOVENTE</th>
                                <th>MUNICIPIO</th>
                                <th>TIPO N.A.</th>
                                <th>NÚCLEO AGRARIO</th>
                                <th>AVANCE</th>
                                <th>NÚM. REITERACIONES</th>
                                <th>DÍAS</th>
                                <th class="no-print">ACCIONES</th>
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
                                        <td>
                                            <?php
                                            $claseBadge = 'bg-secondary';
                                            if ($tramite['Avance'] == 25) $claseBadge = 'bg-info';
                                            else if ($tramite['Avance'] == 50) $claseBadge = 'bg-warning';
                                            else if ($tramite['Avance'] == 75) $claseBadge = 'bg-primary';
                                            else if ($tramite['Avance'] == 100) $claseBadge = 'bg-success';
                                            ?>
                                            <span class="badge <?php echo $claseBadge; ?>"><?php echo $tramite['Estado']; ?> (<?php echo $tramite['Avance']; ?>%)</span>
                                        </td>
                                        <td class="text-center"><?php echo $tramite['NumeroReiteraciones']; ?></td>
                                        <td>
                                            <?php 
                                            echo $tramite['DiasTranscurridos']; 
                                            if ($tramite['DiasTranscurridos'] >= 95 && $tramite['DiasTranscurridos'] < 100) {
                                                echo ' <i class="fas fa-exclamation-circle text-info" title="Próximamente requerirá reiteración"></i>';
                                            } else if ($tramite['DiasTranscurridos'] >= 100 && $tramite['DiasTranscurridos'] <= 105) {
                                                echo ' <i class="fas fa-exclamation-circle text-warning" title="Requiere reiteración"></i>';
                                            } else if ($tramite['DiasTranscurridos'] > 105) {
                                                echo ' <i class="fas fa-exclamation-triangle text-danger" title="Reiteración urgente"></i>';
                                            }
                                            ?>
                                        </td>
                                        <td class="no-print">
                                            <a href="detalle-tramite.php?id=<?php echo $tramite['ID_Tramite']; ?>" class="btn btn-sm btn-info" title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="registrar-reiteracion.php?id=<?php echo $tramite['ID_Tramite']; ?>" class="btn btn-sm btn-warning" title="Registrar Reiteración">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center">No hay trámites que requieran reiteración</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3" id="contadorRegistros">
                    <strong>Total de trámites:</strong> <?php echo count($tramites); ?>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <a href="listado-tramites.php?filtro=reiteracion" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver al Listado
                    </a>
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
        
        /* Asegurarse de que se muestren los backgrounds de las badges al imprimir */
        .badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Esperar a que jQuery esté disponible
    function iniciarCuandoJQueryEsteDisponible() {
        if (typeof jQuery !== 'undefined') {
            // Ahora jQuery está disponible, podemos usarlo con seguridad
            jQuery(document).ready(function($) {
                // Checkbox "Seleccionar todos"
                $('#checkAll').change(function() {
                    $('.check-item').prop('checked', $(this).prop('checked'));
                });
                
                // Al hacer clic en "Seleccionar Todos"
                $('#btnSeleccionarTodos').click(function() {
                    $('.check-item').prop('checked', true);
                    $('#checkAll').prop('checked', true);
                });
                
                // Al hacer clic en "Deseleccionar Todos"
                $('#btnDeseleccionarTodos').click(function() {
                    $('.check-item').prop('checked', false);
                    $('#checkAll').prop('checked', false);
                });
                
                // Al hacer clic en "Excluir Seleccionados"
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
                    var nuevosExcluidos = [];
                    
                    // Combinar arrays evitando duplicados
                    if (excluirActuales.length > 0) {
                        nuevosExcluidos = excluirActuales.slice(); // Copiar array existente
                    }
                    
                    // Añadir solo IDs que no estén ya en el array
                    seleccionados.forEach(function(id) {
                        if (nuevosExcluidos.indexOf(id) === -1) {
                            nuevosExcluidos.push(id);
                        }
                    });
                    
                    // Redireccionar con los parámetros actualizados
                    window.location.href = 'reporte-reiteraciones.php?excluir=' + nuevosExcluidos.join(',');
                });
                
                // Al hacer clic en "Imprimir Reporte"
                $('#btnImprimir').click(function() {
                    window.print();
                });
                
                // Al hacer clic en "Generar PDF"
                $('#btnGenPDF').click(function(e) {
                    e.preventDefault();
                    
                    var excluirActuales = <?php echo json_encode($excluir); ?>;
                    var url = 'generar-pdf-reiteraciones.php';
                    
                    if (excluirActuales.length > 0) {
                        url += '?excluir=' + excluirActuales.join(',');
                    }
                    
                    window.location.href = url;
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