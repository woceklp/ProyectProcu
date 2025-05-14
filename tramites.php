<?php
/**
 * index.php - Página principal del Sistema Gestor de Trámites Agrarios
 */

// Incluir archivo de configuración
require_once 'config.php';

// Rutas relativas para index.php (está en la raíz)
$rutaCSS = 'css/styles.css';
$rutaJS = 'js/main.js';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PROCURADURIA AGRARIA</title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 para alertas más elegantes -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- CSS personalizado -->
    <link href="<?php echo $rutaCSS; ?>" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-file-contract me-2"></i>PROCURADURIA AGRARIA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i>INICIO
                        </a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#buscarPromovente">
                        <i class="fas fa-plus-circle me-1"></i>NUEVO TRÁMITE
                    </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="paginas/listado-tramites.php">
                            <i class="fas fa-list me-1"></i>LISTADO DE TRÁMITES
                        </a>
                    </li>
                    <li>
                        <a class="nav-link" href="paginas/lista-promoventes.php">
                            <i class="fas fa-users me-1"></i>VER PROMOVENTES
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-tachometer-alt me-2"></i>PANEL DE CONTROL - TRÁMITES AGRARIOS</h5>
                    </div>
                    <div class="card-body">
                        <!-- Tarjetas de resumen -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card text-center mb-3 border-primary">
                                    <div class="card-body">
                                        <div class="display-4 text-primary mb-2">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <h5 class="card-title">TRÁMITES ACTIVOS</h5>
                                        <?php
                                        // Consultar cantidad de trámites activos (con estados diferentes a Completa y Prevenido)
                                        $sqlActivos = "SELECT COUNT(*) AS total FROM Tramites t
              WHERE NOT EXISTS (
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
                                        $resultadoActivos = ejecutarConsulta($sqlActivos);
                                        
                                        if($resultadoActivos['status'] === 'success') {
                                            $activos = sqlsrv_fetch_array($resultadoActivos['stmt'], SQLSRV_FETCH_ASSOC);
                                            $totalActivos = $activos['total'] ?? 0;
                                            cerrarConexion($resultadoActivos['conn'], $resultadoActivos['stmt']);
                                        } else {
                                            $totalActivos = 0;
                                        }
                                        ?>
                                        <p class="card-text display-5"><?php echo $totalActivos; ?></p>
                                        <a href="paginas/listado-tramites.php?filtro=activos" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>VER DETALLES
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center mb-3 border-info">
                                    <div class="card-body">
                                        <div class="display-4 text-info mb-2">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <h5 class="card-title">PREVENIDOS</h5>
                                        <?php
                                        // Consultar cantidad de trámites prevenidos
                                        $sqlPrevenidos = "SELECT COUNT(*) AS total 
                                        FROM Tramites t
                                        WHERE t.ID_EstadoTramite = 7 
                                        OR EXISTS (
                                            SELECT 1 
                                            FROM Acuses a 
                                            WHERE a.ID_Tramite = t.ID_Tramite 
                                            AND a.ID_EstadoBasico = 2
                                        )";
                                        $resultadoPrevenidos = ejecutarConsulta($sqlPrevenidos);

                                        if($resultadoPrevenidos['status'] === 'success') {
                                        $prevenidos = sqlsrv_fetch_array($resultadoPrevenidos['stmt'], SQLSRV_FETCH_ASSOC);
                                        $totalPrevenidos = $prevenidos['total'] ?? 0;
                                        cerrarConexion($resultadoPrevenidos['conn'], $resultadoPrevenidos['stmt']);
                                        } else {
                                        $totalPrevenidos = 0;
                                        }
                                        ?>
                                        <p class="card-text display-5"><?php echo $totalPrevenidos; ?></p>
                                        <a href="paginas/listado-tramites.php?filtro=prevenidos" class="btn btn-outline-info">
                                            <i class="fas fa-eye me-1"></i>VER DETALLES
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center mb-3 border-danger">
                                    <div class="card-body">
                                        <div class="display-4 text-danger mb-2">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <h5 class="card-title">POR REITERARSE</h5>
                                        <?php
                                        // Consultar trámites que necesitan reiteración o están próximos (más de 95 días sin cambios)
                                        $sqlReiteracion = "SELECT COUNT(*) AS total FROM Tramites t
                                                      WHERE (
                                                          (EXISTS (
                                                              SELECT 1 
                                                              FROM Reiteraciones r 
                                                              WHERE r.ID_Tramite = t.ID_Tramite 
                                                              AND DATEDIFF(day, r.FechaReiteracion, GETDATE()) >= 95
                                                          ))
                                                          OR 
                                                          (NOT EXISTS (
                                                              SELECT 1 
                                                              FROM Reiteraciones r 
                                                              WHERE r.ID_Tramite = t.ID_Tramite
                                                          ) 
                                                          AND t.FechaRCHRP IS NOT NULL 
                                                          AND DATEDIFF(day, t.FechaRCHRP, GETDATE()) >= 95)
                                                      )
                                                      AND t.ID_EstadoTramite <> 5";
                                        $resultadoReiteracion = ejecutarConsulta($sqlReiteracion);
                                        
                                        if($resultadoReiteracion['status'] === 'success') {
                                            $reiteracion = sqlsrv_fetch_array($resultadoReiteracion['stmt'], SQLSRV_FETCH_ASSOC);
                                            $totalReiteracion = $reiteracion['total'] ?? 0;
                                            cerrarConexion($resultadoReiteracion['conn'], $resultadoReiteracion['stmt']);
                                        } else {
                                            $totalReiteracion = 0;
                                        }
                                        ?>
                                        <p class="card-text display-5"><?php echo $totalReiteracion; ?></p>
                                        <a href="paginas/reporte-reiteraciones.php" class="btn btn-outline-danger">
                                            <i class="fas fa-eye me-1"></i>VER DETALLES
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center mb-3 border-success">
                                    <div class="card-body">
                                        <div class="display-4 text-success mb-2">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <h5 class="card-title">COMPLETADOS</h5>
                                        <?php
                                        // Consultar cantidad de trámites completados
                                        $sqlCompletados = "SELECT COUNT(*) AS total FROM Tramites 
                                                        WHERE ID_EstadoTramite = 5"; // Estado completa
                                        $resultadoCompletados = ejecutarConsulta($sqlCompletados);
                                        
                                        if($resultadoCompletados['status'] === 'success') {
                                            $completados = sqlsrv_fetch_array($resultadoCompletados['stmt'], SQLSRV_FETCH_ASSOC);
                                            $totalCompletados = $completados['total'] ?? 0;
                                            cerrarConexion($resultadoCompletados['conn'], $resultadoCompletados['stmt']);
                                        } else {
                                            $totalCompletados = 0;
                                        }
                                        ?>
                                        <p class="card-text display-5"><?php echo $totalCompletados; ?></p>
                                        <a href="paginas/listado-tramites.php?filtro=completados" class="btn btn-outline-success">
                                            <i class="fas fa-eye me-1"></i>VER DETALLES
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Buscador Rápido -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-search me-2"></i>BUSCADOR RÁPIDO</h5>
            </div>
            <div class="card-body">
                <form id="formBusquedaRapida" action="paginas/listado-tramites.php" method="get">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="promovente" placeholder="Nombre del Promovente">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                <input type="text" class="form-control" name="ciia" placeholder="CIIA (13 dígitos)" maxlength="13">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                                <input type="text" class="form-control" name="num_tramite" placeholder="Número de Trámite/Acuse">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="descripcion" placeholder="Buscar en descripción">
                            </div>
                        </div>
                        <div class="col-md-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>BUSCAR
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

                        <!-- Trámites Recientes -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h5><i class="fas fa-clipboard-list me-2"></i>TRÁMITES RECIENTES</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>PROMOVENTE</th>
                                                        <th>CIIA</th>
                                                        <th>TIPO DE TRÁMITE</th>
                                                        <th>FECHA</th>
                                                        <th>ESTATUS</th>
                                                        <th>ACCIONES</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
$sqlRecientes = "
SELECT * FROM (
    SELECT TOP (5) t.ID_Tramite, 
    p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
    t.CIIA, 
    tt.Nombre AS TipoTramite, 
    t.FechaRegistro, 
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
    INNER JOIN TiposTramite tt ON t.ID_TipoTramite = tt.ID_TipoTramite 
    INNER JOIN EstadosTramite e ON t.ID_EstadoTramite = e.ID_EstadoTramite
    ORDER BY t.ID_Tramite DESC
) AS Ultimos
ORDER BY Ultimos.ID_Tramite ASC;
";

$resultadoRecientes = ejecutarConsulta($sqlRecientes);


if($resultadoRecientes['status'] === 'success') {
    $tramites = obtenerResultados($resultadoRecientes['stmt']);
    cerrarConexion($resultadoRecientes['conn'], $resultadoRecientes['stmt']);
    
    if(count($tramites) > 0) {
        foreach($tramites as $tramite) {
            // Formatear fecha
            $fecha = $tramite['FechaRegistro']->format('d/m/Y');
            
            // Determinar clase de badge según EstadoReal
            $claseBadge = 'bg-primary'; // Por defecto EN PROCESO (azul)
            $estadoTexto = isset($tramite['StatusReal']) ? $tramite['StatusReal'] : 'EN PROCESO';            
            if ($estadoTexto === 'PREVENIDO') {
                $claseBadge = 'bg-info';
            } else if ($estadoTexto === 'COMPLETA') {
                $claseBadge = 'bg-success';
            }
            
            echo '<tr>
                    <td>'.$tramite['ID_Tramite'].'</td>
                    <td>'.$tramite['Promovente'].'</td>
                    <td>'.$tramite['CIIA'].'</td>
                    <td>'.$tramite['TipoTramite'].'</td>
                    <td>'.$fecha.'</td>
                    <td><span class="badge '.$claseBadge.'">'.$estadoTexto.'</span></td>
                    <td>
                        <a href="paginas/detalle-tramite.php?id='.$tramite['ID_Tramite'].'" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>                                                                            
                    </td>
                </tr>';
        }
    } else {
        echo '<tr><td colspan="7" class="text-center">No hay trámites registrados</td></tr>';
    }
} else {
    echo '<tr><td colspan="7" class="text-center text-danger">Error al cargar trámites: '.$resultadoRecientes['message'].'</td></tr>';
}
?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-end mt-3">
                                            <a href="paginas/listado-tramites.php" class="btn btn-outline-primary">
                                                <i class="fas fa-list me-1"></i>VER TODOS LOS TRÁMITES
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light text-center text-lg-start mt-5">
        <div class="text-center p-3" style="background-color: rgba(0, 0, 0, 0.05);">
            &copy; <?php echo date('Y'); ?> PROCURADURIA AGRARIA
        </div>
    </footer>

    <?php
    // Incluir los modales
    include 'modulos/modal_buscar_promovente.php';
    include 'modulos/modal_nuevo_promovente.php';
    include 'modulos/modal_nuevo_tramite.php';
    ?>

    <!-- Scripts jQuery, Bootstrap y SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    
    <!-- Script personalizado -->
    <script src="<?php echo $rutaJS; ?>"></script>
    <script src="js/validacion-promovente.js"></script>
</body>
</html>