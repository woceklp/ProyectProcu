<?php
/**
 * paginas/listado-tramites.php - Listado de trámites con filtros
 * Versión actualizada con el nuevo diseño unificado
 */

// Incluir archivo de configuración
require_once '../config.php';

// Mostrar errores para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Recoger los filtros de la URL
$filtro = isset($_GET['filtro']) ? sanitizarEntrada($_GET['filtro']) : '';
$promovente = isset($_GET['promovente']) ? sanitizarEntrada($_GET['promovente']) : '';
$ciia = isset($_GET['ciia']) ? sanitizarEntrada($_GET['ciia']) : '';
$numTramite = isset($_GET['num_tramite']) ? sanitizarEntrada($_GET['num_tramite']) : '';

// Definir el título según los filtros
$tituloListado = "Listado de Trámites";

// SECCIÓN 1: CONSULTAS SQL BASE SEGÚN EL TIPO DE FILTRO
// -------------------------------------------------------

// Base común para todas las consultas SQL
$sqlBase = "SELECT t.ID_Tramite, 
       p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
       t.CIIA, 
       t.FolioRCHRP,
       (SELECT TOP 1 a.NumeroAcuse FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite ORDER BY a.FechaRegistro DESC) AS NumeroAcuse,
       tt.Nombre AS TipoTramite, 
       t.FechaRegistro, 
       e.Nombre AS Estado, 
       e.Porcentaje,
       e.ID_EstadoTramite,
       CASE 
           -- Si hay al menos un acuse con ID_EstadoBasico = 2 (PREVENIDO)
           WHEN EXISTS (
               SELECT 1 FROM Acuses a 
               WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
           ) THEN 'PREVENIDO'
           
           -- Si todos los acuses tienen ID_EstadoBasico = 3 (COMPLETA) y hay al menos un acuse
           WHEN (
               (SELECT COUNT(*) FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite) > 0
               AND 
               NOT EXISTS (
                   SELECT 1 FROM Acuses a 
                   WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
               )
           ) THEN 'COMPLETA'
           
           -- Si hay exactamente un acuse, usar su estado básico directamente
           WHEN (SELECT COUNT(*) FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite) = 1 THEN 
               (SELECT CASE 
                   WHEN a.ID_EstadoBasico = 3 THEN 'COMPLETA'
                   WHEN a.ID_EstadoBasico = 2 THEN 'PREVENIDO'
                   ELSE 'EN PROCESO' 
                END 
                FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite)
           
           -- En cualquier otro caso
           ELSE 'EN PROCESO'
       END AS StatusReal
FROM Tramites t 
INNER JOIN Promoventes p ON t.ID_Promovente = p.ID_Promovente 
INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite 
INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite";

// Determinar la consulta específica según los filtros
if($filtro === 'activos') {
    $tituloListado = "Trámites Activos";
    $sql = $sqlBase . " WHERE 
        NOT EXISTS (
            SELECT 1 FROM Acuses a 
            WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
        )
        AND NOT (
            NOT EXISTS (
                SELECT 1 FROM Acuses a 
                WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
            ) 
            AND EXISTS (
                SELECT 1 FROM Acuses a 
                WHERE a.ID_Tramite = t.ID_Tramite
            )
        )";
} elseif($filtro === 'pendientes') {
    $tituloListado = "Trámites Pendientes de Respuesta";
    $sql = $sqlBase . " WHERE t.ID_EstadoTramite NOT IN (5, 7)
                        AND NOT EXISTS (
                            SELECT 1 FROM Acuses a 
                            WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
                        )";
} 
elseif($filtro === 'reiteracion') {
    $tituloListado = "Trámites a Reiterarse (95 días o más)";
    $sql = "WITH UltimasFechas AS (
            SELECT t.ID_Tramite, 
                   p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
                   t.CIIA, 
                   t.FolioRCHRP,
                   t.FechaRCHRP,
                   (SELECT TOP 1 a.NumeroAcuse FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite ORDER BY a.FechaRegistro DESC) AS NumeroAcuse,
                   tt.Nombre AS TipoTramite, 
                   t.FechaRegistro, 
                   e.Nombre AS Estado, 
                   e.Porcentaje,
                   e.ID_EstadoTramite,
                   CASE
                       -- Si el estado básico es COMPLETA (ID 1), devolver 0 días
                       WHEN t.ID_EstadoBasico = 1 THEN 0
                       -- Si hay al menos un acuse con ID_EstadoBasico = 3 (COMPLETA) y no hay ninguno con otro estado
                       WHEN (
                           (SELECT COUNT(*) FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite) > 0
                           AND 
                           NOT EXISTS (
                               SELECT 1 FROM Acuses a 
                               WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
                           )
                       ) THEN 0
                       -- En caso contrario, calcular los días normalmente
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
                   END AS DiasTranscurridos,
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
            INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
            WHERE (
                EXISTS (SELECT 1 FROM Reiteraciones r WHERE r.ID_Tramite = t.ID_Tramite)
                OR t.FechaRCHRP IS NOT NULL
            )
        )
        SELECT * FROM UltimasFechas
        WHERE DiasTranscurridos >= 95
        AND StatusReal <> 'COMPLETA'  -- Excluir los trámites con status COMPLETA
        ORDER BY ID_Tramite ASC";
        
} elseif($filtro === 'prevenidos') {
    $tituloListado = "Trámites Prevenidos";
    $sql = $sqlBase . " WHERE t.ID_EstadoTramite = 7 
                         OR EXISTS (
                             SELECT 1 FROM Acuses a 
                             WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
                         )";
} elseif($filtro === 'completados') {
    $tituloListado = "Trámites con Status Completa";
    $sql = $sqlBase . " WHERE 
    (
        NOT EXISTS (
            SELECT 1 FROM Acuses a 
            WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
        ) AND EXISTS (
            SELECT 1 FROM Acuses a 
            WHERE a.ID_Tramite = t.ID_Tramite
        )
    )";
} else {
    // Consulta por defecto o para búsquedas
    if(!empty($promovente) || !empty($ciia) || !empty($numTramite)) {
        $tituloListado = "Resultados de Búsqueda";
    }
    
    $sql = $sqlBase;
    
    // Aplicar filtros de búsqueda
    $where = [];
    $params = [];
    
    if(!empty($promovente)) {
        $where[] = "(p.Nombre LIKE ? OR p.ApellidoPaterno LIKE ? OR p.ApellidoMaterno LIKE ?)";
        $params[] = '%' . $promovente . '%';
        $params[] = '%' . $promovente . '%';
        $params[] = '%' . $promovente . '%';
    }

    if(!empty($ciia)) {
        $where[] = "t.CIIA LIKE ?";
        $params[] = '%' . $ciia . '%';
    }

    if(!empty($numTramite)) {
        $where[] = "EXISTS (SELECT 1 FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite AND a.NumeroAcuse LIKE ?)";
        $params[] = '%' . $numTramite . '%';
    }

    // Construir la cláusula WHERE completa
    if(!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
}

// SECCIÓN 2: ORDENAMIENTO DE RESULTADOS
// -------------------------------------

// Ordenar los resultados para todos los casos excepto reiteración
if ($filtro !== 'reiteracion') {
    $sql .= " ORDER BY t.ID_Tramite ASC";
}

// SECCIÓN 3: EJECUCIÓN DE LA CONSULTA
// -----------------------------------

// Ejecutar la consulta
$resultado = ejecutarConsulta($sql, isset($params) ? $params : []);
?>

<!-- SECCIÓN 4: INTERFAZ DE USUARIO - HTML -->
<!-- ------------------------------------- -->

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $tituloListado; ?></li>
            </ol>
        </nav>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i><?php echo $tituloListado; ?></h5>
                    
                    <div>
                        <?php if($filtro === 'reiteracion'): ?>
                        <a href="reporte-reiteraciones.php" class="btn btn-light btn-sm me-2">
                            <i class="fas fa-tasks me-1"></i>Reporte Interactivo
                        </a>
                        <a href="generar-pdf-reiteraciones.php" class="btn btn-light btn-sm">
                            <i class="fas fa-file-pdf me-1"></i>GENERAR PDF
                        </a>
                        <?php elseif($filtro === 'prevenidos'): ?>
                        <a href="reporte-prevenidos.php" class="btn btn-light btn-sm me-2">
                            <i class="fas fa-tasks me-1"></i>REPORTE INTERCATIVO
                        </a>
                        <a href="generar-pdf-prevenidos.php" class="btn btn-light btn-sm">
                            <i class="fas fa-file-pdf me-1"></i>GENERAR PDF
                        </a>
                        <?php else: ?>
                        <a href="#" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#filtrosAvanzados">
                            <i class="fas fa-filter me-1"></i>FILTROS AVANZADOS
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal Filtros Avanzados -->
            <div class="modal fade" id="filtrosAvanzados" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-filter me-2"></i>FILTROS AVANZADOS
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formFiltrosAvanzados" action="listado-tramites.php" method="get">
                                <div class="mb-3">
                                    <label for="filtroPromovente" class="form-label">PROMOVENTE</label>
                                    <input type="text" class="form-control" id="filtroPromovente" name="promovente" value="<?php echo $promovente; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="filtroCIIA" class="form-label">CIIA</label>
                                    <input type="text" class="form-control" id="filtroCIIA" name="ciia" value="<?php echo $ciia; ?>" maxlength="13">
                                </div>
                                <div class="mb-3">
                                    <label for="filtroNumTramite" class="form-label">Número de Trámite/Acuse</label>
                                    <input type="text" class="form-control" id="filtroNumTramite" name="num_tramite" value="<?php echo $numTramite; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="filtroEstado" class="form-label">STATUS DE TRAMITE</label>
                                    <select class="form-select" id="filtroEstado" name="filtro">
                                        <option value="">Todos</option>
                                        <option value="activos" <?php echo ($filtro === 'activos') ? 'selected' : ''; ?>>ACTIVOS</option>
                                        <option value="pendientes" <?php echo ($filtro === 'pendientes') ? 'selected' : ''; ?>>Pendientes de respuesta</option>
                                        <option value="prevenidos" <?php echo ($filtro === 'prevenidos') ? 'selected' : ''; ?>>Con Status Prevenido</option>
                                        <option value="reiteracion" <?php echo ($filtro === 'reiteracion') ? 'selected' : ''; ?>>POR REITERAR</option>
                                        <option value="completados" <?php echo ($filtro === 'completados') ? 'selected' : ''; ?>>Con Status Completa</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>CANCELAR
                            </button>
                            <button type="button" id="btnLimpiarFiltros" class="btn btn-warning">
                                <i class="fas fa-eraser me-1"></i>Limpiar Filtros
                            </button>
                            <button type="submit" form="formFiltrosAvanzados" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Aplicar Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 5: RESULTADOS DE LA CONSULTA -->
            <!-- ------------------------------------ -->

            <div class="card-body">
                <?php if($resultado['status'] === 'success'): ?>
                    <?php 
                    $tramites = obtenerResultados($resultado['stmt']);
                    cerrarConexion($resultado['conn'], $resultado['stmt']);
                    
                    if(count($tramites) > 0): 
                    ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>PROMOVENTE</th>
                                    <th>CIIA</th>
                                    <th>FOLIO RCHRP</th>
                                    <th>NÚM. TRÁMITE</th>
                                    <th>TIPO</th>
                                    <th>FECHA</th>
                                    <th>STATUS</th>
                                    <th>ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($tramites as $tramite): 
                                // Formatear fecha
                                $fecha = $tramite['FechaRegistro']->format('d/m/Y');
                                
                                // Determinar la clase y el texto del status real
                                $statusTexto = isset($tramite['StatusReal']) ? $tramite['StatusReal'] : 'EN PROCESO';
                                $claseBadge = 'bg-primary'; // Por defecto EN PROCESO (azul)

                                if ($statusTexto === 'PREVENIDO') {
                                    $claseBadge = 'bg-info';
                                } else if ($statusTexto === 'COMPLETA') {
                                    $claseBadge = 'bg-success';
                                }
                            ?>
                            <tr>
                                <td><?php echo $tramite['ID_Tramite']; ?></td>
                                <td><?php echo $tramite['Promovente']; ?></td>
                                <td><?php echo $tramite['CIIA']; ?></td>
                                <td><?php echo $tramite['FolioRCHRP'] ?? 'N/A'; ?></td>
                                <td><?php echo $tramite['NumeroAcuse'] ?? 'N/A'; ?></td>
                                <td><?php echo $tramite['TipoTramite']; ?></td>
                                <td><?php echo $fecha; ?></td>
                                <td>
                                <span class="badge <?php echo $claseBadge; ?>"><?php echo $statusTexto; ?></span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="detalle-tramite.php?id=<?php echo $tramite['ID_Tramite']; ?>" class="btn btn-sm btn-info" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <p class="mb-0"><strong>Total de trámites encontrados:</strong> <?php echo count($tramites); ?></p>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No se encontraron trámites que coincidan con los criterios de búsqueda.
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>Error al cargar los trámites: <?php echo $resultado['message']; ?>
                </div>
                <?php endif; ?>
                
                <!-- Botones inferiores -->
                <div class="d-flex justify-content-between mt-3">
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECCIÓN 6: INCLUIR MODALES Y FOOTER -->
<!-- ------------------------------------ -->

<?php
// Incluir los modales
include '../modulos/modal_buscar_promovente.php';
include '../modulos/modal_nuevo_promovente.php';
include '../modulos/modal_nuevo_tramite.php';

// Incluir el footer
include_once '../modulos/footer.php';
?>

<!-- SECCIÓN 7: JAVASCRIPT -->
<!-- --------------------- -->

<script>
$(document).ready(function() {
    // Cuando se hace clic en el botón de limpiar filtros
    $('#btnLimpiarFiltros').click(function() {
        $('#filtroPromovente').val('');
        $('#filtroCIIA').val('');
        $('#filtroNumTramite').val('');
        $('#filtroEstado').val('');
    });
});
</script>