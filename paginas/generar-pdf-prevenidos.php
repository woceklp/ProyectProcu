<?php
/**
 * paginas/generar-pdf-prevenidos.php - Genera el PDF de trámites prevenidos
 */

// Incluir archivo de configuración
require_once '../config.php';

// Incluir TCPDF - usar la ruta correcta según tu instalación
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

// Obtener ID de trámites a excluir (si existen)
$excluir = isset($_GET['excluir']) ? explode(',', $_GET['excluir']) : [];
$excludeClause = '';

if (!empty($excluir)) {
    $excludeClause = " AND t.ID_Tramite NOT IN (" . implode(',', array_map('intval', $excluir)) . ")";
}

// Modificar la consulta para usar la misma lógica de filtrado que implementamos
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
        WHERE EXISTS (
            SELECT 1 FROM Acuses a 
            WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
        ) " . $excludeClause . " 
        ORDER BY DiasTranscurridos DESC";

$resultado = ejecutarConsulta($sql);
$tramites = [];

if ($resultado['status'] === 'success') {
    $tramites = obtenerResultados($resultado['stmt']);
    cerrarConexion($resultado['conn'], $resultado['stmt']);
}

// Agregar líneas de depuración para verificar si hay resultados
error_log("Consulta SQL para PDF: " . $sql);
error_log("Número de trámites encontrados: " . count($tramites));

// Crear nuevo documento PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Establecer información del documento
$pdf->SetCreator('PROCURADURIA AGRARIA');
$pdf->SetAuthor('PROCURADURIA AGRARIA');
$pdf->SetTitle('Reporte de Trámites Prevenidos');
$pdf->SetSubject('Trámites en estado prevenido');
$pdf->SetKeywords('CIIA, Prevenido, Tramites, PROCURADURIA AGRARIA');

// Establecer información de encabezado sin logotipo
$pdf->setHeaderData('', 0, 'PROCURADURIA AGRARIA', 'Reporte de Trámites Prevenidos (' . date('d/m/Y') . ')');

// Establecer márgenes
$pdf->SetMargins(10, 20, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Establecer saltos de página automáticos
$pdf->SetAutoPageBreak(TRUE, 15);

// Añadir una página
$pdf->AddPage('L', 'A4'); // Landscape para mejor visualización

// Definir estilos
$pdf->SetFont('helvetica', 'B', 12);

// Título del reporte
$pdf->Cell(0, 10, 'TRÁMITES EN ESTADO PREVENIDO', 0, 1, 'C');
$pdf->Ln(5);

// Crear la tabla
$pdf->SetFont('helvetica', 'B', 8);

// Cabecera de la tabla
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(25, 7, 'CIIA', 1, 0, 'C', 1);
$pdf->Cell(25, 7, 'FOLIO RCHRP', 1, 0, 'C', 1);
$pdf->Cell(45, 7, 'PROMOVENTE', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'MUNICIPIO', 1, 0, 'C', 1);
$pdf->Cell(15, 7, 'TIPO N.A.', 1, 0, 'C', 1);
$pdf->Cell(40, 7, 'NÚCLEO AGRARIO', 1, 0, 'C', 1);
$pdf->Cell(15, 7, 'CLAVE', 1, 0, 'C', 1);
$pdf->Cell(40, 7, 'DESCRIPCIÓN', 1, 0, 'C', 1);
$pdf->Cell(25, 7, 'AVANCE', 1, 0, 'C', 1);
$pdf->Cell(15, 7, 'DÍAS', 1, 1, 'C', 1);

// Datos de la tabla
$pdf->SetFont('helvetica', '', 7);
if (count($tramites) > 0) {
    foreach ($tramites as $tramite) {
        $pdf->Cell(25, 6, $tramite['CIIA'], 1, 0, 'C');
        $pdf->Cell(25, 6, $tramite['FolioRCHRP'] ?? 'N/A', 1, 0, 'C');
        $pdf->Cell(45, 6, $tramite['Promovente'], 1, 0, 'L');
        $pdf->Cell(30, 6, $tramite['Municipio'], 1, 0, 'L');
        $pdf->Cell(15, 6, $tramite['TipoNucleoAgrario'], 1, 0, 'C');
        $pdf->Cell(40, 6, $tramite['NucleoAgrario'], 1, 0, 'L');
        $pdf->Cell(15, 6, $tramite['ClaveTramite'], 1, 0, 'C');
        $pdf->Cell(40, 6, $tramite['DescripcionTramite'], 1, 0, 'L');
        $pdf->Cell(25, 6, $tramite['Estado'], 1, 0, 'C');
        $pdf->Cell(15, 6, $tramite['DiasTranscurridos'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(275, 6, 'No hay trámites en estado prevenido', 1, 1, 'C');
}

// Pie con totales
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 10, 'Total de trámites: ' . count($tramites), 0, 1, 'R');

// Enviar el documento al navegador
$pdf->Output('Reporte_Prevenidos_' . date('Ymd') . '.pdf', 'I');
exit;