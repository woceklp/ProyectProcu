<?php
/**
 * paginas/lista-promoventes.php - Listado de promoventes con diseño unificado
 */

// Incluir archivo de configuración
require_once '../config.php';

// Definir variable para rutas en el subdirectorio paginas
$paginasDir = true;

// Incluir el header
include_once '../modulos/header.php';

// Consulta para obtener promoventes únicos que tienen trámites
$sql = "SELECT DISTINCT 
        p.ID_Promovente,
        p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS NombreCompleto,
        COUNT(t.ID_Tramite) AS TotalTramites
        FROM Promoventes p
        INNER JOIN Tramites t ON p.ID_Promovente = t.ID_Promovente
        GROUP BY p.ID_Promovente, p.Nombre, p.ApellidoPaterno, p.ApellidoMaterno
        ORDER BY NombreCompleto";

$resultado = ejecutarConsulta($sql);
?>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">INICIO</a></li>
                <li class="breadcrumb-item active" aria-current="page">LISTADO DE PROMOVENTES</li>
            </ol>
        </nav>
        
        <?php if(isset($_GET['update_promovente'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>Los datos del promovente han sido actualizados correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>LISTADO DE PROMOVENTES</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>NOMBRE COMPLETO</th>
                                <th>TOTAL DE TRÁMITES</th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if($resultado['status'] === 'success') {
                                $promoventes = obtenerResultados($resultado['stmt']);
                                cerrarConexion($resultado['conn'], $resultado['stmt']);
                                
                                if(count($promoventes) > 0) {
                                    foreach($promoventes as $promovente) {
                                        echo '<tr>
                                                <td>'.$promovente['ID_Promovente'].'</td>
                                                <td>'.$promovente['NombreCompleto'].'</td>
                                                <td>'.$promovente['TotalTramites'].'</td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="lista-por-promoventes.php?id='.$promovente['ID_Promovente'].'" class="btn btn-sm btn-info" title="Ver CIIA">
                                                            <i class="fas fa-file-alt me-1"></i>Ver CIIA
                                                        </a>
                                                    <a href="#" class="btn btn-sm btn-primary" onclick="abrirModalEditarPromovente('.$promovente['ID_Promovente'].', \'lista\', 0)">
                                                        <i class="fas fa-user-edit me-1"></i>Editar
                                                    </a>                                                    </div>
                                                </td>
                                            </tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center">No hay promoventes con trámites registrados</td></tr>';
                                }
                            } else {
                                echo '<tr><td colspan="4" class="text-center text-danger">Error al cargar promoventes: '.$resultado['message'].'</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Botones inferiores -->
                <div class="d-flex justify-content-between mt-3">
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>REGRESAR
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script para manejar la edición de promoventes -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si jQuery está disponible
    if (typeof jQuery !== 'undefined') {
        // Manejar clic en botón de editar promovente usando jQuery
        $(document).on('click', '.editar-promovente', function(e) {
            e.preventDefault();
            var idPromovente = $(this).data('id');
            var referrer = $(this).data('referrer');
            var tramiteId = $(this).data('tramite');
            
            // Redireccionar a la página de edición
            window.location.href = 'editar-promovente.php?id=' + idPromovente + '&referrer=' + referrer + '&tramite=' + tramiteId;
        });
    } else {
        // Alternativa sin jQuery usando JavaScript puro
        document.querySelectorAll('.editar-promovente').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var idPromovente = this.getAttribute('data-id');
                var referrer = this.getAttribute('data-referrer');
                var tramiteId = this.getAttribute('data-tramite');
                
                // Redireccionar a la página de edición
                window.location.href = 'editar-promovente.php?id=' + idPromovente + '&referrer=' + referrer + '&tramite=' + tramiteId;
            });
        });
    }
});
</script>

<?php
// Incluir el footer
include_once '../modulos/footer.php';
?>