<?php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

// Evitar timeouts en archivos grandes
@set_time_limit(0);

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!isset($_SESSION['tipo_usuario_id']) || $_SESSION['tipo_usuario_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se envio archivo']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$tmpPath = $file['tmp_name'];

function extractTxtToTemp($tmpPath, $ext)
{
    if ($ext === 'txt') {
        return $tmpPath;
    }

    $tempTxt = tempnam(sys_get_temp_dir(), 'txtcut_');
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP');
        }
        $txtIndex = -1;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'txt') {
                $txtIndex = $i;
                break;
            }
        }
        if ($txtIndex === -1) {
            $zip->close();
            throw new RuntimeException('El ZIP no contiene TXT');
        }
        $stream = $zip->getStream($zip->getNameIndex($txtIndex));
        if (!$stream) {
            $zip->close();
            throw new RuntimeException('No se pudo leer el TXT dentro del ZIP');
        }
        $dest = fopen($tempTxt, 'w');
        stream_copy_to_stream($stream, $dest);
        fclose($stream);
        fclose($dest);
        $zip->close();
        return $tempTxt;
    }

    if ($ext === 'rar') {
        if (!class_exists('RarArchive')) {
            throw new RuntimeException('RAR no soportado en el servidor. Usa .zip o .txt directamente.');
        }
        $rar = RarArchive::open($tmpPath);
        if (!$rar) {
            throw new RuntimeException('No se pudo abrir el RAR');
        }
        $entries = $rar->getEntries();
        $found = false;
        foreach ($entries as $entry) {
            $name = $entry->getName();
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'txt') {
                $stream = $entry->getStream();
                if ($stream) {
                    $dest = fopen($tempTxt, 'w');
                    stream_copy_to_stream($stream, $dest);
                    fclose($stream);
                    fclose($dest);
                    $found = true;
                    break;
                }
            }
        }
        $rar->close();
        if (!$found) {
            throw new RuntimeException('El RAR no contiene TXT legible. Usa .zip o .txt.');
        }
        return $tempTxt;
    }

    throw new RuntimeException('Formato no valido. Usa .txt, .zip o .rar con el TXT');
}

try {
    $txtPath = extractTxtToTemp($tmpPath, $ext);
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$fileObj = new SplFileObject($txtPath, 'r');
$fileObj->setFlags(SplFileObject::DROP_NEW_LINE);

$expectedHeader = 'Consecutivo|Tipo de Documento|Numero de Documento|Primer Nombre|Primer Apellido|Fecha Nacimiento|Dane Dpto|Dane Municipio|Column 8|FechaUltimaVacuna';

$firstLine = $fileObj->fgets();
if ($firstLine === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El archivo no tiene datos']);
    exit;
}
$header = preg_replace('/^\xEF\xBB\xBF/', '', trim($firstLine));
if ($header !== $expectedHeader) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Encabezado incorrecto. Debe ser: ' . $expectedHeader]);
    exit;
}

$useDb = sqlsrv_query($conn, "USE Vacunacion;");
if ($useDb === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo seleccionar la base Vacunacion', 'sqlsrv' => sqlsrv_errors()]);
    exit;
}

$mapTipo = [
    'CC' => 'Cédula de Ciudadanía',
    'RC' => 'Registro Civil',
    'TI' => 'Tarjeta de Identidad',
    'PA' => 'Pasaporte',
    'SA' => 'Salvoconducto',
    'CE' => 'Cédula de Extranjería',
    'PT' => 'Permiso de Protección Temporal',
    'PE' => 'Permiso Especial De Permanencia',
];

$maxCorte = 0;
$stmtCorte = sqlsrv_query($conn, "SELECT ISNULL(MAX(corte),0) AS max_corte FROM Vacunacion.dbo.VacunacionFiebreAmarilla");
if ($stmtCorte && ($row = sqlsrv_fetch_array($stmtCorte, SQLSRV_FETCH_ASSOC))) {
    $maxCorte = (int)$row['max_corte'];
}
sqlsrv_free_stmt($stmtCorte);
$nextCorte = $maxCorte + 1;

$nuevos = [];
$rechazados = [];
$totalLeidas = 0;
$sinFecha = 0;
$conFechaMinisterio = 0;

while (!$fileObj->eof()) {
    $line = $fileObj->fgets();
    if ($line === false || $line === '') {
        continue;
    }
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $totalLeidas++;
    $parts = explode('|', $line);
    if (count($parts) < 10) {
        if (count($rechazados) < 200) {
            $rechazados[] = ['fila' => $totalLeidas + 1, 'doc' => '', 'motivo' => 'Fila con columnas incompletas'];
        }
        continue;
    }
    $tipoCod = trim($parts[1]);
    $tipo = $mapTipo[$tipoCod] ?? null;
    $doc = trim($parts[2]);
    $fechaVac = trim($parts[9]);

    if ($tipo === '' || $doc === '') {
        if (count($rechazados) < 200) {
            $rechazados[] = ['fila' => $totalLeidas + 1, 'doc' => $doc ?: '-', 'motivo' => 'Tipo no valido o documento vacio'];
        }
        continue;
    }

    if ($fechaVac === '') {
        $sinFecha++;
        continue;
    }

    $stmtExiste = sqlsrv_query($conn, "SELECT FechaAplicacionMinisterio FROM Vacunacion.dbo.VacunacionFiebreAmarilla WITH (NOLOCK) WHERE TipoDocumento = ? AND NumeroDocumento = ?", [$tipo, $doc]);
    if ($stmtExiste === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error consultando documento', 'sqlsrv' => sqlsrv_errors()]);
        exit;
    }
    $existe = sqlsrv_fetch_array($stmtExiste, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtExiste);
    if (!$existe) {
        if (count($rechazados) < 200) {
            $rechazados[] = ['fila' => $totalLeidas + 1, 'doc' => $doc, 'motivo' => 'No coincide en la base'];
        }
        continue;
    }

    if (!empty($existe['FechaAplicacionMinisterio'])) {
        $conFechaMinisterio++;
        continue;
    }

    if (count($nuevos) < 200) {
        $nuevos[] = [
            'TipoDocumento' => $tipo,
            'TipoDocumentoCodigo' => $tipoCod,
            'NumeroDocumento' => $doc,
            'FechaUltimaVacuna' => $fechaVac
        ];
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Validacion de nuevo corte completada',
    'next_corte' => $nextCorte,
    'total_leidas' => $totalLeidas,
    'nuevos_total' => count($nuevos) >= 200 ? '200+' : count($nuevos),
    'rechazados_total' => count($rechazados) >= 200 ? '200+' : count($rechazados),
    'vacios_total' => $sinFecha,
    'con_fecha_ministerio_total' => $conFechaMinisterio,
    'nuevos_preview' => $nuevos,
    'rechazados_preview' => $rechazados
]);
exit;
