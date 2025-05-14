<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PROCURADURÍA AGRARIA</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- CSS personalizado -->
    <link href="css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="d-flex align-items-center">
                <img src="img/LOGO1.jpg" alt="Logo Procuraduría Agraria" class="logo">
                <div>
                    <h1>PROCURADURÍA AGRARIA</h1>
                    <p class="text-muted mb-0">Sistema Integral de Gestión de Trámites y Servicios</p>
                </div>
                <div class="ms-auto text-end">
                    <p class="text-muted">Fecha actual:</p>
                    <p class="fw-bold"><?php echo date('d/m/Y'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="mb-0"><i class="fas fa-th-large me-2"></i>Panel Principal</h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-5">
            <!-- Gestión de Trámites -->
            <div class="col">
                <div class="card module-card">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0">Gestión de Trámites</h3>
                    </div>
                    <span class="status-badge status-active">Activo</span>
                    <div class="card-body d-flex flex-column">
                        <div class="text-center card-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <p class="card-text">Administre los trámites de la Procuraduría Agraria, seguimiento a CIIA, acuses y reiteraciones.</p>
                        <a href="tramites.php" class="btn btn-primary btn-access mt-auto">Acceder</a>
                    </div>
                </div>
            </div>
            
            <!-- Generación de Documentos -->
            <div class="col">
                <div class="card module-card">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0">Generación de Documentos</h3>
                    </div>
                    <span class="status-badge status-active">Activo</span>
                    <div class="card-body d-flex flex-column">
                        <div class="text-center card-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <p class="card-text">Cree y gestione documentos relacionados con trámites y servicios agrarios.</p>
                        <a href="paginas/documentos.php" class="btn btn-primary btn-access mt-auto">Acceder</a>
                    </div>
                </div>
            </div>
            
            <!-- Agenda de Visitas -->
            <div class="col">
                <div class="card module-card">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0">Agenda de Visitas</h3>
                    </div>
                    <span class="status-badge status-pending">Próximamente</span>
                    <div class="card-body d-flex flex-column">
                        <div class="text-center card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <p class="card-text">Planifique y administre las visitas a ejidos y comunidades del área de atención.</p>
                        <button class="btn btn-secondary btn-access-disabled mt-auto" disabled>Acceder</button>
                    </div>
                </div>
            </div>
            
            <!-- Directorio -->
            <div class="col">
                <div class="card module-card">
                    <div class="card-header py-3">
                        <h3 class="card-title mb-0">Directorio</h3>
                    </div>
                    <span class="status-badge status-pending">Próximamente</span>
                    <div class="card-body d-flex flex-column">
                        <div class="text-center card-icon">
                            <i class="fas fa-address-book"></i>
                        </div>
                        <p class="card-text">Acceda al directorio de contactos, comisariados ejidales y autoridades agrarias.</p>
                        <button class="btn btn-secondary btn-access-disabled mt-auto" disabled>Acceder</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer text-center">
            <p>&copy; <?php echo date('Y'); ?> PROCURADURÍA AGRARIA - Todos los derechos reservados</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>