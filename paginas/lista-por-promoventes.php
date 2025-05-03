<?php
/**
 * paginas/lista-por-promoventes.php - Listado de CIIA por promovente
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Obtener el ID del promovente de la URL
$idPromovente = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($idPromovente <= 0) {
    // Redirigir si no hay ID válido
    echo '<script>window.location.href = "lista-promoventes.php";</script>';
    exit;
}

// Obtener información del promovente
$sqlPromovente = "SELECT Nombre + ' ' + ApellidoPaterno + ' ' + ApellidoMaterno AS NombreCompleto 
                 FROM Promoventes WHERE ID_Promovente = ?";
$resultadoPromovente = ejecutarConsulta($sqlPromovente, array($idPromovente));

if($resultadoPromovente['status'] !== 'success') {
    echo '<div class="alert alert-danger">ERROR AL CARGAR DATOS DEL PROMOVENTE: '.$resultadoPromovente['message'].'</div>';
    include_once '../modulos/footer.php';
    exit;
}

$promovente = sqlsrv_fetch_array($resultadoPromovente['stmt'], SQLSRV_FETCH_ASSOC);
cerrarConexion($resultadoPromovente['conn'], $resultadoPromovente['stmt']);

if(!$promovente) {
    echo '<div class="alert alert-warning">El promovente solicitado no existe o ha sido eliminado.</div>';
    include_once '../modulos/footer.php';
    exit;
}

// Consultar los CIIA/trámites del promovente
$sqlCIIA = "SELECT t.ID_Tramite, 
            t.CIIA, 
            t.FolioRCHRP,
            (SELECT TOP 1 a.NumeroAcuse FROM Acuses a WHERE a.ID_Tramite = t.ID_Tramite ORDER BY a.FechaRegistro DESC) AS NumeroAcuse,
            tt.Nombre AS TipoTramite, 
            t.FechaRegistro, 
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
            INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite 
            INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite 
            WHERE t.ID_Promovente = ?
            ORDER BY t.FechaRegistro DESC";

$resultadoCIIA = ejecutarConsulta($sqlCIIA, array($idPromovente));
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item"><a href="lista-promoventes.php">LISTADO DE PROMOVENTES</a></li>
                <li class="breadcrumb-item active" aria-current="page">CIIA de <?php echo $promovente['NombreCompleto']; ?></li>
            </ol>
        </nav>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>LISTADO DE CIIA's</h5>
            </div>
            <div class="card-body">
                <!-- Panel de información del promovente -->
                <div class="alert alert-info mb-4">
                    <strong>PROMOVENTE:</strong> <?php echo $promovente['NombreCompleto']; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>CIIA</th>
                                <th>FOLIO RCHRP</th>
                                <th>NÚM. TRÁMITE/ACUSE</th>
                                <th>TIPO DE TRÁMITE</th>
                                <th>FECHA</th>
                                <th>ESTATUS</th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if($resultadoCIIA['status'] === 'success') {
                                $ciias = obtenerResultados($resultadoCIIA['stmt']);
                                cerrarConexion($resultadoCIIA['conn'], $resultadoCIIA['stmt']);
                                
                                if(count($ciias) > 0) {
                                    foreach($ciias as $ciia) {
                                        // Determinar clase de badge según el status real
                                        $claseBadge = 'bg-primary'; // Por defecto EN PROCESO (azul)
                                        $statusTexto = isset($ciia['StatusReal']) ? $ciia['StatusReal'] : 'EN PROCESO';
                                        
                                        if ($statusTexto === 'PREVENIDO') {
                                            $claseBadge = 'bg-info';
                                        } else if ($statusTexto === 'COMPLETA') {
                                            $claseBadge = 'bg-success';
                                        }
                                        
                                        // Formatear fecha
                                        $fecha = $ciia['FechaRegistro']->format('d/m/Y');
                                        
                                        echo '<tr>
                                                <td>'.$ciia['CIIA'].'</td>
                                                <td>'.($ciia['FolioRCHRP'] ?? 'N/A').'</td>
                                                <td>'.($ciia['NumeroAcuse'] ?? 'N/A').'</td>
                                                <td>'.$ciia['TipoTramite'].'</td>
                                                <td>'.$fecha.'</td>
                                                <td><span class="badge '.$claseBadge.'">'.$statusTexto.'</span></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="detalle-tramite.php?id='.$ciia['ID_Tramite'].'" class="btn btn-sm btn-info" title="VER DETALLES">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="7" class="text-center">NO SE ENCONTRARON CIIAs PARA ESTE PROMOVENTE</td></tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-danger">ERROR AL CARGAR CIIA: '.$resultadoCIIA['message'].'</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Botones inferiores -->
                <div class="d-flex justify-content-between mt-3">
                    <a href="lista-promoventes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>VOLVER A PROMOVENTES
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el footer (que ahora incluirá automáticamente los modales necesarios)
include_once '../modulos/footer.php';
?>