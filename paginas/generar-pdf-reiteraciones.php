<?php
/**
 * paginas/generar-pdf-reiteraciones.php - Genera el PDF de trámites por reiterarse
 */

// Incluir archivo de configuración
require_once '../config.php';

// Incluir TCPDF - usar la misma ruta que usaste para el otro PDF
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

// Obtener ID de trámites a excluir (si existen)
$excluir = isset($_GET['excluir']) ? explode(',', $_GET['excluir']) : [];
$excludeClause = '';

if (!empty($excluir)) {
    $excludeClause = " AND t.ID_Tramite NOT IN (" . implode(',', array_map('intval', $excluir)) . ")";
}

// Consulta para obtener los trámites por reiterarse - usando la misma lógica que implementamos
$sql = "WITH UltimasFechas AS (
    SELECT t.ID_Tramite, 
           p.Nombre + ' ' + p.ApellidoPaterno + ' ' + p.ApellidoMaterno AS Promovente,
           t.CIIA, 
           t.FolioRCHRP,
           t.FechaRCHRP,
           m.Nombre AS Municipio,
           tna.Descripcion AS TipoNucleoAgrario,
           na.Nombre AS NucleoAgrario,
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
    WHERE t.ID_EstadoTramite <> 5
    AND (
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

// Agregar líneas de depuración
error_log("Consulta SQL para PDF de reiteraciones: " . $sql);
error_log("Número de trámites por reiterarse encontrados: " . count($tramites));

// Crear nuevo documento PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Establecer información del documento
$pdf->SetCreator('PROCURADURIA AGRARIA');
$pdf->SetAuthor('PROCURADURIA AGRARIA');
$pdf->SetTitle('Reporte de Trámites por Reiterarse');
$pdf->SetSubject('Trámites que requieren reiteración');
$pdf->SetKeywords('CIIA, Reiteración, Tramites, PROCURADURIA AGRARIA');

// Establecer información de encabezado sin logotipo
$pdf->setHeaderData('', 0, 'PROCURADURIA AGRARIA', 'Reporte de Trámites por Reiterarse (' . date('d/m/Y') . ')');

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
$pdf->Cell(0, 10, 'TRÁMITES QUE REQUIEREN REITERACIÓN (95 DÍAS O MÁS)', 0, 1, 'C');
$pdf->Ln(5);

// Crear la tabla
$pdf->SetFont('helvetica', 'B', 8);

// Cabecera de la tabla
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(20, 7, 'CIIA', 1, 0, 'C', 1);
$pdf->Cell(20, 7, 'FOLIO RCHRP', 1, 0, 'C', 1);
$pdf->Cell(45, 7, 'PROMOVENTE', 1, 0, 'C', 1);
$pdf->Cell(30, 7, 'MUNICIPIO', 1, 0, 'C', 1);
$pdf->Cell(15, 7, 'TIPO N.A.', 1, 0, 'C', 1);
$pdf->Cell(45, 7, 'NÚCLEO AGRARIO', 1, 0, 'C', 1);
$pdf->Cell(25, 7, 'AVANCE', 1, 0, 'C', 1);
$pdf->Cell(15, 7, 'NÚM. REIT.', 1, 0, 'C', 1);
$pdf->Cell(15, 7, 'DÍAS', 1, 1, 'C', 1);

// Datos de la tabla
$pdf->SetFont('helvetica', '', 7);
if (count($tramites) > 0) {
    foreach ($tramites as $tramite) {
        $pdf->Cell(20, 6, $tramite['CIIA'], 1, 0, 'C');
        $pdf->Cell(20, 6, $tramite['FolioRCHRP'] ?? 'N/A', 1, 0, 'C');
        $pdf->Cell(45, 6, $tramite['Promovente'], 1, 0, 'L');
        $pdf->Cell(30, 6, $tramite['Municipio'], 1, 0, 'L');
        $pdf->Cell(15, 6, $tramite['TipoNucleoAgrario'], 1, 0, 'C');
        $pdf->Cell(45, 6, $tramite['NucleoAgrario'], 1, 0, 'L');
        $pdf->Cell(25, 6, $tramite['Estado'] . ' (' . $tramite['Avance'] . '%)', 1, 0, 'C');
        $pdf->Cell(15, 6, $tramite['NumeroReiteraciones'], 1, 0, 'C');
        
        // Días con marcador visual
        $dias = $tramite['DiasTranscurridos'];
        $indicador = '';
        if ($dias >= 100) {
            $indicador = ' (!!)';
        } else if ($dias >= 95) {
            $indicador = ' (!)';
        }
        $pdf->Cell(15, 6, $dias . $indicador, 1, 1, 'C');
    }
} else {
    $pdf->Cell(230, 6, 'No hay trámites que requieran reiteración', 1, 1, 'C');
}

// Pie con totales
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 10, 'Total de trámites: ' . count($tramites), 0, 1, 'R');

// Enviar el documento al navegador
$pdf->Output('Reporte_Reiteraciones_' . date('Ymd') . '.pdf', 'I');