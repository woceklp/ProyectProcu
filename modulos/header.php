<?php
/**
 * modulos/header.php - Encabezado común para todas las páginas
 * Versión corregida con rutas relativas apropiadas
 */

// Determinar si estamos en una página del directorio "paginas"
$paginasDir = isset($paginasDir) ? $paginasDir : false;
$rutaBase = $paginasDir ? '../' : '';

// Detectar la página actual para activar la navegación
$currentPage = basename($_SERVER['PHP_SELF']);
$isIndex = ($currentPage === 'index.php');
$isListadoTramites = ($currentPage === 'listado-tramites.php');
$isListaPromoventes = ($currentPage === 'lista-promoventes.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PROCURADURIA AGRARIA</title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- CSS personalizado - Incluir versión para forzar recarga (evitar caché) -->
    <link href="<?php echo $rutaBase; ?>css/styles.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $rutaBase; ?>index.php">
                <i class="fas fa-file-contract me-2"></i>PROCURADURIA AGRARIA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isIndex ? 'active' : ''; ?>" href="<?php echo $rutaBase; ?>index.php">
                            <i class="fas fa-home me-1"></i>INICIO
                        </a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#buscarPromovente">
                        <i class="fas fa-plus-circle me-1"></i>NUEVO TRÁMITE
                    </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isListadoTramites ? 'active' : ''; ?>" href="<?php echo $paginasDir ? '' : 'paginas/'; ?>listado-tramites.php">
                            <i class="fas fa-list me-1"></i>LISTADO DE TRÁMITES
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isListaPromoventes ? 'active' : ''; ?>" href="<?php echo $paginasDir ? '' : 'paginas/'; ?>lista-promoventes.php">
                            <i class="fas fa-users me-1"></i>VER PROMOVENTES
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Contenedor principal para el contenido -->