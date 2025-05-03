<?php
// Determinar si estamos en una página del directorio "paginas"
$paginasDir = isset($paginasDir) ? $paginasDir : false;
$rutaBase = $paginasDir ? '../' : '';

// Define la URL base del sitio (ajustar según tu configuración)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/procugestion/');
}
?>
</div><!-- Cierre del contenedor principal -->

    <!-- Footer -->
    <footer class="bg-light text-center text-lg-start mt-5">
        <div class="text-center p-3" style="background-color: rgba(0, 0, 0, 0.05);">
            &copy; <?php echo date('Y'); ?> PROCURADURIA AGRARIA
        </div>
    </footer>

    <!-- Scripts jQuery, Bootstrap y SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    
    <!-- Script personalizado - Incluir versión para forzar recarga (evitar caché) -->
<!-- Script personalizado - Incluir versión para forzar recarga (evitar caché) -->
    <script src="<?php echo $rutaBase; ?>js/main.js?v=<?php echo time(); ?>"></script>    
    <script src="<?php echo $rutaBase; ?>js/alertas.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo $rutaBase; ?>js/validacion-promovente.js?v=<?php echo time(); ?>"></script>

<?php
// Incluir los modales - Usando require_once para evitar errores si el archivo no existe
$modalBuscarPromovente = $rutaBase . 'modulos/modal_buscar_promovente.php';
$modalNuevoPromovente = $rutaBase . 'modulos/modal_nuevo_promovente.php';
$modalNuevoTramite = $rutaBase . 'modulos/modal_nuevo_tramite.php';
$modalEditarPromovente = $rutaBase . 'modulos/modal_editar_promovente.php';
$modalRegistrarFolio = $rutaBase . 'modulos/modal_registrar_folio.php';

if (file_exists($modalEditarPromovente)) {
    include_once $modalEditarPromovente;
}

if (file_exists($modalRegistrarFolio)) {
    include_once $modalRegistrarFolio;
}

if (file_exists($modalBuscarPromovente)) {
    include_once $modalBuscarPromovente;
} 

if (file_exists($modalNuevoPromovente)) {
    include_once $modalNuevoPromovente;
}

if (file_exists($modalNuevoTramite)) {
    include_once $modalNuevoTramite;
}
?>
</body>
</html>